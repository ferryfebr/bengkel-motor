<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cash\StoreCashMutationRequest;
use App\Http\Requests\Cash\WithdrawCashRequest;
use App\Models\CashMutation;
use App\Services\ActivityLogService;
use App\Services\CashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CashController extends Controller
{
    public function __construct(
        private readonly CashService $cashService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $isManager = $user->hasRole('owner', 'super_admin');

        [$from, $to] = $this->range($request, $isManager);

        $base = CashMutation::query()->whereBetween('created_at', [$from, $to]);

        $totalIn = (float) (clone $base)->where('type', CashMutation::TYPE_IN)->sum('amount');
        $totalOut = (float) (clone $base)->where('type', CashMutation::TYPE_OUT)->sum('amount');

        $mutations = (clone $base)->with(['user', 'transaction'])
            ->when($request->filled('type') && $request->type !== 'semua', function ($query) use ($request) {
                $query->where('type', $request->string('type'));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Kasir: saldo = net hari ini (reset harian). Owner: saldo keseluruhan.
        $balance = $isManager ? CashMutation::balance() : round($totalIn - $totalOut, 2);

        return view('cash.index', [
            'mutations' => $mutations,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'balance' => $balance,
            'balanceAllTime' => CashMutation::balance(),
            'typeFilter' => $request->string('type')->toString() ?: 'semua',
            'from' => $from,
            'to' => $to,
            'isManager' => $isManager,
            'period' => $request->string('period')->toString(),
        ]);
    }

    public function store(StoreCashMutationRequest $request): RedirectResponse
    {
        $mutation = $this->cashService->record(
            $request->string('type'),
            (float) $request->input('amount'),
            $request->input('description'),
            $request->user(),
            null,
            $request->attributes->get('impersonated_by'),
        );

        $this->activityLog->log('create cash_mutation', $mutation, new: $mutation->only([
            'type', 'amount', 'description', 'transaction_id',
        ]));

        return redirect()->route('cash.index')->with('status', 'Mutasi kas dicatat.');
    }

    /**
     * Penarikan kas oleh owner. Dicatat sebagai kas keluar.
     */
    public function withdraw(WithdrawCashRequest $request): RedirectResponse
    {
        $description = trim('Penarikan owner'.($request->filled('description') ? ' - '.$request->input('description') : ''));

        $mutation = $this->cashService->record(
            CashMutation::TYPE_OUT,
            (float) $request->input('amount'),
            $description,
            $request->user(),
            null,
            $request->attributes->get('impersonated_by'),
        );

        $this->activityLog->log('withdraw cash', $mutation, new: $mutation->only([
            'type', 'amount', 'description',
        ]));

        return redirect()->route('cash.index')->with('status', 'Penarikan kas berhasil dicatat.');
    }

    /**
     * Rentang tanggal: kasir dipaksa hari ini; owner bisa filter atau pakai shortcut.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request, bool $isManager): array
    {
        if (! $isManager) {
            return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
        }

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

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : Carbon::today()->startOfDay();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : Carbon::today()->endOfDay();

        return [$from, $to];
    }
}