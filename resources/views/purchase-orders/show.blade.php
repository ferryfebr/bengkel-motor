<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">PO — {{ $order->po_number }}</h1>
            <a href="{{ route('purchase-orders.index') }}" class="btn-secondary shrink-0">← Pesanan Pembelian</a>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ session('error') }}</div>
        @endif

        <div class="bg-white border border-line rounded-md p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span class="text-ink-soft">No PO:</span> <span class="font-mono text-ink">{{ $order->po_number }}</span></div>
            <div><span class="text-ink-soft">Tanggal:</span> <span class="text-ink">{{ $order->created_at->format('d/m/Y H:i') }}</span></div>
            <div><span class="text-ink-soft">Distributor:</span> <span class="text-ink">{{ $order->supplier?->name ?? '-' }}</span></div>
            <div><span class="text-ink-soft">Dibuat oleh:</span> <span class="text-ink">{{ $order->user?->name }}</span></div>
            <div class="flex items-center gap-2">
                <span class="text-ink-soft">Status:</span>
                @php
                    [$cls, $lbl] = match ($order->status) {
                        'diterima' => ['bg-success-light text-success', '✅ Diterima'],
                        'dikirim' => ['bg-paper-dim text-ink-700', '🚚 Dikirim'],
                        'dibatalkan' => ['bg-paper-dim text-ink-500', '✖ Dibatalkan'],
                        default => ['bg-danger-light text-danger', '📝 Draft'],
                    };
                @endphp
                <span class="badge {{ $cls }}">{{ $lbl }}</span>
            </div>
            @if ($order->isReceived())
                <div><span class="text-ink-soft">Diterima:</span> <span class="text-ink">{{ $order->received_at?->format('d/m/Y H:i') }} ({{ $order->receiver?->name }})</span></div>
            @endif
            @if ($order->notes)
                <div class="md:col-span-2"><span class="text-ink-soft">Catatan:</span> <span class="text-ink">{{ $order->notes }}</span></div>
            @endif
        </div>

        <div class="bg-white border border-line rounded-md overflow-hidden">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Produk</th>
                        <th class="px-4 py-3 text-right font-semibold">Qty</th>
                        <th class="px-4 py-3 text-right font-semibold">Harga Beli</th>
                        <th class="px-4 py-3 text-right font-semibold">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="px-4 py-2.5 text-ink">{{ $item->product_name }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-600">{{ $item->qty }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-600">Rp {{ number_format($item->purchase_price, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-paper-dim font-semibold text-ink">
                        <td colspan="3" class="px-4 py-3">TOTAL</td>
                        <td class="px-4 py-3 text-right tabular text-lg">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bg-white border border-line rounded-md p-4 flex flex-wrap items-center gap-3">
            <a href="{{ route('purchase-orders.print', $order) }}" target="_blank" class="btn-primary">Cetak / Simpan PDF</a>
            <a href="{{ route('purchase-orders.pdf', $order) }}" class="btn-secondary">Unduh PDF</a>

            @if ($order->isReceived())
                <span class="text-sm text-success font-medium">Barang sudah diterima &amp; stok ditambahkan.</span>
            @elseif ($order->isCancelled())
                <span class="text-sm text-ink-500 font-medium">PO ini sudah dibatalkan.</span>
            @else
                <form method="POST" action="{{ route('purchase-orders.status', $order) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $order->status === 'draft' ? 'dikirim' : 'draft' }}">
                    <x-secondary-button type="submit">
                        {{ $order->status === 'draft' ? 'Tandai Sudah Dikirim' : 'Kembalikan ke Draft' }}
                    </x-secondary-button>
                </form>

                <form method="POST" action="{{ route('purchase-orders.receive', $order) }}"
                      onsubmit="return confirm('Terima barang PO ini? Stok produk akan ditambahkan otomatis.');">
                    @csrf
                    <x-primary-button type="submit">Terima Barang</x-primary-button>
                </form>

                <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}"
                      onsubmit="return confirm('Batalkan PO ini? PO tidak akan diproses lagi.');">
                    @csrf
                    <button type="submit" class="btn-danger px-4">Batalkan PO</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>