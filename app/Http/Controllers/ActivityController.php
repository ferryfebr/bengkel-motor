<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CashMutation;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ActivityPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ActivityController extends Controller
{
    /**
     * Daftar aktivitas (owner & super_admin), dipisah per kategori.
     */
    public function index(Request $request): View
    {
        $category = $request->string('category')->toString() ?: 'transaksi';
        if (! array_key_exists($category, ActivityPresenter::CATEGORIES)) {
            $category = 'transaksi';
        }

        [$from, $to] = $this->range($request);

        $transaksiActions = ['create wo', 'update work_status', 'update draft', 'checkout', 'create external_product'];

        if ($category === 'transaksi') {
            // Satu baris per transaksi; rincian aktivitas dilihat di halaman detail.
            $transactions = Transaction::query()
                ->whereIn('id', function ($q) use ($from, $to, $request, $transaksiActions) {
                    $q->select('model_id')
                        ->from('activity_logs')
                        ->where('model_type', Transaction::class)
                        ->whereIn('action', $transaksiActions)
                        ->whereBetween('created_at', [$from, $to])
                        ->when($request->filled('user_id'), fn ($sub) => $sub->where('user_id', $request->integer('user_id')))
                        ->distinct();
                })
                ->when($request->filled('q'), function ($q) use ($request) {
                    $qq = $request->string('q');
                    $q->where(function ($sub) use ($qq) {
                        $sub->where('invoice_number', 'like', "%{$qq}%")
                            ->orWhere('customer_name', 'like', "%{$qq}%")
                            ->orWhere('plate_number', 'like', "%{$qq}%");
                    });
                })
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString();

            $logs = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        } else {
            $transactions = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);

            $logs = ActivityLog::with('user')
                ->whereBetween('created_at', [$from, $to])
                ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $q->where('action', 'like', '%'.$request->string('q').'%');
                })
                ->where(fn (Builder $q) => $this->applyCategory($q, $category))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString();
        }

        return view('activity.index', [
            'logs' => $logs,
            'transactions' => $transactions,
            'category' => $category,
            'categories' => ActivityPresenter::CATEGORIES,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'userId' => $request->integer('user_id') ?: null,
            'q' => $request->string('q')->toString(),
            'from' => $from,
            'to' => $to,
            'period' => $request->string('period')->toString(),
        ]);
    }

    /**
     * Detail aktivitas satu transaksi: timeline proses + aktor, bahasa manusia.
     */
    public function show(Transaction $transaction): View
    {
        $transaction->load(['cashier', 'details.product', 'services.shares.mechanic']);

        $logs = ActivityLog::with('user')
            ->where('model_type', $transaction->getMorphClass())
            ->where('model_id', $transaction->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $stockHistories = StockHistory::with(['product', 'user'])
            ->where('transaction_id', $transaction->id)
            ->orderBy('created_at')
            ->get();

        $cashMutations = CashMutation::with('user')
            ->where('transaction_id', $transaction->id)
            ->orderBy('created_at')
            ->get();

        $timeline = collect();

        foreach ($logs as $log) {
            $detail = '';
            $old = $log->old_values['items'] ?? null;
            $new = $log->new_values['items'] ?? null;

            if (is_array($old) || is_array($new)) {
                $added = array_values(array_diff($new ?? [], $old ?? []));
                $removed = array_values(array_diff($old ?? [], $new ?? []));
                $parts = [];
                foreach ($added as $a) {
                    $parts[] = 'Menambah: '.$a;
                }
                foreach ($removed as $r) {
                    $parts[] = 'Menghapus: '.$r;
                }
                $detail = implode("\n", $parts);
            }

            $timeline->push([
                'time' => $log->created_at,
                'actor' => $log->user?->name ?? '-',
                'impersonated_by' => $log->impersonated_by,
                'title' => ActivityPresenter::label($log->action),
                'detail' => trim(ActivityPresenter::describe($log)."\n".$detail),
                'kind' => 'log',
            ]);
        }

        foreach ($stockHistories as $h) {
            $timeline->push([
                'time' => $h->created_at,
                'actor' => $h->user?->name ?? '-',
                'impersonated_by' => null,
                'title' => 'Perubahan stok',
                'detail' => ($h->product?->name ?? 'Produk').': '.(($h->qty_change > 0) ? '+' : '').$h->qty_change,
                'kind' => 'stock',
            ]);
        }

        foreach ($cashMutations as $m) {
            $timeline->push([
                'time' => $m->created_at,
                'actor' => $m->user?->name ?? '-',
                'impersonated_by' => null,
                'title' => 'Kas '.($m->type === 'in' ? 'masuk' : 'keluar'),
                'detail' => 'Rp '.number_format((float) $m->amount, 0, ',', '.').' - '.$m->description,
                'kind' => 'cash',
            ]);
        }

        $timeline = $timeline->sortBy('time')->values();

        return view('activity.show', compact('transaction', 'timeline'));
    }

    private function applyCategory(Builder $query, string $category): void
    {
        match ($category) {
            'transaksi' => $query->whereIn('action', ['create wo', 'update work_status', 'update draft', 'checkout', 'create external_product']),
            'kas' => $query->whereIn('action', ['create cash_mutation', 'withdraw cash']),
            'akun' => $query->where(function ($q) {
                $q->whereIn('action', ['create kasir', 'update kasir', 'delete kasir'])
                    ->orWhere('action', 'like', 'impersonate%');
            }),
            'mekanik' => $query->where(function ($q) {
                $q->where('action', 'update mechanic_ratio')
                    ->orWhere('model_type', \App\Models\Mechanic::class);
            }),
            'produk' => $query->where(function ($q) {
                $q->whereIn('model_type', [\App\Models\Product::class, \App\Models\Category::class])
                    ->orWhereIn('action', ['update stock', 'update product_price', 'update product_hpp']);
            }),
            default => $query->whereNotIn('action', [
                'create wo', 'update work_status', 'update draft', 'checkout', 'create external_product',
                'create cash_mutation', 'withdraw cash', 'create kasir', 'update kasir', 'delete kasir',
                'update mechanic_ratio', 'update stock', 'update product_price', 'update product_hpp',
            ])->whereNotIn('model_type', [\App\Models\Product::class, \App\Models\Category::class, \App\Models\Mechanic::class]),
        };
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

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : Carbon::today()->startOfDay();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : Carbon::today()->endOfDay();

        return [$from, $to];
    }
}