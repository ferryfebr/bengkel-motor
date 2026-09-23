<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    public const WORK_ANTRE = 'antre';

    public const WORK_PROSES = 'proses';

    public const WORK_SELESAI = 'selesai';

    public const PAY_BELUM = 'belum_bayar';

    public const PAY_DP = 'dp';

    public const PAY_LUNAS = 'lunas';

    protected $fillable = [
        'invoice_number',
        'cashier_id',
        'impersonated_by',
        'customer_name',
        'plate_number',
        'motor_type',
        'complaint',
        'subtotal_products',
        'subtotal_services',
        'grand_total',
        'paid_amount',
        'payment_method',
        'work_status',
        'payment_status',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_products' => 'decimal:2',
            'subtotal_services' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(TransactionService::class);
    }

    public function mechanicShares(): HasMany
    {
        return $this->hasMany(TransactionMechanicShare::class);
    }

    public function isFinal(): bool
    {
        return $this->work_status === self::WORK_SELESAI
            && $this->payment_status === self::PAY_LUNAS;
    }

    /**
     * Sisa yang belum dibayar customer.
     */
    public function remainingAmount(): float
    {
        return round((float) $this->grand_total - (float) $this->paid_amount, 2);
    }

    /**
     * Apakah transaksi sudah terbayar penuh (boleh difinalisasi).
     */
    public function isPaid(): bool
    {
        return $this->remainingAmount() <= 0;
    }

    public function scopeFinal($query)
    {
        return $query->where('work_status', self::WORK_SELESAI)
            ->where('payment_status', self::PAY_LUNAS);
    }

    /**
     * Transaksi berjalan (belum final) - dipakai Work Order paralel.
     */
    public function scopeOngoing($query)
    {
        return $query->where(function ($q) {
            $q->where('work_status', '!=', self::WORK_SELESAI)
                ->orWhere('payment_status', '!=', self::PAY_LUNAS);
        });
    }
}
