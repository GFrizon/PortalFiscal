<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            && ($this->user()?->can('review', $invoice) ?? false);
    }

    public function rules(): array
    {
        return [
            'attachment' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => trim((string) $this->input('notes')),
        ]);
    }
}
