<?php

use App\Services\DailySummaryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pagar hosting 2GB: reposisi & retensi log hanya via cron, bukan saat checkout
// (RESIKO_HOSTING.md R2/J7). Dijalankan di jam sepi.
Schedule::command('retention:run')->dailyAt('02:00');

// Rotasi laravel.log harian (J10).
Schedule::command('logs:rotate')->dailyAt('03:00');

// Ringkasan harian untuk laporan hemat CPU (K9).
Artisan::command('summaries:build {date?}', function (?string $date = null) {
    $target = $date ? Carbon::parse($date) : Carbon::today();
    app(DailySummaryService::class)->build($target);
    $this->info('Ringkasan harian dibangun: '.$target->toDateString());
})->purpose('Bangun ulang daily_summaries untuk satu tanggal');

Schedule::command('summaries:build')->dailyAt('23:55');
