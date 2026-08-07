<?php

namespace App\Http\Controllers;

use App\Enums\AlertLevel;
use App\Enums\AlertType;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Mail\InvoicePendingResolvedMail;
use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceAlertService;
use App\Services\InvoiceHistoryService;
use App\Services\InvoiceService;
use App\Services\PdfExtractionService;
use App\Services\PdfStorageService;
use App\Services\PurchaseOrderService;
use App\Support\InvoiceVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class InvoiceController extends Controller
{
    private const AI_VALIDATION_HISTORY_ACTION = 'Leitura complementar via OpenAI';

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $defaultStatuses = [
            InvoiceStatus::AwaitingReview,
            InvoiceStatus::Pending,
        ];
        $filterableStatuses = [
            InvoiceStatus::AwaitingReview,
            InvoiceStatus::Pending,
            InvoiceStatus::Launched,
        ];

        if (! $request->user()->isFiscal()) {
            array_unshift($defaultStatuses, InvoiceStatus::Draft);
            array_unshift($filterableStatuses, InvoiceStatus::Draft);
        }
        $defaultStatusValues = array_map(fn (InvoiceStatus $status): string => $status->value, $defaultStatuses);
        $filterableStatusValues = array_map(fn (InvoiceStatus $status): string => $status->value, $filterableStatuses);
        $selectedStatus = $request->string('status')->toString();
        $statusValues = in_array($selectedStatus, $filterableStatusValues, true)
            ? [$selectedStatus]
            : $defaultStatusValues;
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
        $requestedSort = $request->string('sort')->toString();
        $hasExplicitSort = array_key_exists($requestedSort, $sortableColumns);
        $sort = $hasExplicitSort
            ? $request->string('sort')->toString()
            : 'arrival';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $query = Invoice::query()
            ->with(['businessUnit:id,name', 'submitter:id,name', 'purchaseOrderCheck:id,invoice_id,supplier_name'])
            ->whereIn('status', $statusValues);

        InvoiceVisibility::apply($query, $request->user());

        if ($request->filled('protocol')) {
            $query->where('protocol', 'like', '%'.$request->string('protocol')->toString().'%');
        }

        if ($request->filled('purchase_order_number')) {
            $reference = $request->string('purchase_order_number')->toString();

            $query->where(function ($query) use ($reference): void {
                $query
                    ->where('purchase_order_number', 'like', '%'.$reference.'%')
                    ->orWhere('invoice_number', 'like', '%'.$reference.'%');
            });
        }

        if ($request->filled('supplier')) {
            $supplier = $request->string('supplier')->toString();

            $query->where(function ($query) use ($supplier): void {
                $query
                    ->where('issuer_legal_name', 'like', '%'.$supplier.'%')
                    ->orWhere('recipient_legal_name', 'like', '%'.$supplier.'%')
                    ->orWhereHas('purchaseOrderCheck', function ($query) use ($supplier): void {
                        $query->where('supplier_name', 'like', '%'.$supplier.'%');
                    });
            });
        }

        if ($request->string('business_unit_id')->toString() === 'none') {
            $query->whereNull('business_unit_id');
        } elseif ($request->filled('business_unit_id')) {
            $query->where('business_unit_id', $request->integer('business_unit_id'));
        }

        if (! $hasExplicitSort) {
            $query->orderByDesc('is_urgent');
        }

        if ($sort === 'due') {
            $query
                ->orderByRaw('due_date IS NULL ASC')
                ->orderBy('due_date', $direction);
        }

        if ($sort === 'supplier') {
            $query->orderByRaw(
                "COALESCE((select supplier_name from purchase_order_checks where purchase_order_checks.invoice_id = invoices.id limit 1), issuer_legal_name, recipient_legal_name, '') {$direction}"
            );
        } elseif ($sort !== 'due') {
            $query->orderBy($sortableColumns[$sort], $direction);
        }

        $query->orderBy('id', $direction);

        $unitSummaryQuery = Invoice::query()
            ->with('businessUnit:id,name')
            ->whereIn('status', $statusValues);

        InvoiceVisibility::apply($unitSummaryQuery, $request->user());

        $unitSummary = $unitSummaryQuery
            ->selectRaw('business_unit_id, count(*) as total')
            ->groupBy('business_unit_id')
            ->get();

        return view('invoices.index', [
            'invoices' => $query->paginate(15)->withQueryString(),
            'businessUnits' => BusinessUnit::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => $filterableStatuses,
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
            'isEditing' => false,
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('update', $invoice);

        return view('invoices.create', [
            'businessUnits' => BusinessUnit::query()->orderBy('name')->get(['id', 'name']),
            'invoice' => $invoice,
            'isEditing' => true,
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
    ): RedirectResponse|JsonResponse {
        $isDraft = $request->isDraftIntent();
        $uploadedFile = $request->file('pdf');
        $uploadedPdfHash = hash_file('sha256', $uploadedFile->getPathname());

        $duplicateInvoice = Invoice::query()
            ->where('pdf_sha256', $uploadedPdfHash)
            ->first();

        if ($duplicateInvoice) {
            throw ValidationException::withMessages([
                'pdf' => 'Este PDF ja foi anexado no protocolo '.$duplicateInvoice->protocol.'. Abra a nota existente ou confira se selecionou o arquivo correto.',
            ]);
        }

        $extracted = $pdfExtractionService->extract($uploadedFile->getPathname());
        $precheckedPurchaseOrder = null;

        if (
            ! $isDraft
            && $request->string('document_type')->toString() === InvoiceDocumentType::Nf->value
            && filled($request->string('purchase_order_number')->toString())
        ) {
            $precheckedPurchaseOrder = $purchaseOrderService->find($request->string('purchase_order_number')->toString());
            $issuerCnpj = $pdfExtractionService->normalizeCnpj((string) ($extracted['issuer_cnpj'] ?? ''));

            $this->ensurePurchaseOrderCanBeSubmitted($precheckedPurchaseOrder, $issuerCnpj, 'enviar para conferencia');
        }

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
                $precheckedPurchaseOrder,
                $historyService,
                $alertService,
                $isDraft
            ): Invoice {
                $status = $isDraft ? InvoiceStatus::Draft : InvoiceStatus::AwaitingReview;

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
                    'sent_at' => $isDraft ? null : now(),
                    'user_notes' => $request->string('user_notes')->toString() ?: null,
                    'pdf_path' => $storedPdf['path'],
                    'original_pdf_name' => $storedPdf['original_name'],
                    'original_file_size' => max((int) $storedPdf['original_size'], (int) $storedPdf['stored_size']),
                    'file_size' => $storedPdf['stored_size'],
                    'pdf_sha256' => $storedPdf['sha256'],
                    'pdf_optimized' => $storedPdf['optimized'],
                    'pdf_processed_at' => $storedPdf['processed_at'],
                    'status' => $status,
                ]);

                $historyService->record($invoice, $request->user(), $isDraft ? 'Rascunho salvo' : 'Nota enviada', null, $status, null, $request);

                if ($storedPdf['optimized']) {
                    $historyService->record($invoice, $request->user(), 'PDF otimizado para armazenamento', null, $status, null, $request);
                }

                if ($extracted['success']) {
                    $historyService->record($invoice, $request->user(), 'PDF processado', null, $status, null, $request);
                    $this->recordAiValidationHistoryIfNeeded($invoice, (string) ($extracted['source'] ?? ''), $historyService, $request);
                } else {
                    $alertService->create(
                        $invoice,
                        AlertType::PdfReadError,
                        $extracted['error'] ?: 'Nao foi possivel ler automaticamente o PDF.',
                        AlertLevel::Warning
                    );
                }

                if (blank($invoice->invoice_number)) {
                    $alertService->create($invoice, AlertType::InvoiceNumberNotIdentified, 'Numero da nota nao foi identificado automaticamente. Confira o PDF antes de lancar.', AlertLevel::Critical);
                }

                if ($businessUnit) {
                    $historyService->record($invoice, $request->user(), 'Unidade identificada', null, $status, $businessUnit->name, $request);
                } else {
                    $alertService->create($invoice, AlertType::BusinessUnitNotIdentified, 'Unidade de negocio nao identificada pelo CNPJ do destinatario.', AlertLevel::Warning);
                }

                if (! $isDraft && $invoice->documentType() === InvoiceDocumentType::Nf && $invoice->purchase_order_number) {
                    $purchaseOrder = $precheckedPurchaseOrder ?? $purchaseOrderService->find($invoice->purchase_order_number);
                    $invoice->purchaseOrderCheck()->create($purchaseOrder + [
                        'purchase_order_number' => $invoice->purchase_order_number,
                    ]);

                    $purchaseOrderSource = $purchaseOrder['raw_response']['source'] ?? null;

                    if (! $purchaseOrder['exists'] && in_array($purchaseOrderSource, $this->purchaseOrderLookupFailedSources(), true)) {
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $isDraft ? 'Rascunho salvo com sucesso.' : 'Nota anexada com sucesso.',
                'redirect' => $isDraft ? route('invoices.show', $invoice) : route('invoices.index'),
            ]);
        }

        return $isDraft
            ? redirect()->route('invoices.show', $invoice)->with('success', 'Rascunho salvo com sucesso.')
            : redirect()->route('invoices.index')->with('success', 'Nota anexada com sucesso.');
    }

    public function update(
        UpdateInvoiceRequest $request,
        Invoice $invoice,
        PurchaseOrderService $purchaseOrderService,
        PdfExtractionService $pdfExtractionService,
        InvoiceAlertService $alertService,
        InvoiceHistoryService $historyService
    ): RedirectResponse|JsonResponse {
        $this->authorize('update', $invoice);

        $refreshedExtraction = null;

        if ($invoice->status === InvoiceStatus::Draft && ! $request->isDraftIntent()) {
            $refreshedExtraction = $this->refreshInvoiceExtractionFromStoredPdf($invoice, $pdfExtractionService);
            $invoice->refresh();
        }

        if (
            ! $request->isDraftIntent()
            && $request->string('document_type')->toString() === InvoiceDocumentType::Nf->value
            && filled($request->string('purchase_order_number')->toString())
        ) {
            $purchaseOrder = $purchaseOrderService->find($request->string('purchase_order_number')->toString());
            $issuerCnpj = $pdfExtractionService->normalizeCnpj((string) $invoice->issuer_cnpj);

            $this->ensurePurchaseOrderCanBeSubmitted($purchaseOrder, $issuerCnpj, 'enviar para conferencia');
        }

        $notifyFiscalTeam = false;
        $usedAiValidation = $this->extractionUsedAi($refreshedExtraction);

        DB::transaction(function () use ($request, $invoice, $purchaseOrderService, $pdfExtractionService, $alertService, $historyService, &$notifyFiscalTeam, $usedAiValidation): void {
            $previousStatus = $invoice->status;
            $nextStatus = $invoice->status;
            $isDraftInvoice = $invoice->status === InvoiceStatus::Draft;
            $isDraftIntent = $request->isDraftIntent();
            $historyAction = 'Dados da nota atualizados';

            if ($isDraftInvoice && ! $isDraftIntent) {
                $nextStatus = InvoiceStatus::AwaitingReview;
                $historyAction = 'Rascunho enviado para conferencia';
            } elseif ($isDraftInvoice && $isDraftIntent) {
                $nextStatus = InvoiceStatus::Draft;
                $historyAction = 'Rascunho atualizado';
            } elseif ($request->user()->isRegularUser() && $invoice->status === InvoiceStatus::Pending) {
                $nextStatus = InvoiceStatus::AwaitingReview;
                $notifyFiscalTeam = true;
            }

            $invoice->update([
                'is_urgent' => $request->boolean('is_urgent'),
                'document_type' => $request->string('document_type')->toString(),
                'purchase_order_number' => $request->string('purchase_order_number')->toString() ?: null,
                'arrival_date' => $request->date('arrival_date'),
                'payment_method' => $request->string('payment_method')->toString(),
                'payment_installments' => $this->paymentInstallments($request),
                'due_date' => $this->paymentDueDate($request),
                'sent_at' => $previousStatus === InvoiceStatus::Draft && $nextStatus === InvoiceStatus::AwaitingReview ? now() : $invoice->sent_at,
                'user_notes' => $request->string('user_notes')->toString() ?: null,
                'status' => $nextStatus,
            ]);

            if ($nextStatus === InvoiceStatus::Draft) {
                $this->clearPurchaseOrderCheck($invoice->refresh());
            } else {
                $this->syncPurchaseOrderCheck($invoice->refresh(), $purchaseOrderService, $pdfExtractionService, $alertService);
            }

            $historyService->record(
                $invoice,
                $request->user(),
                $historyAction,
                $previousStatus,
                $nextStatus,
                null,
                $request
            );

            if ($usedAiValidation) {
                $this->recordAiValidationHistoryIfNeeded($invoice, 'ai', $historyService, $request, $nextStatus);
            }
        });

        if ($notifyFiscalTeam) {
            $this->notifyFiscalTeamPendingWasResolved($invoice->refresh(), $request->user(), $historyService, $request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $invoice->status === InvoiceStatus::Draft ? 'Rascunho atualizado com sucesso.' : 'Dados da nota atualizados com sucesso.',
                'redirect' => route('invoices.show', $invoice),
            ]);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', $invoice->status === InvoiceStatus::Draft ? 'Rascunho atualizado com sucesso.' : 'Dados da nota atualizados com sucesso.');
    }

    public function show(Invoice $invoice, InvoiceHistoryService $historyService, Request $request): View
    {
        $this->authorize('view', $invoice);

        if ($request->user()->isFiscal() || $request->user()->isAdmin()) {
            $historyService->record($invoice, $request->user(), 'Nota visualizada pelo Fiscal', $invoice->status, $invoice->status, null, $request);
        }

        return view('invoices.show', [
            'invoice' => $invoice->load(['businessUnit', 'submitter', 'fiscalUser', 'alerts.resolver', 'histories.user', 'purchaseOrderCheck.businessUnit', 'annotation', 'attachments.uploader']),
            'businessUnits' => BusinessUnit::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function extractionUsedAi(?array $extracted): bool
    {
        return str_contains((string) ($extracted['source'] ?? ''), 'ai');
    }

    private function recordAiValidationHistoryIfNeeded(
        Invoice $invoice,
        string $source,
        InvoiceHistoryService $historyService,
        Request $request,
        ?InvoiceStatus $status = null
    ): void {
        if (! str_contains($source, 'ai')) {
            return;
        }

        $alreadyRecorded = $invoice->histories()
            ->where('action', self::AI_VALIDATION_HISTORY_ACTION)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $historyService->record(
            $invoice,
            $request->user(),
            self::AI_VALIDATION_HISTORY_ACTION,
            $status,
            $status,
            'Leitura auxiliar do PDF feita com OpenAI.',
            $request
        );
    }

    private function ensurePurchaseOrderCanBeSubmitted(array $purchaseOrder, string $issuerCnpj, string $action): void
    {
        $purchaseOrderExists = (bool) ($purchaseOrder['exists'] ?? false);
        $purchaseOrderSource = $purchaseOrder['raw_response']['source'] ?? null;

        if (! $purchaseOrderExists && in_array($purchaseOrderSource, $this->purchaseOrderLookupFailedSources(), true)) {
            throw ValidationException::withMessages([
                'purchase_order_number' => 'Nao foi possivel validar a ordem de compra no ERP. Verifique a integracao e tente novamente antes de '.$action.'.',
            ]);
        }

        if (! $purchaseOrderExists) {
            throw ValidationException::withMessages([
                'purchase_order_number' => 'Ordem de compra nao encontrada no ERP. Verifique a OC antes de '.$action.'.',
            ]);
        }

        if (($purchaseOrder['status'] ?? null) === 'cancelada') {
            throw ValidationException::withMessages([
                'purchase_order_number' => 'Ordem de compra cancelada. Corrija a OC antes de '.$action.'.',
            ]);
        }

        $supplierCnpj = preg_replace('/\D/', '', (string) ($purchaseOrder['supplier_cnpj'] ?? '')) ?? '';

        if ($issuerCnpj && $supplierCnpj && $issuerCnpj !== $supplierCnpj) {
            throw ValidationException::withMessages([
                'purchase_order_number' => 'O CNPJ do fornecedor da OC e diferente do CNPJ do emitente da nota. Verifique a OC ou selecione o PDF correto antes de '.$action.'.',
            ]);
        }
    }

    private function refreshInvoiceExtractionFromStoredPdf(Invoice $invoice, PdfExtractionService $pdfExtractionService): ?array
    {
        if (blank($invoice->pdf_path) || ! Storage::disk('local')->exists($invoice->pdf_path)) {
            return null;
        }

        $extracted = $pdfExtractionService->extract(Storage::disk('local')->path($invoice->pdf_path));
        $recipientCnpj = $extracted['recipient_cnpj'] ?? null;
        $businessUnit = null;

        if ($recipientCnpj) {
            $businessUnit = BusinessUnit::query()
                ->where('cnpj', $recipientCnpj)
                ->first();
        }

        $invoice->forceFill([
            'business_unit_id' => $businessUnit?->id ?? $invoice->business_unit_id,
            'invoice_number' => $extracted['invoice_number'] ?: $invoice->invoice_number,
            'invoice_access_key' => $extracted['invoice_access_key'] ?: $invoice->invoice_access_key,
            'issuer_cnpj' => $extracted['issuer_cnpj'] ?: $invoice->issuer_cnpj,
            'issuer_legal_name' => $extracted['issuer_legal_name'] ?: $invoice->issuer_legal_name,
            'recipient_cnpj' => $recipientCnpj ?: $invoice->recipient_cnpj,
            'recipient_legal_name' => $extracted['recipient_legal_name'] ?: $invoice->recipient_legal_name,
        ])->save();

        return $extracted;
    }

    public function storeDraftFollowUp(Request $request, Invoice $invoice, InvoiceHistoryService $historyService): RedirectResponse
    {
        $this->authorize('update', $invoice);

        abort_unless($invoice->status === InvoiceStatus::Draft, 404);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $historyService->record(
            $invoice,
            $request->user(),
            'Acompanhamento do rascunho',
            InvoiceStatus::Draft,
            InvoiceStatus::Draft,
            trim($validated['note']),
            $request
        );

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Acompanhamento registrado no rascunho.');
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

    private function syncPurchaseOrderCheck(
        Invoice $invoice,
        PurchaseOrderService $purchaseOrderService,
        PdfExtractionService $pdfExtractionService,
        InvoiceAlertService $alertService
    ): void {
        $this->clearPurchaseOrderCheck($invoice);

        if ($invoice->documentType() !== InvoiceDocumentType::Nf || blank($invoice->purchase_order_number)) {
            return;
        }

        $purchaseOrder = $purchaseOrderService->find($invoice->purchase_order_number);

        $invoice->purchaseOrderCheck()->updateOrCreate(
            ['invoice_id' => $invoice->id],
            $purchaseOrder + ['purchase_order_number' => $invoice->purchase_order_number]
        );

        $purchaseOrderSource = $purchaseOrder['raw_response']['source'] ?? null;

        if (! $purchaseOrder['exists'] && in_array($purchaseOrderSource, $this->purchaseOrderLookupFailedSources(), true)) {
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

    private function purchaseOrderLookupFailedSources(): array
    {
        return [
            'oci8_missing',
            'oracle_error',
            'http_not_configured',
            'http_error',
            'http_exception',
        ];
    }

    private function clearPurchaseOrderCheck(Invoice $invoice): void
    {
        $relatedAlertTypes = [
            AlertType::PurchaseOrderLookupFailed,
            AlertType::PurchaseOrderNotFound,
            AlertType::PurchaseOrderCancelled,
            AlertType::CnpjMismatch,
        ];

        $invoice->alerts()
            ->whereIn('type', array_map(fn (AlertType $type): string => $type->value, $relatedAlertTypes))
            ->where('resolved', false)
            ->delete();

        $invoice->purchaseOrderCheck()->delete();
    }

    private function notifyFiscalTeamPendingWasResolved(
        Invoice $invoice,
        User $submitter,
        InvoiceHistoryService $historyService,
        Request $request
    ): void {
        $recipients = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Fiscal->value])
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $sent = collect();

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send(new InvoicePendingResolvedMail($invoice, $submitter));
                $sent->push($recipient);
            } catch (Throwable $exception) {
                Log::warning('Falha ao enviar e-mail de pendencia respondida para fiscal.', [
                    'invoice_id' => $invoice->id,
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($sent->isEmpty()) {
            Log::warning('Nenhum fiscal recebeu o e-mail de pendencia respondida.', [
                'invoice_id' => $invoice->id,
                'recipients' => $recipients->all(),
            ]);

            return;
        }

        $historyService->record(
            $invoice,
            $submitter,
            'Fiscal avisado sobre pendencia respondida',
            $invoice->status,
            $invoice->status,
            'Aviso enviado para '.$sent->count().' destinatario'.($sent->count() === 1 ? '' : 's').'.',
            $request
        );
    }
}
