<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">Buat Pesanan Pembelian</h1>
            <a href="{{ route('purchase-orders.index') }}" class="btn-secondary shrink-0">← Pesanan Pembelian</a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ $errors->first() }}</div>
        @endif

        <div
            x-data="poForm({
                products: {{ Js::from($products->map(fn ($p) => ['id' => $p->id, 'code' => $p->code_sku, 'name' => $p->name, 'price' => (float) $p->purchase_price, 'stock' => $p->stock])) }}
            })"
            class="space-y-4"
        >
            <form method="POST" action="{{ route('purchase-orders.store') }}" class="space-y-4">
                @csrf

                <div class="bg-white border border-line rounded-md p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="supplier_id" value="Distributor" />
                        <select id="supplier_id" name="supplier_id" class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]">
                            <option value="">- Pilih distributor -</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-ink-400 mt-1">Belum ada distributor? <a href="{{ route('suppliers.index') }}" class="text-signal font-medium hover:underline">Tambah di sini</a>.</p>
                    </div>
                    <div>
                        <x-input-label for="notes" value="Catatan (opsional)" />
                        <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full" :value="old('notes')" placeholder="mis. minta dikirim cepat" />
                    </div>
                </div>

                <div class="bg-white border border-line rounded-md p-4">
                    <div class="flex items-center justify-between mb-3 gap-2">
                        <h3 class="font-semibold text-ink">Produk yang Dipesan</h3>
                        <x-secondary-button type="button" @click="addRow()">+ Tambah Produk</x-secondary-button>
                    </div>

                    <template x-if="rows.length === 0">
                        <p class="text-sm text-ink-400">Belum ada produk. Klik "Tambah Produk".</p>
                    </template>

                    <div class="space-y-3">
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="grid grid-cols-12 gap-2 items-start">
                                <div class="col-span-12 sm:col-span-6">
                                    <select :name="`items[${i}][product_id]`" x-model="row.product_id" @change="onProduct(row)"
                                            class="border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]">
                                        <option value="">- Pilih produk -</option>
                                        <template x-for="p in products" :key="p.id">
                                            <option :value="p.id" x-text="p.code + ' - ' + p.name + ' (stok ' + p.stock + ')'"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-4 sm:col-span-2">
                                    <input type="number" min="1" :name="`items[${i}][qty]`" x-model="row.qty" placeholder="Qty"
                                           class="border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]" />
                                </div>
                                <div class="col-span-6 sm:col-span-3">
                                    <input type="hidden" :name="`items[${i}][purchase_price]`" :value="num(row.price)" />
                                    <input type="text" inputmode="numeric"
                                           :value="formatRibuan(row.price)" @input="row.price = formatRibuan($event.target.value)"
                                           placeholder="Harga beli (HPP)"
                                           class="border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]" />
                                </div>
                                <div class="col-span-2 sm:col-span-1 flex justify-end">
                                    <button type="button" @click="removeRow(i)" class="text-danger text-xl leading-none px-2" title="Hapus">×</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4 flex justify-between items-baseline border-t border-line pt-3">
                        <span class="text-sm font-medium text-ink-500">Estimasi Total</span>
                        <span class="font-num tabular text-2xl font-bold text-ink" x-text="rupiah(total())"></span>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>Simpan PO</x-primary-button>
                    <a href="{{ route('purchase-orders.index') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function poForm(config) {
                return {
                    products: config.products,
                    rows: [{ product_id: '', qty: 1, price: '' }],
                    addRow() { this.rows.push({ product_id: '', qty: 1, price: '' }); },
                    removeRow(i) { this.rows.splice(i, 1); },
                    onProduct(row) {
                        const p = this.products.find(x => String(x.id) === String(row.product_id));
                        if (p) { row.price = this.formatRibuan(p.price || 0); }
                    },
                    num(v) {
                        if (typeof v === 'number') return v;
                        const cleaned = String(v ?? '').replace(/[^\d]/g, '');
                        const parsed = parseFloat(cleaned);
                        return Number.isFinite(parsed) ? parsed : 0;
                    },
                    formatRibuan(v) {
                        const raw = String(v ?? '').replace(/[^\d]/g, '');
                        if (raw === '') return '';
                        return Number(raw).toLocaleString('id-ID');
                    },
                    rupiah(v) { return 'Rp ' + this.num(v).toLocaleString('id-ID'); },
                    total() {
                        return this.rows.reduce((sum, r) => sum + (this.num(r.price) * (this.num(r.qty) || 0)), 0);
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>