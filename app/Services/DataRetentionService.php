<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Transaction;
use App\Models\TransactionArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Retensi data & pagar anti-down hosting 2GB (RINGKASAN §J, RESIKO_HOSTING.md).
 *
 * Prinsip:
 * - Reposisi 8.000 transaksi final HANYA via cron/command, bukan saat checkout (J7).
 * - stock_histories TIDAK dihapus (audit inti); transaction_id jadi NULL via nullOnDelete (J4).
 * - File arsip CSV auto-hapus >30 hari (J8).
 * - activity_logs retensi 12 bulan / 50.000 baris, export dulu (J9).
 */
class DataRetentionService
{
    public const QUOTA_FINAL_TRANSACTIONS = 8000;

    public const ACTIVITY_LOG_MAX_ROWS = 50000;

    public const ACTIVITY_LOG_MAX_MONTHS = 12;

    public const ARCHIVE_FILE_MAX_DAYS = 30;

    public const DISK_WARNING_PERCENT = 70;

    public function __construct(private readonly CsvExportService $csvExport) {}

    /**
     * Jumlah transaksi final saat ini.
     */
    public function finalTransactionCount(): int
    {
        return Transaction::query()
            ->where('work_status', Transaction::WORK_SELESAI)
            ->where('payment_status', Transaction::PAY_LUNAS)
            ->count();
    }

    /**
     * Apakah kuota transaksi final terlampaui (dipakai untuk flag ringan saat checkout).
     */
    public function overQuota(): bool
    {
        return $this->finalTransactionCount() > self::QUOTA_FINAL_TRANSACTIONS;
    }

    /**
     * Arsipkan transaksi final tertua yang melebihi kuota, lalu hapus.
     * Transaksi draft/proses/belum bayar tidak disentuh (J5).
     *
     * @return array{archived: int, archive_path: ?string}
     */
    public function archiveExcess(int $keep = self::QUOTA_FINAL_TRANSACTIONS): array
    {
        $total = $this->finalTransactionCount();
        $excess = $total - $keep;

        if ($excess <= 0) {
            return ['archived' => 0, 'archive_path' => null];
        }

        $oldest = Transaction::with(['details', 'services.shares', 'mechanicShares'])
            ->where('work_status', Transaction::WORK_SELESAI)
            ->where('payment_status', Transaction::PAY_LUNAS)
            ->orderBy('id')
            ->limit($excess)
            ->get();

        if ($oldest->isEmpty()) {
            return ['archived' => 0, 'archive_path' => null];
        }

        $label = $oldest->first()->invoice_number.'_'.$oldest->last()->invoice_number;
        $path = $this->csvExport->writeTransactionsArchive($oldest, $label);

        DB::transaction(function () use ($oldest, $path) {
            TransactionArchive::create([
                'archive_path' => $path,
                'transaction_count' => $oldest->count(),
                'oldest_invoice' => $oldest->first()->invoice_number,
                'newest_invoice' => $oldest->last()->invoice_number,
            ]);

            $ids = $oldest->pluck('id')->all();

            // stock_histories.transaction_id -> NULL otomatis (nullOnDelete); log tetap utuh.
            Transaction::whereIn('id', $ids)->forceDelete();
        });

        return ['archived' => $oldest->count(), 'archive_path' => $path];
    }

    /**
     * Hapus file arsip CSV yang lebih dari 30 hari (J8).
     *
     * @return array{deleted_files: int, deleted_records: int}
     */
    public function pruneArchiveFiles(int $maxDays = self::ARCHIVE_FILE_MAX_DAYS): array
    {
        $threshold = Carbon::now()->subDays($maxDays);

        $deletedFiles = 0;
        $deletedRecords = 0;

        foreach (TransactionArchive::where('created_at', '<', $threshold)->get() as $archive) {
            $absolute = storage_path('app/'.$archive->archive_path);
            if (File::exists($absolute)) {
                File::delete($absolute);
                $deletedFiles++;
            }
            // Catatan arsip boleh dihapus (bukan tabel log append-only).
            $archive->delete();
            $deletedRecords++;
        }

        // Sapu file yatim di direktori arsip yang lebih tua dari batas.
        $dir = storage_path('app/'.CsvExportService::ARCHIVE_DIR);
        if (File::isDirectory($dir)) {
            foreach (File::files($dir) as $file) {
                if ($file->getMTime() < $threshold->getTimestamp()) {
                    File::delete($file->getPathname());
                    $deletedFiles++;
                }
            }
        }

        return ['deleted_files' => $deletedFiles, 'deleted_records' => $deletedRecords];
    }

    /**
     * Arsipkan & hapus activity_logs yang melewati batas 12 bulan atau 50.000 baris (J9).
     *
     * @return array{archived: int, archive_path: ?string}
     */
    public function pruneActivityLogs(
        int $maxRows = self::ACTIVITY_LOG_MAX_ROWS,
        int $maxMonths = self::ACTIVITY_LOG_MAX_MONTHS,
    ): array {
        $count = ActivityLog::count();
        $cutoff = Carbon::now()->subMonths($maxMonths);

        $oldByAge = ActivityLog::where('created_at', '<', $cutoff)->count();
        $oldByRows = max(0, $count - $maxRows);

        if ($oldByAge === 0 && $oldByRows === 0) {
            return ['archived' => 0, 'archive_path' => null];
        }

        // Arsipkan seluruh baris yang lebih tua dari cutoff + kelebihan baris termuda.
        $archiveCsv = $this->csvExport->writeActivityLogsArchive(Carbon::now()->format('Ymd-His'), $cutoff);

        $deleted = DB::transaction(function () use ($cutoff, $maxRows) {
            $deleted = ActivityLog::where('created_at', '<', $cutoff)->delete();

            $remaining = ActivityLog::count();
            $stillOver = $remaining - $maxRows;
            if ($stillOver > 0) {
                $ids = ActivityLog::orderBy('id')->limit($stillOver)->pluck('id');
                $deleted += ActivityLog::whereIn('id', $ids)->delete();
            }

            return $deleted;
        });

        return ['archived' => $deleted, 'archive_path' => $archiveCsv];
    }

    /**
     * Jalankan semua tugas retensi sekaligus (dipakai command terjadwal).
     *
     * @return array<string, mixed>
     */
    public function runAll(): array
    {
        return [
            'transactions' => $this->archiveExcess(),
            'archive_files' => $this->pruneArchiveFiles(),
            'activity_logs' => $this->pruneActivityLogs(),
        ];
    }

    /**
     * Estimasi pemakaian disk (persen) untuk peringatan dini (J11).
     */
    public function diskUsagePercent(): float
    {
        $path = storage_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if (! $total || $free === false) {
            return 0.0;
        }

        return round((($total - $free) / $total) * 100, 2);
    }

    public function diskUsageWarning(): bool
    {
        return $this->diskUsagePercent() >= self::DISK_WARNING_PERCENT;
    }
}
