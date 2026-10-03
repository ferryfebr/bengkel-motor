<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">Pesanan Pembelian (PO)</h1>
            <div class="flex gap-2 shrink-0">
                <a href="{{ route('suppliers.index') }}" class="btn-secondary">Distributor</a>
                <a href="{{ route('purchase-orders.create') }}" class="btn-primary">+ Buat PO</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ session('error') }}</div>
        @endif

        <form method="GET" class="bg-white border border-line rounded-md p-4 flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]" onchange="this.form.submit()">
                    @foreach (['semua' => 'Semua', 'draft' => 'Draft', 'dikirim' => 'Dikirim', 'diterima' => 'Diterima', 'dibatalkan' => 'Dibatalkan'] as $val => $label)
                        <option value="{{ $val }}" @selected($statusFilter === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <x-input-label for="q" value="Cari" />
                <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$q" placeholder="No PO / nama distributor" />
            </div>
            <x-primary-button>Saring</x-primary-button>
        </form>

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">No PO</th>
                        <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                        <th class="px-4 py-3 text-left font-semibold">Distributor</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Total</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-ink-600">{{ $order->po_number }}</td>
                            <td class="px-4 py-2.5 text-ink-600 whitespace-nowrap">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5 text-ink">{{ $order->supplier?->name ?? '-' }}</td>
                            <td class="px-4 py-2.5">
                                @php
                                    [$cls, $lbl] = match ($order->status) {
                                        'diterima' => ['bg-success-light text-success', 'Diterima'],
                                        'dikirim' => ['bg-paper-dim text-ink-700', 'Dikirim'],
                                        'dibatalkan' => ['bg-paper-dim text-ink-500', 'Dibatalkan'],
                                        default => ['bg-danger-light text-danger', 'Draft'],
                                    };
                                @endphp
                                <span class="badge {{ $cls }}">{{ $lbl }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <a href="{{ route('purchase-orders.show', $order) }}" class="btn-secondary px-3">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-ink-400">Belum ada pesanan pembelian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $orders->links() }}
    </div>
</x-app-layout>