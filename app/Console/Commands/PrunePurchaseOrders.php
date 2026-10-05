<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use App\Models\RetentionRunLog;
use App\Models\TransactionArchive;
use App\Services\CsvExportService;
use App\Services\RetentionLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Retensi pesanan pembelian (PO): arsipkan PO tertua ke CSV, VERIFIKASI file,
 * lalu hapus dari DB (pola sama seperti transactions:retain). Hard-delete agar
 * disk benar-benar lega; item ikut terhapus (cascade).
 */
class PrunePurchaseOrders extends Command
{
    protected $signature = 'purchase-orders:retain {--max=2000 : Jumlah PO maksimal yang disimpan} {--trigger=cron : Pemicu: cron atau manual} {--user-id= : User Super Admin bila dijalankan manual} {--dry-run : Hanya tampilkan rencana}';

    protected $description = 'Arsipkan PO lama ke CSV lalu hapus agar jumlahnya tidak melewati batas.';

    public function __construct(
        private readonly CsvExportService $csvExport,
        private readonly RetentionLogService $retentionLog,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $max = max(0, (int) $this->option('max'));
        $trigger = $this->option('trigger') ?: RetentionRunLog::TRIGGER_CRON;
        $userId = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;
        $startedAt = now();

        $count = PurchaseOrder::count();

        if ($count <= $max) {
            $this->info("PO: {$count}. Di bawah/tepat batas {$max}. Tidak ada yang diarsipkan.");

            if (! $this->option('dry-run')) {
                $this->retentionLog->record(
                    RetentionRunLog::TYPE_PURCHASE_ORDERS, $trigger, $userId, 0, 0,
                    ['purchase_orders' => $count, 'note' => 'di bawah batas'],
                    RetentionRunLog::STATUS_SUCCESS,
                    'Tidak ada PO yang diarsipkan.',
                    $startedAt,
                );
            }

            return self::SUCCESS;
        }

        $excess = $count - $max;
        $orders = PurchaseOrder::orderBy('id')->limit($excess)->get(['id', 'po_number']);
        $ids = $orders->pluck('id')->all();

        $oldestPo = $orders->first()?->po_number;
        $newestPo = $orders->last()?->po_number;

        $this->info("PO: {$count}. Arsipkan {$excess} (kelebihan {$excess}).");

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $label = str_replace(['/', ' '], '-', ($oldestPo ?? 'lama').'_'.($newestPo ?? 'baru'))
            .'_'.now()->format('Ymd-His');

        // 1) TULIS CSV
        try {
            $stream = PurchaseOrder::with(['supplier', 'user', 'receiver', 'items'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(200);

            $path = $this->csvExport->writePurchaseOrdersArchive($stream, $label);
        } catch (Throwable $e) {
            $this->error('Gagal menulis CSV arsip PO: '.$e->getMessage());
            $this->retentionLog->record(
                RetentionRunLog::TYPE_PURCHASE_ORDERS, $trigger, $userId, 0, 0,
                ['error' => $e->getMessage()],
                RetentionRunLog::STATUS_FAILED,
                'Gagal menulis CSV arsip; tidak ada baris dihapus.',
                $startedAt,
            );

            return self::FAILURE;
        }

        // 2) VERIFIKASI — SEBELUM hapus
        if (! $this->csvExport->verifyArchiveFile($path, count($ids))) {
            $this->error('VERIFIKASI GAGAL: file arsip PO tidak valid. Penghapusan dibatalkan.');

            $this->retentionLog->record(
                RetentionRunLog::TYPE_PURCHASE_ORDERS, $trigger, $userId, 0, 0,
                ['archive_path' => $path, 'expected_min_rows' => count($ids)],
                RetentionRunLog::STATUS_FAILED,
                'Verifikasi file arsip gagal; tidak ada baris dihapus.',
                $startedAt,
            );

            return self::FAILURE;
        }

        TransactionArchive::create([
            'type' => TransactionArchive::TYPE_PURCHASE_ORDERS,
            'archive_path' => $path,
            'transaction_count' => count($ids),
        ]);

        $itemCount = DB::table('purchase_order_items')->whereIn('purchase_order_id', $ids)->count();

        // 3) BARU HAPUS
        $deleted = 0;
        try {
            foreach (array_chunk($ids, 500) as $chunk) {
                $deleted += DB::table('purchase_orders')->whereIn('id', $chunk)->delete();
            }
        } catch (Throwable $e) {
            $this->error('Penghapusan gagal di tengah proses: '.$e->getMessage());

            $this->retentionLog->record(
                RetentionRunLog::TYPE_PURCHASE_ORDERS, $trigger, $userId, count($ids), $deleted,
                ['archive_path' => $path, 'partial' => true, 'planned' => count($ids), 'purchase_order_items' => $itemCount, 'error' => $e->getMessage()],
                RetentionRunLog::STATUS_FAILED,
                "Gagal menghapus di tengah proses; {$deleted} dari ".count($ids)." PO sempat terhapus. Arsip tetap: {$path}.",
                $startedAt,
            );

            return self::FAILURE;
        }

        $this->retentionLog->record(
            RetentionRunLog::TYPE_PURCHASE_ORDERS,
            $trigger,
            $userId,
            count($ids),
            $deleted,
            ['archive_path' => $path, 'purchase_orders' => $deleted, 'purchase_order_items' => $itemCount],
            RetentionRunLog::STATUS_SUCCESS,
            "Arsip {$path}; terhapus {$deleted} PO.",
            $startedAt,
        );

        $this->info("Selesai. Arsip: {$path}. Terhapus: {$deleted} PO.");

        return self::SUCCESS;
    }
}
