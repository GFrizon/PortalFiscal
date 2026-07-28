<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfController extends Controller
{
    public function show(Invoice $invoice): StreamedResponse
    {
        $this->authorize('viewPdf', $invoice);

        abort_unless(\Storage::disk('local')->exists($invoice->pdf_path), 404);

        return \Storage::disk('local')->response($invoice->pdf_path, $invoice->original_pdf_name, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->original_pdf_name.'"',
        ]);
    }

    public function download(Invoice $invoice): StreamedResponse
    {
        $this->authorize('viewPdf', $invoice);

        abort_unless(\Storage::disk('local')->exists($invoice->pdf_path), 404);

        return \Storage::disk('local')->download($invoice->pdf_path, $invoice->original_pdf_name);
    }
}
