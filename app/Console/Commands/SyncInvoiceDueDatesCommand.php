<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class SyncInvoiceDueDatesCommand extends Command
{
    protected $signature = 'invoices:sync-due-dates {--dry-run : Show changes without updating invoices}';

    protected $description = 'Sync invoice due dates from the earliest installment due date.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $changed = 0;

        Invoice::query()
            ->whereNotNull('payment_installments')
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use ($dryRun, &$checked, &$changed): void {
                foreach ($invoices as $invoice) {
                    $checked++;
                    $dueDate = collect($invoice->payment_installments ?? [])
                        ->pluck('due_date')
                        ->filter()
                        ->sort()
                        ->first();

                    if ($invoice->due_date?->format('Y-m-d') === $dueDate) {
                        continue;
                    }

                    $changed++;
                    $this->line(($dryRun ? 'Would update ' : 'Updating ').$invoice->protocol.' due date to '.($dueDate ?: '-'));

                    if (! $dryRun) {
                        $invoice->forceFill(['due_date' => $dueDate])->save();
                    }
                }
            });

        $this->info(($dryRun ? 'Matched ' : 'Updated ').$changed.' of '.$checked.' invoices.');

        return self::SUCCESS;
    }
}
