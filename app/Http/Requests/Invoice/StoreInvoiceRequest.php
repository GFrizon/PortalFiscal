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
            'arrival_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['anticipated', 'deposit', 'boleto'])],
            'payment_installments_count' => ['required_unless:payment_method,anticipated', 'nullable', 'integer', 'min:1', 'max:36'],
            'payment_installments' => ['required_unless:payment_method,anticipated', 'nullable', 'array'],
            'payment_installments.*.due_date' => ['required_unless:payment_method,anticipated', 'nullable', 'date'],
            'payment_installments.*.amount' => ['required_unless:payment_method,anticipated', 'nullable', 'numeric', 'min:0.01'],
            'user_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('payment_method') === 'anticipated') {
                return;
            }

            $count = (int) $this->input('payment_installments_count');
            $installments = $this->input('payment_installments', []);

            if (! is_array($installments) || count($installments) !== $count) {
                $validator->errors()->add('payment_installments_count', 'Informe os dados de todas as parcelas.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'document_type' => strtolower(trim((string) $this->input('document_type', 'nf'))),
            'purchase_order_number' => trim((string) $this->input('purchase_order_number')),
            'payment_method' => strtolower(trim((string) $this->input('payment_method', 'anticipated'))),
            'payment_installments' => $this->normalizeInstallments((array) $this->input('payment_installments', [])),
            'user_notes' => trim((string) $this->input('user_notes')),
        ]);
    }

    private function normalizeInstallments(array $installments): array
    {
        return array_values(array_map(function (mixed $installment, int $index): array {
            $installment = is_array($installment) ? $installment : [];
            $amount = trim((string) ($installment['amount'] ?? ''));
            $amount = str_contains($amount, ',')
                ? str_replace(['.', ','], ['', '.'], $amount)
                : $amount;

            return [
                'number' => $index + 1,
                'due_date' => trim((string) ($installment['due_date'] ?? '')),
                'amount' => $amount,
            ];
        }, $installments, array_keys($installments)));
    }
}
