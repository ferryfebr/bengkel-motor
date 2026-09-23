<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">Dashboard Owner</h1>
    </x-slot>

    <div class="space-y-6">
        <div class="bg-ink text-paper rounded-md p-6">
            <p class="text-lg font-bold">Halo, {{ $user->name }}</p>
            <p class="text-sm text-paper-dim mt-1">Anda masuk sebagai <strong class="text-signal">Owner</strong>.</p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <x-stat-card label="Omset Hari Ini" value="Rp {{ number_format($todayGross, 0, ',', '.') }}" tone="success" />
            <x-stat-card label="Transaksi Hari Ini" :value="number_format($todayTransactions)" />
            <x-stat-card label="Pengeluaran Kas Produk Luar (Hari Ini)" value="Rp {{ number_format($externalCashOut, 0, ',', '.') }}" />
        </div>

        <div class="bg-paper border border-line rounded-md p-6">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h3 class="font-semibold text-ink">Ringkasan Antrean</h3>
                <a href="{{ route('work-orders.queue') }}" class="btn-secondary">Lihat Daftar Antrean →</a>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="bg-paper-dim rounded-md p-3">
                    <div class="text-[13px] text-ink-500">Antre</div>
                    <div class="font-num tabular text-2xl font-bold text-ink">{{ number_format($antreCount) }}</div>
                </div>
                <div class="bg-paper-dim rounded-md p-3">
                    <div class="text-[13px] text-ink-500">Sedang Dikerjakan</div>
                    <div class="font-num tabular text-2xl font-bold text-ink">{{ number_format($prosesCount) }}</div>
                </div>
            </div>
            @forelse ($queuePreview as $wo)
                <div class="flex items-center justify-between gap-3 border-b border-line py-2 text-sm">
                    <span class="font-semibold text-ink">{{ $wo->plate_number }}</span>
                    <span class="text-ink-500">{{ $wo->customer_name ?: '-' }}</span>
                    <span class="badge {{ $wo->work_status === 'proses' ? 'bg-ink text-paper' : 'bg-paper-dim text-ink-700' }}">
                        {{ $wo->work_status === 'proses' ? '🔧 Dikerjakan' : '🟡 Antre' }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-ink-400">Belum ada motor dalam antrean.</p>
            @endforelse
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-action-card :href="route('manage.index')" title="Manajemen"
                desc="Produk, HPP, mekanik, rasio & akun kasir.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8 12 3 3 8l9 5 9-5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 8v8l9 5 9-5V8M12 13v8"/></svg></x-slot>
            </x-action-card>
            <x-action-card :href="route('reports.net')" title="Laporan Omset Bersih"
                desc="Profit bersih & performa mekanik.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></x-slot>
            </x-action-card>
        </div>
    </div>
</x-app-layout>