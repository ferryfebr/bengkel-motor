<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">Arsip &amp; Backup Transaksi</h1>
            <a href="{{ route('archives.download-all') }}" class="btn-primary shrink-0">Unduh Semua (ZIP)</a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ session('error') }}</div>
        @endif

        <div class="bg-white border border-line rounded-md p-5">
            <p class="text-sm text-ink-600">
                File arsip dibuat otomatis oleh sistem saat transaksi paling lama dipindahkan keluar dari database.
                Simpan file ini ke komputer Anda sebagai cadangan permanen.
            </p>
            <div class="mt-2 text-xs text-ink-500">
                Total file: <strong>{{ number_format($totalFiles) }}</strong> ·
                Total ukuran: <strong>{{ $totalBytes > 0 ? number_format($totalBytes / 1048576, 2, ',', '.').' MB' : '0 MB' }}</strong>
            </div>
        </div>

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Tanggal Dibuat</th>
                        <th class="px-4 py-3 text-left font-semibold">File</th>
                        <th class="px-4 py-3 text-right font-semibold">Jumlah Transaksi</th>
                        <th class="px-4 py-3 text-left font-semibold">Rentang Invoice (tertua–terbaru)</th>
                        <th class="px-4 py-3 text-right font-semibold">Ukuran</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($archives as $archive)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 text-ink-700 whitespace-nowrap">{{ $archive->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5 text-ink-700 break-all">{{ basename($archive->archive_path) }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-700">{{ $archive->transaction_count }}</td>
                            <td class="px-4 py-2.5 text-ink-600">{{ $archive->oldest_invoice }} – {{ $archive->newest_invoice }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-600">{{ $archive->humanSize() }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                @if ($archive->exists())
                                    <a href="{{ route('archives.download', $archive) }}" class="btn-secondary px-3">Unduh</a>
                                @else
                                    <span class="badge bg-danger-light text-danger">File hilang</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-ink-400">Belum ada file arsip.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $archives->links() }}
    </div>
</x-app-layout>