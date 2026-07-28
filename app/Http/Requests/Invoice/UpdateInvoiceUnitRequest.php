<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceUnitRequest extends FormRequest
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
            'business_unit_id' => ['required', 'exists:business_units,id'],
        ];
    }
}
