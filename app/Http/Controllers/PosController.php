<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pos\SavePosDraftRequest;
use App\Http\Requests\Pos\StorePosCheckoutRequest;
use App\Models\Mechanic;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\ActivityLogService;
use App\Services\TransactionService as TransactionServiceEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PosController extends Controller
{
    public function __construct(
        private readonly TransactionServiceEngine $engine,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Layar POS untuk satu transaksi/Work Order.
     */
    public function show(Request $request, Transaction $transaction): View
    {
        $transaction->load(['details.product', 'services.shares.mechanic']);

        return view('pos.show', [
            'transaction' => $transaction,
            'products' => Product::orderBy('name')->get(),
            'mechanics' => Mechanic::active()->orderBy('name')->get(),
            'canViewHpp' => $request->user()->can('viewHpp', Product::class),
        ]);
    }

    /**
     * Simpan transaksi sebagai draft (isi nota tanpa potong stok / kas keluar).
     */
    public function saveDraft(SavePosDraftRequest $request, Transaction $transaction): RedirectResponse
    {
        $before = $this->cartSnapshot($transaction);

        try {
            $this->engine->saveDraft($transaction, $request->validated(), $request->user(), $request->attributes->get('impersonated_by'));
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Activity: catat perubahan isi keranjang draft (tambah/hapus item).
        $after = $this->cartSnapshot($transaction->fresh());

        $this->activityLog->log('update draft', Transaction::class, $transaction->id,
            old: ['items' => $before],
            new: ['items' => $after],
        );

        return back()->with('status', 'Draft transaksi tersimpan. Stok & kas belum berubah.');
    }

    /**
     * Ringkasan isi nota (untuk diff aktivitas keranjang).
     *
     * @return array<int, string>
     */
    private function cartSnapshot(Transaction $transaction): array
    {
        $transaction->loadMissing(['details.product', 'services.shares']);

        $items = [];

        foreach ($transaction->details as $d) {
            $name = $d->is_external ? $d->external_name : ($d->product?->name ?? 'Produk #'.$d->product_id);
            $items[] = ($d->is_external ? 'Luar' : 'Produk').': '.$name.' x'.$d->qty;
        }

        foreach ($transaction->services as $s) {
            $items[] = 'Jasa: '.$s->service_name.' ('.$s->shares->count().' mekanik)';
        }

        return $items;
    }

    /**
     * Checkout transaksi (isi rincian + kurangi stok + kas keluar produk luar).
     */
    public function checkout(StorePosCheckoutRequest $request, Transaction $transaction): RedirectResponse
    {
        try {
            $fresh = $this->engine->checkout(
                $transaction,
                $request->validated(),
                $request->user(),
                $request->attributes->get('impersonated_by'),
            );
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Activity: finalisasi transaksi + snapshot isi nota.
        $this->activityLog->log('checkout', Transaction::class, $fresh->id, new: [
            'items' => $this->cartSnapshot($fresh),
            'grand_total' => (float) $fresh->grand_total,
            'paid_amount' => (float) $fresh->paid_amount,
            'payment_status' => $fresh->payment_status,
            'work_status' => $fresh->work_status,
        ]);

        // Activity: tiap produk luar dicatat terpisah (RINGKASAN §4.1).
        foreach ($request->validated()['external_products'] ?? [] as $external) {
            $this->activityLog->log('create external_product', Transaction::class, $fresh->id,
                new: [
                    'name' => $external['name'],
                    'purchase_price' => $external['purchase_price'],
                    'selling_price' => $external['selling_price'],
                ],
            );
        }

        return redirect()->route('pos.receipt', $fresh)
            ->with('status', 'Transaksi berhasil diselesaikan.');
    }

    /**
     * Struk - layout thermal 58/80mm, print via browser (window.print).
     */
    public function receipt(Transaction $transaction): View
    {
        $transaction->load(['cashier', 'details.product', 'services.shares.mechanic']);

        return view('pos.receipt', compact('transaction'));
    }
}
