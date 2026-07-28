<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceStorageReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupInvoiceStorageCommand extends Command
{
    protected $signature = 'invoices:cleanup-storage
        {--days=1 : Minimum age in days for temporary files}
        {--orphans : Also remove PDF files not referenced by invoices}
        {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Clean temporary and orphaned invoice PDF files.';

    public function handle(InvoiceStorageReportService $reportService): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays(max(0, (int) $this->option('days')))->getTimestamp();
        $dryRun = (bool) $this->option('dry-run');
        $deleted = 0;
        $deletedBytes = 0;

        foreach ($disk->allFiles('notas/tmp') as $path) {
            if ($disk->lastModified($path) > $cutoff) {
                continue;
            }

            $size = $disk->size($path);
            $this->line(($dryRun ? 'Would delete ' : 'Deleting ').$path.' ('.$reportService->humanBytes($size).')');

            if (! $dryRun) {
                $disk->delete($path);
            }

            $deleted++;
            $deletedBytes += $size;
        }

        foreach ($disk->allFiles('notas') as $path) {
            if (! str_ends_with($path, '.optimized.pdf')) {
                continue;
            }

            $size = $disk->size($path);
            $this->line(($dryRun ? 'Would delete ' : 'Deleting ').$path.' ('.$reportService->humanBytes($size).')');

            if (! $dryRun) {
                $disk->delete($path);
            }

            $deleted++;
            $deletedBytes += $size;
        }

        if ($this->option('orphans')) {
            $knownPaths = Invoice::query()
                ->whereNotNull('pdf_path')
                ->pluck('pdf_path')
                ->flip();

            foreach ($disk->allFiles('notas') as $path) {
                if (str_starts_with($path, 'notas/tmp/') || ! str_ends_with(strtolower($path), '.pdf')) {
                    continue;
                }

                if ($knownPaths->has($path)) {
                    continue;
                }

                $size = $disk->size($path);
                $this->line(($dryRun ? 'Would delete orphan ' : 'Deleting orphan ').$path.' ('.$reportService->humanBytes($size).')');

                if (! $dryRun) {
                    $disk->delete($path);
                }

                $deleted++;
                $deletedBytes += $size;
            }
        }

        $this->info(($dryRun ? 'Matched ' : 'Deleted ').$deleted.' files, '.$reportService->humanBytes($deletedBytes).'.');

        return self::SUCCESS;
    }
}
