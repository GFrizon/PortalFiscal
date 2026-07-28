<?php

namespace App\Console\Commands;

use App\Services\InvoiceStorageReportService;
use Illuminate\Console\Command;

class InvoiceStorageReportCommand extends Command
{
    protected $signature = 'invoices:storage-report {--json : Output as JSON}';

    protected $description = 'Show invoice PDF storage usage.';

    public function handle(InvoiceStorageReportService $reportService): int
    {
        $summary = $reportService->summary();

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Invoice PDF storage report');
        $this->table(['Metric', 'Value'], [
            ['Notas cadastradas', $summary['invoice_count']],
            ['PDFs otimizados', $summary['optimized_count']],
            ['Tamanho informado no banco', $reportService->humanBytes($summary['database_stored_bytes'])],
            ['Tamanho original no banco', $reportService->humanBytes($summary['database_original_bytes'])],
            ['Economia registrada', $reportService->humanBytes($summary['database_saved_bytes'])],
            ['Uso real em storage/app/private/notas', $reportService->humanBytes($summary['disk_bytes'])],
            ['Temporarios em notas/tmp', $reportService->humanBytes($summary['tmp_bytes'])],
            ['Media por PDF', $reportService->humanBytes($summary['average_pdf_bytes'])],
            ['Limite de upload', $summary['max_upload_kb'].' KB'],
            ['Otimizacao automatica', $summary['optimization_enabled'] ? 'ativa' : 'inativa'],
            ['Binario de otimizacao', $summary['optimization_binary']],
        ]);

        return self::SUCCESS;
    }
}
