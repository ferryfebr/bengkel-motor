<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink">{{ __('Master Jasa') }}</h1>
            <a href="{{ route('manage.services.create') }}" class="btn-primary">
                + Tambah Jasa
            </a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @include('manage.partials.nav')

        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif
        <p class="text-sm text-ink-500">Tarif di sini hanya <em>template</em>. Nominal final diinput kasir saat transaksi.</p>

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Nama Jasa</th>
                        <th class="px-4 py-3 text-right font-semibold">Tarif</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($services as $service)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 text-ink">{{ $service->name }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">{{ number_format($service->price, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if ($service->is_active)
                                    <span class="badge bg-success-light text-success">✅ Aktif</span>
                                @else
                                    <span class="badge bg-paper-dim text-ink-500">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <a href="{{ route('manage.services.edit', $service) }}" class="text-ink font-medium hover:underline">Edit</a>
                                <form method="POST" action="{{ route('manage.services.destroy', $service) }}" class="inline"
                                      onsubmit="return confirm('Hapus jasa ini?')">
                                    @csrf @method('DELETE')
                                    <button class="text-danger font-medium hover:underline ms-3">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-ink-400">Belum ada jasa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $services->links() }}
    </div>
</x-app-layout>