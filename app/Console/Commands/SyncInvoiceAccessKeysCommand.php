<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\PdfExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncInvoiceAccessKeysCommand extends Command
{
    protected $signature = 'invoices:sync-access-keys
        {--all : Reprocess invoices that already have an access key too}
        {--dry-run : Show changes without updating invoices}';

    protected $description = 'Reprocess stored PDFs and sync NF-e access keys.';

    public function handle(PdfExtractionService $pdfExtractionService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $updated = 0;

        $query = Invoice::query()
            ->whereNotNull('pdf_path')
            ->when(! $this->option('all'), function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('invoice_access_key')
                        ->orWhere('invoice_access_key', '');
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
                $accessKey = $extracted['invoice_access_key'] ?? null;

                if (! $accessKey || $accessKey === $invoice->invoice_access_key) {
                    continue;
                }

                $updated++;
                $this->line(($dryRun ? 'Would update ' : 'Updating ').$invoice->protocol.' access key to '.$accessKey);

                if ($dryRun) {
                    continue;
                }

                $invoice->forceFill([
                    'invoice_access_key' => $accessKey,
                ])->save();
            }
        });

        $this->info(($dryRun ? 'Matched ' : 'Updated ').$updated.' of '.$checked.' invoices.');

        return self::SUCCESS;
    }
}
