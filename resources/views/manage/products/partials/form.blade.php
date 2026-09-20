@php
    $product = $product ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="code_sku" :value="__('Kode / SKU')" />
        <x-text-input id="code_sku" name="code_sku" type="text" class="mt-1 block w-full"
                      :value="old('code_sku', $product?->code_sku)" required />
        <x-input-error :messages="$errors->get('code_sku')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="name" :value="__('Nama Produk')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      :value="old('name', $product?->name)" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="category_id" :value="__('Kategori')" />
        <select id="category_id" name="category_id" class="mt-1 block w-full border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]">
            <option value="">- Tanpa kategori -</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}"
                    @selected(old('category_id', $product?->category_id) == $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
    </div>

    @if ($canManageHpp)
        <div>
            <x-input-label for="purchase_price" :value="__('HPP / Harga Modal')" />
            <x-text-input id="purchase_price" name="purchase_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                          :value="old('purchase_price', $product?->purchase_price)" />
            <x-input-error :messages="$errors->get('purchase_price')" class="mt-2" />
            <p class="text-xs text-ink-400 mt-1">Hanya Owner & Super Admin yang dapat melihat/mengisi HPP.</p>
        </div>
    @endif

    <div>
        <x-input-label for="selling_price" :value="__('Harga Jual')" />
        <x-text-input id="selling_price" name="selling_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                      :value="old('selling_price', $product?->selling_price)" required />
        <x-input-error :messages="$errors->get('selling_price')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="stock" :value="__('Stok')" />
        <x-text-input id="stock" name="stock" type="number" min="0" class="mt-1 block w-full"
                      :value="old('stock', $product?->stock ?? 0)" required />
        <x-input-error :messages="$errors->get('stock')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="min_stock" :value="__('Stok Minimum')" />
        <x-text-input id="min_stock" name="min_stock" type="number" min="0" class="mt-1 block w-full"
                      :value="old('min_stock', $product?->min_stock ?? 3)" />
        <x-input-error :messages="$errors->get('min_stock')" class="mt-2" />
    </div>
</div>