<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Edit Kategori') }}</h1>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        @include('manage.partials.nav')
        <div class="bg-white border border-line rounded-md p-6">
            <form method="POST" action="{{ route('manage.categories.update', $category) }}" class="space-y-6">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label for="name" :value="__('Nama Kategori')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $category->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Perbarui') }}</x-primary-button>
                    <a href="{{ route('manage.categories.index') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>

        <div class="bg-white border border-line rounded-md mt-4 overflow-hidden">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between gap-3">
                <span class="font-semibold text-ink">Produk di Kategori Ini</span>
                <span class="text-xs text-ink-500">{{ $category->products->count() }} produk</span>
            </div>
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">SKU</th>
                        <th class="px-4 py-3 text-left font-semibold">Nama</th>
                        <th class="px-4 py-3 text-right font-semibold">Harga Jual</th>
                        <th class="px-4 py-3 text-right font-semibold">Stok</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($category->products as $product)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-ink-500">{{ $product->code_sku }}</td>
                            <td class="px-4 py-2.5 text-ink">{{ $product->name }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">{{ number_format($product->selling_price, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">{{ $product->stock }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <a href="{{ route('manage.products.edit', $product) }}" class="btn-secondary px-3">Lihat Produk</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-400">Belum ada produk di kategori ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>