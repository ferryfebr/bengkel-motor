<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'code_sku' => ['required', 'string', 'max:50', Rule::unique('products', 'code_sku')->ignore($product->id)],
            'name' => ['required', 'string', 'max:150'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'purchase_price' => [
                Rule::prohibitedIf(fn () => ! $this->user()->can('updateHpp', Product::class)),
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_price.prohibited' => 'Anda tidak berhak mengubah HPP.',
        ];
    }
}
