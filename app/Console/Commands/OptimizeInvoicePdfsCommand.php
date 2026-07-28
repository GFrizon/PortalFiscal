<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceStorageReportService;
use App\Services\PdfOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizeInvoicePdfsCommand extends Command
{
    protected $signature = 'invoices:optimize-pdfs
        {--limit=25 : Maximum number of PDFs to inspect}
        {--min-size-kb=0 : Only inspect PDFs at least this size}
        {--force : Run even when automatic optimization is disabled}
        {--dry-run : Show selected PDFs without changing files}';

    protected $description = 'Optimize stored invoice PDFs in small batches.';

    public function handle(PdfOptimizationService $optimizer, InvoiceStorageReportService $reportService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $minSizeBytes = max(0, (int) $this->option('min-size-kb')) * 1024;
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if (! $force && ! config('invoices.pdf.optimization.enabled')) {
            $this->warn('PDF optimization is disabled. Enable INVOICE_PDF_OPTIMIZATION_ENABLED=true or run with --force.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $optimizer->binaryIsAvailable()) {
            $this->error('PDF optimization binary not available: '.config('invoices.pdf.optimization.binary', 'gs'));

            return self::FAILURE;
        }

        $query = Invoice::query()
            ->where('pdf_optimized', false)
            ->where('file_size', '>=', $minSizeBytes)
            ->orderByDesc('file_size')
            ->limit($limit);

        $invoices = $query->get();

        if ($invoices->isEmpty()) {
            $this->info('No PDFs found for optimization.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry run: PDFs selected for optimization');
            $this->table(['Protocol', 'Path', 'Size'], $invoices->map(fn (Invoice $invoice): array => [
                $invoice->protocol,
                $invoice->pdf_path,
                $reportService->humanBytes((int) $invoice->file_size),
            ])->all());

            return self::SUCCESS;
        }

        $optimized = 0;
        $inspected = 0;
        $savedBytes = 0;

        foreach ($invoices as $invoice) {
            $inspected++;

            if (! Storage::disk('local')->exists($invoice->pdf_path)) {
                $this->warn("Missing file for {$invoice->protocol}: {$invoice->pdf_path}");
                continue;
            }

            $beforeSize = Storage::disk('local')->size($invoice->pdf_path);
            $result = $optimizer->optimize($invoice->pdf_path, $force);
            $storedSize = (int) $result['size'];

            $invoice->update([
                'original_file_size' => $invoice->original_file_size ?: $beforeSize,
                'file_size' => $storedSize,
                'pdf_optimized' => (bool) $result['optimized'],
                'pdf_processed_at' => now(),
            ]);

            if ($result['optimized']) {
                $optimized++;
                $savedBytes += (int) $result['saved_bytes'];
                $this->line("Optimized {$invoice->protocol}: ".$reportService->humanBytes($beforeSize).' -> '.$reportService->humanBytes($storedSize));
            } else {
                $this->line("Kept {$invoice->protocol}: ".$reportService->humanBytes($beforeSize));
            }
        }

        $this->info("Inspected {$inspected} PDFs, optimized {$optimized}, saved ".$reportService->humanBytes($savedBytes).'.');

        return self::SUCCESS;
    }
}
