<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\AlertLevel;
use App\Enums\AlertType;
use App\Models\Invoice;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Services\PdfExtractionService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NavigationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_navigation_pages_render_successfully(): void
    {
        $admin = User::factory()->admin()->create();

        $routes = [
            'dashboard',
            'invoices.index',
            'invoices.create',
            'histories.index',
            'admin.users.index',
            'admin.users.create',
            'admin.business-units.index',
            'admin.business-units.create',
            'admin.settings.index',
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_fiscal_can_access_invoice_navigation_pages(): void
    {
        $fiscal = User::factory()->fiscal()->create();

        $this->actingAs($fiscal)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notas fiscais')
            ->assertSee('Anexar nota')
            ->assertSee('Historico');

        $this->actingAs($fiscal)
            ->get(route('invoices.index'))
            ->assertOk();

        $this->actingAs($fiscal)
            ->get(route('invoices.create'))
            ->assertOk();

        $this->actingAs($fiscal)
            ->get(route('histories.index'))
            ->assertOk();
    }

    public function test_regular_user_can_access_invoice_navigation_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notas fiscais')
            ->assertSee('Anexar nota')
            ->assertSee('Historico')
            ->assertDontSee('Usuarios')
            ->assertDontSee('Configuracoes');

        $this->actingAs($user)
            ->get(route('invoices.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('invoices.create'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('histories.index'))
            ->assertOk();
    }

    public function test_regular_user_history_only_shows_own_invoice_actions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownInvoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
        ]);
        $otherInvoice = Invoice::factory()->create([
            'submitted_by' => $otherUser->id,
        ]);

        $ownInvoice->histories()->create([
            'user_id' => $user->id,
            'action' => 'Acao propria',
            'new_status' => 'awaiting_review',
            'ip_address' => '127.0.0.1',
        ]);
        $otherInvoice->histories()->create([
            'user_id' => $otherUser->id,
            'action' => 'Acao de outro usuario',
            'new_status' => 'awaiting_review',
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($user)
            ->get(route('histories.index'))
            ->assertOk()
            ->assertSee('Acao propria')
            ->assertDontSee('Acao de outro usuario');
    }

    public function test_history_index_groups_events_by_invoice_and_detail_shows_timeline(): void
    {
        $admin = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create([
            'submitted_by' => $admin->id,
            'protocol' => 'NF-2026-999001',
        ]);

        $invoice->histories()->create([
            'user_id' => $admin->id,
            'action' => 'Primeira acao',
            'new_status' => 'awaiting_review',
            'ip_address' => '127.0.0.1',
        ]);

        $invoice->histories()->create([
            'user_id' => $admin->id,
            'action' => 'Segunda acao',
            'new_status' => 'in_review',
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($admin)
            ->get(route('histories.index'))
            ->assertOk()
            ->assertSee('NF-2026-999001')
            ->assertSee('Segunda acao')
            ->assertSee('Ver historico')
            ->assertDontSee('Primeira acao');

        $this->actingAs($admin)
            ->get(route('histories.show', $invoice))
            ->assertOk()
            ->assertSee('Primeira acao')
            ->assertSee('Segunda acao');
    }

    public function test_regular_user_can_attach_invoice_pdf(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $unit = BusinessUnit::factory()->create([
            'name' => 'Matriz',
            'cnpj' => '11222333000181',
        ]);

        $this->mock(PdfExtractionService::class, function ($mock): void {
            $mock->shouldReceive('extract')->once()->andReturn([
                'success' => true,
                'text' => 'PDF simulado',
                'cnpjs' => ['12345678000195', '11222333000181'],
                'issuer_cnpj' => '12345678000195',
                'recipient_cnpj' => '11222333000181',
                'invoice_number' => '123456',
                'issuer_legal_name' => null,
                'recipient_legal_name' => null,
                'error' => null,
            ]);
            $mock->shouldReceive('normalizeCnpj')->andReturnUsing(fn (string $cnpj) => preg_replace('/\D/', '', $cnpj) ?? '');
        });

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'pdf' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
                'purchase_order_number' => '123456',
                'arrival_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'user_notes' => 'Nota enviada em teste.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'submitted_by' => $user->id,
            'business_unit_id' => $unit->id,
            'purchase_order_number' => '123456',
            'status' => 'awaiting_review',
        ]);

        $invoice = Invoice::query()->where('submitted_by', $user->id)->firstOrFail();

        $this->assertStringContainsString('notas/'.now()->format('Y/m').'/matriz/', $invoice->pdf_path);
        $this->assertNotNull($invoice->pdf_sha256);
        $this->assertSame($invoice->original_file_size, $invoice->file_size);
        $this->assertFalse($invoice->pdf_optimized);
        Storage::disk('local')->assertExists($invoice->pdf_path);
    }

    public function test_invoice_upload_requires_tracking_fields_and_numeric_purchase_order(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'pdf' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
                'purchase_order_number' => 'OC123456',
                'arrival_date' => null,
                'due_date' => null,
            ])
            ->assertSessionHasErrors(['purchase_order_number', 'arrival_date', 'due_date']);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_user_can_delete_invoice_that_was_not_launched_and_pdf_is_removed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
            'status' => 'awaiting_review',
            'pdf_path' => 'notas/teste/nota.pdf',
        ]);

        Storage::disk('local')->put($invoice->pdf_path, 'PDF fake');

        $this->actingAs($user)
            ->delete(route('invoices.destroy', $invoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseMissing('invoices', [
            'id' => $invoice->id,
        ]);

        Storage::disk('local')->assertMissing($invoice->pdf_path);
    }

    public function test_launched_invoice_cannot_be_deleted(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
            'status' => 'launched',
            'pdf_path' => 'notas/teste/lancada.pdf',
        ]);

        Storage::disk('local')->put($invoice->pdf_path, 'PDF fake');

        $this->actingAs($user)
            ->delete(route('invoices.destroy', $invoice))
            ->assertForbidden();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
        ]);

        Storage::disk('local')->assertExists($invoice->pdf_path);
    }

    public function test_duplicate_pdf_upload_creates_warning_alert(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->mock(PdfExtractionService::class, function ($mock): void {
            $mock->shouldReceive('extract')->twice()->andReturn([
                'success' => true,
                'text' => 'PDF simulado',
                'cnpjs' => ['12345678000195'],
                'issuer_cnpj' => '12345678000195',
                'recipient_cnpj' => null,
                'invoice_number' => '123456',
                'issuer_legal_name' => null,
                'recipient_legal_name' => null,
                'error' => null,
            ]);
            $mock->shouldReceive('normalizeCnpj')->andReturnUsing(fn (string $cnpj) => preg_replace('/\D/', '', $cnpj) ?? '');
        });

        foreach ([1, 2] as $attempt) {
            $this->actingAs($user)
                ->post(route('invoices.store'), [
                    'pdf' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
                    'purchase_order_number' => '12345'.$attempt,
                    'arrival_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(10)->format('Y-m-d'),
                    'user_notes' => 'Envio '.$attempt,
                ])
                ->assertRedirect();
        }

        $secondInvoice = Invoice::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('invoice_alerts', [
            'invoice_id' => $secondInvoice->id,
            'type' => AlertType::DuplicatePdf->value,
            'level' => AlertLevel::Warning->value,
        ]);
    }

    public function test_purchase_order_lookup_failure_creates_technical_alert_instead_of_not_found(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->mock(PdfExtractionService::class, function ($mock): void {
            $mock->shouldReceive('extract')->once()->andReturn([
                'success' => true,
                'text' => 'PDF simulado',
                'cnpjs' => ['12345678000195'],
                'issuer_cnpj' => '12345678000195',
                'recipient_cnpj' => null,
                'invoice_number' => '123456',
                'issuer_legal_name' => null,
                'recipient_legal_name' => null,
                'error' => null,
            ]);
            $mock->shouldReceive('normalizeCnpj')->andReturnUsing(fn (string $cnpj) => preg_replace('/\D/', '', $cnpj) ?? '');
        });

        $this->mock(PurchaseOrderService::class, function ($mock): void {
            $mock->shouldReceive('find')->once()->andReturn([
                'exists' => false,
                'status' => null,
                'supplier_cnpj' => null,
                'supplier_name' => null,
                'business_unit_id' => null,
                'amount' => null,
                'raw_response' => [
                    'source' => 'oci8_missing',
                    'number' => '123456',
                ],
            ]);
        });

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'pdf' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
                'purchase_order_number' => '123456',
                'arrival_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'user_notes' => null,
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('submitted_by', $user->id)->firstOrFail();

        $this->assertDatabaseHas('invoice_alerts', [
            'invoice_id' => $invoice->id,
            'type' => AlertType::PurchaseOrderLookupFailed->value,
        ]);

        $this->assertDatabaseMissing('invoice_alerts', [
            'invoice_id' => $invoice->id,
            'type' => AlertType::PurchaseOrderNotFound->value,
        ]);
    }

    public function test_fiscal_can_update_unit_and_mark_invoice_as_launched(): void
    {
        $user = User::factory()->create();
        $fiscal = User::factory()->fiscal()->create();
        $unit = BusinessUnit::factory()->create([
            'name' => 'Unidade Fiscal',
        ]);

        $invoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
            'business_unit_id' => null,
            'status' => 'awaiting_review',
        ]);

        $this->actingAs($fiscal)
            ->patch(route('invoices.unit.update', $invoice), [
                'business_unit_id' => $unit->id,
            ])
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'business_unit_id' => $unit->id,
        ]);

        $this->actingAs($fiscal)
            ->post(route('invoices.mark-as-launched', $invoice), [
                'fiscal_notes' => 'Lancada no ERP.',
            ])
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'launched',
            'fiscal_user_id' => $fiscal->id,
            'fiscal_notes' => 'Lancada no ERP.',
        ]);

        $this->assertDatabaseHas('invoice_histories', [
            'invoice_id' => $invoice->id,
            'user_id' => $fiscal->id,
            'action' => 'Nota marcada como lancada',
        ]);
    }

    public function test_fiscal_must_resolve_critical_alert_before_launching_invoice(): void
    {
        $user = User::factory()->create();
        $fiscal = User::factory()->fiscal()->create();
        $invoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
            'status' => 'awaiting_review',
        ]);

        $alert = $invoice->alerts()->create([
            'type' => AlertType::CnpjMismatch,
            'message' => 'CNPJ divergente.',
            'level' => AlertLevel::Critical,
        ]);

        $this->actingAs($fiscal)
            ->post(route('invoices.mark-as-launched', $invoice), [
                'fiscal_notes' => 'Tentativa bloqueada.',
            ])
            ->assertSessionHasErrors('fiscal_notes');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'awaiting_review',
        ]);

        $this->actingAs($fiscal)
            ->post(route('invoices.alerts.resolve', [$invoice, $alert]))
            ->assertRedirect(route('invoices.show', $invoice));

        $this->actingAs($fiscal)
            ->post(route('invoices.mark-as-launched', $invoice), [
                'fiscal_notes' => 'Lancada apos resolver alerta.',
            ])
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'launched',
            'fiscal_user_id' => $fiscal->id,
        ]);

        $this->assertDatabaseHas('invoice_alerts', [
            'id' => $alert->id,
            'resolved' => true,
            'resolved_by' => $fiscal->id,
        ]);
    }

    public function test_invoice_details_show_submitter_notes(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
            'user_notes' => 'Entregar urgente ao fiscal.',
        ]);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Observacoes de '.$user->name)
            ->assertSee('Entregar urgente ao fiscal.');
    }

    public function test_regular_user_can_see_purchase_order_check_on_invoice_details(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
            'purchase_order_number' => '123456',
        ]);

        $invoice->purchaseOrderCheck()->create([
            'purchase_order_number' => '123456',
            'exists' => true,
            'status' => 'aberta',
            'supplier_cnpj' => '12345678000195',
            'supplier_name' => 'Fornecedor CIGAM',
            'amount' => 1500.00,
            'raw_response' => [
                'purchase_order_number' => '123456',
                'supplier_code' => 'FOR001',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Ordem de compra no CIGAM')
            ->assertSee('Fornecedor CIGAM')
            ->assertSee('FOR001');
    }

    public function test_invoice_index_groups_notes_as_unit_folders(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = BusinessUnit::factory()->create([
            'name' => 'Unidade Pasta',
        ]);

        Invoice::factory()->create([
            'business_unit_id' => $unit->id,
        ]);

        Invoice::factory()->create([
            'business_unit_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Pastas por unidade')
            ->assertSee('Unidade Pasta')
            ->assertSee('Nao identificada');

        $this->actingAs($admin)
            ->get(route('invoices.index', ['business_unit_id' => 'none']))
            ->assertOk()
            ->assertSee('Pasta selecionada')
            ->assertSee('Nao identificada');
    }

    public function test_invoice_index_only_shows_operational_statuses(): void
    {
        $admin = User::factory()->admin()->create();

        Invoice::factory()->create([
            'protocol' => 'NF-2026-OP001',
            'status' => 'awaiting_review',
        ]);

        Invoice::factory()->create([
            'protocol' => 'NF-2026-OP002',
            'status' => 'pending',
        ]);

        Invoice::factory()->create([
            'protocol' => 'NF-2026-DONE1',
            'status' => 'launched',
        ]);

        Invoice::factory()->create([
            'protocol' => 'NF-2026-CANC1',
            'status' => 'cancelled',
        ]);

        Invoice::factory()->create([
            'protocol' => 'NF-2026-REV01',
            'status' => 'in_review',
        ]);

        $this->actingAs($admin)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('NF-2026-OP001')
            ->assertSee('NF-2026-OP002')
            ->assertDontSee('NF-2026-DONE1')
            ->assertDontSee('NF-2026-CANC1')
            ->assertDontSee('NF-2026-REV01')
            ->assertDontSee('Lancada')
            ->assertDontSee('Cancelada')
            ->assertDontSee('Em conferencia');
    }

    public function test_admin_can_create_update_and_block_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Usuario Teste',
                'email' => 'usuario.teste@bakof.local',
                'password' => 'Alterar123!',
                'password_confirmation' => 'Alterar123!',
                'role' => UserRole::User->value,
                'status' => UserStatus::Active->value,
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'usuario.teste@bakof.local')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Usuario Atualizado',
                'email' => 'usuario.atualizado@bakof.local',
                'password' => '',
                'password_confirmation' => '',
                'role' => UserRole::Fiscal->value,
                'status' => UserStatus::Active->value,
                'force_password_change' => '0',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Usuario Atualizado',
            'email' => 'usuario.atualizado@bakof.local',
            'role' => UserRole::Fiscal->value,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => UserStatus::Inactive->value,
        ]);
    }

    public function test_admin_can_create_update_and_inactivate_business_unit(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.business-units.store'), [
                'name' => 'Unidade Teste',
                'legal_name' => 'BAKOF Unidade Teste LTDA',
                'cnpj' => '11.222.333/0001-81',
                'internal_code' => 'TST',
                'status' => UserStatus::Active->value,
            ])
            ->assertRedirect(route('admin.business-units.index'));

        $unit = BusinessUnit::query()->where('cnpj', '11222333000181')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.business-units.update', $unit), [
                'name' => 'Unidade Atualizada',
                'legal_name' => 'BAKOF Unidade Atualizada LTDA',
                'cnpj' => '11.222.333/0001-81',
                'internal_code' => 'UPD',
                'status' => UserStatus::Active->value,
            ])
            ->assertRedirect(route('admin.business-units.index'));

        $this->assertDatabaseHas('business_units', [
            'id' => $unit->id,
            'name' => 'Unidade Atualizada',
            'internal_code' => 'UPD',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.business-units.destroy', $unit))
            ->assertRedirect(route('admin.business-units.index'));

        $this->assertDatabaseHas('business_units', [
            'id' => $unit->id,
            'status' => UserStatus::Inactive->value,
        ]);
    }
}
