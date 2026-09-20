<?php

namespace App\Http\Controllers;

use App\Models\CashMutation;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\DataRetentionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DataRetentionService $retention) {}

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
            'lowStock' => Product::query()->lowStock()->count(),
            'cashBalance' => CashMutation::balance(),
        ];

        if ($user->hasRole('owner', 'super_admin')) {
            $data['diskPercent'] = $this->retention->diskUsagePercent();
            $data['diskWarning'] = $this->retention->diskUsageWarning();
            $data['finalCount'] = $this->retention->finalTransactionCount();
        }

        $view = match (true) {
            $user->isSuperAdmin() => 'dashboard.super-admin',
            $user->isOwner() => 'dashboard.owner',
            default => 'dashboard.kasir',
        };

        return view($view, $data);
    }
}