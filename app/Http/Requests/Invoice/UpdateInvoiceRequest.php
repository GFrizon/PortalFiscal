<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;

class UpdateInvoiceRequest extends StoreInvoiceRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            && ($this->user()?->can('update', $invoice) ?? false);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['pdf']);

        return $rules;
    }
}
