<?php

namespace App\Http\Controllers;

use App\Enums\AlertLevel;
use App\Enums\AlertType;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Services\InvoiceAlertService;
use App\Services\InvoiceHistoryService;
use App\Services\InvoiceService;
use App\Services\PdfExtractionService;
use App\Services\PdfStorageService;
use App\Services\PurchaseOrderService;
use App\Support\InvoiceVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $visibleStatuses = [
            InvoiceStatus::AwaitingReview,
            InvoiceStatus::Pending,
        ];
        $visibleStatusValues = array_map(fn (InvoiceStatus $status): string => $status->value, $visibleStatuses);
        $sortableColumns = [
            'protocol' => 'protocol',
            'type' => 'document_type',
            'invoice' => 'invoice_number',
            'reference' => 'purchase_order_number',
            'unit' => 'business_unit_id',
            'user' => 'submitted_by',
            'arrival' => 'arrival_date',
            'due' => 'due_date',
            'supplier' => 'supplier',
            'status' => 'status',
            'created' => 'created_at',
        ];
        $sort = array_key_exists($request->string('sort')->toString(), $sortableColumns)
            ? $request->string('sort')->toString()
            : 'arrival';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $query = Invoice::query()
            ->with(['businessUnit:id,name', 'submitter:id,name', 'purchaseOrderCheck:id,invoice_id,supplier_name'])
            ->whereIn('status', $visibleStatusValues);

        InvoiceVisibility::apply($query, $request->user());

        if ($request->filled('protocol')) {
            $query->where('protocol', 'like', '%'.$request->string('protocol')->toString().'%');
        }

        if ($request->filled('purchase_order_number')) {
            $query->where('purchase_order_number', 'like', '%'.$request->string('purchase_order_number')->toString().'%');
        }

        if ($request->filled('supplier')) {
            $supplier = $request->string('supplier')->toString();

            $query->whereHas('purchaseOrderCheck', function ($query) use ($supplier): void {
                $query->where('supplier_name', 'like', '%'.$supplier.'%');
            });
        }

        if ($request->filled('status') && in_array($request->string('status')->toString(), $visibleStatusValues, true)) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->string('business_unit_id')->toString() === 'none') {
            $query->whereNull('business_unit_id');
        } elseif ($request->filled('business_unit_id')) {
            $query->where('business_unit_id', $request->integer('business_unit_id'));
        }

        $query->orderByDesc('is_urgent');

        if ($sort === 'supplier') {
            $query->orderBy(
                DB::table('purchase_order_checks')
                    ->select('supplier_name')
                    ->whereColumn('purchase_order_checks.invoice_id', 'invoices.id')
                    ->limit(1),
                $direction
            );
        } else {
            $query->orderBy($sortableColumns[$sort], $direction);
        }

        $query->orderByDesc('id');

        $unitSummaryQuery = Invoice::query()
            ->with('businessUnit:id,name')
            ->whereIn('status', $visibleStatusValues);

        InvoiceVisibility::apply($unitSummaryQuery, $request->user());

        $unitSummary = $unitSummaryQuery
            ->selectRaw('business_unit_id, count(*) as total')
            ->groupBy('business_unit_id')
            ->get();

        return view('invoices.index', [
            'invoices' => $query->paginate(15)->withQueryString(),
            'businessUnits' => BusinessUnit::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => $visibleStatuses,
            'unitSummary' => $unitSummary,
            'filters' => $request->only(['protocol', 'purchase_order_number', 'supplier', 'status', 'business_unit_id', 'sort', 'direction']),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Invoice::class);

        return view('invoices.create', [
            'businessUnits' => BusinessUnit::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(
        StoreInvoiceRequest $request,
        InvoiceService $invoiceService,
        PdfExtractionService $pdfExtractionService,
        PdfStorageService $pdfStorageService,
        PurchaseOrderService $purchaseOrderService,
        InvoiceHistoryService $historyService,
        InvoiceAlertService $alertService
    ): RedirectResponse {
        $uploadedFile = $request->file('pdf');
        $extracted = $pdfExtractionService->extract($uploadedFile->getPathname());

        $businessUnit = null;

        if ($extracted['recipient_cnpj']) {
            $businessUnit = BusinessUnit::query()
                ->where('cnpj', $extracted['recipient_cnpj'])
                ->first();
        }

        $storedPdf = $pdfStorageService->store($uploadedFile, $businessUnit);

        try {
            $invoice = DB::transaction(function () use (
                $request,
                $storedPdf,
                $extracted,
                $businessUnit,
                $invoiceService,
                $pdfExtractionService,
                $purchaseOrderService,
                $historyService,
                $alertService
            ): Invoice {
                $duplicateInvoice = Invoice::query()
                    ->where('pdf_sha256', $storedPdf['sha256'])
                    ->first();

                $invoice = Invoice::query()->create([
                    'protocol' => $invoiceService->nextProtocol(),
                    'submitted_by' => $request->user()->id,
                    'business_unit_id' => $businessUnit?->id,
                    'is_urgent' => $request->boolean('is_urgent'),
                    'document_type' => $request->string('document_type')->toString(),
                    'purchase_order_number' => $request->string('purchase_order_number')->toString() ?: null,
                    'invoice_number' => $extracted['invoice_number'],
                    'invoice_access_key' => $extracted['invoice_access_key'] ?? null,
                    'issuer_cnpj' => $extracted['issuer_cnpj'],
                    'issuer_legal_name' => $extracted['issuer_legal_name'],
                    'recipient_cnpj' => $extracted['recipient_cnpj'],
                    'recipient_legal_name' => $extracted['recipient_legal_name'],
                    'arrival_date' => $request->date('arrival_date'),
                    'payment_method' => $request->string('payment_method')->toString(),
                    'payment_installments' => $this->paymentInstallments($request),
                    'due_date' => $this->paymentDueDate($request),
                    'sent_at' => now(),
                    'user_notes' => $request->string('user_notes')->toString() ?: null,
                    'pdf_path' => $storedPdf['path'],
                    'original_pdf_name' => $storedPdf['original_name'],
                    'original_file_size' => max((int) $storedPdf['original_size'], (int) $storedPdf['stored_size']),
                    'file_size' => $storedPdf['stored_size'],
                    'pdf_sha256' => $storedPdf['sha256'],
                    'pdf_optimized' => $storedPdf['optimized'],
                    'pdf_processed_at' => $storedPdf['processed_at'],
                    'status' => InvoiceStatus::AwaitingReview,
                ]);

                $historyService->record($invoice, $request->user(), 'Nota enviada', null, InvoiceStatus::AwaitingReview, null, $request);

                if ($storedPdf['optimized']) {
                    $historyService->record($invoice, $request->user(), 'PDF otimizado para armazenamento', null, InvoiceStatus::AwaitingReview, null, $request);
                }

                if ($duplicateInvoice) {
                    $alertService->create($invoice, AlertType::DuplicatePdf, 'Este PDF ja foi enviado no protocolo '.$duplicateInvoice->protocol.'.', AlertLevel::Warning);
                }

                if ($extracted['success']) {
                    $historyService->record($invoice, $request->user(), 'PDF processado', null, InvoiceStatus::AwaitingReview, null, $request);
                } else {
                    $alertService->create($invoice, AlertType::PdfReadError, 'Nao foi possivel ler automaticamente o PDF.', AlertLevel::Warning);
                }

                if ($businessUnit) {
                    $historyService->record($invoice, $request->user(), 'Unidade identificada', null, InvoiceStatus::AwaitingReview, $businessUnit->name, $request);
                } else {
                    $alertService->create($invoice, AlertType::BusinessUnitNotIdentified, 'Unidade de negocio nao identificada pelo CNPJ do destinatario.', AlertLevel::Warning);
                }

                if ($invoice->documentType() === InvoiceDocumentType::Nf && $invoice->purchase_order_number) {
                    $purchaseOrder = $purchaseOrderService->find($invoice->purchase_order_number);
                    $invoice->purchaseOrderCheck()->create($purchaseOrder + [
                        'purchase_order_number' => $invoice->purchase_order_number,
                    ]);

                    $purchaseOrderSource = $purchaseOrder['raw_response']['source'] ?? null;
                    $lookupFailedSources = [
                        'oci8_missing',
                        'oracle_error',
                        'http_not_configured',
                        'http_error',
                        'http_exception',
                    ];

                    if (! $purchaseOrder['exists'] && in_array($purchaseOrderSource, $lookupFailedSources, true)) {
                        $alertService->create($invoice, AlertType::PurchaseOrderLookupFailed, 'Nao foi possivel consultar a ordem de compra no ERP. Verifique a API local/Oracle.', AlertLevel::Critical);
                    } elseif (! $purchaseOrder['exists']) {
                        $alertService->create($invoice, AlertType::PurchaseOrderNotFound, 'Ordem de compra nao encontrada no ERP.', AlertLevel::Warning);
                    } elseif ($purchaseOrder['status'] === 'cancelada') {
                        $alertService->create($invoice, AlertType::PurchaseOrderCancelled, 'Ordem de compra cancelada.', AlertLevel::Critical);
                    }

                    $issuerCnpj = $pdfExtractionService->normalizeCnpj((string) $invoice->issuer_cnpj);
                    $supplierCnpj = $pdfExtractionService->normalizeCnpj((string) $purchaseOrder['supplier_cnpj']);

                    if ($issuerCnpj && $supplierCnpj && $issuerCnpj !== $supplierCnpj) {
                        $alertService->create($invoice, AlertType::CnpjMismatch, 'CNPJ do emitente diferente do fornecedor da ordem de compra.', AlertLevel::Critical);
                    }
                }

                return $invoice;
            });
        } catch (Throwable $exception) {
            $pdfStorageService->deleteIfExists($storedPdf['path'] ?? null);

            throw $exception;
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Nota anexada com sucesso.');
    }

    public function show(Invoice $invoice, InvoiceHistoryService $historyService, Request $request): View
    {
        $this->authorize('view', $invoice);

        if ($request->user()->isFiscal() || $request->user()->isAdmin()) {
            $historyService->record($invoice, $request->user(), 'Nota visualizada pelo Fiscal', $invoice->status, $invoice->status, null, $request);
        }

        return view('invoices.show', [
            'invoice' => $invoice->load(['businessUnit', 'submitter', 'fiscalUser', 'alerts.resolver', 'histories.user', 'purchaseOrderCheck.businessUnit', 'annotation']),
            'businessUnits' => BusinessUnit::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function destroy(Invoice $invoice, PdfStorageService $pdfStorageService): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        $pdfPath = $invoice->pdf_path;
        $protocol = $invoice->protocol;

        DB::transaction(function () use ($invoice): void {
            $invoice->delete();
        });

        $pdfStorageService->deleteIfExists($pdfPath);

        return redirect()
            ->route('invoices.index')
            ->with('success', 'Nota '.$protocol.' excluida com sucesso.');
    }

    private function paymentInstallments(StoreInvoiceRequest $request): ?array
    {
        if ($request->string('payment_method')->toString() === 'anticipated') {
            return null;
        }

        return collect($request->input('payment_installments', []))
            ->map(fn (array $installment, int $index): array => [
                'number' => $index + 1,
                'due_date' => $installment['due_date'] ?? null,
                'amount' => isset($installment['amount']) ? round($this->decimalAmount($installment['amount']), 2) : null,
            ])
            ->values()
            ->all();
    }

    private function paymentDueDate(StoreInvoiceRequest $request): ?string
    {
        if ($request->string('payment_method')->toString() === 'anticipated') {
            return null;
        }

        return collect($request->input('payment_installments', []))
            ->pluck('due_date')
            ->filter()
            ->sort()
            ->first();
    }

    private function decimalAmount(mixed $amount): float
    {
        $amount = trim((string) $amount);

        if (str_contains($amount, ',')) {
            $amount = str_replace(['.', ','], ['', '.'], $amount);
        }

        return (float) $amount;
    }
}
