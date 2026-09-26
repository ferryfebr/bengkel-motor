<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">Work Order — {{ $transaction->plate_number }}</h1>
            <a href="{{ route('work-orders.index') }}" class="btn-secondary shrink-0">← Kembali</a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto space-y-4">
        {{-- Saat form POS tampil, notifikasi ditangani modal di dalam form. --}}
        @if ($transaction->isFinal())
            @if (session('status'))
                <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ session('error') }}</div>
            @endif
        @endif

        <div class="bg-paper border border-line rounded-md p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span class="text-ink-soft">Invoice:</span> <span class="font-mono text-ink">{{ $transaction->invoice_number }}</span></div>
            <div><span class="text-ink-soft">Kasir:</span> <span class="text-ink">{{ $transaction->cashier?->name }}</span></div>
            <div><span class="text-ink-soft">Plat:</span> <span class="font-semibold text-ink">{{ $transaction->plate_number }}</span></div>
            <div><span class="text-ink-soft">Jenis Motor:</span> <span class="text-ink">{{ $transaction->motor_type ?: '-' }}</span></div>
            <div><span class="text-ink-soft">Customer:</span> <span class="text-ink">{{ $transaction->customer_name }}</span></div>
            <div class="md:col-span-2"><span class="text-ink-soft">Keluhan:</span> <span class="text-ink">{{ $transaction->complaint ?: '-' }}</span></div>
            <div><span class="text-ink-soft">Dibuat:</span> <span class="text-ink">{{ $transaction->created_at->format('d/m/Y H:i') }}</span></div>
            <div class="md:col-span-2 flex flex-wrap items-center gap-4">
                <span class="flex items-center gap-2">
                    <span class="text-ink-soft">Pengerjaan:</span>
                    @php
                        [$wCls, $wIco, $wLbl] = match ($transaction->work_status) {
                            'selesai' => ['bg-success-light text-success', '✅', 'Selesai'],
                            'proses' => ['bg-ink text-paper', '🔧', 'Dikerjakan'],
                            default => ['bg-paper-dim text-ink-700', '🟡', 'Antre'],
                        };
                    @endphp
                    <span class="badge {{ $wCls }}">{{ $wIco }} {{ $wLbl }}</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="text-ink-soft">Pembayaran:</span>
                    @php
                        [$pCls, $pIco, $pLbl] = match ($transaction->payment_status) {
                            'lunas' => ['bg-success-light text-success', '✅', 'Lunas'],
                            'dp' => ['bg-paper-dim text-ink-700', '🟡', 'DP'],
                            default => ['bg-danger-light text-danger', '⏳', 'Belum Bayar'],
                        };
                    @endphp
                    <span class="badge {{ $pCls }}">{{ $pIco }} {{ $pLbl }}</span>
                </span>
            </div>
        </div>

        @if (! $transaction->isFinal())
            <div class="bg-paper border border-line rounded-md p-6">
                <h3 class="font-semibold text-ink mb-3">Ubah Status Pengerjaan</h3>
                <form method="POST" action="{{ route('work-orders.status', $transaction) }}" class="flex flex-wrap items-center gap-2">
                    @csrf @method('PATCH')
                    @foreach (['antre' => '🟡 Antre', 'proses' => '🔧 Sedang Dikerjakan', 'selesai' => '✅ Selesai'] as $val => $label)
                        <button name="work_status" value="{{ $val }}"
                                class="px-4 py-2.5 min-h-[44px] rounded-md text-sm font-medium border transition
                                {{ $transaction->work_status === $val ? 'bg-ink text-paper border-ink' : 'bg-paper text-ink-700 border-line hover:bg-paper-dim' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </form>
            </div>
        @else
            <div class="bg-paper-dim border border-line text-ink-600 px-4 py-3 rounded-md text-sm">
                Transaksi ini sudah final. Data transaksi final bersifat append-only (tidak dapat diubah).
            </div>
        @endif

        {{-- Rincian transaksi (hasil checkout). Saat belum final, rincian sudah tampil di form POS. --}}
        @if ($transaction->isFinal() && ($transaction->details->isNotEmpty() || $transaction->services->isNotEmpty()))
            <div class="bg-paper border border-line rounded-md p-6">
                <div class="flex items-center justify-between mb-3 gap-3">
                    <h3 class="font-semibold text-ink">Rincian</h3>
                    <a href="{{ route('pos.receipt', $transaction) }}" class="btn-secondary">
                        Lihat / Cetak Struk
                    </a>
                </div>

                <table class="min-w-full font-condensed text-sm mt-2">
                    <tbody class="divide-y divide-line">
                        @foreach ($transaction->details as $d)
                            <tr>
                                <td class="py-2 text-ink">{{ $d->displayName() }}</td>
                                <td class="py-2 text-right text-ink-soft tabular">{{ $d->qty }} × {{ number_format($d->selling_price, 0, ',', '.') }}</td>
                                <td class="py-2 text-right w-32 text-ink tabular">{{ number_format($d->line_total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        @foreach ($transaction->services as $s)
                            <tr>
                                <td class="py-2 text-ink">{{ $s->service_name }}</td>
                                <td class="py-2 text-right text-ink-soft">Jasa</td>
                                <td class="py-2 text-right w-32 text-ink tabular">{{ number_format($s->service_price, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr class="font-semibold text-ink">
                            <td class="py-3" colspan="2">TOTAL</td>
                            <td class="py-3 text-right font-num tabular text-xl">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Refund produk stok (hanya transaksi final) --}}
        @if ($transaction->isFinal())
            <div class="bg-paper border border-line rounded-md p-6">
                <h3 class="font-semibold text-ink mb-1">Refund / Retur Produk</h3>
                <p class="text-xs text-ink-500 mb-3">Hanya produk stok bengkel yang bisa direfund. Produk luar &amp; jasa tidak bisa.</p>

                @php
                    $refundableDetails = $transaction->details->filter(fn ($d) => $d->refundableQty() > 0);
                @endphp

                @if ($refundableDetails->isNotEmpty())
                    <form method="POST" action="{{ route('work-orders.refund', $transaction) }}" class="space-y-4"
                          onsubmit="return confirm('Proses refund ini? Stok & kas akan disesuaikan.');">
                        @csrf
                        <div class="overflow-x-auto">
                            <table class="min-w-full font-condensed text-sm">
                                <thead class="bg-paper-dim text-ink">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold">Produk</th>
                                        <th class="px-4 py-2 text-right font-semibold">Harga</th>
                                        <th class="px-4 py-2 text-right font-semibold">Dibeli</th>
                                        <th class="px-4 py-2 text-right font-semibold">Sudah Refund</th>
                                        <th class="px-4 py-2 text-right font-semibold">Qty Refund</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($refundableDetails as $d)
                                        <tr>
                                            <td class="px-4 py-2 text-ink">{{ $d->displayName() }}</td>
                                            <td class="px-4 py-2 text-right tabular text-ink-600">{{ number_format($d->selling_price, 0, ',', '.') }}</td>
                                            <td class="px-4 py-2 text-right tabular text-ink-600">{{ $d->qty }}</td>
                                            <td class="px-4 py-2 text-right tabular text-ink-600">{{ $d->refundedQty() }}</td>
                                            <td class="px-4 py-2 text-right">
                                                <input type="hidden" name="items[{{ $loop->index }}][transaction_detail_id]" value="{{ $d->id }}">
                                                <input type="number" name="items[{{ $loop->index }}][qty]" min="0" max="{{ $d->refundableQty() }}" value="0"
                                                       class="w-24 text-right border-line focus:border-signal focus:ring-signal rounded-md min-h-[40px]" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div>
                            <x-input-label for="reason" value="Alasan Refund (opsional)" />
                            <x-text-input id="reason" name="reason" type="text" class="mt-1 block w-full"
                                          :value="old('reason')" placeholder="mis. barang tidak cocok / rusak" />
                        </div>

                        <x-primary-button>Proses Refund</x-primary-button>
                    </form>
                @else
                    <p class="text-sm text-ink-400">Tidak ada produk stok yang bisa direfund (semua sudah direfund atau transaksi tanpa produk stok).</p>
                @endif

                @if ($transaction->returns->isNotEmpty())
                    <div class="mt-5">
                        <h4 class="font-semibold text-ink mb-2 text-sm">Riwayat Refund</h4>
                        @foreach ($transaction->returns as $ret)
                            <div class="border border-danger/40 bg-danger-light/40 rounded-md p-3 mb-2">
                                <div class="flex flex-wrap justify-between gap-2 text-sm">
                                    <span class="text-ink-700">{{ $ret->created_at->format('d/m/Y H:i') }} · {{ $ret->user?->name }}</span>
                                    <span class="font-semibold text-danger">Rp {{ number_format($ret->total, 0, ',', '.') }}</span>
                                </div>
                                <ul class="text-xs text-ink-600 mt-1 space-y-0.5">
                                    @foreach ($ret->items as $it)
                                        <li>{{ $it->product?->name ?? 'Produk' }} × {{ $it->qty }} — Rp {{ number_format($it->line_total, 0, ',', '.') }}</li>
                                    @endforeach
                                </ul>
                                @if ($ret->reason)
                                    <div class="text-xs text-ink-500 mt-1">Alasan: {{ $ret->reason }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Form POS langsung tampil untuk transaksi yang belum final --}}
        @if (! $transaction->isFinal())
            <div class="bg-paper border border-line rounded-md p-6">
                <h3 class="font-semibold text-ink mb-1">Rincian &amp; POS</h3>
                <p class="text-sm text-ink-soft mb-4">Tambah produk/jasa. Tekan "Simpan Transaksi Sementara" untuk menunda, atau "Selesaikan &amp; Cetak" bila motor sudah selesai dan sudah lunas.</p>

                @include('pos._form', [
                    'transaction' => $transaction,
                    'products' => $products,
                    'mechanics' => $mechanics,
                ])
            </div>
        @endif
    </div>
</x-app-layout>