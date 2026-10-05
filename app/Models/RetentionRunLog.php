<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak proses retensi (RESIKO_HOSTING.md). Append-only, tidak kena retensi apa pun.
 */
class RetentionRunLog extends Model
{
    // Append-only: hanya created_at.
    public const UPDATED_AT = null;

    public const TYPE_TRANSACTIONS = 'transactions:retain';

    public const TYPE_ACTIVITY = 'activity:prune';

    public const TYPE_PURCHASE_ORDERS = 'purchase-orders:retain';

    public const TRIGGER_CRON = 'cron';

    public const TRIGGER_MANUAL = 'manual';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'run_type',
        'trigger',
        'user_id',
        'status',
        'archived_count',
        'deleted_count',
        'details',
        'message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'archived_count' => 'integer',
            'deleted_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runTypeLabel(): string
    {
        return match ($this->run_type) {
            self::TYPE_TRANSACTIONS => 'Retensi Transaksi',
            self::TYPE_ACTIVITY => 'Retensi Aktivitas',
            self::TYPE_PURCHASE_ORDERS => 'Retensi Pesanan Pembelian',
            default => $this->run_type,
        };
    }

    public function triggerLabel(): string
    {
        return $this->trigger === self::TRIGGER_MANUAL ? 'Manual' : 'Cron';
    }
}
