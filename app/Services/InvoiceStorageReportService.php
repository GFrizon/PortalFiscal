<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class InvoiceStorageReportService
{
    public function summary(): array
    {
        $invoiceCount = Invoice::query()->count();
        $optimizedCount = Invoice::query()->where('pdf_optimized', true)->count();
        $databaseStoredBytes = (int) Invoice::query()->sum('file_size');
        $databaseOriginalBytes = (int) (Invoice::query()
            ->selectRaw('SUM(COALESCE(original_file_size, file_size)) as total')
            ->value('total') ?? 0);
        $diskBytes = $this->directorySize(Storage::disk('local')->path('notas'));
        $tmpBytes = $this->directorySize(Storage::disk('local')->path('notas/tmp'));

        return [
            'invoice_count' => $invoiceCount,
            'optimized_count' => $optimizedCount,
            'database_stored_bytes' => $databaseStoredBytes,
            'database_original_bytes' => $databaseOriginalBytes,
            'database_saved_bytes' => max(0, $databaseOriginalBytes - $databaseStoredBytes),
            'disk_bytes' => $diskBytes,
            'tmp_bytes' => $tmpBytes,
            'average_pdf_bytes' => $invoiceCount > 0 ? (int) floor($databaseStoredBytes / $invoiceCount) : 0,
            'optimization_enabled' => (bool) config('invoices.pdf.optimization.enabled'),
            'optimization_binary' => (string) config('invoices.pdf.optimization.binary', 'gs'),
            'max_upload_kb' => (int) config('invoices.pdf.max_upload_kb', 10240),
        ];
    }

    public function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = max(0, $bytes);
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, $unitIndex === 0 ? 0 : 2, ',', '.').' '.$units[$unitIndex];
    }

    private function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $bytes += $file->getSize();
            }
        }

        return $bytes;
    }
}
