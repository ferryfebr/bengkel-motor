<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DailySummary extends Model
{
    protected $fillable = [
        'summary_date',
        'gross_revenue',
        'net_revenue',
        'total_transactions',
        'total_cash_in',
        'total_cash_out',
        'refund_total',
        'cogs',
        'mechanic_fee',
        'bengkel_fee',
    ];

    /**
     * Simpan/ubah ringkasan satu tanggal (aman lintas driver: lookup pakai whereDate).
     *
     * @param  array<string, mixed>  $data
     */
    public static function storeForDate(Carbon $date, array $data): self
    {
        $model = static::whereDate('summary_date', $date->toDateString())->first() ?? new static;

        $model->fill(array_merge(['summary_date' => $date->toDateString()], $data));
        $model->save();

        return $model;
    }

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'gross_revenue' => 'decimal:2',
            'net_revenue' => 'decimal:2',
            'total_transactions' => 'integer',
            'total_cash_in' => 'decimal:2',
            'total_cash_out' => 'decimal:2',
            'refund_total' => 'decimal:2',
            'cogs' => 'decimal:2',
            'mechanic_fee' => 'decimal:2',
            'bengkel_fee' => 'decimal:2',
        ];
    }
}
