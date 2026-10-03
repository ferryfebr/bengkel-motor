<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retensi pesanan pembelian (PO): hapus yang tertua bila melewati batas jumlah.
 * Hard-delete agar disk benar-benar lega; item ikut terhapus (cascade).
 */
class PrunePurchaseOrders extends Command
{
    protected $signature = 'purchase-orders:retain {--max=2000 : Jumlah PO maksimal yang disimpan}';

    protected $description = 'Hapus PO lama agar jumlahnya tidak melewati batas.';

    public function handle(): int
    {
        $max = max(0, (int) $this->option('max'));
        $count = PurchaseOrder::count();

        if ($count <= $max) {
            $this->info("PO: {$count}. Di bawah/tepat batas {$max}. Tidak ada yang dihapus.");

            return self::SUCCESS;
        }

        $excess = $count - $max;
        $ids = PurchaseOrder::orderBy('id')->limit($excess)->pluck('id');
        $deleted = DB::table('purchase_orders')->whereIn('id', $ids)->delete();

        $this->info("PO dihapus: {$deleted}.");

        return self::SUCCESS;
    }
}
