<?php

namespace Tests\Unit;

use App\Enums\AlertLevel;
use App\Enums\AlertType;
use App\Models\Invoice;
use App\Services\InvoiceAlertService;
use App\Services\PdfExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class PurchaseOrderCnpjRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_issuer_and_purchase_order_supplier_cnpj_does_not_create_alert(): void
    {
        $invoice = Invoice::factory()->create([
            'issuer_cnpj' => '12345678000195',
        ]);

        $created = $this->createMismatchAlertIfNeeded($invoice, '12.345.678/0001-95');

        $this->assertFalse($created);
        $this->assertDatabaseMissing('invoice_alerts', [
            'invoice_id' => $invoice->id,
            'type' => AlertType::CnpjMismatch->value,
        ]);
    }

    public function test_different_issuer_and_purchase_order_supplier_cnpj_creates_alert(): void
    {
        $invoice = Invoice::factory()->create([
            'issuer_cnpj' => '12345678000195',
        ]);

        $created = $this->createMismatchAlertIfNeeded($invoice, '99.999.999/0001-99');

        $this->assertTrue($created);
        $this->assertDatabaseHas('invoice_alerts', [
            'invoice_id' => $invoice->id,
            'type' => AlertType::CnpjMismatch->value,
            'level' => AlertLevel::Critical->value,
        ]);
    }

    private function createMismatchAlertIfNeeded(Invoice $invoice, string $supplierCnpj): bool
    {
        $pdfExtractionService = new PdfExtractionService(new Parser());
        $issuerCnpj = $pdfExtractionService->normalizeCnpj((string) $invoice->issuer_cnpj);
        $normalizedSupplierCnpj = $pdfExtractionService->normalizeCnpj($supplierCnpj);

        if ($issuerCnpj && $normalizedSupplierCnpj && $issuerCnpj !== $normalizedSupplierCnpj) {
            app(InvoiceAlertService::class)->create(
                $invoice,
                AlertType::CnpjMismatch,
                'CNPJ do emitente diferente do fornecedor da ordem de compra.',
                AlertLevel::Critical
            );

            return true;
        }

        return false;
    }
}
