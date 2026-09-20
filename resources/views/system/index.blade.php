<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">
            {{ __('Panel Sistem') }}
        </h1>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif

        @if ($diskWarning)
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">
                PERINGATAN: pemakaian disk {{ $diskPercent }}% (batas {{ \App\Services\DataRetentionService::DISK_WARNING_PERCENT }}%).
                Bersihkan arsip/log atau jalankan retensi.
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-stat-card label="Pemakaian Disk" value="{{ $diskPercent }}%" :tone="$diskWarning ? 'danger' : 'default'" />
            <x-stat-card label="Transaksi Final / Kuota" :value="number_format($finalCount) . ' / ' . number_format($quota)" />
            <x-stat-card label="Activity Log / Batas" :value="number_format($activityLogCount) . ' / ' . number_format($activityLogMax)" />
        </div>

        <div class="bg-white border border-line rounded-md p-5 flex flex-wrap items-center gap-4">
            <form method="POST" action="{{ route('system.retention') }}">
                @csrf
                <x-primary-button>Jalankan Retensi Sekarang</x-primary-button>
            </form>
            <a href="{{ route('system.activity-logs.export') }}" class="text-sm font-medium text-ink-500 hover:text-ink hover:underline">Export CSV Activity Logs</a>
        </div>

        <div class="bg-white border border-line rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-line font-semibold text-ink">Riwayat Arsip Transaksi</div>
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Waktu</th>
                        <th class="px-4 py-3 text-left font-semibold">File</th>
                        <th class="px-4 py-3 text-right font-semibold">Jumlah</th>
                        <th class="px-4 py-3 text-left font-semibold">Invoice (tertua–terbaru)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($archives as $archive)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 text-ink-700">{{ $archive->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-2.5 text-ink-700 break-all">{{ $archive->archive_path }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-700">{{ $archive->transaction_count }}</td>
                            <td class="px-4 py-2.5 text-ink-600">{{ $archive->oldest_invoice }} – {{ $archive->newest_invoice }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-ink-400">Belum ada arsip.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $archives->links() }}</div>
    </div>
</x-app-layout>