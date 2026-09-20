<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">
                POS — {{ $transaction->plate_number }} <span class="font-mono text-xs text-ink-400 font-normal">({{ $transaction->invoice_number }})</span>
            </h1>
            <a href="{{ route('work-orders.show', $transaction) }}" class="shrink-0 text-sm font-medium text-ink-500 hover:text-ink hover:underline">← Work Order</a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @include('pos._form', [
            'transaction' => $transaction,
            'products' => $products,
            'services' => $services,
            'mechanics' => $mechanics,
        ])
    </div>
</x-app-layout>