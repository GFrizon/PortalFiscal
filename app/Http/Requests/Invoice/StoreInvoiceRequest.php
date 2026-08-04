<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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
        $isDraft = $this->isDraftIntent();

        return [
            'submit_intent' => ['nullable', Rule::in(['submit', 'draft'])],
            'pdf' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.$maxUploadKb],
            'is_urgent' => ['nullable', 'boolean'],
            'document_type' => ['required', Rule::in(['nf', 'nf_no_oc', 'cte'])],
            'purchase_order_number' => [$isDraft ? 'nullable' : 'required_unless:document_type,nf_no_oc', 'nullable', 'digits_between:1,80'],
            'arrival_date' => [$isDraft ? 'nullable' : 'required', 'date'],
            'payment_method' => [$isDraft ? 'nullable' : 'required', Rule::in(['anticipated', 'deposit', 'boleto'])],
            'payment_installments_count' => [$isDraft ? 'nullable' : 'required_unless:payment_method,anticipated', 'nullable', 'integer', 'min:1', 'max:12'],
            'payment_installments' => [$isDraft ? 'nullable' : 'required_unless:payment_method,anticipated', 'nullable', 'array'],
            'payment_installments.*.due_date' => [$isDraft ? 'nullable' : 'required_unless:payment_method,anticipated', 'nullable', 'date'],
            'user_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->isDraftIntent()) {
                return;
            }

            if ($this->input('payment_method') === 'anticipated') {
                return;
            }

            $count = (int) $this->input('payment_installments_count');
            $installments = $this->input('payment_installments', []);

            if (! is_array($installments) || count($installments) !== $count) {
                $validator->errors()->add('payment_installments_count', 'Informe os dados de todas as parcelas.');
            }

            $minimumDueDate = $this->minimumDueDate();

            foreach ($installments as $index => $installment) {
                $dueDate = $installment['due_date'] ?? null;

                if (! $dueDate) {
                    continue;
                }

                try {
                    $date = Carbon::parse($dueDate)->startOfDay();
                } catch (\Throwable) {
                    continue;
                }

                if ($date->isWeekend()) {
                    $validator->errors()->add("payment_installments.{$index}.due_date", 'O vencimento deve cair em dia util.');
                }

                if ($date->lt($minimumDueDate)) {
                    $validator->errors()->add(
                        "payment_installments.{$index}.due_date",
                        'O vencimento deve ter no minimo 2 dias uteis a partir de hoje.'
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'submit_intent' => $this->input('submit_intent') === 'draft' ? 'draft' : 'submit',
            'document_type' => strtolower(trim((string) $this->input('document_type', 'nf'))),
            'is_urgent' => $this->boolean('is_urgent'),
            'purchase_order_number' => trim((string) $this->input('purchase_order_number')),
            'payment_method' => strtolower(trim((string) $this->input('payment_method', 'anticipated'))) ?: 'anticipated',
            'payment_installments' => $this->normalizeInstallments((array) $this->input('payment_installments', [])),
            'user_notes' => trim((string) $this->input('user_notes')),
        ]);
    }

    public function isDraftIntent(): bool
    {
        return $this->input('submit_intent') === 'draft';
    }

    private function normalizeInstallments(array $installments): array
    {
        return array_values(array_map(function (mixed $installment, int $index): array {
            $installment = is_array($installment) ? $installment : [];

            return [
                'number' => $index + 1,
                'due_date' => trim((string) ($installment['due_date'] ?? '')),
            ];
        }, $installments, array_keys($installments)));
    }

    private function minimumDueDate(): Carbon
    {
        $date = Carbon::today(config('app.timezone'));
        $businessDays = 0;

        while ($businessDays < 2) {
            $date->addDay();

            if ($date->isWeekday()) {
                $businessDays++;
            }
        }

        return $date->startOfDay();
    }
}
