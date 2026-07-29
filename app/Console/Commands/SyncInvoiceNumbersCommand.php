<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\PdfExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncInvoiceNumbersCommand extends Command
{
    protected $signature = 'invoices:sync-numbers
        {--missing : Only update invoices without an invoice number}
        {--dry-run : Show changes without updating invoices}';

    protected $description = 'Reprocess stored PDFs and sync invoice numbers.';

    public function handle(PdfExtractionService $pdfExtractionService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $updated = 0;

        $query = Invoice::query()
            ->whereNotNull('pdf_path')
            ->when($this->option('missing'), function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('invoice_number')
                        ->orWhere('invoice_number', '');
                });
            })
            ->orderBy('id');

        $query->chunkById(50, function ($invoices) use ($pdfExtractionService, $dryRun, &$checked, &$updated): void {
            foreach ($invoices as $invoice) {
                $checked++;

                if (! Storage::disk('local')->exists($invoice->pdf_path)) {
                    $this->warn("Missing PDF for {$invoice->protocol}: {$invoice->pdf_path}");
                    continue;
                }

                $extracted = $pdfExtractionService->extract(Storage::disk('local')->path($invoice->pdf_path));
                $invoiceNumber = $extracted['invoice_number'] ?? null;

                if (! $invoiceNumber || $invoiceNumber === $invoice->invoice_number) {
                    continue;
                }

                $updated++;
                $this->line(($dryRun ? 'Would update ' : 'Updating ').$invoice->protocol.' invoice number from '.($invoice->invoice_number ?: '-').' to '.$invoiceNumber);

                if ($dryRun) {
                    continue;
                }

                $invoice->forceFill([
                    'invoice_number' => $invoiceNumber,
                ])->save();
            }
        });

        $this->info(($dryRun ? 'Matched ' : 'Updated ').$updated.' of '.$checked.' invoices.');

        return self::SUCCESS;
    }
}
