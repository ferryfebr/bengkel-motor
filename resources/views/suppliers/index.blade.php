<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">Distributor</h1>
            <a href="{{ route('purchase-orders.index') }}" class="btn-secondary shrink-0">← Pesanan Pembelian</a>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ $errors->first() }}</div>
        @endif

        <div class="bg-white border border-line rounded-md p-4">
            <h3 class="font-semibold text-ink mb-3">Tambah Distributor</h3>
            <form method="POST" action="{{ route('suppliers.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @csrf
                <div>
                    <x-input-label for="name" value="Nama Distributor" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                </div>
                <div>
                    <x-input-label for="phone" value="Telepon (opsional)" />
                    <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" />
                </div>
                <div class="md:col-span-2">
                    <x-input-label for="address" value="Alamat (opsional)" />
                    <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address')" />
                </div>
                <div class="md:col-span-2">
                    <x-primary-button>Tambah</x-primary-button>
                </div>
            </form>
        </div>

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Nama</th>
                        <th class="px-4 py-3 text-left font-semibold">Telepon</th>
                        <th class="px-4 py-3 text-left font-semibold">Alamat</th>
                        <th class="px-4 py-3 text-right font-semibold">Jumlah PO</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($suppliers as $supplier)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 text-ink font-medium">{{ $supplier->name }}</td>
                            <td class="px-4 py-2.5 text-ink-600">{{ $supplier->phone ?: '-' }}</td>
                            <td class="px-4 py-2.5 text-ink-600">{{ $supplier->address ?: '-' }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink-600">{{ $supplier->purchase_orders_count }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="inline"
                                      onsubmit="return confirm('Hapus distributor ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-danger px-3">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-400">Belum ada distributor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $suppliers->links() }}
    </div>
</x-app-layout>