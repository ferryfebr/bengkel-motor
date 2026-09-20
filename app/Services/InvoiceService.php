<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

class InvoiceService
{
    /**
     * Format: INV-YYYYMMDD-0001 (nomor urut per hari).
     * Sederhana & aman dari race: dipanggil di dalam DB::transaction saat create.
     */
    public function generate(?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'INV-'.$date->format('Ymd').'-';

        $last = Transaction::withTrashed()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
