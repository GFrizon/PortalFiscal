<?php

namespace App\Services;

use App\Models\BusinessUnit;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfExtractionService
{
    public function __construct(private readonly Parser $parser)
    {
    }

    public function extract(string $absolutePath): array
    {
        try {
            $pdf = $this->parser->parseFile($absolutePath);

            return $this->extractFromText($pdf->getText());
        } catch (Throwable $exception) {
            Log::warning('Falha ao extrair texto do PDF.', [
                'path' => $absolutePath,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'text' => '',
                'cnpjs' => [],
                'issuer_cnpj' => null,
                'recipient_cnpj' => null,
                'invoice_number' => null,
                'issuer_legal_name' => null,
                'recipient_legal_name' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function extractFromText(string $text): array
    {
        $cnpjs = $this->extractCnpjs($text);
        $recipientCnpj = $this->identifyRecipientCnpj($cnpjs, $text);

        return [
            'success' => true,
            'text' => $text,
            'cnpjs' => $cnpjs,
            'issuer_cnpj' => $this->identifyIssuerCnpj($cnpjs, $recipientCnpj),
            'recipient_cnpj' => $recipientCnpj,
            'invoice_number' => $this->extractInvoiceNumber($text),
            'issuer_legal_name' => null,
            'recipient_legal_name' => null,
            'error' => null,
        ];
    }

    public function normalizeCnpj(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj) ?? '';
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

    private function identifyIssuerCnpj(array $cnpjs, ?string $recipientCnpj): ?string
    {
        return collect($cnpjs)
            ->first(fn (string $cnpj) => $cnpj !== $recipientCnpj);
    }

    private function extractInvoiceNumber(string $text): ?string
    {
        $patterns = [
            '/N(?:F|OTA)\s*(?:E|FISCAL)?\s*(?:N[ºO.]*)?\s*[:\-]?\s*(\d{3,})/iu',
            '/Numero\s*(?:da)?\s*Nota\s*[:\-]?\s*(\d{3,})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $match[1];
            }
        }

        return null;
    }
}
