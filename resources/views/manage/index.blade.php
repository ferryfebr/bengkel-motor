<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Manajemen') }}</h1>
    </x-slot>

    <div class="space-y-4">
        @include('manage.partials.nav')

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-action-card :href="route('manage.products.index')" title="Produk / Sparepart"
                desc="Kelola produk, stok, dan harga jual." />

            @if (auth()->user()->hasRole('owner', 'super_admin'))
                <x-action-card :href="route('manage.categories.index')" title="Kategori"
                    desc="Kelompok produk." />
                <x-action-card :href="route('manage.services.index')" title="Master Jasa"
                    desc="Template tarif jasa servis." />
                <x-action-card :href="route('manage.mechanics.index')" title="Mekanik & Rasio"
                    desc="Data mekanik dan rasio komisi." />
            @endif
        </div>
    </div>
</x-app-layout>