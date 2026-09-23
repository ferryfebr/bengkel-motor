{{-- Shared POS form. Dipakai oleh pos/show.blade.php & work-orders/show.blade.php. --}}
@php
    $existingCart = [];
    foreach ($transaction->details as $d) {
        $key = $d->is_external ? 'e'.$d->id : 'p'.$d->product_id;
        $existingCart[$key] = [
            'type' => $d->is_external ? 'external' : 'product',
            'product_id' => $d->product_id,
            'external_name' => $d->external_name,
            'purchase_price' => (float) $d->purchase_price,
            'label' => $d->is_external ? '[Luar] '.$d->external_name : ($d->product?->name ?? 'Produk #'.$d->product_id),
            'price' => (float) $d->selling_price,
            'qty' => (int) $d->qty,
        ];
    }
    foreach ($transaction->services as $s) {
        $existingCart['s'.$s->id] = [
            'type' => 'service',
            'service_id' => $s->service_id,
            'service_name' => $s->service_name,
            'price' => (float) $s->service_price,
            'qty' => 1,
            'shares' => $s->shares->map(fn ($sh) => ['mechanic_id' => (int) $sh->mechanic_id, 'amount' => (float) $sh->share_amount])->values()->all(),
            'label' => '[Jasa] '.$s->service_name,
        ];
    }
@endphp
<div x-data="posApp({
    products: {{ Js::from($products->map(fn($p) => ['id'=>$p->id,'code'=>$p->code_sku,'name'=>$p->name,'price'=>(float)$p->selling_price,'stock'=>$p->stock])) }},
    mechanics: {{ Js::from($mechanics->map(fn($m) => ['id'=>$m->id,'name'=>$m->name,'ratio'=>(float)$m->mechanic_percentage])) }},
    existing: {{ Js::from((object) $existingCart) }},
    grandTotal: {{ (float) $transaction->grand_total }},
    paidAmount: {{ (float) $transaction->paid_amount }},
    paymentStatus: '{{ $transaction->payment_status ?? 'belum_bayar' }}',
    paymentMethod: '{{ $transaction->payment_method ?? 'cash' }}'
})" x-init="$nextTick(() => $refs.scan.focus())">
    <div class="space-y-4">
        <form method="POST" action="{{ route('pos.checkout', $transaction) }}" @submit="onCheckout($event)">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-10 gap-4">
                {{-- Kolom kiri: katalog --}}
                <div class="lg:col-span-6 space-y-4">
                    {{-- Produk stok --}}
                    <div class="bg-white border border-line rounded-md p-4">
                        <h3 class="font-semibold text-ink mb-3">Produk (barcode / manual)</h3>

                        {{-- SKU / scan barcode - exact match --}}
                        <div>
                            <x-input-label for="pos_scan" value="SKU / Scan Barcode" />
                            <div class="flex gap-2 mt-1">
                                <input id="pos_scan" type="text" x-ref="scan" x-model="scan" @keydown.enter.prevent="addByScan()"
                                       autofocus
                                       placeholder="Scan / ketik SKU lalu Enter"
                                       class="border-line focus:border-signal focus:ring-signal rounded-md flex-1 min-h-[44px]" />
                                <button type="button" @click="addByScan()" class="btn-secondary px-4">+</button>
                            </div>
                        </div>

                        {{-- Cari produk - partial match nama --}}
                        <div class="mt-3">
                            <x-input-label for="pos_search" value="Cari Produk" />
                            <input id="pos_search" type="text" x-model="search" placeholder="Ketik sebagian nama produk"
                                   class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]" />
                            <template x-if="search.trim() !== ''">
                                <div class="mt-2 border border-line rounded-md divide-y divide-line max-h-64 overflow-y-auto">
                                    <template x-for="p in searchResults()" :key="p.id">
                                        <button type="button" @click="addProduct(p.id); search = ''"
                                                class="flex items-center justify-between gap-3 w-full px-3 py-2.5 min-h-[44px] text-left hover:bg-paper-dim">
                                            <span class="min-w-0">
                                                <span class="block text-ink truncate" x-text="p.name"></span>
                                                <span class="block text-xs text-ink-500 font-mono" x-text="p.code + ' · stok ' + p.stock"></span>
                                            </span>
                                            <span class="shrink-0 font-num tabular text-ink" x-text="rupiah(p.price)"></span>
                                        </button>
                                    </template>
                                    <template x-if="searchResults().length === 0">
                                        <div class="px-3 py-2.5 text-sm text-ink-400">Tidak ada produk cocok.</div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Pilih manual --}}
                        <select class="mt-3 border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]" @change="addProduct($event.target.value); $event.target.value=''">
                            <option value="">- Pilih produk -</option>
                            <template x-for="p in products" :key="p.id">
                                <option :value="p.id" x-text="p.code + ' - ' + p.name + ' (stok ' + p.stock + ')'"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Produk luar --}}
                    <div class="bg-white border border-line rounded-md p-4">
                        <h3 class="font-semibold text-ink mb-1">Produk Luar (dari toko lain)</h3>
                        <p class="text-xs text-ink-500 mb-3">Tidak masuk stok. HPP akan otomatis tercatat sebagai kas keluar saat transaksi final.</p>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" x-model="ep.name" placeholder="Nama produk" class="border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]" />
                            <input type="number" x-model="ep.qty" min="1" placeholder="Qty" class="border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]" />
                            <input type="text" inputmode="numeric" :value="formatRibuan(ep.purchase_price)" @input="ep.purchase_price = formatRibuan($event.target.value)" placeholder="HPP (kas keluar)" class="border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]" />
                            <input type="text" inputmode="numeric" :value="formatRibuan(ep.selling_price)" @input="ep.selling_price = formatRibuan($event.target.value)" placeholder="Harga jual ke customer" class="border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]" />
                        </div>
                        <button type="button" @click="addExternal()" class="btn-secondary mt-3">Tambah Produk dari Luar ke Nota</button>
                    </div>

                    {{-- Jasa + mekanik --}}
                    <div class="bg-white border border-line rounded-md p-4">
                        <h3 class="font-semibold text-ink mb-3">Jasa Servis (nominal fleksibel)</h3>
                        <div class="mb-2">
                            <input type="text" inputmode="numeric" :value="formatRibuan(sv.price)" @input="sv.price = formatRibuan($event.target.value)" placeholder="Biaya jasa (Rp)" class="border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]" />
                        </div>
                        <input type="text" x-model="sv.service_name" placeholder="Nama jasa" class="border-line focus:border-signal focus:ring-signal rounded-md w-full mb-3 min-h-[44px]" />

                        <div class="text-[13px] font-medium text-ink-500 mb-2">Mekanik pengerja &amp; pembagian nominal:</div>
                        <template x-if="sv.shares.length === 0">
                            <p class="text-sm text-ink-400 mb-3">Belum ada mekanik ditambahkan</p>
                        </template>
                        <template x-for="(m, i) in sv.shares" :key="i">
                            <div class="mb-3">
                                <div class="flex gap-2">
                                    <select x-model="m.mechanic_id" @change="onMechanicChange(m)" class="border-line focus:border-signal focus:ring-signal rounded-md flex-1 min-h-[44px]">
                                        <option value="">- Pilih Mekanik -</option>
                                        <template x-for="mech in mechanics" :key="mech.id">
                                            <option :value="mech.id"
                                                    :disabled="sv.shares.some((s, j) => j !== i && String(s.mechanic_id) === String(mech.id))"
                                                    x-text="mech.name + ' (' + mech.ratio + '%)'"></option>
                                        </template>
                                    </select>
                                    <input type="text" inputmode="numeric" :value="formatRibuan(m.amount)" @input="m.amount = formatRibuan($event.target.value); m.touched = true" placeholder="Nominal" class="border-line focus:border-signal focus:ring-signal rounded-md w-32 min-h-[44px]" />
                                    <button type="button" @click="removeShare(i)" class="text-danger px-2 text-lg" title="Hapus mekanik">×</button>
                                </div>
                                <p class="text-xs text-ink-500 mt-1" x-show="m.mechanic_id" x-text="shareReference(m)"></p>
                            </div>
                        </template>
                        <x-secondary-button @click="sv.shares.push({mechanic_id:'', amount:'', touched:false}); syncShares()">+ Tambah Mekanik</x-secondary-button>

                        <button type="button" @click="addService()" class="btn-secondary mt-4">+ Tambah Jasa ke Nota</button>
                    </div>
                </div>

                {{-- Kolom kanan: nota --}}
                <div class="lg:col-span-4 space-y-4">
                    <div class="bg-white border border-line rounded-md p-4">
                        <h3 class="font-semibold text-ink mb-3">Keranjang</h3>
                        <template x-if="Object.keys(cart).length === 0">
                            <p class="text-sm text-ink-400">Belum ada item.</p>
                        </template>
                        <template x-for="(row, key) in cart" :key="key">
                            <div class="flex items-center justify-between border-b border-line py-2 text-sm gap-2">
                                <div class="min-w-0">
                                    <div class="text-ink truncate" x-text="row.label"></div>
                                    <div class="text-xs text-ink-400">
                                        @ <span x-text="rupiah(row.price)"></span>
                                        <template x-if="row.type === 'service'">
                                            <span> · <span x-text="row.shares.length"></span> mekanik</span>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <template x-if="row.type !== 'service'">
                                        <input type="number" min="1" x-model="row.qty" class="w-20 border-line focus:border-signal focus:ring-signal rounded-md text-sm" />
                                    </template>
                                    <span class="w-28 text-right font-num tabular whitespace-nowrap" x-text="rupiah(rowSubtotal(row))"></span>
                                    <template x-if="row.type === 'service'">
                                        <button type="button" @click="openEditService(key)" class="px-2 py-1 text-xs font-semibold rounded-md border border-ink bg-white text-ink hover:bg-paper-dim" title="Edit jasa">Edit</button>
                                    </template>
                                    <button type="button" @click="removeRow(key)" class="text-danger text-lg leading-none" title="Hapus item">×</button>
                                </div>
                            </div>
                        </template>

                        <div class="mt-4 flex justify-between items-baseline border-t border-line pt-3">
                            <span class="text-sm font-medium text-ink-500">Total</span>
                            <span class="font-num tabular text-3xl font-bold text-ink" x-text="rupiah(grandTotal())"></span>
                        </div>
                    </div>

                    <div class="bg-white border border-line rounded-md p-4 space-y-3">
                        <div>
                            <x-input-label value="Metode Pembayaran" />
                            <select name="payment_method" x-model="paymentMethod" class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]">
                                <option value="cash">Tunai</option>
                                <option value="qris">Transfer</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Status Pembayaran" />
                            <select name="payment_status" x-model="paymentStatus" class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]">
                                <option value="belum_bayar">Belum Bayar</option>
                                <option value="dp">DP</option>
                                <option value="lunas">Lunas</option>
                            </select>
                        </div>

                        {{-- Input jumlah DP - hanya muncul saat status DP --}}
                        <template x-if="paymentStatus === 'dp'">
                            <div>
                                <x-input-label value="Jumlah DP Dibayarkan (Rp)" />
                                <input type="hidden" name="paid_amount" :value="num(paidAmount)" />
                                <input type="text" inputmode="numeric" :value="formatRibuan(paidAmount)" @input="paidAmount = formatRibuan($event.target.value)" placeholder="0"
                                       class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]" />
                            </div>
                        </template>

                        {{-- Ringkasan pembayaran --}}
                        <div class="border-t border-line pt-3 space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-ink-500">Terbayar</span>
                                <span class="font-num tabular text-ink" x-text="rupiah(terbayar())"></span>
                            </div>
                            <div class="flex justify-between font-semibold">
                                <span class="text-ink-500">Sisa Belum Dibayar</span>
                                <span class="font-num tabular" :class="sisa() > 0 ? 'text-danger' : 'text-success'" x-text="rupiah(sisa())"></span>
                            </div>
                        </div>

                        <template x-if="sisa() > 0">
                            <div class="bg-danger-light border border-danger/40 text-danger px-3 py-2 rounded-md text-sm">
                                Transaksi belum bisa diselesaikan. Sisa belum dibayar <span class="font-num tabular" x-text="rupiah(sisa())"></span>.
                            </div>
                        </template>

                        <div class="space-y-2">
                            <button type="submit"

                                    class="btn-primary w-full py-3 text-base">
                                Selesaikan &amp; Cetak
                            </button>
                            <button type="submit" formaction="{{ route('pos.draft', $transaction) }}"
                                    data-action="draft"
                                    class="btn-secondary w-full py-3 text-base">
                                Simpan Transaksi Sementara
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Hidden inputs yang di-generate saat submit --}}
            <div x-ref="hidden"></div>
        </form>

        {{-- Modal edit jasa --}}
        <div x-show="edit.open" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6" role="dialog" aria-modal="true" style="display: none;">
            <div class="fixed inset-0 bg-ink opacity-75" @click="closeEdit()"></div>

            <div class="relative mb-6 bg-white border border-line rounded-md shadow-lg sm:w-full sm:max-w-lg sm:mx-auto"
                 @keydown.escape.window="closeEdit()">
                <div class="p-6 space-y-4">
                    <h3 class="font-semibold text-ink">Edit Jasa</h3>

                    <div>
                        <x-input-label value="Nama Jasa" />
                        <input type="text" x-model="edit.service_name" class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]" />
                    </div>
                    <div>
                        <x-input-label value="Biaya Jasa (Rp)" />
                        <input type="text" inputmode="numeric" :value="formatRibuan(edit.price)" @input="edit.price = formatRibuan($event.target.value); syncEditShares()"
                               class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md w-full min-h-[44px]" />
                    </div>

                    <div class="text-[13px] font-medium text-ink-500">Mekanik & pembagian:</div>
                    <template x-if="edit.shares.length === 0">
                        <p class="text-sm text-ink-400">Belum ada mekanik ditambahkan</p>
                    </template>
                    <template x-for="(m, i) in edit.shares" :key="i">
                        <div class="space-y-1">
                            <div class="flex gap-2">
                                <select x-model="m.mechanic_id" @change="onEditMechanicChange(m)" class="border-line focus:border-signal focus:ring-signal rounded-md flex-1 min-h-[44px]">
                                    <option value="">- Pilih Mekanik -</option>
                                    <template x-for="mech in mechanics" :key="mech.id">
                                        <option :value="mech.id"
                                                :disabled="edit.shares.some((s, j) => j !== i && String(s.mechanic_id) === String(mech.id))"
                                                x-text="mech.name + ' (' + mech.ratio + '%)'"></option>
                                    </template>
                                </select>
                                <input type="text" inputmode="numeric" :value="formatRibuan(m.amount)" @input="m.amount = formatRibuan($event.target.value); m.touched = true" placeholder="Nominal"
                                       class="border-line focus:border-signal focus:ring-signal rounded-md w-32 min-h-[44px]" />
                                <button type="button" @click="removeEditShare(i)" class="text-danger px-2 text-lg" title="Hapus mekanik">×</button>
                            </div>
                            <p class="text-xs text-ink-500" x-show="m.mechanic_id" x-text="editShareReference(m)"></p>
                        </div>
                    </template>
                    <x-secondary-button @click="edit.shares.push({mechanic_id:'', amount:'', touched:false}); syncEditShares()">+ Tambah Mekanik</x-secondary-button>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary px-6" @click="closeEdit()">Batal</button>
                        <button type="button" class="btn-primary px-6" @click="saveEditService()">Simpan Perubahan</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal notifikasi berhasil / gagal --}}
        <div x-show="notif.open" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6" role="dialog" aria-modal="true" style="display: none;">
            <div class="fixed inset-0 bg-ink opacity-75" @click="closeNotif()"></div>

            <div class="relative mb-6 bg-white border border-line rounded-md shadow-lg sm:w-full sm:max-w-md sm:mx-auto"
                 @keydown.escape.window="closeNotif()">

                <div class="p-6">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full text-lg font-bold"
                              :class="notif.type === 'success' ? 'bg-success-light text-success' : 'bg-danger-light text-danger'"
                              x-text="notif.type === 'success' ? '✓' : '!'"></span>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-ink" x-text="notif.title"></h3>
                            <p class="text-sm text-ink-soft mt-1 whitespace-pre-line" x-text="notif.message"></p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" class="btn-primary px-6" @click="closeNotif()">Mengerti</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
        function posApp(config) {
            return {
                products: config.products,
                mechanics: config.mechanics,
                scan: '',
                search: '',
                ep: { name: '', qty: 1, purchase_price: '', selling_price: '' },
                sv: { service_name: '', price: '', shares: [{ mechanic_id: '', amount: '', touched: false }] },
                cart: Object.assign({}, config.existing || {}),
                paymentMethod: config.paymentMethod || 'cash',
                paymentStatus: config.paymentStatus || 'belum_bayar',
                paidAmount: config.paidAmount || 0,
                notif: { open: false, type: 'success', title: '', message: '' },
                edit: { open: false, key: null, service_name: '', price: '', shares: [] },
                init() {
                    this.$watch('sv.price', () => this.syncShares());

                    const flashOk = @js(session('status'));
                    const flashFail = @js(session('error'));
                    if (flashOk) {
                        this.notifySuccess('Berhasil', flashOk);
                    } else if (flashFail) {
                        this.notifyFail(flashFail);
                    }
                },
                notifySuccess(title, message) {
                    this.notif.type = 'success';
                    this.notif.title = title;
                    this.notif.message = message;
                    this.notif.open = true;
                },
                notifyFail(message, title = 'Gagal') {
                    this.notif.type = 'fail';
                    this.notif.title = title;
                    this.notif.message = message;
                    this.notif.open = true;
                },
                closeNotif() { this.notif.open = false; },
                openEditService(key) {
                    const row = this.cart[key];
                    if (!row || row.type !== 'service') return;
                    this.edit.open = true;
                    this.edit.key = key;
                    this.edit.service_name = row.service_name;
                    this.edit.price = this.formatRibuan(row.price);
                    this.edit.shares = row.shares.map(s => ({ mechanic_id: s.mechanic_id, amount: s.amount, touched: false }));
                },
                closeEdit() { this.edit.open = false; this.edit.key = null; },
                poolEditPorsiMekanik() {
                    const base = this.num(this.edit.price);
                    const first = this.edit.shares.find(s => s.mechanic_id);
                    if (!base || !first) return 0;
                    return Math.round(base * this.ratioFor(first.mechanic_id) / 100);
                },
                syncEditShares() {
                    const base = this.num(this.edit.price);
                    const pool = this.poolEditPorsiMekanik();
                    const selected = this.edit.shares.filter(s => s.mechanic_id);
                    const totalRatio = selected.reduce((sum, s) => sum + this.ratioFor(s.mechanic_id), 0);
                    this.edit.shares.forEach((s) => {
                        if (s.touched) return;
                        if (!s.mechanic_id || !base || totalRatio <= 0) { s.amount = ''; return; }
                        s.amount = Math.round(pool * this.ratioFor(s.mechanic_id) / totalRatio);
                    });
                },
                onEditMechanicChange(share) { share.touched = false; this.syncEditShares(); },
                removeEditShare(i) { this.edit.shares.splice(i, 1); this.syncEditShares(); },
                editShareReference(share) {
                    if (!share.mechanic_id) return '';
                    const pct = this.ratioFor(share.mechanic_id);
                    return 'Bagian: ' + this.rupiah(share.amount) + ' (proporsional dari rasio ' + pct + '%)';
                },
                saveEditService() {
                    const row = this.cart[this.edit.key];
                    if (!row) return;
                    if (!this.edit.service_name || !this.edit.price) { this.notifyFail('Nama jasa dan biaya jasa wajib diisi.'); return; }
                    const shares = this.edit.shares.filter(s => s.mechanic_id && s.amount !== '' && s.amount !== null);
                    if (shares.length === 0) { this.notifyFail('Setiap jasa servis wajib memiliki minimal 1 mekanik pengerja.'); return; }
                    const totalShare = shares.reduce((sum, s) => sum + this.num(s.amount), 0);
                    const pool = this.poolEditPorsiMekanik();
                    if (pool > 0 && totalShare > pool + 0.001) {
                        this.notifyFail('Total nominal mekanik (' + this.rupiah(totalShare) + ') melebihi porsi mekanik (' + this.rupiah(pool) + ').');
                        return;
                    }
                    const name = this.edit.service_name;
                    row.service_name = name;
                    row.price = this.num(this.edit.price);
                    row.shares = shares.map(s => ({ mechanic_id: Number(s.mechanic_id), amount: this.num(s.amount) }));
                    row.label = '[Jasa] ' + name;
                    this.closeEdit();
                    this.notifySuccess('Jasa diperbarui', name + ' berhasil diubah.');
                },
                num(v) {
                    if (typeof v === 'number') return v;
                    const cleaned = String(v ?? '').replace(/[^\d-]/g, '');
                    const parsed = parseFloat(cleaned);
                    return Number.isFinite(parsed) ? parsed : 0;
                },
                formatRibuan(v) {
                    const raw = String(v ?? '').replace(/[^\d]/g, '');
                    if (raw === '') return '';
                    return Number(raw).toLocaleString('id-ID');
                },
                rupiah(v) { return 'Rp ' + (this.num(v)).toLocaleString('id-ID'); },
                rowSubtotal(row) { return this.num(row.price) * (row.type === 'service' ? 1 : (this.num(row.qty) || 1)); },
                grandTotal() { return Object.values(this.cart).reduce((sum, r) => sum + this.num(r.price) * (r.type === 'service' ? 1 : (this.num(r.qty) || 1)), 0); },
                terbayar() {
                    if (this.paymentStatus === 'lunas') return this.grandTotal();
                    if (this.paymentStatus === 'dp') return this.num(this.paidAmount);
                    return 0;
                },
                sisa() { return Math.round(this.grandTotal() - this.terbayar()); },
                focusScan() { this.$nextTick(() => this.$refs.scan?.focus()); },
                searchResults() {
                    const q = this.search.trim().toLowerCase();
                    if (!q) return [];
                    return this.products.filter(p => p.name.toLowerCase().includes(q));
                },
                ratioFor(mechanicId) {
                    const m = this.mechanics.find(x => String(x.id) === String(mechanicId));
                    return m ? Number(m.ratio) : 0;
                },
                // Porsi mekanik = rasio mekanik pertama yang dipilih × biaya jasa.
                // (Konsisten dengan snapshot mechanic_fee di server.)
                poolPorsiMekanik() {
                    const base = this.num(this.sv.price);
                    const first = this.sv.shares.find(s => s.mechanic_id);
                    if (!base || !first) return 0;
                    return Math.round(base * this.ratioFor(first.mechanic_id) / 100);
                },
                shareReference(share) {
                    if (!share.mechanic_id) return '';
                    const pct = this.ratioFor(share.mechanic_id);
                    return 'Bagian: ' + this.rupiah(share.amount) + ' (proporsional dari rasio ' + pct + '%)';
                },
                // Default: bagi porsi mekanik secara proporsional ke rasio tiap mekanik.
                syncShares() {
                    const base = this.num(this.sv.price);
                    const pool = this.poolPorsiMekanik();
                    const selected = this.sv.shares.filter(s => s.mechanic_id);
                    const totalRatio = selected.reduce((sum, s) => sum + this.ratioFor(s.mechanic_id), 0);

                    this.sv.shares.forEach((s) => {
                        if (s.touched) return;
                        if (!s.mechanic_id || !base || totalRatio <= 0) { s.amount = ''; return; }
                        s.amount = Math.round(pool * this.ratioFor(s.mechanic_id) / totalRatio);
                    });
                },
                onMechanicChange(share) {
                    share.touched = false;
                    this.syncShares();
                },
                markTouched(share) {
                    share.touched = true;
                },
                removeShare(i) {
                    this.sv.shares.splice(i, 1);
                    this.syncShares();
                },
                addProduct(id) {
                    if (!id) return;
                    const p = this.products.find(x => String(x.id) === String(id));
                    if (!p) return;
                    const key = 'p' + p.id;
                    if (this.cart[key]) {
                        this.cart[key].qty++;
                        this.notifySuccess('Produk ditambahkan', p.name + ' — jumlah sekarang ' + this.cart[key].qty + '.');
                    } else {
                        this.cart[key] = { type: 'product', product_id: p.id, label: p.name, price: p.price, qty: 1 };
                        this.notifySuccess('Produk ditambahkan', p.name + ' masuk ke keranjang.');
                    }
                    this.focusScan();
                },
                addByScan() {
                    const q = this.scan.trim().toLowerCase();
                    if (!q) return;
                    const p = this.products.find(x => x.code.toLowerCase() === q);
                    if (p) { this.addProduct(p.id); this.scan = ''; }
                    else { this.notifyFail('Produk dengan SKU "' + this.scan + '" tidak ditemukan. Periksa kembali kode/scan barcode.'); }
                },
                addExternal() {
                    if (!this.ep.name || !this.ep.selling_price) {
                        this.notifyFail('Nama produk dan harga jual produk luar wajib diisi.');
                        return;
                    }
                    const key = 'e' + Date.now();
                    this.cart[key] = { type: 'external', external_name: this.ep.name, purchase_price: this.num(this.ep.purchase_price), price: this.num(this.ep.selling_price), qty: Number(this.ep.qty||1), label: '[Luar] ' + this.ep.name };
                    this.notifySuccess('Produk luar ditambahkan', this.ep.name + ' masuk ke nota. Tidak masuk stok; HPP tercatat sebagai kas keluar saat transaksi final.');
                    this.ep = { name: '', qty: 1, purchase_price: '', selling_price: '' };
                },
                addService() {
                    if (!this.sv.service_name || !this.sv.price) {
                        this.notifyFail('Nama jasa dan biaya jasa wajib diisi.');
                        return;
                    }
                    const shares = this.sv.shares.filter(s => s.mechanic_id && s.amount !== '' && s.amount !== null);
                    if (shares.length === 0) {
                        this.notifyFail('Setiap jasa servis wajib memiliki minimal 1 mekanik pengerja. Pilih mekanik dulu.');
                        return;
                    }
                    const totalShare = shares.reduce((sum, s) => sum + this.num(s.amount), 0);
                    const pool = this.poolPorsiMekanik();
                    if (pool > 0 && totalShare > pool + 0.001) {
                        this.notifyFail('Total nominal mekanik (' + this.rupiah(totalShare) + ') melebihi porsi mekanik (' + this.rupiah(pool) + ').');
                        return;
                    }
                    const key = 's' + Date.now();
                    this.cart[key] = { type: 'service', service_name: this.sv.service_name, price: this.num(this.sv.price), qty: 1, shares: shares.map(s => ({mechanic_id: Number(s.mechanic_id), amount: this.num(s.amount)})), label: '[Jasa] ' + this.sv.service_name };
                    this.notifySuccess('Jasa ditambahkan', this.sv.service_name + ' masuk ke nota dengan ' + shares.length + ' mekanik.');
                    this.sv = { service_name: '', price: '', shares: [{ mechanic_id: '', amount: '', touched: false }] };
                },
                removeRow(key) { delete this.cart[key]; },
                isDraftSubmit(event) {
                    const btn = event.submitter;
                    if (btn && btn.dataset && btn.dataset.action === 'draft') return true;

                    return false;
                },
                onCheckout(event) {
                    // Tombol "Simpan Transaksi Sementara" punya alur sendiri.
                    if (this.isDraftSubmit(event)) {
                        this.onSaveDraft(event);
                        return;
                    }

                    this.prepare();

                    if (Object.keys(this.cart).length === 0) {
                        event.preventDefault();
                        this.notifyFail('Keranjang masih kosong. Tambahkan minimal 1 produk atau jasa sebelum menyelesaikan transaksi.');
                        return;
                    }

                    if (this.sisa() > 0) {
                        event.preventDefault();
                        this.notifyFail('Transaksi belum bisa diselesaikan. Sisa belum dibayar ' + this.rupiah(this.sisa()) + '.');
                    }
                },
                onSaveDraft(event) {
                    // Draft tidak butuh pembayaran; hanya butuh minimal 1 item.
                    this.prepare();

                    if (Object.keys(this.cart).length === 0) {
                        event.preventDefault();
                        this.notifyFail('Keranjang masih kosong. Tambahkan minimal 1 produk atau jasa sebelum menyimpan draft.');
                    }
                },
                prepare() {
                    const container = this.$refs.hidden;
                    container.innerHTML = '';
                    let pi = 0, ei = 0, si = 0;
                    for (const key in this.cart) {
                        const r = this.cart[key];
                        if (r.type === 'product') {
                            addHidden(container, `products[${pi}][product_id]`, r.product_id);
                            addHidden(container, `products[${pi}][qty]`, r.qty);
                            pi++;
                        } else if (r.type === 'external') {
                            addHidden(container, `external_products[${ei}][name]`, r.external_name);
                            addHidden(container, `external_products[${ei}][qty]`, r.qty);
                            addHidden(container, `external_products[${ei}][purchase_price]`, r.purchase_price);
                            addHidden(container, `external_products[${ei}][selling_price]`, r.price);
                            ei++;
                        } else if (r.type === 'service') {
                            addHidden(container, `services[${si}][service_name]`, r.service_name);
                            addHidden(container, `services[${si}][price]`, r.price);
                            r.shares.forEach((sh, k) => {
                                addHidden(container, `services[${si}][shares][${k}][mechanic_id]`, sh.mechanic_id);
                                addHidden(container, `services[${si}][shares][${k}][amount]`, sh.amount);
                            });
                            si++;
                        }
                    }
                }
            };
        }
        function addHidden(parent, name, value) {
            const i = document.createElement('input');
            i.type = 'hidden';
            i.name = name;
            i.value = value;
            parent.appendChild(i);
        }
    </script>
    @endpush
@endonce
