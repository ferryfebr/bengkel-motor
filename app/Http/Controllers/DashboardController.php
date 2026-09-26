<?php

namespace App\Http\Controllers;

use App\Models\CashMutation;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Tampilkan dashboard sesuai role user yang login.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $data = [
            'user' => $user,
            'todayTransactions' => Transaction::whereDate('created_at', Carbon::today())->count(),
            'ongoingWorkOrders' => Transaction::where('work_status', '!=', Transaction::WORK_SELESAI)->count(),
            'todayGross' => (float) Transaction::final()
                ->whereDate('created_at', Carbon::today())
                ->sum('grand_total'),
            // Semua kas keluar hari ini (manual, gaji, produk luar, refund) + rincian per kategori.
            'cashOutToday' => (float) CashMutation::where('type', CashMutation::TYPE_OUT)
                ->whereDate('created_at', Carbon::today())
                ->sum('amount'),
            'cashOutBreakdown' => CashMutation::where('type', CashMutation::TYPE_OUT)
                ->whereDate('created_at', Carbon::today())
                ->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')
                ->pluck('total', 'category'),
            'antreCount' => Transaction::where('work_status', Transaction::WORK_ANTRE)->count(),
            'prosesCount' => Transaction::where('work_status', Transaction::WORK_PROSES)->count(),
            'queuePreview' => Transaction::whereIn('work_status', [Transaction::WORK_ANTRE, Transaction::WORK_PROSES])
                ->orderByRaw("CASE work_status WHEN 'proses' THEN 0 ELSE 1 END")
                ->orderBy('created_at')
                ->limit(5)
                ->get(['id', 'plate_number', 'customer_name', 'work_status', 'created_at']),
        ];

        $view = match (true) {
            $user->isSuperAdmin() => 'dashboard.super-admin',
            $user->isOwner() => 'dashboard.owner',
            default => 'dashboard.kasir',
        };

        return view($view, $data);
    }
}
