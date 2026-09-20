<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionMechanicShare extends Model
{
    protected $fillable = [
        'transaction_id',
        'transaction_service_id',
        'mechanic_id',
        'mechanic_ratio',
        'share_amount',
    ];

    protected function casts(): array
    {
        return [
            'mechanic_ratio' => 'decimal:2',
            'share_amount' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(TransactionService::class, 'transaction_service_id');
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }
}
