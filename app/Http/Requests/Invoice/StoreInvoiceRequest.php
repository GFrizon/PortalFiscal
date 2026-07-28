<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }

    public function rules(): array
    {
        $maxUploadKb = (int) config('invoices.pdf.max_upload_kb', 10240);

        return [
            'pdf' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.$maxUploadKb],
            'document_type' => ['required', Rule::in(['nf', 'cte'])],
            'purchase_order_number' => ['required', 'digits_between:1,80'],
            'due_date' => ['required', 'date'],
            'arrival_date' => ['required', 'date'],
            'user_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'document_type' => strtolower(trim((string) $this->input('document_type', 'nf'))),
            'purchase_order_number' => trim((string) $this->input('purchase_order_number')),
            'user_notes' => trim((string) $this->input('user_notes')),
        ]);
    }
}
