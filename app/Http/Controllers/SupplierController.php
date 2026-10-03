<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrder\StoreSupplierRequest;
use App\Models\Supplier;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function index(): View
    {
        $suppliers = Supplier::withCount('purchaseOrders')->orderBy('name')->paginate(20);

        return view('suppliers.index', compact('suppliers'));
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        $this->activityLog->log('create supplier', $supplier, new: $supplier->only(['name', 'phone']));

        return back()->with('status', 'Distributor "'.$supplier->name.'" ditambahkan.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->activityLog->log('delete supplier', $supplier, old: ['name' => $supplier->name]);

        $supplier->delete();

        return back()->with('status', 'Distributor dihapus.');
    }
}
