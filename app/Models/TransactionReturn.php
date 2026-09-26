<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionReturn extends Model
{
    // Append-only.
    public const UPDATED_AT = null;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'impersonated_by',
        'reason',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionReturnItem::class);
    }
}
