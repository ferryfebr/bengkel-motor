<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Gaji Karyawan (Mekanik)') }}</h1>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('payroll.index', ['period' => 'today']) }}" class="btn-secondary px-4 {{ $period === 'today' ? 'ring-2 ring-ink' : '' }}">Harian</a>
            <a href="{{ route('payroll.index', ['period' => 'week']) }}" class="btn-secondary px-4 {{ $period === 'week' ? 'ring-2 ring-ink' : '' }}">Mingguan</a>
            <a href="{{ route('payroll.index', ['period' => 'month']) }}" class="btn-secondary px-4 {{ $period === 'month' ? 'ring-2 ring-ink' : '' }}">Bulanan</a>
        </div>

        <form method="GET" class="bg-white border border-line rounded-md p-4 flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="from" value="Dari Tanggal" />
                <x-text-input id="from" name="from" type="date" class="mt-1 block" :value="$from->toDateString()" />
            </div>
            <div>
                <x-input-label for="to" value="Sampai Tanggal" />
                <x-text-input id="to" name="to" type="date" class="mt-1 block" :value="$to->toDateString()" />
            </div>
            <x-primary-button>Tampilkan</x-primary-button>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-stat-card label="Total Gaji Terkumpul (Periode)" value="Rp {{ number_format($totalEarned, 0, ',', '.') }}" />
            <x-stat-card label="Total Saldo Gaji (Keseluruhan)" value="Rp {{ number_format($totalBalance, 0, ',', '.') }}" :tone="$totalBalance < 0 ? 'danger' : 'default'" />
        </div>

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Mekanik</th>
                        <th class="px-4 py-3 text-right font-semibold">Gaji Terkumpul (Periode)</th>
                        <th class="px-4 py-3 text-right font-semibold">Total Ditarik</th>
                        <th class="px-4 py-3 text-right font-semibold">Saldo Gaji</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 font-semibold text-ink">{{ $row['mechanic']->name }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">Rp {{ number_format($row['earned'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-600">Rp {{ number_format($row['withdrawn'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right tabular font-semibold {{ $row['balance'] < 0 ? 'text-danger' : 'text-ink' }}">Rp {{ number_format($row['balance'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <a href="{{ route('payroll.show', ['mechanic' => $row['mechanic'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn-secondary px-3">Lihat &amp; Ambil Gaji</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-400">Belum ada mekanik.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>