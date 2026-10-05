<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\RetentionRunLog;
use App\Models\TransactionArchive;
use App\Services\CsvExportService;
use App\Services\RetentionLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Retensi activity_logs: arsipkan ke CSV dulu, VERIFIKASI file, baru hapus
 * (RINGKASAN §H6/J9, RESIKO_HOSTING.md). Arsip permanen & bisa diunduh via
 * menu Arsip & Backup, konsisten dengan pola transactions:retain.
 *
 * Memakai query builder langsung karena observer append-only memblokir
 * penghapusan via Eloquent (SECURITY.md §1); retensi adalah pengecualian sadar.
 */
class PruneActivityLogs extends Command
{
    protected $signature = 'activity:prune {--max=3000 : Jumlah baris maksimal yang disimpan} {--months=3 : Umur maksimal baris (bulan)} {--trigger=cron : Pemicu: cron atau manual} {--user-id= : User Super Admin bila dijalankan manual} {--dry-run : Hanya tampilkan rencana}';

    protected $description = 'Arsipkan activity_logs lama ke CSV lalu hapus (batas baris / umur).';

    public function __construct(
        private readonly CsvExportService $csvExport,
        private readonly RetentionLogService $retentionLog,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $max = max(0, (int) $this->option('max'));
        $months = max(1, (int) $this->option('months'));
        $trigger = $this->option('trigger') ?: RetentionRunLog::TRIGGER_CRON;
        $userId = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;
        $startedAt = now();
        $cutoff = Carbon::now()->subMonths($months);

        // Tentukan himpunan baris yang akan diarsip + dihapus (by umur, lalu sisa by jumlah).
        $ageIds = DB::table('activity_logs')->where('created_at', '<', $cutoff)->orderBy('id')->pluck('id')->all();

        $survivorCount = DB::table('activity_logs')->where('created_at', '>=', $cutoff)->count();
        $excess = max(0, $survivorCount - $max);
        $countIds = $excess > 0
            ? DB::table('activity_logs')->where('created_at', '>=', $cutoff)->orderBy('id')->limit($excess)->pluck('id')->all()
            : [];

        $deleteIds = array_values(array_unique(array_merge($ageIds, $countIds)));
        $totalDelete = count($deleteIds);

        if ($totalDelete === 0) {
            $this->info('Tidak ada activity_logs yang perlu diarsipkan/dihapus.');

            if (! $this->option('dry-run')) {
                $this->retentionLog->record(
                    RetentionRunLog::TYPE_ACTIVITY, $trigger, $userId, 0, 0,
                    ['activity_logs' => 0, 'note' => 'di bawah batas'],
                    RetentionRunLog::STATUS_SUCCESS,
                    'Tidak ada activity_logs yang diarsipkan.',
                    $startedAt,
                );
            }

            return self::SUCCESS;
        }

        $this->info("Activity logs akan diarsipkan & dihapus: {$totalDelete} baris (umur {$months} bln, batas {$max}).");

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $label = 'prune_'.now()->format('Ymd-His');

        try {
            $stream = ActivityLog::with('user')
                ->whereIn('id', $deleteIds)
                ->orderBy('id')
                ->lazyById(500);

            $path = $this->csvExport->writeActivityLogsArchive($stream, $label);
        } catch (Throwable $e) {
            $this->error('Gagal menulis CSV arsip aktivitas: '.$e->getMessage());
            $this->retentionLog->record(
                RetentionRunLog::TYPE_ACTIVITY, $trigger, $userId, 0, 0,
                ['error' => $e->getMessage()],
                RetentionRunLog::STATUS_FAILED,
                'Gagal menulis CSV arsip; tidak ada baris dihapus.',
                $startedAt,
            );

            return self::FAILURE;
        }

        // VERIFIKASI: CSV harus valid & lengkap SEBELUM data dihapus dari DB.
        if (! $this->csvExport->verifyArchiveFile($path, $totalDelete)) {
            $this->error('VERIFIKASI GAGAL: file arsip aktivitas tidak valid. Penghapusan dibatalkan.');

            $this->retentionLog->record(
                RetentionRunLog::TYPE_ACTIVITY, $trigger, $userId, 0, 0,
                ['archive_path' => $path, 'expected_min_rows' => $totalDelete],
                RetentionRunLog::STATUS_FAILED,
                'Verifikasi file arsip gagal; tidak ada baris dihapus.',
                $startedAt,
            );

            return self::FAILURE;
        }

        TransactionArchive::create([
            'type' => TransactionArchive::TYPE_ACTIVITY,
            'archive_path' => $path,
            'transaction_count' => $totalDelete,
        ]);

        $deleted = 0;
        try {
            foreach (array_chunk($deleteIds, 500) as $chunk) {
                $deleted += DB::table('activity_logs')->whereIn('id', $chunk)->delete();
            }
        } catch (Throwable $e) {
            $this->error('Penghapusan gagal di tengah proses: '.$e->getMessage());

            $this->retentionLog->record(
                RetentionRunLog::TYPE_ACTIVITY,
                $trigger,
                $userId,
                $totalDelete,
                $deleted,
                ['archive_path' => $path, 'partial' => true, 'planned' => $totalDelete, 'activity_logs' => $deleted, 'error' => $e->getMessage()],
                RetentionRunLog::STATUS_FAILED,
                "Gagal menghapus di tengah proses; {$deleted} dari {$totalDelete} baris sempat terhapus. Arsip tetap: {$path}.",
                $startedAt,
            );

            return self::FAILURE;
        }

        $this->retentionLog->record(
            RetentionRunLog::TYPE_ACTIVITY,
            $trigger,
            $userId,
            $totalDelete,
            $deleted,
            ['archive_path' => $path, 'activity_logs' => $deleted],
            RetentionRunLog::STATUS_SUCCESS,
            "Arsip {$path}; terhapus {$deleted} baris.",
            $startedAt,
        );

        $this->info("Selesai. Arsip: {$path}. Terhapus: {$deleted} baris.");

        return self::SUCCESS;
    }
}
