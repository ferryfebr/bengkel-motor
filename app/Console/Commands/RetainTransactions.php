<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\TransactionArchive;
use App\Services\CsvExportService;
use App\Services\DailySummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Retensi transaksi final: arsipkan yang tertua ke CSV lalu hapus dari DB
 * agar jumlahnya tidak melewati batas (RESIKO_HOSTING.md pagar #1).
 *
 * Hanya menyentuh transactions + transaction_details + transaction_services +
 * transaction_mechanic_shares. Log stok, kas, dan refund tetap (transaction_id
 * di-set NULL oleh FK nullOnDelete).
 */
class RetainTransactions extends Command
{
    protected $signature = 'transactions:retain {--limit=8000 : Jumlah transaksi final maksimal yang disimpan} {--max-run=5000 : Maksimal transaksi yang diproses per sekali jalan} {--dry-run : Hanya tampilkan rencana}';

    protected $description = 'Arsipkan transaksi final tertua ke CSV lalu hapus, menjaga batas jumlah.';

    public function __construct(
        private readonly CsvExportService $csvExport,
        private readonly DailySummaryService $summaryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $maxRun = max(1, (int) $this->option('max-run'));

        $total = Transaction::final()->count();
        $excess = $total - $limit;

        if ($excess <= 0) {
            $this->info("Transaksi final: {$total}. Di bawah/tepat batas {$limit}. Tidak ada yang diarsipkan.");

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

        $this->info("Transaksi final: {$total}. Arsipkan {$take} (kelebihan {$excess}).");

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $label = str_replace(['/', ' '], '-', ($oldestInvoice ?? 'lama').'_'.($newestInvoice ?? 'baru'))
            .'_'.now()->format('Ymd-His');

        $stream = Transaction::final()
            ->where('id', '<=', $maxId)
            ->with(['details.product', 'services.shares.mechanic', 'returns.items.product', 'cashMutations', 'cashier'])
            ->orderBy('id')
            ->lazyById(200);

        $path = $this->csvExport->writeTransactionsArchive($stream, $label);

        TransactionArchive::create([
            'archive_path' => $path,
            'transaction_count' => $take,
            'oldest_invoice' => $oldestInvoice,
            'newest_invoice' => $newestInvoice,
        ]);

        // Bangun ringkasan harian untuk rentang tanggal yang diarsip (jaring pengaman laporan).
        if ($dateMin && $dateMax) {
            $this->summaryService->buildRange(Carbon::parse($dateMin), Carbon::parse($dateMax));
        }

        // Hapus bertahap; FK cascadeOnDelete menghapus detail/jasa/share di DB.
        $deleted = 0;
        Transaction::final()
            ->where('id', '<=', $maxId)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$deleted) {
                $ids = $rows->pluck('id')->all();
                $deleted += DB::table('transactions')->whereIn('id', $ids)->delete();
            });

        $this->info("Selesai. Arsip: {$path}. Terhapus: {$deleted} transaksi.");

        return self::SUCCESS;
    }
}
