<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $transaction->invoice_number }}</title>
    @vite(['resources/css/app.css'])
    @php $paper = request('paper') === '80' ? '80' : '58'; @endphp
    @php
        $logoPath = collect(['images/logo-watermark.png', 'images/logo.png'])
            ->map(fn ($p) => public_path($p))
            ->first(fn ($p) => is_file($p));
        $logo = $logoPath ? 'data:image/'.(str_ends_with(strtolower($logoPath), '.png') ? 'png' : 'jpeg').';base64,'.base64_encode(file_get_contents($logoPath)) : null;
    @endphp
    <style>
        html, body { -webkit-text-size-adjust: 100%; }
        .receipt {
            width: {{ $paper }}mm;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: {{ $paper === '80' ? '12px' : '11px' }};
            line-height: 1.55;
            color: #000;
            background: #fff;
        }
        .receipt > div { margin-bottom: 2px; }
        .receipt .dash {
            border: 0;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .receipt-logo {
            display: block;
            width: {{ $paper === '80' ? '26mm' : '20mm' }};
            margin: 0 auto 2mm;
        }
        @media print {
            @page { margin: 0; size: {{ $paper }}mm auto; }
            html, body { width: {{ $paper }}mm; margin: 0; padding: 0; background: #fff; }
            .no-print { display: none !important; }
            .receipt { width: {{ $paper }}mm; padding: 0 2mm; box-shadow: none; }
            .receipt .dash { border-top-color: #000 !important; }
            * { color: #000 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-paper-dim py-6">
    <div class="max-w-sm mx-auto">
        <div class="no-print mb-4 flex flex-wrap justify-center items-center gap-2">
            <button onclick="window.print()" class="btn-primary text-sm">Cetak Struk</button>
            <a href="{{ route('pos.receipt', ['transaction' => $transaction, 'paper' => '58']) }}"
               class="btn-secondary text-sm {{ $paper === '58' ? 'ring-2 ring-ink' : '' }}">58mm</a>
            <a href="{{ route('pos.receipt', ['transaction' => $transaction, 'paper' => '80']) }}"
               class="btn-secondary text-sm {{ $paper === '80' ? 'ring-2 ring-ink' : '' }}">80mm</a>
            <a href="{{ route('work-orders.show', $transaction) }}" class="btn-secondary text-sm">Kembali</a>
        </div>

        <p class="no-print text-center text-xs text-ink-500 mb-3 max-w-xs mx-auto">
            Pada dialog cetak: pilih printer thermal, ukuran kertas {{ $paper }}mm, margin <strong>None</strong>,
            dan matikan <strong>Headers/footers</strong>.
        </p>

        @php
            $customerAddress = $transaction->customer_address ?? null;
            $grandTotal = (float) $transaction->grand_total;
            $paid = (float) $transaction->paid_amount;
            $totalQty = $transaction->details->sum('qty') + $transaction->services->count();
            $subTotal = (float) $transaction->subtotal_products + (float) $transaction->subtotal_services;
            $change = round($paid - $grandTotal, 2);
            $isCash = ($transaction->payment_method ?? 'cash') === 'cash';
            $remaining = $transaction->remainingAmount();
        @endphp

        <div class="receipt bg-white mx-auto p-3">
            {{-- 1-2. Logo + nama + alamat bengkel --}}
            <div class="text-center">
                @if ($logo)
                    <img class="receipt-logo" src="{{ $logo }}" alt="{{ config('app.name') }}">
                @endif
                <div class="font-bold text-sm">{{ config('app.name') }}</div>
                <div>Jl. Imam Bonjol Km.2, kel. Bungin Timur, LUWUK</div>
            </div>

            {{-- 3. Garis pemisah --}}
            <div class="dash"></div>

            {{-- 4. Invoice (kiri) + tanggal/jam (kanan) --}}
            <div class="flex justify-between">
                <span>{{ $transaction->invoice_number }}</span>
                <span>{{ $transaction->created_at->format('d/m/Y H:i') }}</span>
            </div>

            {{-- 5. Plat & motor --}}
            <div>Plat&nbsp;: {{ $transaction->plate_number }}</div>
            <div>Motor: {{ $transaction->motor_type ?: '-' }}</div>

            <div>Pelanggan: {{ $transaction->customer_name }}</div>
            <div>Kasir: {{ $transaction->cashier?->name }}</div>

            {{-- 7. Alamat customer (hanya bila terisi) --}}
            @if (! empty($customerAddress))
                <div>Alamat: {{ $customerAddress }}</div>
            @endif

            {{-- 8. Garis pemisah --}}
            <div class="dash"></div>

            {{-- 9. Daftar item --}}
            @foreach ($transaction->details as $d)
                <div>{{ $d->displayName() }}</div>
                <div class="flex justify-between">
                    <span>{{ $d->qty }} x {{ number_format($d->selling_price, 0, ',', '.') }}</span>
                    <span>{{ number_format($d->line_total, 0, ',', '.') }}</span>
                </div>
            @endforeach

            @foreach ($transaction->services as $s)
                <div>{{ $s->service_name }}</div>
                <div class="flex justify-between">
                    <span>1 x {{ number_format($s->service_price, 0, ',', '.') }}</span>
                    <span>{{ number_format($s->service_price, 0, ',', '.') }}</span>
                </div>
            @endforeach

            {{-- 10. Garis pemisah --}}
            <div class="dash"></div>

            {{-- 11. Total qty --}}
            <div class="flex justify-between">
                <span>Total Qty</span>
                <span>{{ $totalQty }}</span>
            </div>

            {{-- 12. Sub Total --}}
            <div class="flex justify-between">
                <span>Sub Total</span>
                <span>{{ number_format($subTotal, 0, ',', '.') }}</span>
            </div>

            {{-- 13. Total --}}
            <div class="flex justify-between font-bold">
                <span>TOTAL</span>
                <span>{{ number_format($grandTotal, 0, ',', '.') }}</span>
            </div>

            @if ($transaction->returns->isNotEmpty())
                <div class="flex justify-between">
                    <span>Refund</span>
                    <span>-{{ number_format($transaction->returns->sum('total'), 0, ',', '.') }}</span>
                </div>
            @endif

            {{-- 14. Bayar (metode) + nominal --}}
            <div class="flex justify-between">
                <span>Bayar ({{ strtoupper($transaction->payment_method ?? '-') }})</span>
                <span>{{ number_format($paid, 0, ',', '.') }}</span>
            </div>

            {{-- 15. Kembali (hanya tunai & ada kelebihan) --}}
            @if ($isCash && $change > 0)
                <div class="flex justify-between">
                    <span>Kembali</span>
                    <span>{{ number_format($change, 0, ',', '.') }}</span>
                </div>
            @endif

            {{-- 16. Status pembayaran --}}
            <div class="text-xs mt-1">
                @if ($transaction->payment_status === 'lunas')
                    Status: LUNAS
                @elseif ($transaction->payment_status === 'dp')
                    Status: DP (Sisa: Rp {{ number_format($remaining, 0, ',', '.') }})
                @else
                    Status: BELUM BAYAR (Sisa: Rp {{ number_format($remaining, 0, ',', '.') }})
                @endif
            </div>

            <div class="dash"></div>
            <div class="text-center">Terima kasih</div>
        </div>
    </div>
</body>
</html>