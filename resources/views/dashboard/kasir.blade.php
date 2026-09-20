<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">Dashboard Kasir</h1>
    </x-slot>

    <div class="space-y-6">
        <div class="bg-ink text-paper rounded-md p-6 flex items-center justify-between gap-4">
            <div>
                <p class="text-lg font-bold">Halo, {{ $user->name }}</p>
                <p class="text-sm text-paper-dim mt-1">Anda masuk sebagai <strong class="text-signal">Kasir</strong>.</p>
            </div>
            <a href="{{ route('work-orders.create') }}"
               class="btn-primary hidden sm:inline-flex">
                + Motor Masuk
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Transaksi Hari Ini" :value="number_format($todayTransactions)" />
            <x-stat-card label="Work Order Aktif" :value="number_format($ongoingWorkOrders)" tone="brand" />
            <x-stat-card label="Omset Hari Ini" value="Rp {{ number_format($todayGross, 0, ',', '.') }}" tone="success" />
            <x-stat-card label="Stok Menipis" :value="number_format($lowStock)" :tone="$lowStock > 0 ? 'danger' : 'default'" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-action-card :href="route('work-orders.index')" title="Work Order"
                desc="Kelola motor masuk & status pengerjaan.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5h6M9 5a2 2 0 0 0-2 2v0a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v0a2 2 0 0 0-2-2M7 7H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-1"/></svg></x-slot>
            </x-action-card>
            <x-action-card :href="route('cash.index')" title="Kas Bengkel"
                desc="Catat kas masuk/keluar & lihat saldo.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18v10H3zM7 12h.01M12 12h.01M17 12h.01"/></svg></x-slot>
            </x-action-card>
            <x-action-card :href="route('reports.gross')" title="Laporan Omset"
                desc="Lihat omset kotor & komisi mekanik.">
                <x-slot name="icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></x-slot>
            </x-action-card>
        </div>
    </div>
</x-app-layout>