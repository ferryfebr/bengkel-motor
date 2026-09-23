<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink">{{ __('Daftar Antrean') }}</h1>
            <a href="{{ route('work-orders.create') }}" class="btn-primary">+ Motor Masuk</a>
        </div>
    </x-slot>

    <div class="space-y-4">
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <x-stat-card label="Antre" :value="number_format($antreCount)" tone="brand" />
            <x-stat-card label="Sedang Dikerjakan" :value="number_format($prosesCount)" />
            <x-stat-card label="Total Aktif" :value="number_format($ongoing->count())" />
        </div>

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Plat</th>
                        <th class="px-4 py-3 text-left font-semibold">Jenis Motor</th>
                        <th class="px-4 py-3 text-left font-semibold">Customer</th>
                        <th class="px-4 py-3 text-left font-semibold">Kasir</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                        <th class="px-4 py-3 text-left font-semibold">Masuk</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($ongoing as $wo)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 font-semibold text-ink">{{ $wo->plate_number }}</td>
                            <td class="px-4 py-2.5 text-ink-600">{{ $wo->motor_type ?: '-' }}</td>
                            <td class="px-4 py-2.5 text-ink-600">{{ $wo->customer_name ?: '-' }}</td>
                            <td class="px-4 py-2.5 text-ink-600">{{ $wo->cashier?->name }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if ($wo->work_status === 'proses')
                                    <span class="badge bg-ink text-paper">🔧 Dikerjakan</span>
                                @else
                                    <span class="badge bg-paper-dim text-ink-700">🟡 Antre</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-ink-500">{{ $wo->created_at->format('d/m H:i') }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <a href="{{ route('work-orders.show', $wo) }}" class="btn-secondary px-3">Buka</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-ink-400">Belum ada motor dalam antrean.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>