<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\PdfExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncInvoiceSupplierNamesCommand extends Command
{
    protected $signature = 'invoices:sync-suppliers
        {--all : Reprocess invoices even when current names do not look suspicious}
        {--dry-run : Show changes without updating invoices}';

    protected $description = 'Reprocess stored PDFs and sync suspicious issuer/recipient names.';

    public function handle(PdfExtractionService $pdfExtractionService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $forceAll = (bool) $this->option('all');
        $checked = 0;
        $updated = 0;

        Invoice::query()
            ->whereNotNull('pdf_path')
            ->orderBy('id')
            ->chunkById(50, function ($invoices) use ($pdfExtractionService, $dryRun, $forceAll, &$checked, &$updated): void {
                foreach ($invoices as $invoice) {
                    $checked++;

                    if (! Storage::disk('local')->exists($invoice->pdf_path)) {
                        $this->warn("Missing PDF for {$invoice->protocol}: {$invoice->pdf_path}");
                        continue;
                    }

                    $issuerLooksSuspicious = blank($invoice->issuer_legal_name) || $pdfExtractionService->isSuspiciousLegalName($invoice->issuer_legal_name);
                    $recipientLooksSuspicious = blank($invoice->recipient_legal_name) || $pdfExtractionService->isSuspiciousLegalName($invoice->recipient_legal_name);

                    if (! $forceAll && ! $issuerLooksSuspicious && ! $recipientLooksSuspicious) {
                        continue;
                    }

                    $extracted = $pdfExtractionService->extract(Storage::disk('local')->path($invoice->pdf_path));
                    $updates = [];
                    $targetIssuer = $extracted['issuer_legal_name'] ?? null;
                    $targetRecipient = $extracted['recipient_legal_name'] ?? null;

                    if (
                        filled($targetIssuer)
                        && ($forceAll || $issuerLooksSuspicious)
                        && $targetIssuer !== $invoice->issuer_legal_name
                        && $this->isReliableBackfillLegalName($targetIssuer, $pdfExtractionService)
                    ) {
                        $updates['issuer_legal_name'] = $targetIssuer;
                    }

                    if (
                        filled($targetRecipient)
                        && ($forceAll || $recipientLooksSuspicious)
                        && $targetRecipient !== $invoice->recipient_legal_name
                        && $this->isReliableBackfillLegalName($targetRecipient, $pdfExtractionService)
                    ) {
                        $updates['recipient_legal_name'] = $targetRecipient;
                    }

                    if ($updates === []) {
                        continue;
                    }

                    $updated++;
                    $summary = collect($updates)
                        ->map(fn ($value, $field) => $field.'='.$value)
                        ->implode(', ');

                    $this->line(($dryRun ? 'Would update ' : 'Updating ').$invoice->protocol.' '.$summary);

                    if ($dryRun) {
                        continue;
                    }

                    $invoice->forceFill($updates)->save();
                }
            });

        $this->info(($dryRun ? 'Matched ' : 'Updated ').$updated.' of '.$checked.' invoices.');

        return self::SUCCESS;
    }

    private function isReliableBackfillLegalName(string $name, PdfExtractionService $pdfExtractionService): bool
    {
        if ($pdfExtractionService->isSuspiciousLegalName($name)) {
            return false;
        }

        if (
            preg_match('/\b\d{1,2}\/\d{1,2}\/\d{2,4}\b/', $name)
            || preg_match('/\b\d{1,2}:\d{2}(?::\d{2})?\b/', $name)
            || preg_match('/(?:R\$|\b\d{1,3}(?:\.\d{3})*,\d{2}\b)/', $name)
        ) {
            return false;
        }

        $normalized = $this->normalizeName($name);
        $blockedTerms = [
            'REGIME',
            'APURACAO',
            'TRIBUTO',
            'TRIBUTARIA',
            'TRIBUTOS',
            'TRIBUTARIO',
            'PIS',
            'COFINS',
            'VALOR',
            'NATUREZA DA OPERACAO',
            'RETENCAO',
            'ALIQUOTA',
            'BASE DE CALCULO',
            'RAZAO SOCIAL',
            'DATA',
            'HORA',
            'VENCIMENTO',
            'INSCRICAO',
        ];

        foreach ($blockedTerms as $term) {
            if (str_contains($normalized, $term)) {
                return false;
            }
        }

        $alphaTokens = array_values(array_filter(
            preg_split('/\s+/', preg_replace('/[^A-Z]+/', ' ', $normalized) ?? $normalized) ?: [],
            fn (string $token): bool => strlen($token) >= 2
        ));

        if (count($alphaTokens) < 2) {
            return false;
        }

        $companyIndicators = [
            'LTDA',
            'S A',
            'SA',
            'EIRELI',
            'ME',
            'MEI',
            'INDUSTRIA',
            'IND',
            'COMERCIO',
            'COM',
            'SERVICOS',
            'SERVICO',
            'TRANSPORTES',
            'TRANSPORTADORA',
            'DISTRIBUIDORA',
            'PLASTICOS',
            'ENERGIA',
        ];

        $hasCompanyIndicator = false;

        foreach ($companyIndicators as $indicator) {
            if (str_contains($normalized, $indicator)) {
                $hasCompanyIndicator = true;
                break;
            }
        }

        return count($alphaTokens) >= 3 || $hasCompanyIndicator;
    }

    private function normalizeName(string $name): string
    {
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $name = preg_replace('/[^A-Z0-9]+/i', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', strtoupper($name)) ?? strtoupper($name));
    }
}
