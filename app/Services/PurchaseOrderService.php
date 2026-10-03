<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseOrderService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * Format nomor PO: PO-YYYYMMDD-0001 (urut per hari).
     */
    public function generateNumber(?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'PO-'.$date->format('Ymd').'-';

        $last = PurchaseOrder::withTrashed()
            ->where('po_number', 'like', $prefix.'%')
            ->orderByDesc('po_number')
            ->value('po_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Buat PO beserta itemnya.
     *
     * @param  array{supplier_id?: int|null, notes?: string|null, items: array<int, array{product_id: int, qty: int, purchase_price?: float|string|null}>}  $data
     */
    public function create(array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user) {
            $order = PurchaseOrder::create([
                'po_number' => $this->generateNumber(),
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => $user->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'total' => 0,
            ]);

            $total = 0.0;

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = (int) $item['qty'];
                $price = array_key_exists('purchase_price', $item) && $item['purchase_price'] !== null
                    ? (float) $item['purchase_price']
                    : (float) $product->purchase_price;
                $lineTotal = round($price * $qty, 2);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'qty' => $qty,
                    'purchase_price' => $price,
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $order->update(['total' => round($total, 2)]);

            return $order->refresh();
        });
    }

    /**
     * Terima barang PO: tambah stok tiap item SEKALI lalu tandai diterima.
     * Pengaman anti-dobel: menolak bila status sudah diterima.
     */
    public function receive(PurchaseOrder $order, User $user): PurchaseOrder
    {
        if ($order->isReceived()) {
            throw new RuntimeException('PO '.$order->po_number.' sudah pernah diterima.');
        }

        if ($order->isCancelled()) {
            throw new RuntimeException('PO '.$order->po_number.' sudah dibatalkan, tidak bisa diterima.');
        }

        return DB::transaction(function () use ($order, $user) {
            $order->loadMissing('items');

            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::find($item->product_id);
                if (! $product) {
                    continue;
                }

                $this->stockService->adjust(
                    $product,
                    (int) $item->qty,
                    StockHistory::TYPE_IN,
                    'Penerimaan PO '.$order->po_number,
                    $user,
                );
            }

            $order->update([
                'status' => PurchaseOrder::STATUS_DITERIMA,
                'received_at' => now(),
                'received_by' => $user->id,
            ]);

            return $order->refresh();
        });
    }

    public function updateStatus(PurchaseOrder $order, string $status): PurchaseOrder
    {
        $order->update(['status' => $status]);

        return $order->refresh();
    }

    /**
     * Batalkan PO. Tidak boleh bila sudah diterima (stok sudah bertambah).
     */
    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->isReceived()) {
            throw new RuntimeException('PO '.$order->po_number.' sudah diterima, tidak bisa dibatalkan.');
        }

        if ($order->isCancelled()) {
            throw new RuntimeException('PO '.$order->po_number.' sudah dibatalkan.');
        }

        $order->update(['status' => PurchaseOrder::STATUS_DIBATALKAN]);

        return $order->refresh();
    }
}
