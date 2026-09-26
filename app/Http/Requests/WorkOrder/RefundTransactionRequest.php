<?php

namespace App\Http\Requests\WorkOrder;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

class RefundTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('kasir', 'owner', 'super_admin');
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.transaction_detail_id' => ['required', 'integer', 'exists:transaction_details,id'],
            'items.*.qty' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $transaction = $this->route('workOrder');

            if (! $transaction instanceof Transaction || ! $transaction->isFinal()) {
                $validator->errors()->add('items', 'Hanya transaksi final yang dapat direfund.');

                return;
            }

            $hasQty = collect($this->input('items', []))
                ->contains(fn ($item) => (int) ($item['qty'] ?? 0) > 0);

            if (! $hasQty) {
                $validator->errors()->add('items', 'Pilih minimal satu item dengan qty lebih dari 0.');
            }
        });
    }
}
