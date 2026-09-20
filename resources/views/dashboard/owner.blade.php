<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">Dashboard Owner</h1>
    </x-slot>

    <div class="space-y-6">
        @if (! empty($diskWarning))
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">
                PERINGATAN: pemakaian disk {{ $diskPercent }}%. Bersihkan arsip/log atau jalankan retensi di Panel Sistem.
            </div>
        @endif

        <div class="bg-ink text-paper rounded-md p-6">
            <p class="text-lg font-bold">Halo, {{ $user->name }}</p>
            <p class="text-sm text-paper-dim mt-1">Anda masuk sebagai <strong class="text-signal">Owner</strong>.</p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Omset Hari Ini" value="Rp {{ number_format($todayGross, 0, ',', '.') }}" tone="success" />
            <x-stat-card label="Transaksi Hari Ini" :value="number_format($todayTransactions)" />
            <x-stat-card label="Saldo Kas" value="Rp {{ number_format($cashBalance, 0, ',', '.') }}" :tone="$cashBalance < 0 ? 'danger' : 'brand'" />
            <x-stat-card label="Stok Menipis" :value="number_format($lowStock)" :tone="$lowStock > 0 ? 'danger' : 'default'" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-action-card :href="route('manage.index')" title="Manajemen"
                desc="Produk, HPP, jasa, mekanik & rasio komisi.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8 12 3 3 8l9 5 9-5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 8v8l9 5 9-5V8M12 13v8"/></svg></x-slot>
            </x-action-card>
            <x-action-card :href="route('reports.net')" title="Laporan Omset Bersih"
                desc="Profit bersih & performa mekanik.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></x-slot>
            </x-action-card>
            <x-action-card :href="route('impersonation.index')" title="Login Sebagai"
                desc="Masuk sebagai kasir untuk pengecekan.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M21 16v3a2 2 0 0 1-2 2h-3M3 8V5a2 2 0 0 1 2-2h3M9 12h6"/></svg></x-slot>
            </x-action-card>
        </div>
    </div>
</x-app-layout>