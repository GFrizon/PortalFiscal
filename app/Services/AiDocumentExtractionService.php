<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiDocumentExtractionService
{
    public function isConfigured(): bool
    {
        return filled((string) config('services.openai.key'));
    }

    public function extract(string $absolutePath): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Integracao de IA nao configurada.',
            ];
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return [
                'success' => false,
                'error' => 'PDF indisponivel para leitura pela IA.',
            ];
        }

        try {
            $fileContents = file_get_contents($absolutePath);

            if ($fileContents === false || $fileContents === '') {
                return [
                    'success' => false,
                    'error' => 'PDF vazio ou indisponivel para leitura pela IA.',
                ];
            }

            $response = Http::withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->timeout((int) config('services.openai.timeout', 45))
                ->post('https://api.openai.com/v1/responses', [
                    'model' => (string) (config('services.openai.model') ?: 'gpt-4o-mini'),
                    'max_output_tokens' => 400,
                    'input' => [[
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_file',
                                'filename' => basename($absolutePath),
                                'file_data' => 'data:application/pdf;base64,'.base64_encode($fileContents),
                                'detail' => (string) config('services.openai.pdf_detail', 'low'),
                            ],
                            [
                                'type' => 'input_text',
                                'text' => $this->prompt(),
                            ],
                        ],
                    ]],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'invoice_pdf_extraction',
                            'strict' => true,
                            'schema' => $this->schema(),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Falha na extracao de PDF via OpenAI.', [
                    'path' => $absolutePath,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'OpenAI retornou erro ao analisar o PDF.',
                ];
            }

            $payload = $response->json();
            $outputText = $this->extractOutputText(is_array($payload) ? $payload : []);
            $parsed = json_decode($outputText, true);

            if (! is_array($parsed)) {
                Log::warning('Resposta da OpenAI sem JSON parseavel para extracao de PDF.', [
                    'path' => $absolutePath,
                    'output_text' => $outputText,
                ]);

                return [
                    'success' => false,
                    'error' => 'Resposta invalida da OpenAI ao analisar o PDF.',
                ];
            }

            return [
                'success' => true,
                'invoice_number' => $this->cleanNullableString($parsed['invoice_number'] ?? null),
                'invoice_access_key' => $this->cleanNullableString($parsed['invoice_access_key'] ?? null),
                'issuer_cnpj' => $this->cleanNullableString($parsed['issuer_cnpj'] ?? null),
                'issuer_legal_name' => $this->cleanNullableString($parsed['issuer_legal_name'] ?? null),
                'recipient_cnpj' => $this->cleanNullableString($parsed['recipient_cnpj'] ?? null),
                'recipient_legal_name' => $this->cleanNullableString($parsed['recipient_legal_name'] ?? null),
                'error' => null,
                'source' => 'ai',
            ];
        } catch (Throwable $exception) {
            Log::warning('Falha ao acionar OpenAI para extracao de PDF.', [
                'path' => $absolutePath,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Falha ao acionar a OpenAI para analisar o PDF.',
            ];
        }
    }

    private function prompt(): string
    {
        return <<<'TEXT'
Leia o PDF fiscal anexado e extraia somente os campos solicitados.

Regras:
- Retorne null quando nao tiver certeza.
- Nao invente valores.
- issuer_legal_name = razao social do emitente/fornecedor da nota.
- recipient_legal_name = razao social do destinatario/tomador, quando visivel.
- Em faturas de agua, energia, telefone, internet ou servicos publicos, recipient_legal_name/recipient_cnpj = cliente, pagador, usuario, titular ou unidade consumidora que recebe a cobranca.
- issuer_cnpj e recipient_cnpj devem sair apenas com digitos.
- invoice_number deve sair apenas com digitos, sem serie, sem protocolo e sem codigo de verificacao.
- invoice_access_key deve sair apenas com os 44 digitos da chave, quando existir.
- Nao confunda CNPJ, protocolo, pedido, OC, entrada, serie, duplicata ou codigo de verificacao com o numero da nota.
TEXT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'invoice_number' => [
                    'type' => ['string', 'null'],
                ],
                'invoice_access_key' => [
                    'type' => ['string', 'null'],
                ],
                'issuer_cnpj' => [
                    'type' => ['string', 'null'],
                ],
                'issuer_legal_name' => [
                    'type' => ['string', 'null'],
                ],
                'recipient_cnpj' => [
                    'type' => ['string', 'null'],
                ],
                'recipient_legal_name' => [
                    'type' => ['string', 'null'],
                ],
            ],
            'required' => [
                'invoice_number',
                'invoice_access_key',
                'issuer_cnpj',
                'issuer_legal_name',
                'recipient_cnpj',
                'recipient_legal_name',
            ],
        ];
    }

    private function extractOutputText(array $payload): string
    {
        $directOutput = trim((string) data_get($payload, 'output_text', ''));

        if ($directOutput !== '') {
            return $directOutput;
        }

        $parts = [];

        foreach ((array) data_get($payload, 'output', []) as $outputItem) {
            foreach ((array) data_get($outputItem, 'content', []) as $contentItem) {
                $type = (string) data_get($contentItem, 'type', '');
                $text = (string) data_get($contentItem, 'text', '');

                if (in_array($type, ['output_text', 'text'], true) && trim($text) !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function cleanNullableString(mixed $value): ?string
    {
        $clean = trim((string) $value);

        return $clean !== '' ? $clean : null;
    }
}
