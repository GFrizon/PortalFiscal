<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use App\Models\BusinessUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BusinessUnit::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', 'size:14', 'unique:business_units,cnpj'],
            'internal_code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => preg_replace('/\D/', '', (string) $this->input('cnpj')),
        ]);
    }
}
