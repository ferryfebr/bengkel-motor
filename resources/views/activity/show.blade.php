<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">
                Aktivitas Transaksi — {{ $transaction->plate_number ?: $transaction->invoice_number }}
            </h1>
            <a href="{{ route('activity.index') }}" class="btn-secondary shrink-0">← Aktivitas</a>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-4">
        <div class="bg-paper border border-line rounded-md p-6 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div><span class="text-ink-soft">Invoice:</span> <span class="font-mono text-ink">{{ $transaction->invoice_number }}</span></div>
            <div><span class="text-ink-soft">Kasir:</span> <span class="text-ink">{{ $transaction->cashier?->name }}</span></div>
            <div><span class="text-ink-soft">Plat:</span> <span class="font-semibold text-ink">{{ $transaction->plate_number }}</span></div>
            <div><span class="text-ink-soft">Jenis Motor:</span> <span class="text-ink">{{ $transaction->motor_type ?: '-' }}</span></div>
            <div><span class="text-ink-soft">Customer:</span> <span class="text-ink">{{ $transaction->customer_name }}</span></div>
            <div><span class="text-ink-soft">Dibuat:</span> <span class="text-ink">{{ $transaction->created_at->format('d/m/Y H:i') }}</span></div>
            <div>
                <span class="text-ink-soft">Status:</span>
                <span class="badge bg-paper-dim text-ink-700">{{ $transaction->work_status }} / {{ $transaction->payment_status }}</span>
            </div>
        </div>

        <div class="bg-paper border border-line rounded-md p-6">
            <h3 class="font-semibold text-ink mb-4">Timeline Proses</h3>

            @forelse ($timeline as $ev)
                <div class="flex gap-3 pb-4 {{ ! $loop->last ? 'border-l-2 border-line ml-2 pl-4' : 'ml-2 pl-4' }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="badge
                                @class([
                                    'bg-paper-dim text-ink-700' => $ev['kind'] === 'log',
                                    'bg-ink text-paper' => $ev['kind'] === 'stock',
                                    'bg-success-light text-success' => $ev['kind'] === 'cash',
                                ])">{{ $ev['title'] }}</span>
                            <span class="text-xs text-ink-400">{{ $ev['time']?->format('d/m/Y H:i:s') }}</span>
                            <span class="text-xs text-ink-500">oleh <strong class="text-ink">{{ $ev['actor'] }}</strong>
                                @if ($ev['impersonated_by'])
                                    <span class="text-ink-400">(impersonation #{{ $ev['impersonated_by'] }})</span>
                                @endif
                            </span>
                        </div>
                        @if ($ev['detail'])
                            <pre class="mt-1 text-xs text-ink-600 whitespace-pre-wrap font-sans">{{ $ev['detail'] }}</pre>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-ink-400">Belum ada aktivitas untuk transaksi ini.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>