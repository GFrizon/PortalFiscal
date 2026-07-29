<?php

namespace App\Console\Commands;

use App\Enums\AlertType;
use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Services\PdfExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class IdentifyInvoiceUnitsCommand extends Command
{
    protected $signature = 'invoices:identify-units
        {--all : Reprocess invoices that already have a unit too}
        {--dry-run : Show changes without updating invoices}';

    protected $description = 'Reprocess stored PDFs and identify invoice business units.';

    public function handle(PdfExtractionService $pdfExtractionService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $identified = 0;

        $query = Invoice::query()
            ->when(! $this->option('all'), fn ($query) => $query->whereNull('business_unit_id'))
            ->whereNotNull('pdf_path')
            ->orderBy('id');

        $query->chunkById(50, function ($invoices) use ($pdfExtractionService, $dryRun, &$checked, &$identified): void {
            foreach ($invoices as $invoice) {
                $checked++;

                if (! Storage::disk('local')->exists($invoice->pdf_path)) {
                    $this->warn("Missing PDF for {$invoice->protocol}: {$invoice->pdf_path}");
                    continue;
                }

                $extracted = $pdfExtractionService->extract(Storage::disk('local')->path($invoice->pdf_path));
                $recipientCnpj = $extracted['recipient_cnpj'] ?? null;

                if (! $recipientCnpj) {
                    $this->line("Still unidentified {$invoice->protocol}");
                    continue;
                }

                $businessUnit = BusinessUnit::query()
                    ->where('cnpj', $recipientCnpj)
                    ->first();

                if (! $businessUnit) {
                    $this->line("No registered unit for {$invoice->protocol}: {$recipientCnpj}");
                    continue;
                }

                $identified++;
                $this->line(($dryRun ? 'Would identify ' : 'Identifying ').$invoice->protocol.' as '.$businessUnit->name);

                if ($dryRun) {
                    continue;
                }

                $invoice->forceFill([
                    'business_unit_id' => $businessUnit->id,
                    'recipient_cnpj' => $invoice->recipient_cnpj ?: $recipientCnpj,
                    'issuer_cnpj' => $invoice->issuer_cnpj ?: ($extracted['issuer_cnpj'] ?? null),
                    'invoice_number' => $invoice->invoice_number ?: ($extracted['invoice_number'] ?? null),
                ])->save();

                $invoice->alerts()
                    ->where('type', AlertType::BusinessUnitNotIdentified->value)
                    ->where('resolved', false)
                    ->update([
                        'resolved' => true,
                        'resolved_at' => now(),
                    ]);
            }
        });

        $this->info(($dryRun ? 'Matched ' : 'Identified ').$identified.' of '.$checked.' invoices.');

        return self::SUCCESS;
    }
}
