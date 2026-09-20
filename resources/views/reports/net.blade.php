<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Laporan Omset Bersih & Profit') }}</h1>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif

        <form method="GET" class="bg-white border border-line rounded-md p-4 flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="from" value="Dari" />
                <x-text-input id="from" name="from" type="date" class="mt-1" :value="$from->toDateString()" />
            </div>
            <div>
                <x-input-label for="to" value="Sampai" />
                <x-text-input id="to" name="to" type="date" class="mt-1" :value="$to->toDateString()" />
            </div>
            <x-primary-button>Tampilkan</x-primary-button>
            <a href="{{ route('reports.gross', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
               class="text-sm font-medium text-ink-500 hover:text-ink hover:underline">Lihat Omset Kotor</a>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-stat-card label="Omset Kotor" value="Rp {{ number_format($summary['gross_revenue'], 0, ',', '.') }}" />
            <x-stat-card label="Omset Bersih" value="Rp {{ number_format($summary['net_revenue'], 0, ',', '.') }}" :tone="$summary['net_revenue'] < 0 ? 'danger' : 'success'" />
            <x-stat-card label="Transaksi Final" :value="number_format($summary['total_transactions'], 0, ',', '.')" />
        </div>

        <p class="text-xs text-ink-500">
            Omset Bersih = (penjualan produk − HPP produk) + porsi bengkel dari jasa.
        </p>

        <div class="bg-white border border-line rounded-md overflow-hidden">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                        <th class="px-4 py-3 text-right font-semibold">Transaksi</th>
                        <th class="px-4 py-3 text-right font-semibold">Omset Kotor</th>
                        <th class="px-4 py-3 text-right font-semibold">Omset Bersih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($series as $row)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 text-ink-700">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-700">{{ $row['total_transactions'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">Rp {{ number_format($row['gross_revenue'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right tabular font-medium text-ink">Rp {{ number_format($row['net_revenue'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-ink-400">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('reports.rebuild-summaries', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">
            @csrf
            <x-secondary-button type="submit">Bangun Ulang Ringkasan Harian</x-secondary-button>
        </form>
    </div>
</x-app-layout>