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

                    if (filled($targetIssuer) && ($forceAll || $issuerLooksSuspicious) && $targetIssuer !== $invoice->issuer_legal_name) {
                        $updates['issuer_legal_name'] = $targetIssuer;
                    }

                    if (filled($targetRecipient) && ($forceAll || $recipientLooksSuspicious) && $targetRecipient !== $invoice->recipient_legal_name) {
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
}
