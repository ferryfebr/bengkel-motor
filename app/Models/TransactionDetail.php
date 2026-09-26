<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionDetail extends Model
{
    protected $fillable = [
        'transaction_id',
        'product_id',
        'is_external',
        'external_name',
        'qty',
        'purchase_price',
        'selling_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'is_external' => 'boolean',
            'qty' => 'integer',
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(TransactionReturnItem::class, 'transaction_detail_id');
    }

    /**
     * Jumlah qty yang sudah pernah direfund untuk baris ini.
     */
    public function refundedQty(): int
    {
        if ($this->relationLoaded('returnItems')) {
            return (int) $this->returnItems->sum('qty');
        }

        return (int) $this->returnItems()->sum('qty');
    }

    /**
     * Sisa qty yang masih bisa direfund (hanya produk stok internal).
     */
    public function refundableQty(): int
    {
        if ($this->is_external) {
            return 0;
        }

        return max(0, (int) $this->qty - $this->refundedQty());
    }

    public function displayName(): string
    {
        return $this->is_external
            ? (string) $this->external_name
            : (string) ($this->product?->name ?? 'Produk');
    }
}
