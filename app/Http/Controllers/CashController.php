<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cash\StoreCashMutationRequest;
use App\Models\CashMutation;
use App\Services\ActivityLogService;
use App\Services\CashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashController extends Controller
{
    public function __construct(
        private readonly CashService $cashService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): View
    {
        $mutations = CashMutation::with(['user', 'transaction'])
            ->when($request->filled('type') && $request->type !== 'semua', function ($query) use ($request) {
                $query->where('type', $request->string('type'));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $totalIn = (float) CashMutation::where('type', CashMutation::TYPE_IN)->sum('amount');
        $totalOut = (float) CashMutation::where('type', CashMutation::TYPE_OUT)->sum('amount');

        return view('cash.index', [
            'mutations' => $mutations,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'balance' => $totalIn - $totalOut,
            'typeFilter' => $request->string('type')->toString() ?: 'semua',
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
}
