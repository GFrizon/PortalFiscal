<?php

namespace Tests\Feature;

use App\Enums\AlertLevel;
use App\Enums\AlertType;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\InvoicePendingMail;
use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\PdfExtractionService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
            'admin.user-groups.index',
            'admin.user-groups.create',
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

    public function test_regular_user_sees_invoices_from_same_group_only(): void
    {
        $compras = UserGroup::query()->create(['name' => 'Compras']);
        $financeiro = UserGroup::query()->create(['name' => 'Financeiro']);

        $user = User::factory()->create(['user_group_id' => $compras->id]);
        $sameGroupUser = User::factory()->create(['user_group_id' => $compras->id]);
        $otherGroupUser = User::factory()->create(['user_group_id' => $financeiro->id]);

        Invoice::factory()->create([
            'submitted_by' => $user->id,
            'protocol' => 'NF-2026-COMPRA1',
            'invoice_number' => '700001',
        ]);
        Invoice::factory()->create([
            'submitted_by' => $sameGroupUser->id,
            'protocol' => 'NF-2026-COMPRA2',
            'invoice_number' => '700002',
        ]);
        Invoice::factory()->create([
            'submitted_by' => $otherGroupUser->id,
            'protocol' => 'NF-2026-FIN001',
            'invoice_number' => '700003',
        ]);

        $this->actingAs($user)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('700001')
            ->assertSee('700002')
            ->assertDontSee('700003');
    }

    public function test_regular_user_can_open_same_group_invoice_but_not_other_group_invoice(): void
    {
        $compras = UserGroup::query()->create(['name' => 'Compras']);
        $financeiro = UserGroup::query()->create(['name' => 'Financeiro']);

        $user = User::factory()->create(['user_group_id' => $compras->id]);
        $sameGroupUser = User::factory()->create(['user_group_id' => $compras->id]);
        $otherGroupUser = User::factory()->create(['user_group_id' => $financeiro->id]);

        $sameGroupInvoice = Invoice::factory()->create([
            'submitted_by' => $sameGroupUser->id,
        ]);
        $otherGroupInvoice = Invoice::factory()->create([
            'submitted_by' => $otherGroupUser->id,
        ]);

        $this->actingAs($user)
            ->get(route('invoices.show', $sameGroupInvoice))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('invoices.show', $otherGroupInvoice))
            ->assertForbidden();
    }

    public function test_regular_user_history_shows_same_group_invoice_actions(): void
    {
        $compras = UserGroup::query()->create(['name' => 'Compras']);
        $financeiro = UserGroup::query()->create(['name' => 'Financeiro']);

        $user = User::factory()->create(['user_group_id' => $compras->id]);
        $sameGroupUser = User::factory()->create(['user_group_id' => $compras->id]);
        $otherGroupUser = User::factory()->create(['user_group_id' => $financeiro->id]);

        $sameGroupInvoice = Invoice::factory()->create(['submitted_by' => $sameGroupUser->id]);
        $otherGroupInvoice = Invoice::factory()->create(['submitted_by' => $otherGroupUser->id]);

        $sameGroupInvoice->histories()->create([
            'user_id' => $sameGroupUser->id,
            'action' => 'Acao do grupo compras',
            'new_status' => 'awaiting_review',
            'ip_address' => '127.0.0.1',
        ]);
        $otherGroupInvoice->histories()->create([
            'user_id' => $otherGroupUser->id,
            'action' => 'Acao do grupo financeiro',
            'new_status' => 'awaiting_review',
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($user)
            ->get(route('histories.index'))
            ->assertOk()
            ->assertSee('Acao do grupo compras')
            ->assertDontSee('Acao do grupo financeiro');
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
                'document_type' => 'nf',
                'is_urgent' => '1',
                'purchase_order_number' => '123456',
                'arrival_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'user_notes' => 'Nota enviada em teste.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'submitted_by' => $user->id,
            'business_unit_id' => $unit->id,
            'is_urgent' => true,
            'document_type' => 'nf',
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
                'document_type' => 'nf',
                'purchase_order_number' => 'OC123456',
                'arrival_date' => null,
                'payment_method' => 'boleto',
                'payment_installments_count' => null,
            ])
            ->assertSessionHasErrors(['purchase_order_number', 'arrival_date', 'payment_installments_count']);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_cte_upload_requires_invoice_reference_and_skips_purchase_order_lookup(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->mock(PdfExtractionService::class, function ($mock): void {
            $mock->shouldReceive('extract')->once()->andReturn([
                'success' => true,
                'text' => 'PDF CTE simulado',
                'cnpjs' => ['12345678000195'],
                'issuer_cnpj' => '12345678000195',
                'recipient_cnpj' => null,
                'invoice_number' => '777888',
                'issuer_legal_name' => null,
                'recipient_legal_name' => null,
                'error' => null,
            ]);
        });

        $this->mock(PurchaseOrderService::class, function ($mock): void {
            $mock->shouldNotReceive('find');
        });

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'pdf' => UploadedFile::fake()->create('cte.pdf', 100, 'application/pdf'),
                'document_type' => 'cte',
                'purchase_order_number' => '31426',
                'arrival_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'user_notes' => 'CTE vinculado a NF.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'submitted_by' => $user->id,
            'document_type' => 'cte',
            'purchase_order_number' => '31426',
        ]);

        $invoice = Invoice::query()->where('submitted_by', $user->id)->firstOrFail();

        $this->assertDatabaseMissing('purchase_order_checks', [
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_invoice_without_purchase_order_does_not_require_reference_or_lookup(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->mock(PdfExtractionService::class, function ($mock): void {
            $mock->shouldReceive('extract')->once()->andReturn([
                'success' => true,
                'text' => 'PDF simulado sem ordem de compra',
                'cnpjs' => ['12345678000195'],
                'issuer_cnpj' => '12345678000195',
                'recipient_cnpj' => null,
                'invoice_number' => '987654',
                'issuer_legal_name' => null,
                'recipient_legal_name' => null,
                'error' => null,
            ]);
        });

        $this->mock(PurchaseOrderService::class, function ($mock): void {
            $mock->shouldNotReceive('find');
        });

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'pdf' => UploadedFile::fake()->create('nota-sem-oc.pdf', 100, 'application/pdf'),
                'document_type' => 'nf_no_oc',
                'purchase_order_number' => '',
                'arrival_date' => now()->format('Y-m-d'),
                'payment_method' => 'anticipated',
                'user_notes' => 'Nota fiscal sem ordem de compra.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'submitted_by' => $user->id,
            'document_type' => 'nf_no_oc',
            'purchase_order_number' => null,
            'invoice_number' => '987654',
        ]);

        $invoice = Invoice::query()->where('submitted_by', $user->id)->firstOrFail();

        $this->assertDatabaseMissing('purchase_order_checks', [
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_boleto_upload_requires_installments_and_stores_payment_terms(): void
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

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'pdf' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
                'document_type' => 'nf',
                'purchase_order_number' => '123456',
                'arrival_date' => now()->format('Y-m-d'),
                'payment_method' => 'boleto',
                'payment_installments_count' => 2,
                'payment_installments' => [
                    ['due_date' => '2026-08-10', 'amount' => '100,50'],
                    ['due_date' => '2026-09-10', 'amount' => '200.75'],
                ],
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('submitted_by', $user->id)->firstOrFail();

        $this->assertSame('boleto', $invoice->payment_method->value);
        $this->assertSame('2026-08-10', $invoice->due_date?->format('Y-m-d'));
        $this->assertSame(100.50, (float) $invoice->payment_installments[0]['amount']);
        $this->assertSame(200.75, (float) $invoice->payment_installments[1]['amount']);
    }

    public function test_deposit_or_boleto_requires_all_installment_fields(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'pdf' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
                'document_type' => 'nf',
                'purchase_order_number' => '123456',
                'arrival_date' => now()->format('Y-m-d'),
                'payment_method' => 'deposit',
                'payment_installments_count' => 2,
                'payment_installments' => [
                    ['due_date' => '2026-08-10', 'amount' => '100'],
                ],
            ])
            ->assertSessionHasErrors(['payment_installments_count']);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invoice_due_date_requires_at_least_two_business_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-29 10:00:00', config('app.timezone')));
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'pdf' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
                'document_type' => 'nf',
                'purchase_order_number' => '123456',
                'arrival_date' => '2026-07-29',
                'payment_method' => 'deposit',
                'payment_installments_count' => 1,
                'payment_installments' => [
                    ['due_date' => '2026-07-30', 'amount' => '100'],
                ],
            ])
            ->assertSessionHasErrors(['payment_installments.0.due_date']);

        $this->assertDatabaseCount('invoices', 0);

        Carbon::setTestNow();
    }

    public function test_invoice_due_date_must_be_business_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-29 10:00:00', config('app.timezone')));
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'pdf' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
                'document_type' => 'nf',
                'purchase_order_number' => '123456',
                'arrival_date' => '2026-07-29',
                'payment_method' => 'deposit',
                'payment_installments_count' => 1,
                'payment_installments' => [
                    ['due_date' => '2026-08-01', 'amount' => '100'],
                ],
            ])
            ->assertSessionHasErrors(['payment_installments.0.due_date']);

        $this->assertDatabaseCount('invoices', 0);

        Carbon::setTestNow();
    }

    public function test_payment_installments_are_limited_to_twelve(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'pdf' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
                'document_type' => 'nf',
                'purchase_order_number' => '123456',
                'arrival_date' => now()->format('Y-m-d'),
                'payment_method' => 'boleto',
                'payment_installments_count' => 13,
                'payment_installments' => array_fill(0, 13, [
                    'due_date' => '2026-08-10',
                    'amount' => '100',
                ]),
            ])
            ->assertSessionHasErrors(['payment_installments_count']);

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

    public function test_admin_can_delete_invoice_that_was_not_launched(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create([
            'status' => 'pending',
            'pdf_path' => 'notas/teste/admin-delete.pdf',
        ]);

        Storage::disk('local')->put($invoice->pdf_path, 'PDF fake');

        $this->actingAs($admin)
            ->delete(route('invoices.destroy', $invoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertDatabaseMissing('invoices', [
            'id' => $invoice->id,
        ]);

        Storage::disk('local')->assertMissing($invoice->pdf_path);
    }

    public function test_fiscal_cannot_delete_invoice(): void
    {
        Storage::fake('local');

        $fiscal = User::factory()->fiscal()->create();
        $invoice = Invoice::factory()->create([
            'status' => 'awaiting_review',
            'pdf_path' => 'notas/teste/fiscal-blocked.pdf',
        ]);

        Storage::disk('local')->put($invoice->pdf_path, 'PDF fake');

        $this->actingAs($fiscal)
            ->delete(route('invoices.destroy', $invoice))
            ->assertForbidden();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
        ]);

        Storage::disk('local')->assertExists($invoice->pdf_path);
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
                    'document_type' => 'nf',
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
                'document_type' => 'nf',
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

    public function test_fiscal_pending_status_sends_email_to_submitter(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'usuario.nota@bakof.local',
        ]);
        $fiscal = User::factory()->fiscal()->create();
        $invoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
            'status' => 'awaiting_review',
            'protocol' => 'NF-2026-EMAIL01',
            'invoice_number' => '445566',
        ]);

        $this->actingAs($fiscal)
            ->post(route('invoices.mark-as-pending', $invoice), [
                'fiscal_notes' => 'CNPJ divergente, favor revisar.',
            ])
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'pending',
            'fiscal_notes' => 'CNPJ divergente, favor revisar.',
        ]);

        Mail::assertSent(InvoicePendingMail::class, function (InvoicePendingMail $mail) use ($user, $invoice): bool {
            return $mail->hasTo($user->email)
                && $mail->invoice->is($invoice)
                && $mail->notes === 'CNPJ divergente, favor revisar.';
        });
    }

    public function test_fiscal_can_attach_supporting_document_to_invoice(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $fiscal = User::factory()->fiscal()->create();
        $invoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
            'status' => 'awaiting_review',
        ]);

        $file = UploadedFile::fake()->create('comprovante.pdf', 64, 'application/pdf');

        $this->actingAs($fiscal)
            ->post(route('invoices.attachments.store', $invoice), [
                'attachment' => $file,
                'notes' => 'Comprovante conferido pelo fiscal.',
            ])
            ->assertRedirect(route('invoices.show', $invoice));

        $attachment = $invoice->attachments()->firstOrFail();

        Storage::disk('local')->assertExists($attachment->path);

        $this->assertDatabaseHas('invoice_attachments', [
            'invoice_id' => $invoice->id,
            'uploaded_by' => $fiscal->id,
            'original_name' => 'comprovante.pdf',
            'notes' => 'Comprovante conferido pelo fiscal.',
        ]);

        $this->assertDatabaseHas('invoice_histories', [
            'invoice_id' => $invoice->id,
            'user_id' => $fiscal->id,
            'action' => 'Documento complementar anexado',
        ]);

        $this->actingAs($user)
            ->get(route('invoices.attachments.download', [$invoice, $attachment]))
            ->assertOk();
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
            'invoice_access_key' => '35260754163230000109550040000314261443991849',
        ]);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Observacoes de '.$user->name)
            ->assertSee('Entregar urgente ao fiscal.')
            ->assertSee('35260754163230000109550040000314261443991849')
            ->assertSee('data-copy-text="35260754163230000109550040000314261443991849"', false);
    }

    public function test_fiscal_can_save_invoice_pdf_annotations(): void
    {
        $fiscal = User::factory()->fiscal()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($fiscal)
            ->putJson(route('invoices.annotations.update', $invoice), [
                'strokes' => [
                    [
                        'page' => 1,
                        'tool' => 'rectangle',
                        'color' => '#d92d20',
                        'width' => 4,
                        'points' => [
                            ['x' => 0.1, 'y' => 0.2],
                            ['x' => 0.4, 'y' => 0.5],
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('invoice_annotations', [
            'invoice_id' => $invoice->id,
            'user_id' => $fiscal->id,
        ]);
    }

    public function test_regular_user_cannot_save_invoice_pdf_annotations(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->putJson(route('invoices.annotations.update', $invoice), [
                'strokes' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('invoice_annotations', [
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_invoice_details_tolerate_legacy_empty_document_and_payment_fields(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'submitted_by' => $user->id,
        ]);

        DB::table('invoices')
            ->where('id', $invoice->id)
            ->update([
                'document_type' => '',
                'payment_method' => '',
            ]);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Ordem de compra')
            ->assertSee('Antecipado');
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
            'invoice_number' => '800001',
            'status' => 'awaiting_review',
        ]);

        Invoice::factory()->create([
            'protocol' => 'NF-2026-OP002',
            'invoice_number' => '800002',
            'status' => 'pending',
        ]);

        Invoice::factory()->create([
            'protocol' => 'NF-2026-DONE1',
            'invoice_number' => '800003',
            'status' => 'launched',
        ]);

        Invoice::factory()->create([
            'protocol' => 'NF-2026-CANC1',
            'invoice_number' => '800004',
            'status' => 'cancelled',
        ]);

        Invoice::factory()->create([
            'protocol' => 'NF-2026-REV01',
            'invoice_number' => '800005',
            'status' => 'in_review',
        ]);

        $this->actingAs($admin)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Fila aberta')
            ->assertSee('Lancadas')
            ->assertSee('800001')
            ->assertSee('800002')
            ->assertDontSee('800003')
            ->assertDontSee('800004')
            ->assertDontSee('800005')
            ->assertDontSee('Cancelada')
            ->assertDontSee('Em conferencia');

        $this->actingAs($admin)
            ->get(route('invoices.index', ['status' => InvoiceStatus::Launched->value]))
            ->assertOk()
            ->assertSee('800003')
            ->assertDontSee('800001')
            ->assertDontSee('800002')
            ->assertDontSee('800004')
            ->assertDontSee('800005');
    }

    public function test_invoice_index_shows_due_date_and_sorts_by_columns(): void
    {
        $user = User::factory()->create();

        $later = Invoice::factory()->create([
            'submitted_by' => $user->id,
            'protocol' => 'NF-2026-000030',
            'invoice_number' => '900030',
            'due_date' => '2026-09-15',
            'created_at' => '2026-07-29 15:30:00',
        ]);

        $earlier = Invoice::factory()->create([
            'submitted_by' => $user->id,
            'protocol' => 'NF-2026-000031',
            'invoice_number' => '900031',
            'due_date' => '2026-08-10',
            'created_at' => '2026-07-29 08:15:00',
        ]);

        $response = $this->actingAs($user)
            ->get(route('invoices.index', [
                'sort' => 'due',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSee('Inclusao')
            ->assertSee('Vencimento')
            ->assertSee('29/07/2026 08:15')
            ->assertSee('29/07/2026 15:30')
            ->assertSee('10/08/2026')
            ->assertSee('15/09/2026');

        $response->assertSeeInOrder([
            '900031',
            '900030',
        ]);

        $this->actingAs($user)
            ->get(route('invoices.index', [
                'sort' => 'created',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSeeInOrder([
                '900031',
                '900030',
            ]);
    }

    public function test_invoice_index_highlights_urgent_invoices_and_filters_supplier(): void
    {
        $admin = User::factory()->admin()->create();

        $normal = Invoice::factory()->create([
            'protocol' => 'NF-2026-NORMAL',
            'invoice_number' => '910001',
            'is_urgent' => false,
            'due_date' => '2026-08-10',
        ]);
        $normal->purchaseOrderCheck()->create([
            'purchase_order_number' => $normal->purchase_order_number,
            'exists' => true,
            'supplier_name' => 'Fornecedor Comum',
        ]);

        $urgent = Invoice::factory()->create([
            'protocol' => 'NF-2026-URGENT',
            'invoice_number' => '910002',
            'is_urgent' => true,
            'due_date' => '2026-09-10',
        ]);
        $urgent->purchaseOrderCheck()->create([
            'purchase_order_number' => $urgent->purchase_order_number,
            'exists' => true,
            'supplier_name' => 'Fornecedor Especial',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('invoices.index', [
                'sort' => 'due',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSee('Fornecedor')
            ->assertSee('Urgente')
            ->assertSee('Fornecedor Especial');

        $response->assertSeeInOrder([
            '910002',
            '910001',
        ]);

        $this->actingAs($admin)
            ->get(route('invoices.index', ['supplier' => 'Especial']))
            ->assertOk()
            ->assertSee('910002')
            ->assertDontSee('910001');
    }

    public function test_admin_can_create_update_and_block_user(): void
    {
        $admin = User::factory()->admin()->create();
        $group = UserGroup::query()->create(['name' => 'Compras']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Usuario Teste',
                'email' => 'usuario.teste@bakof.local',
                'password' => 'Alterar123!',
                'password_confirmation' => 'Alterar123!',
                'role' => UserRole::User->value,
                'status' => UserStatus::Active->value,
                'user_group_id' => $group->id,
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
                'user_group_id' => $group->id,
                'force_password_change' => '0',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Usuario Atualizado',
            'email' => 'usuario.atualizado@bakof.local',
            'role' => UserRole::Fiscal->value,
            'user_group_id' => $group->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => UserStatus::Inactive->value,
        ]);
    }

    public function test_admin_can_manage_user_groups(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.user-groups.store'), [
                'name' => 'Compras',
            ])
            ->assertRedirect(route('admin.user-groups.index'));

        $group = UserGroup::query()->where('name', 'Compras')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.user-groups.update', $group), [
                'name' => 'Compras nacionais',
            ])
            ->assertRedirect(route('admin.user-groups.index'));

        $this->assertDatabaseHas('user_groups', [
            'id' => $group->id,
            'name' => 'Compras nacionais',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.user-groups.destroy', $group->refresh()))
            ->assertRedirect(route('admin.user-groups.index'));

        $this->assertDatabaseMissing('user_groups', [
            'id' => $group->id,
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
