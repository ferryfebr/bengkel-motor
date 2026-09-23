<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payroll\PayoutRequest;
use App\Models\Mechanic;
use App\Models\MechanicPayout;
use App\Models\TransactionMechanicShare;
use App\Services\ActivityLogService;
use App\Services\MechanicPayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(
        private readonly MechanicPayrollService $payroll,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $activeOnly = true;

        $mechanics = Mechanic::query()
            ->when($activeOnly, fn ($q) => $q->active())
            ->orderBy('name')
            ->get()
            ->map(function (Mechanic $m) use ($from, $to) {
                return [
                    'mechanic' => $m,
                    'earned' => $this->payroll->earned($m->id, $from, $to),
                    'withdrawn' => $this->payroll->withdrawn($m->id),
                    'balance' => $this->payroll->balance($m->id),
                ];
            });

        return view('payroll.index', [
            'rows' => $mechanics,
            'from' => $from,
            'to' => $to,
            'period' => $request->string('period')->toString(),
            'totalEarned' => $mechanics->sum('earned'),
            'totalBalance' => $mechanics->sum('balance'),
        ]);
    }

    public function show(Request $request, Mechanic $mechanic): View
    {
        [$from, $to] = $this->range($request);

        $shares = TransactionMechanicShare::with('transaction')
            ->where('mechanic_id', $mechanic->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get();

        $payouts = MechanicPayout::with('user')
            ->where('mechanic_id', $mechanic->id)
            ->orderByDesc('created_at')
            ->get();

        return view('payroll.show', [
            'mechanic' => $mechanic,
            'shares' => $shares,
            'payouts' => $payouts,
            'earned' => $this->payroll->earned($mechanic->id, $from, $to),
            'earnedAll' => $this->payroll->earned($mechanic->id),
            'withdrawn' => $this->payroll->withdrawn($mechanic->id),
            'balance' => $this->payroll->balance($mechanic->id),
            'from' => $from,
            'to' => $to,
            'period' => $request->string('period')->toString(),
        ]);
    }

    public function payout(PayoutRequest $request, Mechanic $mechanic): RedirectResponse
    {
        $this->payroll->payout(
            $mechanic,
            (float) $request->input('amount'),
            $request->input('description'),
            $request->user(),
            $request->attributes->get('impersonated_by'),
        );

        $this->activityLog->log('mechanic payout', $mechanic, new: [
            'amount' => (float) $request->input('amount'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('payroll.show', $mechanic)
            ->with('status', 'Penarikan gaji mekanik dicatat.');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $period = $request->string('period')->toString();
        if ($period === 'today') {
            return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
        }
        if ($period === 'week') {
            return [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfWeek()->endOfDay()];
        }
        if ($period === 'month') {
            return [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfMonth()->endOfDay()];
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : Carbon::now()->endOfMonth()->endOfDay();

        return [$from, $to];
    }
}
