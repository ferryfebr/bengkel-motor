<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">Work Order — {{ $transaction->plate_number }}</h1>
            <a href="{{ route('work-orders.index') }}" class="shrink-0 text-sm font-medium text-ink-soft hover:text-ink hover:underline">← Kembali</a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ session('error') }}</div>
        @endif

        <div class="bg-paper border border-line rounded-md p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span class="text-ink-soft">Invoice:</span> <span class="font-mono text-ink">{{ $transaction->invoice_number }}</span></div>
            <div><span class="text-ink-soft">Kasir:</span> <span class="text-ink">{{ $transaction->cashier?->name }}</span></div>
            <div><span class="text-ink-soft">Plat:</span> <span class="font-semibold text-ink">{{ $transaction->plate_number }}</span></div>
            <div><span class="text-ink-soft">Customer:</span> <span class="text-ink">{{ $transaction->customer_name }}</span></div>
            <div class="md:col-span-2"><span class="text-ink-soft">Keluhan:</span> <span class="text-ink">{{ $transaction->complaint ?: '-' }}</span></div>
            <div><span class="text-ink-soft">Dibuat:</span> <span class="text-ink">{{ $transaction->created_at->format('d/m/Y H:i') }}</span></div>
            <div>
                <span class="text-ink-soft">Status:</span>
                @if ($transaction->isFinal())
                    <span class="badge bg-success-light text-success">✅ Final</span>
                @else
                    <span class="badge bg-signal-100 text-ink-700">🟡 Belum final</span>
                @endif
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
                                {{ $transaction->work_status === $val ? 'bg-signal text-ink border-signal' : 'bg-paper text-ink-700 border-line hover:bg-paper-dim' }}">
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

        {{-- Rincian transaksi (hasil checkout) --}}
        @if ($transaction->details->isNotEmpty() || $transaction->services->isNotEmpty())
            <div class="bg-paper border border-line rounded-md p-6">
                <div class="flex items-center justify-between mb-3 gap-3">
                    <h3 class="font-semibold text-ink">Rincian</h3>
                    @if ($transaction->isFinal())
                        <a href="{{ route('pos.receipt', $transaction) }}" class="btn-secondary">
                            Lihat / Cetak Struk
                        </a>
                    @endif
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

        {{-- Form POS langsung tampil untuk transaksi yang belum final --}}
        @if (! $transaction->isFinal())
            <div class="bg-paper border border-line rounded-md p-6">
                <h3 class="font-semibold text-ink mb-1">Rincian &amp; POS</h3>
                <p class="text-sm text-ink-soft mb-4">Tambah produk/jasa. Tekan "Simpan Transaksi Sementara" untuk menunda, atau "Selesaikan &amp; Cetak" bila motor sudah selesai dan sudah lunas.</p>

                @include('pos._form', [
                    'transaction' => $transaction,
                    'products' => $products,
                    'services' => $services,
                    'mechanics' => $mechanics,
                ])
            </div>
        @endif
    </div>
</x-app-layout>