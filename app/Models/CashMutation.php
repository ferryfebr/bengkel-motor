<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMutation extends Model
{
    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const CATEGORY_MANUAL = 'manual';

    public const CATEGORY_EXTERNAL_PRODUCT = 'external_product';

    public const CATEGORY_MECHANIC_PAYOUT = 'mechanic_payout';

    public const CATEGORY_REFUND = 'refund';

    public const CATEGORY_TRANSACTION_INCOME = 'transaction_income';

    // Append-only.
    public const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'amount',
        'description',
        'category',
        'transaction_id',
        'user_id',
        'impersonated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Label kategori untuk tampilan.
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_MANUAL => 'Manual',
            self::CATEGORY_EXTERNAL_PRODUCT => 'Produk Luar',
            self::CATEGORY_MECHANIC_PAYOUT => 'Gaji Mekanik',
            self::CATEGORY_REFUND => 'Refund',
            self::CATEGORY_TRANSACTION_INCOME => 'Pembayaran Transaksi',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categoryLabels()[$this->category] ?? $this->category;
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function balance(): float
    {
        $in = (float) static::where('type', self::TYPE_IN)->sum('amount');
        $out = (float) static::where('type', self::TYPE_OUT)->sum('amount');

        return $in - $out;
    }
}
