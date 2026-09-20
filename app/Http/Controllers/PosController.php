<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pos\SavePosDraftRequest;
use App\Http\Requests\Pos\StorePosCheckoutRequest;
use App\Models\Mechanic;
use App\Models\Product;
use App\Models\Service;
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
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'mechanics' => Mechanic::active()->orderBy('name')->get(),
            'canViewHpp' => $request->user()->can('viewHpp', Product::class),
        ]);
    }

    /**
     * Simpan transaksi sebagai draft (isi nota tanpa potong stok / kas keluar).
     */
    public function saveDraft(SavePosDraftRequest $request, Transaction $transaction): RedirectResponse
    {
        try {
            $this->engine->saveDraft($transaction, $request->validated(), $request->user());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Draft transaksi tersimpan. Stok & kas belum berubah.');
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

        if ($request->boolean('print_receipt')) {
            return redirect()->route('pos.receipt', $fresh);
        }

        return redirect()->route('work-orders.show', $fresh)
            ->with('status', 'Transaksi berhasil diproses.');
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
