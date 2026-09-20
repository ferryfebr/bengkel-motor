<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Laporan Komisi Mekanik') }}</h1>
    </x-slot>

    <div class="space-y-4">
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
        </form>

        <div class="bg-white border border-line rounded-md overflow-hidden">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Mekanik</th>
                        <th class="px-4 py-3 text-right font-semibold">Jumlah Pekerjaan</th>
                        <th class="px-4 py-3 text-right font-semibold">Total Komisi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($commissions as $row)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 font-medium text-ink">{{ $row['mechanic_name'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-700">{{ $row['total_jobs'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">Rp {{ number_format($row['total_share'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-ink-400">Belum ada komisi pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-xs text-ink-500">Pencairan komisi dilakukan manual di luar sistem. Laporan ini hanya akumulasi.</p>
    </div>
</x-app-layout>