<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('kasir', 'owner', 'super_admin');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount')) {
            $clean = preg_replace('/[^\d]/', '', (string) $this->input('amount'));
            $this->merge(['amount' => $clean === '' ? null : $clean]);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal penarikan gaji wajib diisi.',
            'amount.min' => 'Nominal penarikan gaji harus lebih dari 0.',
        ];
    }
}
