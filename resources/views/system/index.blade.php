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

        @php
            $pct = $disk['available'] ? $disk['percent'] : null;
            $tone = $pct === null ? 'ink' : ($pct >= 85 ? 'danger' : ($pct >= 70 ? 'warning' : 'success'));
            $barColor = match ($tone) {
                'danger' => 'bg-danger',
                'warning' => 'bg-signal',
                default => 'bg-success',
            };
            $mb = fn ($b) => number_format($b / 1048576, 2, ',', '.').' MB';
        @endphp

        {{-- Status disk --}}
        <div class="bg-white border border-line rounded-md p-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-[13px] font-medium text-ink-500">Pemakaian Kuota Hosting</div>
                    @if ($disk['available'])
                        <div class="font-num tabular text-2xl font-bold mt-1 {{ $tone === 'danger' ? 'text-danger' : 'text-ink' }}">{{ $pct }}%</div>
                        <div class="text-xs text-ink-500 mt-1">{{ $mb($disk['used']) }} dipakai dari kuota {{ $mb($disk['quota']) }}</div>
                    @else
                        <div class="text-sm text-ink-500 mt-1">Kuota disk belum dikonfigurasi.</div>
                    @endif
                </div>
                <form method="POST" action="{{ route('system.retention') }}">
                    @csrf
                    <x-primary-button>Jalankan Retensi Sekarang</x-primary-button>
                </form>
            </div>

            @if ($disk['available'])
                <div class="mt-3 h-2 w-full bg-paper-dim rounded-full overflow-hidden">
                    <div class="h-full {{ $barColor }}" style="width: {{ min(100, $pct) }}%"></div>
                </div>
                @if ($pct >= 85)
                    <p class="text-xs text-danger mt-2 font-medium">⚠️ Kuota hampir penuh. Unduh &amp; hapus file arsip lama, atau jalankan retensi.</p>
                @elseif ($pct >= 70)
                    <p class="text-xs text-ink-600 mt-2">Pemakaian melewati 70%. Pantau berkala.</p>
                @endif
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4 text-xs text-ink-500">
                <div>Kode aplikasi: <strong class="text-ink-700">{{ $mb($disk['code']) }}</strong></div>
                <div>Database: <strong class="text-ink-700">{{ $mb($disk['database']) }}</strong></div>
                <div>storage/: <strong class="text-ink-700">{{ $mb($disk['storage']) }}</strong></div>
                <div>File arsip: <strong class="text-ink-700">{{ $mb($disk['archives']) }}</strong></div>
                <div>Log aplikasi: <strong class="text-ink-700">{{ $mb($disk['logs']) }}</strong></div>
            </div>

            @if ($disk['server_total'])
                <p class="text-xs text-ink-400 mt-2">
                    Info server (indikatif): {{ $mb($disk['server_free']) }} kosong dari {{ number_format($disk['server_total'] / 1073741824, 1, ',', '.') }} GB partisi.
                </p>
            @endif
        </div>

        {{-- Batas retensi aktif --}}
        <div class="bg-white border border-line rounded-md p-5">
            <div class="font-semibold text-ink mb-2">Pagar Retensi Aktif</div>
            <ul class="text-sm text-ink-600 space-y-1">
                <li>• Transaksi final disimpan maksimal <strong>{{ number_format($retention['transactions']) }}</strong>; lebih dari itu diarsip ke CSV lalu dihapus.</li>
                <li>• Aktivitas disimpan maksimal <strong>{{ number_format($retention['activity_max']) }}</strong> baris atau <strong>{{ $retention['activity_months'] }} bulan</strong>; lebih dari itu diarsip ke CSV lalu dihapus.</li>
                <li>• Pesanan pembelian (PO) disimpan maksimal <strong>{{ number_format($retention['purchase_orders']) }}</strong>; lebih dari itu diarsip ke CSV lalu dihapus.</li>
                <li>• Log aplikasi dirotasi otomatis (14 hari).</li>
                <li>• File arsip disimpan permanen; unduh via menu <a href="{{ route('archives.index') }}" class="text-signal font-medium hover:underline">Arsip &amp; Backup</a>.</li>
            </ul>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-stat-card label="Transaksi Final" :value="number_format($finalCount)" />
            <x-stat-card label="Activity Log" :value="number_format($activityLogCount)" />
            <x-stat-card label="File Arsip" :value="number_format($archiveCount)" />
        </div>

        {{-- Jejak proses retensi (retention_run_logs, append-only) --}}
        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <div class="px-5 py-3 border-b border-line font-semibold text-ink">Riwayat Proses Retensi</div>
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Waktu</th>
                        <th class="px-4 py-3 text-left font-semibold">Proses</th>
                        <th class="px-4 py-3 text-left font-semibold">Pemicu</th>
                        <th class="px-4 py-3 text-left font-semibold">Oleh</th>
                        <th class="px-4 py-3 text-right font-semibold">Diarsip</th>
                        <th class="px-4 py-3 text-right font-semibold">Dihapus</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($retentionRuns as $run)
                        <tr class="hover:bg-paper-dim/60">
                            <td class="px-4 py-2.5 text-ink-700 whitespace-nowrap">{{ $run->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5 text-ink-700">{{ $run->runTypeLabel() }}</td>
                            <td class="px-4 py-2.5 text-ink-600">{{ $run->triggerLabel() }}</td>
                            <td class="px-4 py-2.5 text-ink-600">{{ $run->user?->name ?? 'Sistem (cron)' }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-700">{{ number_format($run->archived_count) }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-700">{{ number_format($run->deleted_count) }}</td>
                            <td class="px-4 py-2.5">
                                @if ($run->status === \App\Models\RetentionRunLog::STATUS_FAILED)
                                    <span class="badge bg-danger-light text-danger">Gagal</span>
                                @else
                                    <span class="badge bg-success-light text-success">Sukses</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-ink-400">Belum ada proses retensi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white border border-line rounded-md p-5 flex flex-wrap items-center gap-4">
            <a href="{{ route('system.activity-logs.export') }}" class="btn-secondary">Export CSV Activity Logs</a>
            <a href="{{ route('archives.index') }}" class="btn-secondary">Buka Arsip &amp; Backup</a>
            <p class="text-xs text-ink-500">Sistem bersifat append-only. Export hanya mengunduh, tidak menghapus data.</p>
        </div>
    </div>
</x-app-layout>