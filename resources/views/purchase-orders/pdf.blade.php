@php
    $wmPath = public_path('images/logo-watermark.png');
    $watermark = is_file($wmPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($wmPath)) : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Order Produk {{ $order->po_number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body {
            font-size: 12px; color: #000; margin: 0;
        }
        .watermark {
            position: absolute;
            top: 20px;
            left: 0;
            width: 100%;
            text-align: center;
            opacity: 0.2;
        }
        .watermark img { width: 380px; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { font-size: 20px; font-weight: bold; }
        .muted { color: #444; font-size: 11px; }
        .title { text-align: center; font-size: 16px; font-weight: bold; letter-spacing: 1px; margin: 12px 0 4px; }
        .docnum { text-align: center; font-size: 11px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .items th, .items td { border: 1px solid #000; padding: 6px; }
        .items th { background: #eee; text-align: left; }
        .right { text-align: right; }
        .total-row td { font-weight: bold; }
        .sign { margin-top: 48px; width: 100%; }
        .sign td { width: 50%; text-align: center; padding-top: 40px; }
        .sign .line { border-top: 1px solid #000; margin: 0 40px; padding-top: 4px; }
    </style>
</head>
<body>
    @if ($watermark)
        <div class="watermark"><img src="{{ $watermark }}" alt=""></div>
    @endif

    <div class="header">
        <div class="brand">{{ config('app.name', 'One Nine Nine') }}</div>
        <div class="muted">One Nine Nine Motor</div>
    </div>

    <div class="title">ORDER PRODUK</div>
    <div class="docnum"> {{ $order->created_at->format('d/m/Y') }}</div>

    <table class="meta">
        <tr>
            <td width="55%">
                <strong>Kepada:</strong><br>
                {{ $order->supplier?->name ?? '-' }}<br>
                @if ($order->supplier?->address){{ $order->supplier->address }}<br>@endif
                @if ($order->supplier?->phone)Telp: {{ $order->supplier->phone }}@endif
            </td>
            <td width="45%">
                <strong>Dari:</strong><br>
                {{ config('app.name', 'One Nine Nine') }}<br>
                Dibuat oleh: {{ $order->user?->name }}<br>
                Tanggal: {{ $order->created_at->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    <br>

    <table class="items">
        <thead>
            <tr>
                <th width="6%">No</th>
                <th>Nama Produk</th>
                <th width="10%" class="right">Jumlah</th>
                {{-- <th width="22%" class="right">Harga Beli</th>
                <th width="22%" class="right">Subtotal</th> --}}
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td class="right">{{ $item->qty }}</td>
                    {{-- <td class="right">Rp {{ number_format($item->purchase_price, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($item->line_total, 0, ',', '.') }}</td> --}}
                </tr>
            @endforeach
            <tr class="total-row">
                {{-- <td colspan="4" class="right">TOTAL</td> --}}
                {{-- <td class="right">Rp {{ number_format($order->total, 0, ',', '.') }}</td> --}}
            </tr>
        </tbody>
    </table>

    @if ($order->notes)
        <p style="margin-top:14px"><strong>Catatan:</strong> {{ $order->notes }}</p>
    @endif

    {{-- <table class="sign">
        <tr>
            <td>
                <div class="line">Pemesan</div>
            </td>
            <td>
                <div class="line">Distributor</div>
            </td>
        </tr>
    </table> --}}
</body>
</html>