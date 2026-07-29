<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\StoreInvoiceAttachmentRequest;
use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use App\Services\InvoiceHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceAttachmentController extends Controller
{
    public function store(StoreInvoiceAttachmentRequest $request, Invoice $invoice, InvoiceHistoryService $historyService): RedirectResponse
    {
        $file = $request->file('attachment');
        $path = $file->store('invoice-attachments/'.now()->format('Y/m').'/invoice-'.$invoice->id, 'local');

        $attachment = $invoice->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: 0,
            'notes' => $request->string('notes')->toString() ?: null,
        ]);

        $historyService->record(
            $invoice,
            $request->user(),
            'Documento complementar anexado',
            $invoice->status,
            $invoice->status,
            $attachment->original_name.($attachment->notes ? ' - '.$attachment->notes : ''),
            $request
        );

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Documento complementar anexado com sucesso.');
    }

    public function download(Invoice $invoice, InvoiceAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $invoice);

        abort_unless($attachment->invoice_id === $invoice->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
