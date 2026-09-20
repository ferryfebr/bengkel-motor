<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">
                <span class="hidden sm:inline">Work Order / Antrean Servis</span>
                <span class="sm:hidden">Work Order</span>
            </h1>
            <div class="flex items-center gap-2">
                <a href="{{ route('queue-board') }}" target="_blank"
                   class="btn-secondary hidden sm:inline-flex">
                    Queue Board
                </a>
                <a href="{{ route('work-orders.create') }}"
                   class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                    Motor Masuk
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif

        <form method="GET" class="bg-white border border-line rounded-md p-4 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <x-input-label for="q" :value="__('Cari')" />
                <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="request('q')" placeholder="Plat / nama / invoice" />
            </div>
            <div>
                <x-input-label for="status" :value="__('Status Pengerjaan')" />
                <select id="status" name="status" class="mt-1 border-line rounded-md focus:border-signal focus:ring-signal min-h-[44px]">
                    @foreach (['semua' => 'Semua', 'antre' => 'Antre', 'proses' => 'Proses', 'selesai' => 'Selesai'] as $val => $label)
                        <option value="{{ $val }}" @selected($statusFilter === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-primary-button>Saring</x-primary-button>
        </form>

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Invoice</th>
                        <th class="px-4 py-3 text-left font-semibold">Plat</th>
                        <th class="px-4 py-3 text-left font-semibold">Customer</th>
                        <th class="px-4 py-3 text-left font-semibold">Keluhan</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                        <th class="px-4 py-3 text-center font-semibold">Bayar</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($transactions as $t)
                        <tr class="{{ $t->isFinal() ? 'bg-paper-dim/60' : 'hover:bg-paper-dim/60' }} transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-ink-500">{{ $t->invoice_number }}</td>
                            <td class="px-4 py-2.5 font-semibold text-ink">{{ $t->plate_number }}</td>
                            <td class="px-4 py-2.5 text-ink-700">{{ $t->customer_name }}</td>
                            <td class="px-4 py-2.5 text-ink-400 max-w-xs truncate">{{ $t->complaint ?: '-' }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @php
                                    $wBadge = match($t->work_status) {
                                        'selesai' => 'bg-success-light text-success',
                                        'proses' => 'bg-signal-100 text-ink-700',
                                        default => 'bg-paper-dim text-ink-600',
                                    };
                                    $wIcon = match($t->work_status) {
                                        'selesai' => '✅',
                                        'proses' => '🔧',
                                        default => '🟡',
                                    };
                                    $wLabel = match($t->work_status) {
                                        'selesai' => 'Selesai',
                                        'proses' => 'Dikerjakan',
                                        default => 'Antre',
                                    };
                                @endphp
                                <span class="badge {{ $wBadge }}">{{ $wIcon }} {{ $wLabel }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @php
                                    $pBadge = match($t->payment_status) {
                                        'lunas' => 'bg-success-light text-success',
                                        'dp' => 'bg-signal-100 text-ink-700',
                                        default => 'bg-danger-light text-danger',
                                    };
                                    $pIcon = match($t->payment_status) {
                                        'lunas' => '✅',
                                        'dp' => '🟡',
                                        default => '⏳',
                                    };
                                    $pLabel = match($t->payment_status) {
                                        'lunas' => 'Lunas',
                                        'dp' => 'DP',
                                        default => 'Belum Bayar',
                                    };
                                @endphp
                                <span class="badge {{ $pBadge }}">{{ $pIcon }} {{ $pLabel }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <a href="{{ route('work-orders.show', $t) }}" class="font-medium text-ink hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-ink-400">Belum ada Work Order.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $transactions->links() }}
    </div>
</x-app-layout>