<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionService extends Model
{
    protected $fillable = [
        'transaction_id',
        'service_id',
        'service_name',
        'mechanic_id',
        'service_price',
        'mechanic_fee',
        'bengkel_fee',
    ];

    protected function casts(): array
    {
        return [
            'service_price' => 'decimal:2',
            'mechanic_fee' => 'decimal:2',
            'bengkel_fee' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(TransactionMechanicShare::class);
    }
}
