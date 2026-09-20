<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkStatusRequest;
use App\Models\Mechanic;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\ActivityLogService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Daftar Work Order (paralel). Bisa difilter status.
     */
    public function index(Request $request): View
    {
        $transactions = Transaction::with('cashier')
            ->when($request->filled('status') && $request->status !== 'semua', function ($query) use ($request) {
                $query->where('work_status', $request->string('status'));
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->string('q');
                $query->where(function ($sub) use ($q) {
                    $sub->where('plate_number', 'like', "%{$q}%")
                        ->orWhere('customer_name', 'like', "%{$q}%")
                        ->orWhere('invoice_number', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('work-orders.index', [
            'transactions' => $transactions,
            'statusFilter' => $request->string('status')->toString() ?: 'semua',
        ]);
    }

    public function create(): View
    {
        return view('work-orders.create');
    }

    /**
     * Buat Work Order baru. Tidak menghalangi WO lain yang belum selesai.
     */
    public function store(StoreWorkOrderRequest $request): RedirectResponse
    {
        $transaction = DB::transaction(function () use ($request) {
            return Transaction::create([
                'invoice_number' => $this->invoiceService->generate(),
                'cashier_id' => $request->user()->id,
                'impersonated_by' => $request->attributes->get('impersonated_by'),
                'customer_name' => $request->string('customer_name'),
                'plate_number' => strtoupper($request->string('plate_number')->toString()),
                'complaint' => $request->input('complaint'),
                'work_status' => Transaction::WORK_ANTRE,
                'payment_status' => Transaction::PAY_BELUM,
            ]);
        });

        $this->activityLog->log('create wo', $transaction, new: $transaction->only([
            'invoice_number', 'customer_name', 'plate_number', 'complaint', 'work_status',
        ]));

        return redirect()->route('work-orders.show', $transaction)
            ->with('status', 'Work Order berhasil dibuat.');
    }

    public function show(Request $request, Transaction $workOrder): View
    {
        $workOrder->load(['cashier', 'details.product', 'services.mechanic', 'services.shares.mechanic', 'mechanicShares.mechanic']);

        return view('work-orders.show', [
            'transaction' => $workOrder,
            'products' => Product::orderBy('name')->get(),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'mechanics' => Mechanic::active()->orderBy('name')->get(),
            'canViewHpp' => $request->user()->can('viewHpp', Product::class),
        ]);
    }

    /**
     * Ubah status pengerjaan (antre → proses → selesai) oleh kasir.
     * Urutan bebas: WO mana pun boleh diselesaikan lebih dulu.
     */
    public function updateStatus(UpdateWorkStatusRequest $request, Transaction $workOrder): RedirectResponse
    {
        // Transaksi yang sudah final tidak boleh diubah lagi (immutability).
        if ($workOrder->isFinal()) {
            return back()->with('error', 'Transaksi sudah final (lunas & selesai), tidak dapat diubah.');
        }

        $old = $workOrder->work_status;
        $workOrder->update(['work_status' => $request->string('work_status')]);

        $this->activityLog->log('update work_status', $workOrder, null,
            ['work_status' => $old],
            ['work_status' => $workOrder->work_status],
        );

        return redirect()->route('work-orders.show', $workOrder)
            ->with('status', 'Status pengerjaan diperbarui.');
    }

    /**
     * Queue Board - layar pantau antrean (read-only). Dapat diakses tanpa login.
     */
    public function queueBoard(): View
    {
        $ongoing = Transaction::where('work_status', '!=', Transaction::WORK_SELESAI)
            ->orderByRaw("CASE work_status WHEN 'proses' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->get(['id', 'invoice_number', 'plate_number', 'work_status', 'created_at']);

        return view('work-orders.queue-board', compact('ongoing'));
    }
}
