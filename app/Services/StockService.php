<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\User;
use RuntimeException;

class StockService
{
    /**
     * Perubahan stok manual (restock, barang rusak, koreksi).
     */
    public function adjust(
        Product $product,
        int $qtyChange,
        string $type,
        string $reason,
        User $user,
    ): StockHistory {
        $newStock = $product->stock + $qtyChange;

        if ($newStock < 0) {
            throw new RuntimeException("Stok {$product->name} tidak cukup (tersisa {$product->stock}).");
        }

        $oldStock = $product->stock;

        $history = StockHistory::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => $type,
            'qty_change' => $qtyChange,
            'old_selling_price' => $product->selling_price,
            'new_selling_price' => $product->selling_price,
            'reason' => $reason,
        ]);

        $product->update(['stock' => $newStock]);

        return $history;
    }

    /**
     * Pengurangan stok otomatis saat POS checkout (tipe 'sale').
     * Terhubung ke transaction_id. Dipanggil dari TransactionService.
     */
    public function deductFromSale(Transaction $transaction, User $user): void
    {
        $transaction->loadMissing('details.product');

        foreach ($transaction->details as $detail) {
            // Produk luar tidak mengurangi stok bengkel.
            if ($detail->is_external || ! $detail->product) {
                continue;
            }

            $product = $detail->product;
            $newStock = $product->stock - $detail->qty;

            if ($newStock < 0) {
                throw new RuntimeException("Stok {$product->name} tidak cukup untuk penjualan.");
            }

            StockHistory::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'type' => StockHistory::TYPE_SALE,
                'qty_change' => -$detail->qty,
                'old_selling_price' => $detail->selling_price,
                'new_selling_price' => $detail->selling_price,
                'reason' => 'Penjualan',
            ]);

            $product->update(['stock' => $newStock]);
        }
    }
}
