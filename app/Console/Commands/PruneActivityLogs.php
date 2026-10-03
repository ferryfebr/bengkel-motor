<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Retensi activity_logs: hapus yang lebih tua dari N bulan ATAU melebihi
 * jumlah maksimal (FIFO). TANPA arsip CSV (kebijakan operasional).
 *
 * Memakai query builder langsung karena observer append-only memblokir
 * penghapusan via Eloquent (SECURITY.md §1); retensi adalah pengecualian sadar.
 */
class PruneActivityLogs extends Command
{
    protected $signature = 'activity:prune {--max=3000 : Jumlah baris maksimal yang disimpan} {--months=3 : Umur maksimal baris (bulan)}';

    protected $description = 'Hapus activity_logs lama (batas baris / umur).';

    public function handle(): int
    {
        $max = max(0, (int) $this->option('max'));
        $months = max(1, (int) $this->option('months'));
        $cutoff = Carbon::now()->subMonths($months);

        $byAge = DB::table('activity_logs')->where('created_at', '<', $cutoff)->delete();

        $remaining = DB::table('activity_logs')->count();
        $byCount = 0;

        if ($remaining > $max) {
            $excess = $remaining - $max;
            $ids = DB::table('activity_logs')->orderBy('id')->limit($excess)->pluck('id');
            $byCount = DB::table('activity_logs')->whereIn('id', $ids)->delete();
        }

        $this->info("Activity logs dihapus — karena umur: {$byAge}, karena batas baris: {$byCount}.");

        return self::SUCCESS;
    }
}
