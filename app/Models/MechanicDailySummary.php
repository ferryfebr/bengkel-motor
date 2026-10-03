<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MechanicDailySummary extends Model
{
    protected $fillable = [
        'mechanic_id',
        'summary_date',
        'total_jobs',
        'total_share',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'total_jobs' => 'integer',
            'total_share' => 'decimal:2',
        ];
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    /**
     * Simpan/ubah ringkasan satu tanggal (aman lintas driver: lookup pakai whereDate).
     *
     * @param  array<string, mixed>  $data
     */
    public static function storeForDate(int $mechanicId, Carbon $date, array $data): self
    {
        $model = static::where('mechanic_id', $mechanicId)
            ->whereDate('summary_date', $date->toDateString())
            ->first() ?? new static;

        $model->fill(array_merge([
            'mechanic_id' => $mechanicId,
            'summary_date' => $date->toDateString(),
        ], $data));
        $model->save();

        return $model;
    }
}
