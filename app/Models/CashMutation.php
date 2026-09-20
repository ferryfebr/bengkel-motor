<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMutation extends Model
{
    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    // Append-only.
    public const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'amount',
        'description',
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
