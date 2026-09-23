<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePosDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('kasir', 'owner', 'super_admin');
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', Rule::in(['cash', 'qris'])],
            'payment_status' => ['nullable', Rule::in(['belum_bayar', 'dp', 'lunas'])],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'work_status' => ['nullable', Rule::in(['antre', 'proses', 'selesai'])],

            'products' => ['array'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.qty' => ['required', 'integer', 'min:1'],

            'external_products' => ['array'],
            'external_products.*.name' => ['required', 'string', 'max:150'],
            'external_products.*.qty' => ['required', 'integer', 'min:1'],
            'external_products.*.purchase_price' => ['required', 'numeric', 'min:0'],
            'external_products.*.selling_price' => ['required', 'numeric', 'min:0'],

            'services' => ['array'],
            'services.*.service_id' => ['nullable', 'integer'],
            'services.*.service_name' => ['required', 'string', 'max:150'],
            'services.*.price' => ['required', 'numeric', 'min:0'],
            'services.*.shares' => ['required', 'array', 'min:1'],
            'services.*.shares.*.mechanic_id' => ['required', 'exists:mechanics,id'],
            'services.*.shares.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'services.*.price.min' => 'Biaya jasa tidak boleh negatif.',
            'services.*.shares.required' => 'Setiap jasa servis wajib memiliki minimal 1 mekanik pengerja.',
            'services.*.shares.min' => 'Setiap jasa servis wajib memiliki minimal 1 mekanik pengerja.',
            'services.*.shares.*.mechanic_id.required' => 'Mekanik wajib dipilih untuk setiap jasa servis.',
            'external_products.*.purchase_price.required' => 'HPP produk luar wajib diisi.',
            'paid_amount.numeric' => 'Jumlah DP harus berupa angka.',
            'paid_amount.min' => 'Jumlah DP tidak boleh negatif.',
        ];
    }
}