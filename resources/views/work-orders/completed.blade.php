<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">Transaksi Selesai</h1>
            <a href="{{ route('work-orders.index') }}" class="btn-secondary shrink-0">← Work Order</a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif

        <form method="GET" class="bg-white border border-line rounded-md p-4 flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="from" :value="__('Dari Tanggal')" />
                <x-text-input id="from" name="from" type="date" class="mt-1 block" :value="$from->toDateString()" />
            </div>
            <div>
                <x-input-label for="to" :value="__('Sampai Tanggal')" />
                <x-text-input id="to" name="to" type="date" class="mt-1 block" :value="$to->toDateString()" />
            </div>
            <div class="flex-1 min-w-[200px]">
                <x-input-label for="q" :value="__('Cari')" />
                <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$q" placeholder="Plat / nama / invoice" />
            </div>
            <x-primary-button>Saring</x-primary-button>
            <a href="{{ route('work-orders.completed.export', request()->only(['from', 'to', 'q'])) }}"
               class="btn-secondary">Export CSV</a>
        </form>

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Invoice</th>
                        <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                        <th class="px-4 py-3 text-left font-semibold">Plat</th>
                        <th class="px-4 py-3 text-left font-semibold">Jenis Motor</th>
                        <th class="px-4 py-3 text-left font-semibold">Customer</th>
                        <th class="px-4 py-3 text-left font-semibold">Item</th>
                        <th class="px-4 py-3 text-right font-semibold">Total Dibayar</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($transactions as $t)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-ink-500">{{ $t->invoice_number }}</td>
                            <td class="px-4 py-2.5 text-ink-600 whitespace-nowrap">{{ $t->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5 font-semibold text-ink">{{ $t->plate_number }}</td>
                            <td class="px-4 py-2.5 text-ink-700">{{ $t->motor_type ?: '-' }}</td>
                            <td class="px-4 py-2.5 text-ink-700">{{ $t->customer_name ?: '-' }}</td>
                            <td class="px-4 py-2.5 text-ink-500 text-xs">
                                @foreach ($t->details as $d)
                                    <div>{{ $d->displayName() }} x{{ $d->qty }} — {{ number_format($d->line_total, 0, ',', '.') }}</div>
                                @endforeach
                                @foreach ($t->services as $s)
                                    <div>{{ $s->service_name }} — {{ number_format($s->service_price, 0, ',', '.') }}</div>
                                @endforeach
                            </td>
                            <td class="px-4 py-2.5 text-right font-num tabular font-semibold text-ink whitespace-nowrap">
                                Rp {{ number_format($t->grand_total, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <a href="{{ route('work-orders.show', $t) }}" class="btn-secondary px-3">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-ink-400">Belum ada transaksi selesai pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $transactions->links() }}
    </div>
</x-app-layout>