<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySummary extends Model
{
    protected $fillable = [
        'summary_date',
        'gross_revenue',
        'net_revenue',
        'total_transactions',
        'total_cash_in',
        'total_cash_out',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'gross_revenue' => 'decimal:2',
            'net_revenue' => 'decimal:2',
            'total_transactions' => 'integer',
            'total_cash_in' => 'decimal:2',
            'total_cash_out' => 'decimal:2',
        ];
    }
}
