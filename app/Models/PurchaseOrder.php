<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DIKIRIM = 'dikirim';

    public const STATUS_DITERIMA = 'diterima';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'po_number',
        'supplier_id',
        'user_id',
        'status',
        'notes',
        'total',
        'received_at',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_DITERIMA;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_DIBATALKAN;
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_DIKIRIM => 'Dikirim',
            self::STATUS_DITERIMA => 'Diterima',
            self::STATUS_DIBATALKAN => 'Dibatalkan',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }
}
