<?php

namespace App\Http\Requests\Cash;

use App\Models\CashMutation;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('owner', 'super_admin');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $balance = CashMutation::balance();
            if ((float) $this->input('amount') > $balance + 0.001) {
                $validator->errors()->add(
                    'amount',
                    'Nominal penarikan melebihi saldo kas (Rp '.number_format($balance, 0, ',', '.').').'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal penarikan wajib diisi.',
            'amount.min' => 'Nominal penarikan harus lebih dari 0.',
        ];
    }
}
