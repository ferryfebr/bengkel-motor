<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Laporan Omset Kotor') }}</h1>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.gross', ['period' => 'today']) }}" class="btn-secondary px-4 {{ $period === 'today' ? 'ring-2 ring-ink' : '' }}">Harian</a>
            <a href="{{ route('reports.gross', ['period' => 'week']) }}" class="btn-secondary px-4 {{ $period === 'week' ? 'ring-2 ring-ink' : '' }}">Mingguan</a>
            <a href="{{ route('reports.gross', ['period' => 'month']) }}" class="btn-secondary px-4 {{ $period === 'month' ? 'ring-2 ring-ink' : '' }}">Bulanan</a>
        </div>

        <form method="GET" class="bg-white border border-line rounded-md p-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="period" value="">
            <div>
                <x-input-label for="from" value="Dari" />
                <x-text-input id="from" name="from" type="date" class="mt-1" :value="$from->toDateString()" />
            </div>
            <div>
                <x-input-label for="to" value="Sampai" />
                <x-text-input id="to" name="to" type="date" class="mt-1" :value="$to->toDateString()" />
            </div>
            <x-primary-button>Tampilkan</x-primary-button>
            <a href="{{ route('reports.export-transactions', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
               class="btn-secondary">Export CSV Transaksi</a>
            @if ($canViewNet)
                <a href="{{ route('reports.net', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                   class="btn-secondary">Lihat Omset Bersih</a>
            @endif
        </form>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-stat-card label="Omset Kotor" value="Rp {{ number_format($summary['gross_revenue'], 0, ',', '.') }}" />
            <x-stat-card label="Jumlah Transaksi Final" :value="number_format($summary['total_transactions'], 0, ',', '.')" />
            <div class="bg-white border border-line rounded-md p-5">
                <div class="text-[13px] font-medium text-ink-500">Kas Masuk / Keluar</div>
                <div class="font-num tabular text-xl font-bold text-success mt-2">Rp {{ number_format($summary['total_cash_in'], 0, ',', '.') }}</div>
                <div class="font-num tabular text-sm text-danger mt-1">Rp {{ number_format($summary['total_cash_out'], 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-white border border-line rounded-md overflow-hidden">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                        <th class="px-4 py-3 text-right font-semibold">Transaksi</th>
                        <th class="px-4 py-3 text-right font-semibold">Omset Kotor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($series as $row)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 text-ink-700">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-700">{{ $row['total_transactions'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular font-medium text-ink">Rp {{ number_format($row['gross_revenue'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-ink-400">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>