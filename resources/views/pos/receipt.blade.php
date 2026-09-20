<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $transaction->invoice_number }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            @page { margin: 0; size: 58mm auto; }
            body { margin: 0; }
            .no-print { display: none !important; }
        }
        .receipt { width: 58mm; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11px; }
    </style>
</head>
<body class="bg-paper-dim py-6">
    <div class="max-w-sm mx-auto">
        <div class="no-print mb-4 flex justify-center gap-2">
            <button onclick="window.print()" class="btn-primary text-sm">Cetak Struk</button>
            <a href="{{ route('work-orders.show', $transaction) }}" class="btn-secondary text-sm">Kembali</a>
        </div>

        <div class="receipt bg-white mx-auto p-3">
            <div class="text-center">
                <div class="font-bold text-sm">{{ config('app.name') }}</div>
                <div>Struk Pembayaran</div>
            </div>
            <div class="border-t border-dashed my-1"></div>
            <div>No : {{ $transaction->invoice_number }}</div>
            <div>Tgl: {{ $transaction->created_at->format('d/m/Y H:i') }}</div>
            <div>Plat: {{ $transaction->plate_number }}</div>
            <div>Cust: {{ $transaction->customer_name }}</div>
            <div>Kasir: {{ $transaction->cashier?->name }}</div>
            <div class="border-t border-dashed my-1"></div>

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
                    <span>Jasa</span>
                    <span>{{ number_format($s->service_price, 0, ',', '.') }}</span>
                </div>
            @endforeach

            <div class="border-t border-dashed my-1"></div>
            <div class="flex justify-between font-bold">
                <span>TOTAL</span>
                <span>{{ number_format($transaction->grand_total, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between">
                <span>Bayar</span>
                <span>{{ strtoupper(str_replace('_', ' ', $transaction->payment_status)) }} / {{ strtoupper($transaction->payment_method ?? '-') }}</span>
            </div>
            <div class="border-t border-dashed my-1"></div>
            <div class="text-center">Terima kasih</div>
        </div>
    </div>
</body>
</html>