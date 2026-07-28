<?php

namespace App\Http\Controllers;

use App\Enums\AlertLevel;
use App\Enums\InvoiceStatus;
use App\Http\Requests\Invoice\FiscalStatusRequest;
use App\Http\Requests\Invoice\FiscalReviewRequest;
use App\Http\Requests\Invoice\UpdateInvoiceUnitRequest;
use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Models\InvoiceAlert;
use App\Services\InvoiceAlertService;
use App\Services\InvoiceHistoryService;
use Illuminate\Http\RedirectResponse;

class FiscalReviewController extends Controller
{
    public function updateUnit(UpdateInvoiceUnitRequest $request, Invoice $invoice, InvoiceHistoryService $historyService): RedirectResponse
    {
        $previousUnit = $invoice->businessUnit?->name ?? 'Nao identificada';
        $unit = BusinessUnit::query()->findOrFail($request->integer('business_unit_id'));

        $invoice->update([
            'business_unit_id' => $unit->id,
            'recipient_cnpj' => $invoice->recipient_cnpj ?: $unit->cnpj,
            'recipient_legal_name' => $invoice->recipient_legal_name ?: $unit->legal_name,
        ]);

        $historyService->record(
            $invoice,
            $request->user(),
            'Unidade alterada',
            $invoice->status,
            $invoice->status,
            $previousUnit.' -> '.$unit->name,
            $request
        );

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Unidade da nota atualizada com sucesso.');
    }

    public function markAsPending(FiscalStatusRequest $request, Invoice $invoice, InvoiceHistoryService $historyService): RedirectResponse
    {
        if (blank($request->string('fiscal_notes')->toString())) {
            return back()->withErrors(['fiscal_notes' => 'Informe o motivo da pendencia.']);
        }

        return $this->changeStatus($request, $invoice, $historyService, InvoiceStatus::Pending, 'Nota marcada com pendencia');
    }

    public function markAsLaunched(FiscalReviewRequest $request, Invoice $invoice, InvoiceHistoryService $historyService): RedirectResponse
    {
        if ($invoice->alerts()->where('resolved', false)->where('level', AlertLevel::Critical)->exists()) {
            return back()->withErrors(['fiscal_notes' => 'Resolva os alertas criticos antes de marcar a nota como lancada.']);
        }

        $previousStatus = $invoice->status;

        $invoice->update([
            'status' => InvoiceStatus::Launched,
            'fiscal_notes' => $request->string('fiscal_notes')->toString() ?: $invoice->fiscal_notes,
            'fiscal_user_id' => $request->user()->id,
            'launched_at' => now(),
        ]);

        $historyService->record(
            $invoice,
            $request->user(),
            'Nota marcada como lancada',
            $previousStatus,
            InvoiceStatus::Launched,
            $request->string('fiscal_notes')->toString() ?: null,
            $request
        );

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Nota marcada como lancada com sucesso.');
    }

    public function cancel(FiscalStatusRequest $request, Invoice $invoice, InvoiceHistoryService $historyService): RedirectResponse
    {
        if (blank($request->string('fiscal_notes')->toString())) {
            return back()->withErrors(['fiscal_notes' => 'Informe o motivo do cancelamento.']);
        }

        return $this->changeStatus($request, $invoice, $historyService, InvoiceStatus::Cancelled, 'Nota cancelada');
    }

    public function resolveAlert(
        FiscalStatusRequest $request,
        Invoice $invoice,
        InvoiceAlert $alert,
        InvoiceAlertService $alertService,
        InvoiceHistoryService $historyService
    ): RedirectResponse {
        abort_unless($alert->invoice_id === $invoice->id, 404);

        if (! $alert->resolved) {
            $alertService->resolve($alert, $request->user());
            $historyService->record($invoice, $request->user(), 'Alerta resolvido', $invoice->status, $invoice->status, $alert->type->label(), $request);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Alerta resolvido com sucesso.');
    }

    private function changeStatus(
        FiscalStatusRequest $request,
        Invoice $invoice,
        InvoiceHistoryService $historyService,
        InvoiceStatus $newStatus,
        string $action
    ): RedirectResponse {
        $previousStatus = $invoice->status;
        $fiscalNotes = $request->string('fiscal_notes')->toString();

        $invoice->update([
            'status' => $newStatus,
            'fiscal_notes' => $fiscalNotes ?: $invoice->fiscal_notes,
            'fiscal_user_id' => $request->user()->id,
            'launched_at' => $newStatus === InvoiceStatus::Launched ? now() : $invoice->launched_at,
        ]);

        $historyService->record(
            $invoice,
            $request->user(),
            $action,
            $previousStatus,
            $newStatus,
            $fiscalNotes ?: null,
            $request
        );

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', $action.' com sucesso.');
    }
}
