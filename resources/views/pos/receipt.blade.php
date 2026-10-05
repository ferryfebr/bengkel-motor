<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $transaction->invoice_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/receipt-share.js'])
    @php
        $paper = in_array(request('paper'), ['57', '58', '80'], true) ? request('paper') : '58';
        $contentWidth = (int) $paper;
    @endphp
    @php
        $logoPath = collect(['images/logo-watermark.png', 'images/logo.png'])
            ->map(fn ($p) => public_path($p))
            ->first(fn ($p) => is_file($p));
        $logo = $logoPath ? 'data:image/'.(str_ends_with(strtolower($logoPath), '.png') ? 'png' : 'jpeg').';base64,'.base64_encode(file_get_contents($logoPath)) : null;
    @endphp
    <style>
        html, body { -webkit-text-size-adjust: 100%; }
        .receipt {
            width: {{ $contentWidth }}mm;
            margin-left: auto;
            margin-right: auto;
            text-align: left;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: {{ $paper === '80' ? '17px' : '13.5px' }};
            line-height: 1.25;
            color: #000;
            background: #fff;
        }
        .receipt > div { margin-bottom: 2px; }
        .receipt .dash {
            border: 0;
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .receipt-logo {
            display: block;
            width: {{ $paper === '80' ? '38mm' : '31mm' }};
            margin: 0 auto 3mm;
        }
        .receipt .item-name,
        .receipt .item-line { line-height: 1.2; }
        .receipt .item-name { margin-bottom: 1px; }
        .receipt .item-line { padding-bottom: 2px; }
        @media print {
            @page { size: {{ $paper }}mm auto; margin: 0mm; }
            html, body {
                width: auto;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            body > :not(.receipt) { display: none !important; }
            .receipt {
                width: {{ $paper }}mm !important;
                margin: 0 auto !important;
                padding: 0 !important;
                box-shadow: none !important;
                font-size: {{ $paper === '80' ? '17pt' : '14pt' }} !important;
                line-height: 1.4 !important;
            }
            .receipt * { font-size: inherit !important; }
            .receipt div,
            .receipt p,
            .receipt table,
            .receipt tr,
            .receipt td,
            .receipt span,
            .receipt img {
                page-break-inside: avoid !important;
                page-break-before: auto !important;
                page-break-after: auto !important;
            }
            .receipt .dash { border-top: 1px dashed #000 !important; }
            * { color: #000 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-paper-dim py-6 flex flex-col items-center px-3">
    <div class="no-print mb-4 flex flex-wrap justify-center items-center gap-2">
        <button onclick="bagikanStruk()" class="btn-primary text-sm">Cetak Struk</button>
        <a href="{{ route('pos.receipt.pdf', $transaction) }}"
           class="btn-secondary text-sm">Unduh PDF</a>
        <a href="{{ route('work-orders.show', $transaction) }}" class="btn-secondary text-sm">Kembali</a>
    </div>

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
                <div class="font-bold">{{ config('app.name') }}</div>
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
                <div class="item-name">{{ $d->displayName() }}</div>
                <div class="item-line flex justify-between">
                    <span>{{ $d->qty }} x {{ number_format($d->selling_price, 0, ',', '.') }}</span>
                    <span>{{ number_format($d->line_total, 0, ',', '.') }}</span>
                </div>
            @endforeach

            @foreach ($transaction->services as $s)
                <div class="item-name">{{ $s->service_name }}</div>
                <div class="item-line flex justify-between">
                    <span>1 x {{ number_format($s->service_price, 0, ',', '.') }}</span>
                    <span>{{ number_format($s->service_price, 0, ',', '.') }}</span>
                </div>
            @endforeach

            {{-- 10. Garis pemisah --}}
            <div class="dash"></div>

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

            {{-- 16. Status pembayaran disembunyikan --}}

            <div class="dash"></div>
            <div class="text-center">Terima kasih</div>
        </div>
</body>
</html>