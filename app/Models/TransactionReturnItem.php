<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionReturnItem extends Model
{
    // Append-only.
    public const UPDATED_AT = null;

    protected $fillable = [
        'transaction_return_id',
        'transaction_detail_id',
        'product_id',
        'qty',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function transactionReturn(): BelongsTo
    {
        return $this->belongsTo(TransactionReturn::class);
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(TransactionDetail::class, 'transaction_detail_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
