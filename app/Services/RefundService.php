<?php

namespace App\Services;

use App\Models\CashMutation;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefundService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly CashService $cashService,
        private readonly ActivityLogService $activityLog,
        private readonly DailySummaryService $summaryService,
    ) {}

    /**
     * Refund produk stok (internal) dari transaksi final.
     * Append-only: tidak mengubah transaksi/detail lama, membuat record refund baru.
     *
     * @param  array<int, array{transaction_detail_id: int|string, qty: int|string}>  $items
     */
    public function refund(
        Transaction $transaction,
        array $items,
        ?string $reason,
        User $user,
        ?int $impersonatedBy = null,
    ): TransactionReturn {
        if (! $transaction->isFinal()) {
            throw new RuntimeException('Hanya transaksi yang sudah final (lunas & selesai) yang dapat direfund.');
        }

        $transaction->loadMissing('details.product');
        $details = $transaction->details->keyBy('id');

        $lines = [];
        $total = 0.0;

        foreach ($items as $item) {
            $qty = (int) ($item['qty'] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $detail = $details->get((int) ($item['transaction_detail_id'] ?? 0));

            if (! $detail) {
                throw new RuntimeException('Item refund tidak ditemukan pada transaksi ini.');
            }

            if ($detail->is_external || ! $detail->product) {
                throw new RuntimeException('Produk luar tidak dapat direfund.');
            }

            $refundable = $detail->refundableQty();
            if ($qty > $refundable) {
                throw new RuntimeException(
                    "Qty refund melebihi sisa ({$refundable}) untuk {$detail->displayName()}."
                );
            }

            $unitPrice = (float) $detail->selling_price;
            $lines[] = [
                'detail' => $detail,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $qty, 2),
            ];
            $total += $unitPrice * $qty;
        }

        if ($lines === []) {
            throw new RuntimeException('Tidak ada item yang dipilih untuk direfund.');
        }

        $total = round($total, 2);

        $return = DB::transaction(function () use ($transaction, $lines, $reason, $total, $user, $impersonatedBy) {
            $return = TransactionReturn::create([
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'impersonated_by' => $impersonatedBy,
                'reason' => $reason,
                'total' => $total,
            ]);

            foreach ($lines as $line) {
                TransactionReturnItem::create([
                    'transaction_return_id' => $return->id,
                    'transaction_detail_id' => $line['detail']->id,
                    'product_id' => $line['detail']->product_id,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);

                // Stok balik karena refund.
                $this->stockService->adjust(
                    $line['detail']->product,
                    $line['qty'],
                    StockHistory::TYPE_RETURN,
                    'Refund '.$transaction->invoice_number,
                    $user,
                );
            }

            // Kas keluar sebesar nilai refund.
            $this->cashService->record(
                CashMutation::TYPE_OUT,
                $total,
                'Refund - '.$transaction->invoice_number,
                $user,
                $transaction,
                $impersonatedBy,
                CashMutation::CATEGORY_REFUND,
            );

            $this->activityLog->log('refund', $transaction, $transaction->id, null, [
                'total' => $total,
                'items_count' => array_sum(array_map(fn ($line) => $line['qty'], $lines)),
                'items' => array_map(fn ($line) => $line['detail']->displayName().' x'.$line['qty'], $lines),
                'reason' => $reason,
            ]);

            return $return->refresh();
        });

        // Segarkan ringkasan hari transaksi agar omset bersih ikut berkurang.
        $this->summaryService->build($transaction->created_at);

        return $return;
    }
}
