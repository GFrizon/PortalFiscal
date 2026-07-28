<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class FiscalReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            && ($this->user()?->can('markAsLaunched', $invoice) ?? false);
    }

    public function rules(): array
    {
        return [
            'fiscal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fiscal_notes' => trim((string) $this->input('fiscal_notes')),
        ]);
    }
}
