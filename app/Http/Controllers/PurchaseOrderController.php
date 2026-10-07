<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\ActivityLogService;
use App\Services\PurchaseOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): View
    {
        $orders = PurchaseOrder::with(['supplier', 'user'])
            ->when($request->filled('status') && $request->status !== 'semua', fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(function ($sub) use ($term) {
                    $sub->where('po_number', 'like', "%{$term}%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('purchase-orders.index', [
            'orders' => $orders,
            'statusFilter' => $request->string('status')->toString() ?: 'semua',
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function create(): View
    {
        return view('purchase-orders.create', [
            'products' => Product::orderBy('name')->get(['id', 'code_sku', 'name', 'purchase_price', 'stock']),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        try {
            $order = $this->service->create($request->validated(), $request->user());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->activityLog->log('create po', $order, new: [
            'po_number' => $order->po_number,
            'total' => (float) $order->total,
            'items_count' => $order->items()->count(),
        ]);

        return redirect()->route('purchase-orders.show', $order)
            ->with('status', 'Pesanan pembelian '.$order->po_number.' dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['items.product', 'supplier', 'user', 'receiver']);

        return view('purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function pdf(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['items', 'supplier', 'user']);

        $pdf = Pdf::loadView('purchase-orders.pdf', ['order' => $purchaseOrder])
            ->setPaper('a4');

        return $pdf->download($purchaseOrder->po_number.'.pdf');
    }

    /**
     * Halaman cetak (HTML) - memakai ulang view PDF, tambah tombol cetak.
     * Browser dapat langsung mencetak atau "Simpan sebagai PDF".
     */
    public function print(PurchaseOrder $purchaseOrder): \Illuminate\Http\Response
    {
        $purchaseOrder->load(['items', 'supplier', 'user']);

        $html = view('purchase-orders.pdf', ['order' => $purchaseOrder])->render();

        $head = '<style>
            body { background: #e5e7eb; }
            .po-toolbar {
                position: sticky; top: 0; z-index: 10;
                display: flex; gap: 10px; justify-content: center; align-items: center;
                padding: 14px; background: rgba(255,255,255,0.92);
                border-bottom: 1px solid #d1d5db; backdrop-filter: blur(6px);
                font-family: "Barlow", system-ui, sans-serif;
            }
            .po-toolbar .btn {
                display: inline-flex; align-items: center; gap: 8px;
                padding: 10px 20px; border-radius: 8px; font-size: 14px;
                font-weight: 600; text-decoration: none; cursor: pointer;
                border: 1px solid transparent; transition: background .15s, box-shadow .15s;
            }
            .po-toolbar .btn-print { background: #111; color: #fff; }
            .po-toolbar .btn-print:hover { background: #333; box-shadow: 0 4px 12px rgba(0,0,0,.18); }
            .po-toolbar .btn-back { background: #fff; color: #111; border-color: #d1d5db; }
            .po-toolbar .btn-back:hover { background: #f3f4f6; }
            .po-scroll { padding: 28px 16px 56px; }
            .po-paper {
                position: relative; width: 210mm; min-height: 297mm; max-width: 100%;
                margin: 0 auto; background: #fff; box-sizing: border-box;
                padding: 16mm 14mm; box-shadow: 0 6px 24px rgba(0,0,0,.14); border-radius: 2px;
            }
            @media print {
                body { background: #fff !important; }
                .no-print { display: none !important; }
                .po-scroll { padding: 0 !important; }
                .po-paper { width: auto !important; min-height: 0 !important; box-shadow: none !important; border-radius: 0 !important; padding: 0 !important; margin: 0 !important; }
                @page { margin: 12mm; }
            }
        </style>';

        $open = '<div class="po-toolbar no-print">'
            .'<button type="button" class="btn btn-print" onclick="window.print()">Cetak / Simpan PDF</button>'
            .'<a class="btn btn-back" href="'.e(route('purchase-orders.pdf', $purchaseOrder)).'">Unduh PDF</a>'
            .'<a class="btn btn-back" href="'.e(route('purchase-orders.show', $purchaseOrder)).'">Kembali</a>'
            .'</div><div class="po-scroll"><div class="po-paper">';

        $html = str_replace('</head>', $head.'</head>', $html);
        $html = preg_replace('/<body([^>]*)>/', '<body$1>'.$open, $html, 1);
        $html = str_replace('</body>', '</div></div></body>', $html);

        return response($html);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $order = $this->service->receive($purchaseOrder, $request->user());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->activityLog->log('receive po', $order, new: [
            'po_number' => $order->po_number,
            'items_count' => $order->items()->count(),
        ]);

        return redirect()->route('purchase-orders.show', $order)
            ->with('status', 'Barang diterima. Stok sudah ditambahkan.');
    }

    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,dikirim'],
        ]);

        if ($purchaseOrder->isReceived() || $purchaseOrder->isCancelled()) {
            return back()->with('error', 'PO yang sudah diterima/dibatalkan tidak bisa diubah statusnya.');
        }

        $this->service->updateStatus($purchaseOrder, $validated['status']);

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('status', 'Status PO diperbarui.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $order = $this->service->cancel($purchaseOrder);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->activityLog->log('cancel po', $order, new: ['po_number' => $order->po_number]);

        return redirect()->route('purchase-orders.show', $order)
            ->with('status', 'PO '.$order->po_number.' dibatalkan.');
    }
}
