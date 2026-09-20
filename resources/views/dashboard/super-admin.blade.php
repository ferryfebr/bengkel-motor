<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">Dashboard Super Admin</h1>
    </x-slot>

    <div class="space-y-6">
        @if (! empty($diskWarning))
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">
                PERINGATAN: pemakaian disk {{ $diskPercent }}%.
            </div>
        @endif

        <div class="bg-ink text-paper rounded-md p-6">
            <p class="text-lg font-bold">Halo, {{ $user->name }}</p>
            <p class="text-sm text-paper-dim mt-1">Anda masuk sebagai <strong class="text-signal">Super Admin</strong>.</p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Transaksi Hari Ini" :value="number_format($todayTransactions)" />
            <x-stat-card label="Omset Hari Ini" value="Rp {{ number_format($todayGross, 0, ',', '.') }}" tone="success" />
            <x-stat-card label="Transaksi Final" :value="number_format($finalCount ?? 0)" :hint="'Batas ' . number_format(\App\Services\DataRetentionService::QUOTA_FINAL_TRANSACTIONS)" />
            <x-stat-card label="Pemakaian Disk" value="{{ $diskPercent ?? 0 }}%" :tone="! empty($diskWarning) ? 'danger' : 'default'" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-action-card :href="route('system.index')" title="Panel Sistem"
                desc="Disk, retensi, arsip & export log.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v6H4zM4 14h16v6H4zM8 7h.01M8 17h.01"/></svg></x-slot>
            </x-action-card>
            <x-action-card :href="route('manage.index')" title="Manajemen"
                desc="Produk, jasa, mekanik & rasio.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8 12 3 3 8l9 5 9-5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 8v8l9 5 9-5V8M12 13v8"/></svg></x-slot>
            </x-action-card>
            <x-action-card :href="route('reports.net')" title="Laporan"
                desc="Omset kotor, bersih & komisi.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></x-slot>
            </x-action-card>
        </div>
    </div>
</x-app-layout>