<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_report_command_runs_successfully(): void
    {
        Invoice::factory()->create([
            'file_size' => 2048,
            'original_file_size' => 4096,
            'pdf_optimized' => true,
        ]);

        $this->artisan('invoices:storage-report')
            ->expectsOutputToContain('Invoice PDF storage report')
            ->assertExitCode(0);
    }

    public function test_optimize_command_dry_run_does_not_change_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'file_size' => 2048,
            'pdf_optimized' => false,
        ]);

        $this->artisan('invoices:optimize-pdfs --force --dry-run --limit=1')
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        $this->assertFalse($invoice->refresh()->pdf_optimized);
    }

    public function test_cleanup_command_dry_run_keeps_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('notas/tmp/teste.pdf', 'conteudo');

        $this->artisan('invoices:cleanup-storage --dry-run --days=0')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('notas/tmp/teste.pdf');
    }
}
