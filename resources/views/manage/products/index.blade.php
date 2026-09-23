<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink">{{ __('Produk / Sparepart') }}</h1>
            <a href="{{ route('manage.products.create') }}" class="btn-primary">
                + Tambah Produk
            </a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @include('manage.partials.nav')

        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ session('error') }}</div>
        @endif

        <form method="GET" class="flex gap-2">
            <x-text-input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama / SKU..." class="w-64" />
            <x-primary-button type="submit">Cari</x-primary-button>
        </form>

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">SKU</th>
                        <th class="px-4 py-3 text-left font-semibold">Nama</th>
                        <th class="px-4 py-3 text-left font-semibold">Kategori</th>
                        @if ($canViewHpp)
                            <th class="px-4 py-3 text-right font-semibold">HPP</th>
                        @endif
                        <th class="px-4 py-3 text-right font-semibold">Harga Jual</th>
                        <th class="px-4 py-3 text-right font-semibold">Stok</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($products as $product)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-ink-500">{{ $product->code_sku }}</td>
                            <td class="px-4 py-2.5 text-ink">
                                <span>{{ $product->name }}</span>
                                @if ($product->stock < 5)
                                    <span class="badge bg-danger-light text-danger ms-2">⚠️ stok dibawah 5</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-ink-600">{{ $product->category?->name ?? '-' }}</td>
                            @if ($canViewHpp)
                                <td class="px-4 py-2.5 text-right tabular text-ink-600">
                                    {{ $product->purchase_price !== null ? number_format($product->purchase_price, 0, ',', '.') : '-' }}
                                </td>
                            @endif
                            <td class="px-4 py-2.5 text-right tabular text-ink">{{ number_format($product->selling_price, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right tabular {{ $product->stock <= $product->min_stock ? 'text-danger font-semibold' : 'text-ink' }}">
                                {{ $product->stock }}
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <a href="{{ route('manage.products.edit', $product) }}" class="btn-secondary px-3">Edit</a>
                                @can('delete', $product)
                                    <form method="POST" action="{{ route('manage.products.destroy', $product) }}" class="inline"
                                          onsubmit="return confirm('Hapus produk ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn-danger px-3 ms-2">Hapus</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-ink-400">Belum ada produk.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $products->links() }}
    </div>
</x-app-layout>