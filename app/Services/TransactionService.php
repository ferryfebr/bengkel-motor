<?php

namespace App\Services;

use App\Models\Mechanic;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionArchive;
use App\Models\TransactionDetail;
use App\Models\TransactionService as TransactionServiceModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionService
{
    /**
     * Setiap kelipatan jumlah ini, transaksi final diarsipkan ke CSV (tanpa dihapus).
     */
    public const ARCHIVE_EVERY = 100;

    public function __construct(
        private readonly CommissionService $commissionService,
        private readonly MechanicShareService $mechanicShareService,
        private readonly StockService $stockService,
        private readonly CashService $cashService,
        private readonly CsvExportService $csvExport,
    ) {}

    /**
     * Isi rincian transaksi (produk stok + produk luar + jasa) dan hitung total.
     * Semua perubahan dibungkus satu DB::transaction oleh pemanggil (PosController).
     *
     * @param  array{products?: array, external_products?: array, services?: array}  $payload
     */
    public function fill(Transaction $transaction, array $payload, User $user): Transaction
    {
        $subtotalProducts = 0.0;
        $subtotalServices = 0.0;

        // Produk stok (snapshot harga; HPP dari master - hanya disimpan sebagai snapshot).
        foreach ($payload['products'] ?? [] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $qty = (int) $item['qty'];
            $lineTotal = round((float) $product->selling_price * $qty, 2);

            TransactionDetail::create([
                'transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'is_external' => false,
                'qty' => $qty,
                'purchase_price' => $product->purchase_price,
                'selling_price' => $product->selling_price,
                'line_total' => $lineTotal,
            ]);

            $subtotalProducts += $lineTotal;
        }

        // Produk luar: tidak masuk stok. HPP diinput kasir.
        foreach ($payload['external_products'] ?? [] as $item) {
            $qty = (int) $item['qty'];
            $selling = (float) $item['selling_price'];
            $lineTotal = round($selling * $qty, 2);

            TransactionDetail::create([
                'transaction_id' => $transaction->id,
                'product_id' => null,
                'is_external' => true,
                'external_name' => $item['name'],
                'qty' => $qty,
                'purchase_price' => (float) $item['purchase_price'],
                'selling_price' => $selling,
                'line_total' => $lineTotal,
            ]);

            $subtotalProducts += $lineTotal;
        }

        // Jasa: nominal fleksibel; multi-mekanik dengan pembagian manual.
        foreach ($payload['services'] ?? [] as $item) {
            $price = (float) $item['price'];
            $shares = $item['shares'] ?? [];

            // Porsi mekanik dihitung dari rasio mekanik pertama yang dipilih,
            // atau fallback ke rasio bengkel global.
            $firstMechanic = ! empty($shares)
                ? Mechanic::find($shares[0]['mechanic_id'])
                : null;

            $split = $this->commissionService->split($price, $firstMechanic);

            $service = TransactionServiceModel::create([
                'transaction_id' => $transaction->id,
                'service_id' => $item['service_id'] ?? null,
                'service_name' => $item['service_name'],
                'mechanic_id' => $firstMechanic?->id,
                'service_price' => $price,
                'mechanic_fee' => $split['mechanic_fee'],
                'bengkel_fee' => $split['bengkel_fee'],
            ]);

            if (! empty($shares)) {
                $this->mechanicShareService->distribute($service, $shares);
            }

            $subtotalServices += $price;
        }

        $transaction->update([
            'subtotal_products' => round($subtotalProducts, 2),
            'subtotal_services' => round($subtotalServices, 2),
            'grand_total' => round($subtotalProducts + $subtotalServices, 2),
        ]);

        return $transaction->refresh();
    }

    /**
     * Simpan draft: isi/rewrite rincian + status, TANPA memotong stok atau
     * mencatat kas keluar. Transaksi tetap belum final dan bisa direvisi lagi.
     *
     * @param  array{products?: array, external_products?: array, services?: array, payment_method?: string, payment_status?: string, work_status?: string}  $payload
     */
    public function saveDraft(Transaction $transaction, array $payload, User $user, ?int $impersonatedBy = null): Transaction
    {
        if ($transaction->isFinal()) {
            throw new RuntimeException('Transaksi sudah final, tidak dapat diubah.');
        }

        return DB::transaction(function () use ($transaction, $payload, $user, $impersonatedBy) {
            $transaction->details()->delete();
            $transaction->mechanicShares()->delete();
            $transaction->services()->delete();

            $this->fill($transaction, $payload, $user);

            $grandTotal = (float) $transaction->refresh()->grand_total;
            $paymentStatus = $payload['payment_status'] ?? Transaction::PAY_BELUM;
            $paidAmount = $this->resolvePaidAmount($payload, $grandTotal, $paymentStatus);

            $transaction->update([
                'payment_method' => $payload['payment_method'] ?? 'cash',
                'payment_status' => $paymentStatus,
                'work_status' => $payload['work_status'] ?? $transaction->work_status,
                'paid_amount' => $paidAmount,
                'finalized_at' => null,
            ]);

            // Kas masuk dari uang yang benar-benar diterima (mis. DP).
            $this->cashService->recordTransactionIncome($transaction->refresh(), $user, $impersonatedBy);

            return $transaction->refresh();
        });
    }

    /**
     * Checkout/finalisasi transaksi dalam satu DB transaction:
     * - isi rincian
     * - kurangi stok otomatis (tipe sale)
     * - catat kas keluar HPP produk luar
     * - set status bayar & finalized_at bila final
     *
     * @param  array{products?: array, external_products?: array, services?: array, payment_method?: string, payment_status?: string}  $payload
     */
    public function checkout(Transaction $transaction, array $payload, User $user, ?int $impersonatedBy = null): Transaction
    {
        if ($transaction->isFinal()) {
            throw new RuntimeException('Transaksi sudah final, tidak dapat diubah.');
        }

        return DB::transaction(function () use ($transaction, $payload, $user, $impersonatedBy) {
            // Hapus rincian lama (transaksi belum final - boleh direvisi).
            $transaction->details()->delete();
            $transaction->mechanicShares()->delete();
            $transaction->services()->delete();

            $this->fill($transaction, $payload, $user);

            $workStatus = $payload['work_status'] ?? Transaction::WORK_SELESAI;
            $requestedPaymentStatus = $payload['payment_status'] ?? Transaction::PAY_LUNAS;
            $grandTotal = (float) $transaction->refresh()->grand_total;

            // Nominal terbayar mengikuti status yang dipilih kasir.
            $paidAmount = $this->resolvePaidAmount($payload, $grandTotal, $requestedPaymentStatus);
            $remaining = round($grandTotal - $paidAmount, 2);

            // Syarat finalisasi: pembayaran harus penuh (boleh belum lunas hanya
            // bila pekerjaan belum ditandai selesai).
            if ($workStatus === Transaction::WORK_SELESAI && $remaining > 0.001) {
                throw new RuntimeException(
                    'Transaksi belum lunas. Sisa yang belum dibayar: Rp '.
                    number_format($remaining, 0, ',', '.').'.'
                );
            }

            $paymentStatus = match (true) {
                $remaining <= 0.001 => Transaction::PAY_LUNAS,
                $paidAmount <= 0.001 => Transaction::PAY_BELUM,
                default => Transaction::PAY_DP,
            };

            $transaction->update([
                'payment_method' => $payload['payment_method'] ?? 'cash',
                'payment_status' => $paymentStatus,
                'work_status' => $workStatus,
                'paid_amount' => $paidAmount,
                'finalized_at' => ($paymentStatus === Transaction::PAY_LUNAS && $workStatus === Transaction::WORK_SELESAI)
                    ? now() : null,
            ]);

            $fresh = $transaction->refresh();

            // Kas masuk dari pembayaran yang diterima (DP / lunas).
            $this->cashService->recordTransactionIncome($fresh, $user, $impersonatedBy);

            // Stok & kas keluar produk luar HANYA saat transaksi FINAL.
            // Ini mencegah stok terpotong dobel bila checkout sempat dilakukan
            // sebelum transaksi final (mis. DP/proses lalu dilunasi).
            if ($fresh->isFinal()) {
                $this->stockService->deductFromSale($fresh, $user);
                $this->cashService->recordExternalPurchase($fresh, $user, $impersonatedBy);
                $this->maybeArchive($fresh);
            }

            return $fresh;
        });
    }

    /**
     * Arsipkan (salin ke CSV) transaksi final tiap kelipatan ARCHIVE_EVERY.
     * TIDAK menghapus data apa pun — hanya backup read-only (append-only).
     */
    private function maybeArchive(Transaction $transaction): void
    {
        $count = Transaction::final()->count();

        if ($count === 0 || $count % self::ARCHIVE_EVERY !== 0) {
            return;
        }

        $batch = Transaction::with(['details', 'services.shares', 'mechanicShares'])
            ->final()
            ->orderByDesc('id')
            ->limit(self::ARCHIVE_EVERY)
            ->get();

        if ($batch->isEmpty()) {
            return;
        }

        $label = $batch->last()->invoice_number.'_'.$batch->first()->invoice_number;
        $path = $this->csvExport->writeTransactionsArchive($batch, $label);

        TransactionArchive::create([
            'archive_path' => $path,
            'transaction_count' => $batch->count(),
            'oldest_invoice' => $batch->last()->invoice_number,
            'newest_invoice' => $batch->first()->invoice_number,
        ]);
    }

    /**
     * Tentukan nominal terbayar dari payload.
     * - lunas   -> grand total penuh (abaikan input dp).
     * - dp      -> pakai paid_amount yang diinput kasir.
     * - lainnya -> 0 (belum bayar).
     */
    private function resolvePaidAmount(array $payload, float $grandTotal, string $paymentStatus): float
    {
        if ($paymentStatus === Transaction::PAY_LUNAS) {
            return round($grandTotal, 2);
        }

        if ($paymentStatus === Transaction::PAY_DP) {
            return round((float) ($payload['paid_amount'] ?? 0), 2);
        }

        return 0.0;
    }
}
