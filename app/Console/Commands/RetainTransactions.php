<?php

namespace App\Console\Commands;

use App\Models\RetentionRunLog;
use App\Models\Transaction;
use App\Models\TransactionArchive;
use App\Services\CsvExportService;
use App\Services\DailySummaryService;
use App\Services\RetentionLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Retensi transaksi final: arsipkan yang tertua ke CSV, VERIFIKASI file,
 * lalu hapus dari DB agar jumlahnya tidak melewati batas (RESIKO_HOSTING.md pagar #1).
 *
 * Hanya menyentuh transactions + transaction_details + transaction_services +
 * transaction_mechanic_shares. Log stok, kas, dan refund tetap (transaction_id
 * di-set NULL oleh FK nullOnDelete).
 */
class RetainTransactions extends Command
{
    protected $signature = 'transactions:retain {--limit=8000 : Jumlah transaksi final maksimal yang disimpan} {--max-run=5000 : Maksimal transaksi yang diproses per sekali jalan} {--trigger=cron : Pemicu: cron atau manual} {--user-id= : User Super Admin bila dijalankan manual} {--dry-run : Hanya tampilkan rencana}';

    protected $description = 'Arsipkan transaksi final tertua ke CSV lalu hapus, menjaga batas jumlah.';

    public function __construct(
        private readonly CsvExportService $csvExport,
        private readonly DailySummaryService $summaryService,
        private readonly RetentionLogService $retentionLog,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $maxRun = max(1, (int) $this->option('max-run'));
        $trigger = $this->option('trigger') ?: RetentionRunLog::TRIGGER_CRON;
        $userId = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;
        $startedAt = now();

        $total = Transaction::final()->count();
        $excess = $total - $limit;

        if ($excess <= 0) {
            $this->info("Transaksi final: {$total}. Di bawah/tepat batas {$limit}. Tidak ada yang diarsipkan.");

            if (! $this->option('dry-run')) {
                $this->retentionLog->record(
                    RetentionRunLog::TYPE_TRANSACTIONS,
                    $trigger,
                    $userId,
                    0,
                    0,
                    ['transactions' => $total, 'note' => 'di bawah batas'],
                    RetentionRunLog::STATUS_SUCCESS,
                    'Tidak ada transaksi yang diarsipkan.',
                    $startedAt,
                );
            }

            return self::SUCCESS;
        }

        $take = min($excess, $maxRun);

        // Batch = transaksi final tertua sebanyak $take.
        $batch = Transaction::final()->orderBy('id')->limit($take)->get(['id', 'invoice_number', 'created_at']);

        $oldestInvoice = $batch->first()?->invoice_number;
        $newestInvoice = $batch->last()?->invoice_number;
        $maxId = (int) $batch->last()?->id;
        $dateMin = $batch->min('created_at');
        $dateMax = $batch->max('created_at');
        $batchIds = $batch->pluck('id')->all();

        $this->info("Transaksi final: {$total}. Arsipkan {$take} (kelebihan {$excess}).");

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $label = str_replace(['/', ' '], '-', ($oldestInvoice ?? 'lama').'_'.($newestInvoice ?? 'baru'))
            .'_'.now()->format('Ymd-His');

        try {
            $stream = Transaction::final()
                ->where('id', '<=', $maxId)
                ->with(['details.product', 'services.shares.mechanic', 'returns.items.product', 'cashMutations', 'cashier'])
                ->orderBy('id')
                ->lazyById(200);

            $path = $this->csvExport->writeTransactionsArchive($stream, $label);
        } catch (Throwable $e) {
            $this->error('Gagal menulis CSV arsip: '.$e->getMessage());
            $this->retentionLog->record(
                RetentionRunLog::TYPE_TRANSACTIONS, $trigger, $userId, 0, 0,
                ['error' => $e->getMessage()],
                RetentionRunLog::STATUS_FAILED,
                'Gagal menulis CSV arsip; tidak ada baris dihapus.',
                $startedAt,
            );

            return self::FAILURE;
        }

        // VERIFIKASI: CSV harus valid & lengkap SEBELUM data dihapus dari DB.
        if (! $this->csvExport->verifyArchiveFile($path, $take)) {
            $this->error('VERIFIKASI GAGAL: file arsip tidak valid. Penghapusan dibatalkan.');

            $this->retentionLog->record(
                RetentionRunLog::TYPE_TRANSACTIONS, $trigger, $userId, 0, 0,
                ['archive_path' => $path, 'expected_min_rows' => $take],
                RetentionRunLog::STATUS_FAILED,
                'Verifikasi file arsip gagal; tidak ada baris dihapus.',
                $startedAt,
            );

            return self::FAILURE;
        }

        TransactionArchive::create([
            'type' => TransactionArchive::TYPE_TRANSACTIONS,
            'archive_path' => $path,
            'transaction_count' => $take,
            'oldest_invoice' => $oldestInvoice,
            'newest_invoice' => $newestInvoice,
        ]);

        // Bangun ringkasan harian untuk rentang tanggal yang diarsip (jaring pengaman laporan).
        if ($dateMin && $dateMax) {
            $this->summaryService->buildRange(Carbon::parse($dateMin), Carbon::parse($dateMax));
        }

        // Hitung baris anak SEBELUM dihapus (cascadeOnDelete menghapus detail/jasa/share).
        $childCounts = $this->childCounts($batchIds);

        // Hapus bertahap; FK cascadeOnDelete menghapus detail/jasa/share di DB.
        $deleted = 0;
        try {
            Transaction::final()
                ->where('id', '<=', $maxId)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$deleted) {
                    $ids = $rows->pluck('id')->all();
                    $deleted += DB::table('transactions')->whereIn('id', $ids)->delete();
                });
        } catch (Throwable $e) {
            $this->error('Penghapusan gagal di tengah proses: '.$e->getMessage());

            $this->retentionLog->record(
                RetentionRunLog::TYPE_TRANSACTIONS,
                $trigger,
                $userId,
                $take,
                $deleted,
                array_merge(['archive_path' => $path, 'partial' => true, 'planned' => $take, 'error' => $e->getMessage()], $childCounts),
                RetentionRunLog::STATUS_FAILED,
                "Gagal menghapus di tengah proses; {$deleted} dari {$take} transaksi sempat terhapus. Arsip tetap: {$path}.",
                $startedAt,
            );

            return self::FAILURE;
        }

        $this->retentionLog->record(
            RetentionRunLog::TYPE_TRANSACTIONS,
            $trigger,
            $userId,
            $take,
            $deleted,
            array_merge(['archive_path' => $path], $childCounts),
            RetentionRunLog::STATUS_SUCCESS,
            "Arsip {$path}; terhapus {$deleted} transaksi.",
            $startedAt,
        );

        $this->info("Selesai. Arsip: {$path}. Terhapus: {$deleted} transaksi.");

        return self::SUCCESS;
    }

    /**
     * Jumlah baris anak yang ikut terarsip/terhapus per tabel.
     *
     * @param  array<int, int>  $ids
     * @return array<string, int>
     */
    private function childCounts(array $ids): array
    {
        $counts = [
            'transactions' => count($ids),
            'transaction_details' => 0,
            'transaction_services' => 0,
            'transaction_mechanic_shares' => 0,
        ];

        foreach (array_chunk($ids, 500) as $chunk) {
            $counts['transaction_details'] += DB::table('transaction_details')->whereIn('transaction_id', $chunk)->count();
            $counts['transaction_services'] += DB::table('transaction_services')->whereIn('transaction_id', $chunk)->count();
            $counts['transaction_mechanic_shares'] += DB::table('transaction_mechanic_shares')->whereIn('transaction_id', $chunk)->count();
        }

        return $counts;
    }
}
