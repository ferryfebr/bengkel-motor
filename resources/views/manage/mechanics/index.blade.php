<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink">{{ __('Mekanik & Rasio Komisi') }}</h1>
            <a href="{{ route('manage.mechanics.create') }}" class="btn-primary">
                + Tambah Mekanik
            </a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @include('manage.partials.nav')

        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Nama Mekanik</th>
                        <th class="px-4 py-3 text-right font-semibold">Rasio Mekanik (%)</th>
                        <th class="px-4 py-3 text-right font-semibold">Rasio Bengkel (%)</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($mechanics as $mechanic)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 text-ink">{{ $mechanic->name }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">{{ rtrim(rtrim(number_format($mechanic->mechanic_percentage, 2, ',', '.'), '0'), ',') }}</td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">{{ rtrim(rtrim(number_format($mechanic->bengkel_percentage ?? (100 - $mechanic->mechanic_percentage), 2, ',', '.'), '0'), ',') }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if ($mechanic->is_active)
                                    <span class="badge bg-success-light text-success">✅ Aktif</span>
                                @else
                                    <span class="badge bg-paper-dim text-ink-500">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <a href="{{ route('manage.mechanics.edit', $mechanic) }}" class="btn-secondary px-3">Edit</a>
                                <form method="POST" action="{{ route('manage.mechanics.destroy', $mechanic) }}" class="inline"
                                      onsubmit="return confirm('Hapus mekanik ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-danger px-3 ms-2">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-400">Belum ada mekanik.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $mechanics->links() }}
    </div>
</x-app-layout>