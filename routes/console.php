<?php

use App\Services\DailySummaryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rotasi laravel.log harian (anti disk penuh; TIDAK menghapus data transaksi/log DB).
Schedule::command('logs:rotate')->dailyAt('03:00');

// Retensi disk (RESIKO_HOSTING.md). Dijalankan dini hari agar tidak mengganggu operasional.
Schedule::command('transactions:retain --limit='.config('retention.transactions', 8000))->dailyAt('02:30');
Schedule::command('activity:prune --max='.config('retention.activity_max', 3000).' --months='.config('retention.activity_months', 3))->dailyAt('02:45');
Schedule::command('purchase-orders:retain --max='.config('retention.purchase_orders', 2000))->dailyAt('02:50');
Schedule::command('sessions:prune')->dailyAt('03:15');

// Ringkasan harian untuk laporan hemat CPU (K9).
Artisan::command('summaries:build {date?}', function (?string $date = null) {
    $target = $date ? Carbon::parse($date) : Carbon::today();
    app(DailySummaryService::class)->build($target);
    $this->info('Ringkasan harian dibangun: '.$target->toDateString());
})->purpose('Bangun ulang daily_summaries untuk satu tanggal');

Schedule::command('summaries:build')->dailyAt('23:55');
