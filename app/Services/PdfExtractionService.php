<?php

namespace App\Services;

use App\Models\BusinessUnit;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfExtractionService
{
    public function __construct(
        private readonly Parser $parser,
        private readonly ?PdfOcrService $ocrService = null,
        private readonly ?AiDocumentExtractionService $aiExtractionService = null,
    )
    {
    }

    public function extract(string $absolutePath): array
    {
        try {
            $pdf = $this->parser->parseFile($absolutePath);
            $text = $pdf->getText();

            if (! $this->hasReadableText($text)) {
                $ocrText = $this->ocrService?->extract($absolutePath);

                if ($this->hasReadableText((string) $ocrText)) {
                    return $this->applyAiFallback(
                        $absolutePath,
                        $this->extractFromText((string) $ocrText, 'ocr')
                    );
                }

                return $this->applyAiFallback(
                    $absolutePath,
                    $this->emptyExtractionResult('PDF sem texto pesquisavel. Configure OCR para ler documentos escaneados.', 'blank')
                );
            }

            return $this->applyAiFallback($absolutePath, $this->extractFromText($text, 'text'));
        } catch (Throwable $exception) {
            Log::warning('Falha ao extrair texto do PDF.', [
                'path' => $absolutePath,
                'message' => $exception->getMessage(),
            ]);

            $ocrText = $this->ocrService?->extract($absolutePath);

            if ($this->hasReadableText((string) $ocrText)) {
                return $this->applyAiFallback(
                    $absolutePath,
                    $this->extractFromText((string) $ocrText, 'ocr')
                );
            }

            return $this->applyAiFallback($absolutePath, $this->emptyExtractionResult($exception->getMessage(), 'error'));
        }
    }

    public function extractFromText(string $text, string $source = 'text'): array
    {
        $cnpjs = $this->extractCnpjs($text);
        $recipientCnpj = $this->identifyRecipientCnpj($cnpjs, $text);
        $accessKey = $this->extractAccessKey($text);
        $issuerCnpj = $this->identifyIssuerCnpj($cnpjs, $recipientCnpj, $accessKey);

        return [
            'success' => true,
            'text' => $text,
            'cnpjs' => $cnpjs,
            'issuer_cnpj' => $issuerCnpj,
            'recipient_cnpj' => $recipientCnpj,
            'invoice_number' => $this->extractInvoiceNumber($text, $accessKey),
            'invoice_access_key' => $accessKey,
            'issuer_legal_name' => $this->sanitizeExtractedLegalName($this->extractLegalNameNearCnpj($text, $issuerCnpj)),
            'recipient_legal_name' => $this->sanitizeExtractedLegalName($this->extractLegalNameNearCnpj($text, $recipientCnpj)),
            'error' => null,
            'source' => $source,
        ];
    }

    private function applyAiFallback(string $absolutePath, array $baseResult): array
    {
        if (! $this->shouldUseAiFallback($baseResult) || ! $this->aiExtractionService) {
            return $baseResult;
        }

        $aiResult = $this->aiExtractionService->extract($absolutePath);

        if (! ($aiResult['success'] ?? false)) {
            return $baseResult;
        }

        return $this->mergeExtractionWithAiFallback($baseResult, $aiResult);
    }

    private function shouldUseAiFallback(array $result): bool
    {
        if (! $this->aiExtractionService) {
            return false;
        }

        if (! ($result['success'] ?? false)) {
            return true;
        }

        return blank($result['invoice_number'] ?? null)
            || blank($result['issuer_cnpj'] ?? null)
            || $this->looksSuspiciousLegalName($result['issuer_legal_name'] ?? null)
            || $this->looksSuspiciousLegalName($result['recipient_legal_name'] ?? null);
    }

    private function mergeExtractionWithAiFallback(array $baseResult, array $aiResult): array
    {
        $issuerCnpj = $this->normalizeNullableCnpj($aiResult['issuer_cnpj'] ?? null)
            ?? $this->normalizeNullableCnpj($baseResult['issuer_cnpj'] ?? null);
        $recipientCnpj = $this->normalizeNullableCnpj($aiResult['recipient_cnpj'] ?? null)
            ?? $this->normalizeNullableCnpj($baseResult['recipient_cnpj'] ?? null);
        $invoiceAccessKey = $this->normalizeNullableAccessKey($aiResult['invoice_access_key'] ?? null)
            ?? $this->normalizeNullableAccessKey($baseResult['invoice_access_key'] ?? null);
        $invoiceNumber = $this->normalizeNullableInvoiceNumber($aiResult['invoice_number'] ?? null)
            ?? $this->normalizeNullableInvoiceNumber($baseResult['invoice_number'] ?? null);
        $issuerLegalName = $this->normalizeNullableLegalName($aiResult['issuer_legal_name'] ?? null)
            ?? $this->sanitizeExtractedLegalName($baseResult['issuer_legal_name'] ?? null);
        $recipientLegalName = $this->normalizeNullableLegalName($aiResult['recipient_legal_name'] ?? null)
            ?? $this->sanitizeExtractedLegalName($baseResult['recipient_legal_name'] ?? null);
        $baseSource = trim((string) ($baseResult['source'] ?? ''));

        return [
            'success' => true,
            'text' => (string) ($baseResult['text'] ?? ''),
            'cnpjs' => $this->mergeCnpjs(
                (array) ($baseResult['cnpjs'] ?? []),
                [$issuerCnpj, $recipientCnpj]
            ),
            'issuer_cnpj' => $issuerCnpj,
            'recipient_cnpj' => $recipientCnpj,
            'invoice_number' => $invoiceNumber,
            'invoice_access_key' => $invoiceAccessKey,
            'issuer_legal_name' => $issuerLegalName,
            'recipient_legal_name' => $recipientLegalName,
            'error' => null,
            'source' => $baseSource !== '' ? $baseSource.'+ai' : 'ai',
        ];
    }

    private function mergeCnpjs(array $baseCnpjs, array $additionalCnpjs): array
    {
        return collect(array_merge($baseCnpjs, $additionalCnpjs))
            ->map(fn (mixed $cnpj): string => $this->normalizeCnpj((string) $cnpj))
            ->filter(fn (string $cnpj): bool => $this->isValidCnpj($cnpj))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeNullableCnpj(?string $cnpj): ?string
    {
        $normalized = $this->normalizeCnpj((string) $cnpj);

        return $this->isValidCnpj($normalized) ? $normalized : null;
    }

    private function normalizeNullableInvoiceNumber(?string $number): ?string
    {
        $normalized = $this->normalizeInvoiceNumber((string) $number);

        return $this->isPlausibleInvoiceNumber($normalized) ? $normalized : null;
    }

    private function normalizeNullableAccessKey(?string $accessKey): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $accessKey) ?? '';

        return strlen($digits) === 44 && $this->isValidFiscalAccessKey($digits) ? $digits : null;
    }

    private function normalizeNullableLegalName(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '' || $this->looksSuspiciousLegalName($name)) {
            return null;
        }

        return mb_strtoupper($name, 'UTF-8');
    }

    private function sanitizeExtractedLegalName(?string $name): ?string
    {
        return $this->normalizeNullableLegalName($name);
    }

    private function looksSuspiciousLegalName(?string $name): bool
    {
        if (blank($name)) {
            return false;
        }

        $normalized = $this->normalizeReadableText((string) $name);

        if ($normalized === '' || preg_match('/^\d+$/', $normalized)) {
            return true;
        }

        if ($this->looksLikeDocumentNumberWithoutLetters((string) $name) || preg_match('/\d{6,}/', $normalized)) {
            return true;
        }

        $blockedTerms = [
            'ENTRADA',
            'SAIDA',
            'PROTOCOLO',
            'CHAVE DE ACESSO',
            'ORDEM DE COMPRA',
            'PEDIDO',
            'DOCUMENTO AUXILIAR',
            'DANFE',
            'DACTE',
        ];

        foreach ($blockedTerms as $term) {
            if (str_contains($normalized, $term)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeDocumentNumberWithoutLetters(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '' || preg_match('/\p{L}/u', $value)) {
            return false;
        }

        return in_array(strlen($digits), [11, 14], true);
    }

    private function hasReadableText(?string $text): bool
    {
        return strlen(trim((string) preg_replace('/\s+/', ' ', (string) $text))) >= 20;
    }

    private function emptyExtractionResult(string $error, string $source): array
    {
        return [
            'success' => false,
            'text' => '',
            'cnpjs' => [],
            'issuer_cnpj' => null,
            'recipient_cnpj' => null,
            'invoice_number' => null,
            'invoice_access_key' => null,
            'issuer_legal_name' => null,
            'recipient_legal_name' => null,
            'error' => $error,
            'source' => $source,
        ];
    }

    public function normalizeCnpj(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj) ?? '';
    }

    public function isSuspiciousLegalName(?string $name): bool
    {
        return $this->looksSuspiciousLegalName($name);
    }

    public function extractCnpjs(string $text): array
    {
        $normalizedText = str_replace(["\u{00A0}", "\u{2007}", "\u{202F}"], ' ', $text);

        preg_match_all('/(?<!\d)(\d{2}\s*\.\s*\d{3}\s*\.\s*\d{3}\s*[\.\/]\s*\d{4}\s*-\s*\d{2})(?!\d)/u', $normalizedText, $formattedMatches);
        preg_match_all('/(?<!\d)(\d{14})(?!\d)/u', $normalizedText, $plainMatches);

        return collect(array_merge($formattedMatches[0] ?? [], $plainMatches[0] ?? []))
            ->map(fn (string $cnpj) => $this->normalizeCnpj($cnpj))
            ->filter(fn (string $cnpj) => $this->isValidCnpj($cnpj))
            ->unique()
            ->values()
            ->all();
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $digits = array_map('intval', str_split($cnpj));
        $firstWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $firstSum = array_sum(array_map(fn (int $digit, int $weight) => $digit * $weight, array_slice($digits, 0, 12), $firstWeights));
        $firstDigit = $firstSum % 11 < 2 ? 0 : 11 - ($firstSum % 11);

        $secondSum = array_sum(array_map(fn (int $digit, int $weight) => $digit * $weight, array_slice($digits, 0, 13), $secondWeights));
        $secondDigit = $secondSum % 11 < 2 ? 0 : 11 - ($secondSum % 11);

        return $digits[12] === $firstDigit && $digits[13] === $secondDigit;
    }

    private function identifyRecipientCnpj(array $cnpjs, string $text): ?string
    {
        $units = BusinessUnit::query()->get(['name', 'legal_name', 'cnpj', 'internal_code']);

        $unit = $units->first(function (BusinessUnit $unit) use ($cnpjs): bool {
            return in_array($this->normalizeCnpj($unit->cnpj), $cnpjs, true);
        });

        if ($unit) {
            return $this->normalizeCnpj($unit->cnpj);
        }

        $unit = $this->identifyBusinessUnitByInternalCode($units, $text)
            ?? $this->identifyBusinessUnitByName($units, $text);

        return $unit ? $this->normalizeCnpj($unit->cnpj) : null;
    }

    private function identifyBusinessUnitByInternalCode($units, string $text): ?BusinessUnit
    {
        if (! preg_match_all('/(?:unidade(?:\s+de)?\s+neg[oó]cio|unidade|u\.?n\.?)\D{0,20}(\d{1,4})/iu', $text, $matches)) {
            return null;
        }

        foreach ($matches[1] as $code) {
            $normalizedCode = ltrim($code, '0') ?: '0';
            $matchedUnits = $units->filter(function (BusinessUnit $unit) use ($normalizedCode): bool {
                $unitCode = preg_replace('/\D/', '', (string) $unit->internal_code) ?: '';

                return $unitCode !== '' && (ltrim($unitCode, '0') ?: '0') === $normalizedCode;
            });

            if ($matchedUnits->count() === 1) {
                return $matchedUnits->first();
            }
        }

        return null;
    }

    private function identifyBusinessUnitByName($units, string $text): ?BusinessUnit
    {
        $normalizedText = $this->normalizeSearchText($text);

        return $units
            ->sortByDesc(fn (BusinessUnit $unit): int => strlen((string) $unit->legal_name))
            ->first(function (BusinessUnit $unit) use ($normalizedText): bool {
                foreach ([$unit->legal_name, $unit->name] as $name) {
                    $normalizedName = $this->normalizeSearchText((string) $name);

                    if (strlen($normalizedName) >= 8 && str_contains($normalizedText, $normalizedName)) {
                        return true;
                    }
                }

                return false;
            });
    }

    private function normalizeSearchText(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;

        return preg_replace('/[^A-Z0-9]+/', '', strtoupper($text)) ?? '';
    }

    private function identifyIssuerCnpj(array $cnpjs, ?string $recipientCnpj, ?string $accessKey = null): ?string
    {
        if ($accessKey) {
            $accessKeyCnpj = substr($accessKey, 6, 14);

            if ($this->isValidCnpj($accessKeyCnpj) && $accessKeyCnpj !== $recipientCnpj) {
                return $accessKeyCnpj;
            }
        }

        $issuerCnpj = collect($cnpjs)
            ->first(fn (string $cnpj) => $cnpj !== $recipientCnpj);

        if ($issuerCnpj) {
            return $issuerCnpj;
        }

        return null;
    }

    private function extractLegalNameNearCnpj(string $text, ?string $cnpj): ?string
    {
        if (! $cnpj) {
            return null;
        }

        $lines = collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line): string => trim(preg_replace('/\s+/', ' ', $line) ?? $line))
            ->filter()
            ->values()
            ->all();

        foreach ($lines as $index => $line) {
            if (! str_contains($this->normalizeCnpj($line), $cnpj)) {
                continue;
            }

            $sameLineCandidate = preg_replace('/(?:CNPJ|CPF|CNPJ\/CPF|CNPJ\s*CPF|CGC|INSCRICAO|INSCRICAO\s+ESTADUAL).*/iu', '', $line) ?? '';
            $sameLineName = $this->cleanLegalNameCandidate($sameLineCandidate);

            if ($sameLineName) {
                return $sameLineName;
            }

            foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $distance) {
                $previous = $lines[$index - $distance] ?? null;
                $name = $previous ? $this->cleanLegalNameCandidate($previous) : null;

                if ($name) {
                    return $name;
                }
            }

            foreach ([1, 2, 3] as $distance) {
                $next = $lines[$index + $distance] ?? null;
                $name = $next ? $this->cleanLegalNameCandidate($next) : null;

                if ($name) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function cleanLegalNameCandidate(string $candidate): ?string
    {
        $candidate = trim(preg_replace('/\s+/', ' ', $candidate) ?? $candidate);
        $candidate = preg_replace('/^(?:EMITENTE|REMETENTE|DESTINATARIO|DESTINATARIO\s*\/\s*REMETENTE|TOMADOR|EXPEDIDOR|RECEBEDOR|TRANSPORTADOR|NOME\s*\/?\s*RAZAO\s+SOCIAL|RAZAO\s+SOCIAL|NOME)\s*[:\-]?\s*/iu', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B:-|");

        if ($candidate === '') {
            return null;
        }

        $normalized = $this->normalizeReadableText($candidate);

        if (
            strlen($normalized) < 6
            || preg_match('/^\d+$/', $normalized)
            || $this->looksLikeDocumentNumberWithoutLetters($candidate)
        ) {
            return null;
        }

        $blockedTerms = [
            'CNPJ',
            'CPF',
            'INSCRICAO',
            'I E',
            'IE',
            'ENDERECO',
            'MUNICIPIO',
            'CIDADE',
            'AV',
            'AVENIDA',
            'RUA',
            'ROD',
            'RODOVIA',
            'BAIRRO',
            'CEP',
            'FONE',
            'TELEFONE',
            'CHAVE DE ACESSO',
            'PROTOCOLO',
            'DATA',
            'NUMERO',
            'SERIE',
            'DANFE',
            'DACTE',
            'DOCUMENTO AUXILIAR',
            'ENTRADA',
            'SAIDA',
            'ORDEM DE COMPRA',
            'PEDIDO',
        ];

        foreach ($blockedTerms as $term) {
            if (str_contains($normalized, $term)) {
                return null;
            }
        }

        return mb_strtoupper($candidate, 'UTF-8');
    }

    private function extractInvoiceNumber(string $text, ?string $accessKey = null): ?string
    {
        $accessKeyInvoiceNumber = $accessKey ? $this->normalizeInvoiceNumber(substr($accessKey, 25, 9)) : null;

        if ($accessKeyInvoiceNumber) {
            return $accessKeyInvoiceNumber;
        }

        $nfseNumber = $this->extractNfseInvoiceNumber($text);

        if ($nfseNumber) {
            return $nfseNumber;
        }

        $patterns = [
            [
                'pattern' => '/N(?:.{0,3}|[uú])mero\s+da\s+NFS-?e\s*[:\-]?\s*(\d[\d\.]{0,14})/iu',
                'blocked' => ['protocolo', 'chave de acesso', 'cnpj', 'inscricao estadual', 'inscrição estadual'],
            ],
            [
                'pattern' => '/N(?:.{0,3}|[uú])mero\s+(?:da\s+)?(?:Nota\s+Fiscal|NF-?e)\s*[:\-]?\s*(\d[\d\.]{0,14})/iu',
                'blocked' => ['retorno simbolico', 'retorno simbólico', 'protocolo', 'chave de acesso', 'cnpj', 'duplicata', 'fatura', 'rps', 'codigo de verificacao', 'código de verificação'],
            ],
            [
                'pattern' => '/(?:DANFE|NF-?e).{0,120}?\bN[º°o\.]?\s*[:\-]?\s*(\d[\d\.]{2,14})\b.{0,80}?\bS[ée]rie\b/isu',
                'blocked' => ['retorno simbolico', 'retorno simbólico', 'protocolo', 'cnpj', 'duplicata', 'fatura', 'rps'],
            ],
            [
                'pattern' => '/\bN[º°o\.]?\s*[:\-]?\s*(\d[\d\.]{2,14})\b.{0,80}?\bS[ée]rie\b/isu',
                'blocked' => ['retorno simbolico', 'retorno simbólico', 'protocolo', 'cnpj', 'duplicata', 'fatura', 'rps'],
            ],
            [
                'pattern' => '/\bNota\s+Fiscal\s*(?:Eletr[oô]nica)?\s*(?:N[º°o\.])?\s*[:\-]?\s*(\d[\d\.]{2,14})/iu',
                'blocked' => ['retorno simbolico', 'retorno simbólico', 'protocolo', 'chave de acesso', 'cnpj', 'duplicata', 'fatura', 'rps'],
            ],
        ];

        foreach ($patterns as $definition) {
            if (! preg_match_all($definition['pattern'], $text, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] ?? [] as [$number, $offset]) {
                $normalized = $this->normalizeInvoiceNumber($number);

                if (! $this->isPlausibleInvoiceNumber($normalized)) {
                    continue;
                }

                if ($this->hasBlockedInvoiceNumberContext($text, $offset, $definition['blocked'])) {
                    continue;
                }

                return $normalized;
            }
        }

        return null;
    }

    private function extractNfseInvoiceNumber(string $text): ?string
    {
        $normalizedText = $this->normalizeReadableText($text);
        $looksLikeNfse = str_contains($normalizedText, 'NFS E')
            || str_contains($normalizedText, 'NOTA FISCAL DE SERVICO');

        if (! $looksLikeNfse) {
            return null;
        }

        $patterns = [
            '/\bNUMERO\s+DA\s+NFS\s*E\s*[:\-]?\s*(\d[\d\s\.]{0,18})\b/i',
            '/\bNUMERO\s+NFS\s*E\s*[:\-]?\s*(\d[\d\s\.]{0,18})\b/i',
            '/\bNFS\s*E\s+(?:N(?:O|UMERO)?\.?\s*)[:\-]?\s*(\d[\d\s\.]{0,18})\b/i',
            '/\bNUMERO\s+DA\s+NOTA\s*[:\-]?\s*(\d[\d\s\.]{0,18})\b/i',
            '/\bNOTA\s+FISCAL\s+DE\s+SERVICO.{0,140}?\bNUMERO\s*[:\-]?\s*(\d[\d\s\.]{0,18})\b/i',
            '/\bNOTA\s+FISCAL\s+DE\s+SERVICO.{0,140}?\bN(?:O|UMERO)?\.?\s*[:\-]?\s*(\d[\d\s\.]{0,18})\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $normalizedText, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] ?? [] as [$number, $offset]) {
                $normalized = $this->normalizeInvoiceNumber($number);

                if (! $this->isPlausibleInvoiceNumber($normalized)) {
                    continue;
                }

                if ($this->hasBlockedInvoiceNumberContext($normalizedText, $offset, [
                    'protocolo',
                    'inscricao municipal',
                ])) {
                    continue;
                }

                return $normalized;
            }
        }

        return null;
    }

    private function normalizeReadableText(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^A-Z0-9]+/i', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', strtoupper($text)) ?? strtoupper($text));
    }

    private function isPlausibleInvoiceNumber(string $number): bool
    {
        $length = strlen($number);

        return $length >= 1
            && $length <= 12
            && ! preg_match('/^0+$/', $number)
            && ! in_array($length, [14, 43, 44], true);
    }

    private function hasBlockedInvoiceNumberContext(string $text, int $offset, array $blockedTerms): bool
    {
        $context = mb_substr($text, max(0, $offset - 90), 190);
        $normalizedContext = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $context) ?: $context);

        foreach ($blockedTerms as $term) {
            $normalizedTerm = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $term) ?: $term);

            if ($normalizedTerm !== '' && str_contains($normalizedContext, $normalizedTerm)) {
                return true;
            }
        }

        return false;
    }

    private function extractAccessKey(string $text): ?string
    {
        preg_match_all('/(?<!\d)(\d(?:\D{0,4}\d){43,70})(?!\d)/u', $text, $matches);

        foreach ($matches[1] ?? [] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate) ?? '';

            for ($offset = 0; $offset <= strlen($digits) - 44; $offset++) {
                $key = substr($digits, $offset, 44);

                if (! in_array(substr($key, 20, 2), ['55', '57', '62'], true)) {
                    continue;
                }

                if (! $this->isValidFiscalAccessKey($key)) {
                    continue;
                }

                return $key;
            }
        }

        return null;
    }

    private function isValidFiscalAccessKey(string $key): bool
    {
        if (! preg_match('/^\d{44}$/', $key)) {
            return false;
        }

        $body = substr($key, 0, 43);
        $checkDigit = (int) substr($key, 43, 1);
        $weights = [2, 3, 4, 5, 6, 7, 8, 9];
        $sum = 0;

        for ($position = strlen($body) - 1, $weightIndex = 0; $position >= 0; $position--, $weightIndex++) {
            $sum += (int) $body[$position] * $weights[$weightIndex % count($weights)];
        }

        $remainder = $sum % 11;
        $calculated = $remainder < 2 ? 0 : 11 - $remainder;

        return $checkDigit === $calculated;
    }

    private function normalizeInvoiceNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        return ltrim($digits, '0') ?: '0';
    }
}
