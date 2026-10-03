<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Laporan Omset Bersih & Profit') }}</h1>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.net', ['period' => 'today']) }}" class="btn-secondary px-4 {{ $period === 'today' ? 'ring-2 ring-ink' : '' }}">Harian</a>
            <a href="{{ route('reports.net', ['period' => 'week']) }}" class="btn-secondary px-4 {{ $period === 'week' ? 'ring-2 ring-ink' : '' }}">Mingguan</a>
            <a href="{{ route('reports.net', ['period' => 'month']) }}" class="btn-secondary px-4 {{ $period === 'month' ? 'ring-2 ring-ink' : '' }}">Bulanan</a>
        </div>

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
               class="btn-secondary">Lihat Omset Kotor</a>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-stat-card label="Omset Kotor" value="Rp {{ number_format($summary['gross_revenue'], 0, ',', '.') }}" />
            <x-stat-card label="Omset Bersih" value="Rp {{ number_format($summary['net_revenue'], 0, ',', '.') }}" :tone="$summary['net_revenue'] < 0 ? 'danger' : 'success'" />
            <x-stat-card label="Transaksi Final" :value="number_format($summary['total_transactions'], 0, ',', '.')" />
        </div>

        {{-- Rincian perhitungan --}}
        <div class="bg-white border border-line rounded-md p-5">
            <div class="font-semibold text-ink mb-3">Rincian Perhitungan Omset Bersih</div>
            <table class="min-w-full font-condensed text-sm">
                <tbody class="divide-y divide-line">
                    <tr>
                        <td class="py-2 text-ink-700">Omset Kotor (penjualan)</td>
                        <td class="py-2 text-right tabular text-ink">Rp {{ number_format($summary['gross_revenue'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-ink-700 text-danger">− Refund</td>
                        <td class="py-2 text-right tabular text-danger">Rp {{ number_format($summary['refund_total'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-ink-700 text-danger">− HPP Produk (bersih setelah refund)</td>
                        <td class="py-2 text-right tabular text-danger">Rp {{ number_format($summary['cogs'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-ink-700 text-danger">− Gaji Karyawan (porsi mekanik)</td>
                        <td class="py-2 text-right tabular text-danger">Rp {{ number_format($summary['mechanic_fee'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="font-semibold text-ink">
                        <td class="py-3">= Omset Bersih</td>
                        <td class="py-3 text-right tabular text-xl">Rp {{ number_format($summary['net_revenue'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="text-xs text-ink-500 mt-2">Porsi bengkel dari jasa (Rp {{ number_format($summary['bengkel_fee'], 0, ',', '.') }}) sudah termasuk dalam omset bersih.</p>
        </div>

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