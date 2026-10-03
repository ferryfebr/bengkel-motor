<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hapus session kedaluwarsa (tabel `sessions`, driver database) agar
 * tidak menumpuk (RESIKO_HOSTING.md pagar #6, R4).
 */
class PruneSessions extends Command
{
    protected $signature = 'sessions:prune';

    protected $description = 'Hapus session kedaluwarsa dari tabel sessions.';

    public function handle(): int
    {
        $lifetime = (int) config('session.lifetime', 120);
        $threshold = Carbon::now()->subMinutes($lifetime)->getTimestamp();

        $deleted = DB::table('sessions')->where('last_activity', '<', $threshold)->delete();

        $this->info("Session kedaluwarsa dihapus: {$deleted}.");

        return self::SUCCESS;
    }
}
