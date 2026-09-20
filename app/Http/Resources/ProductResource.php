<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductResource - field `purchase_price` (HPP) hanya muncul untuk yang
 * punya ability viewHpp. Lihat SECURITY.md §2.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'code_sku' => $this->code_sku,
            'name' => $this->name,
            'selling_price' => (float) $this->selling_price,
            'stock' => $this->stock,
            'min_stock' => $this->min_stock,
            'purchase_price' => $this->when(
                $request->user()?->can('viewHpp', Product::class),
                fn () => $this->purchase_price !== null ? (float) $this->purchase_price : null
            ),
        ];
    }
}
