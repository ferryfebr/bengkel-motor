@php
    $logoPath = collect(['images/logo.JPEG', 'images/logo.png'])
        ->map(fn ($p) => public_path($p))
        ->first(fn ($p) => is_file($p));
    $logo = $logoPath ? 'data:image/'.(str_ends_with(strtolower($logoPath), '.png') ? 'png' : 'jpeg').';base64,'.base64_encode(file_get_contents($logoPath)) : null;

    $customerAddress = $transaction->customer_address ?? null;
    $grandTotal = (float) $transaction->grand_total;
    $paid = (float) $transaction->paid_amount;
    $totalQty = $transaction->details->sum('qty') + $transaction->services->count();
    $subTotal = (float) $transaction->subtotal_products + (float) $transaction->subtotal_services;
    $change = round($paid - $grandTotal, 2);
    $isCash = ($transaction->payment_method ?? 'cash') === 'cash';
    $remaining = $transaction->remainingAmount();
    $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: "DejaVu Sans Mono", monospace; color: #000; }
        body { margin: 0; padding: 0 4mm; text-align: left; font-size: {{ $paper === '80' ? '13px' : '11px' }}; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; text-align: left; }
        .r { text-align: right; }
        .c { text-align: center; }
        .bold { font-weight: bold; }
        .dash { border-top: 1px dashed #000; margin: 4px 0; }
        .logo { display: block; margin: 0 auto 3px; width: {{ $paper === '80' ? '78px' : '62px' }}; }
    </style>
</head>
<body>
    <div class="c">
        @if ($logo)
            <img class="logo" src="{{ $logo }}" alt="">
        @endif
        <div class="bold">{{ config('app.name') }}</div>
        <div>Jl. Imam Bonjol Km.2, kel. Bungin Timur, LUWUK</div>
    </div>

    <div class="dash"></div>

    <table>
        <tr><td>{{ $transaction->invoice_number }}</td><td class="r">{{ $transaction->created_at->format('d/m/Y H:i') }}</td></tr>
    </table>
    <div>Plat&nbsp;: {{ $transaction->plate_number }}</div>
    <div>Motor: {{ $transaction->motor_type ?: '-' }}</div>
    <table>
        <tr><td>Kasir: {{ $transaction->cashier?->name }}</td><td class="r">{{ $transaction->customer_name }}</td></tr>
    </table>
    @if (! empty($customerAddress))
        <div>Alamat: {{ $customerAddress }}</div>
    @endif

    <div class="dash"></div>

    @foreach ($transaction->details as $d)
        <div>{{ $d->displayName() }}</div>
        <table>
            <tr><td>{{ $d->qty }} x {{ $rp($d->selling_price) }}</td><td class="r">{{ $rp($d->line_total) }}</td></tr>
        </table>
    @endforeach

    @foreach ($transaction->services as $s)
        <div>{{ $s->service_name }}</div>
        <table>
            <tr><td>1 x {{ $rp($s->service_price) }}</td><td class="r">{{ $rp($s->service_price) }}</td></tr>
        </table>
    @endforeach

    <div class="dash"></div>

    <table>
        <tr><td>Sub Total</td><td class="r">{{ $rp($subTotal) }}</td></tr>
        <tr class="bold"><td>TOTAL</td><td class="r">{{ $rp($grandTotal) }}</td></tr>
        @if ($transaction->returns->isNotEmpty())
            <tr><td>Refund</td><td class="r">-{{ $rp($transaction->returns->sum('total')) }}</td></tr>
        @endif
        <tr><td>Bayar ({{ strtoupper($transaction->payment_method ?? '-') }})</td><td class="r">{{ $rp($paid) }}</td></tr>
        @if ($isCash && $change > 0)
            <tr><td>Kembali</td><td class="r">{{ $rp($change) }}</td></tr>
        @endif
    </table>

    <div class="dash"></div>
    <div class="c">Terima kasih</div>
</body>
</html>