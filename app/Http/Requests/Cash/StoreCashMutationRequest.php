<?php

namespace App\Http\Requests\Cash;

use App\Models\CashMutation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('kasir', 'owner', 'super_admin');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') !== CashMutation::TYPE_OUT) {
                return;
            }

            $balance = CashMutation::balance();
            if ((float) $this->input('amount') > $balance + 0.001) {
                $validator->errors()->add(
                    'amount',
                    'Nominal kas keluar melebihi saldo kas (Rp '.number_format($balance, 0, ',', '.').').'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal wajib diisi.',
            'amount.min' => 'Nominal harus lebih dari 0.',
        ];
    }
}
