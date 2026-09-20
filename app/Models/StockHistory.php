<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHistory extends Model
{
    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_SALE = 'sale';

    // Append-only: tidak pakai updated_at.
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'user_id',
        'transaction_id',
        'type',
        'qty_change',
        'old_selling_price',
        'new_selling_price',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'qty_change' => 'integer',
            'old_selling_price' => 'decimal:2',
            'new_selling_price' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
