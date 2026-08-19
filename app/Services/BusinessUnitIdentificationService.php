<?php

namespace App\Services;

use App\Models\BusinessUnit;
use Illuminate\Support\Collection;

class BusinessUnitIdentificationService
{
    public function identify(array $extracted): ?BusinessUnit
    {
        $units = BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name', 'legal_name', 'cnpj', 'internal_code', 'status']);

        if ($units->isEmpty()) {
            return null;
        }

        $recipientCnpj = $this->onlyDigits($extracted['recipient_cnpj'] ?? null);

        if ($recipientCnpj !== '') {
            $byRecipient = $units->first(fn (BusinessUnit $unit): bool => $this->onlyDigits($unit->cnpj) === $recipientCnpj);

            if ($byRecipient) {
                return $byRecipient;
            }
        }

        $issuerCnpj = $this->onlyDigits($extracted['issuer_cnpj'] ?? null);
        $cnpjs = collect((array) ($extracted['cnpjs'] ?? []))
            ->map(fn (mixed $cnpj): string => $this->onlyDigits($cnpj))
            ->filter()
            ->unique()
            ->values();

        $unitByNonIssuerCnpj = $units
            ->filter(fn (BusinessUnit $unit): bool => $cnpjs->contains($this->onlyDigits($unit->cnpj)))
            ->reject(fn (BusinessUnit $unit): bool => $issuerCnpj !== '' && $this->onlyDigits($unit->cnpj) === $issuerCnpj)
            ->values();

        if ($unitByNonIssuerCnpj->count() === 1) {
            return $unitByNonIssuerCnpj->first();
        }

        $byName = $this->identifyByName($units, $extracted);

        if ($byName) {
            return $byName;
        }

        if ($issuerCnpj === '' && $unitByNonIssuerCnpj->count() > 0) {
            return $unitByNonIssuerCnpj->first();
        }

        return null;
    }

    /**
     * @param Collection<int, BusinessUnit> $units
     */
    private function identifyByName(Collection $units, array $extracted): ?BusinessUnit
    {
        $haystack = $this->normalizeText(implode(' ', array_filter([
            $extracted['recipient_legal_name'] ?? null,
            $extracted['text'] ?? null,
        ], fn (mixed $value): bool => filled($value))));

        if ($haystack === '') {
            return null;
        }

        $matches = $units
            ->filter(function (BusinessUnit $unit) use ($haystack): bool {
                $needles = array_filter([
                    $this->normalizeText($unit->legal_name),
                    $this->normalizeText($unit->name),
                    $this->normalizeText($unit->internal_code),
                ]);

                foreach ($needles as $needle) {
                    if (mb_strlen($needle) >= 4 && str_contains($haystack, $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function onlyDigits(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    private function normalizeText(mixed $value): string
    {
        $text = mb_strtoupper(trim((string) $value), 'UTF-8');
        $text = strtr($text, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ]);

        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $text) ?? '');
    }
}
