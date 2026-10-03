<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('owner', 'super_admin');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Bersihkan pemisah ribuan (mis. "18.000" -> "18000") agar tidak dibaca
     * sebagai desimal oleh validator numeric.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $i => $item) {
            if (isset($item['purchase_price']) && is_string($item['purchase_price'])) {
                $items[$i]['purchase_price'] = preg_replace('/[^\d]/', '', $item['purchase_price']);
            }
        }

        $this->merge(['items' => $items]);
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Tambahkan minimal 1 produk untuk dipesan.',
            'items.*.product_id.required' => 'Produk wajib dipilih.',
            'items.*.qty.min' => 'Jumlah pesan minimal 1.',
        ];
    }
}
