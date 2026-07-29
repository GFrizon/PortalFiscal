<?php

namespace Tests\Feature;

use App\Enums\AlertType;
use App\Enums\AlertLevel;
use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Services\PdfExtractionService;
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

    public function test_sync_due_dates_uses_earliest_installment(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => '2026-09-10',
            'payment_installments' => [
                ['number' => 1, 'due_date' => '2026-08-10', 'amount' => 100],
                ['number' => 2, 'due_date' => '2026-09-10', 'amount' => 200],
            ],
        ]);

        $this->artisan('invoices:sync-due-dates')
            ->expectsOutputToContain('Updating '.$invoice->protocol)
            ->assertExitCode(0);

        $this->assertSame('2026-08-10', $invoice->refresh()->due_date?->format('Y-m-d'));
    }

    public function test_identify_units_command_updates_unidentified_invoice(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('notas/2026/07/unidade-nao-identificada/teste.pdf', 'pdf');

        $unit = BusinessUnit::factory()->create([
            'name' => 'UNIDADE 004',
            'cnpj' => '91967067000317',
        ]);

        $invoice = Invoice::factory()->create([
            'business_unit_id' => null,
            'recipient_cnpj' => null,
            'pdf_path' => 'notas/2026/07/unidade-nao-identificada/teste.pdf',
        ]);

        $invoice->alerts()->create([
            'type' => AlertType::BusinessUnitNotIdentified,
            'message' => 'Unidade nao identificada.',
            'level' => AlertLevel::Warning,
        ]);

        $this->mock(PdfExtractionService::class, function ($mock): void {
            $mock->shouldReceive('extract')->once()->andReturn([
                'success' => true,
                'text' => '',
                'cnpjs' => ['91967067000317'],
                'issuer_cnpj' => null,
                'recipient_cnpj' => '91967067000317',
                'invoice_number' => null,
                'issuer_legal_name' => null,
                'recipient_legal_name' => null,
                'error' => null,
            ]);
        });

        $this->artisan('invoices:identify-units')
            ->expectsOutputToContain('Identifying '.$invoice->protocol)
            ->assertExitCode(0);

        $this->assertSame($unit->id, $invoice->refresh()->business_unit_id);
        $this->assertSame('91967067000317', $invoice->recipient_cnpj);
        $this->assertTrue($invoice->alerts()->firstOrFail()->resolved);
    }
}
