<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Kas Bengkel') }}</h1>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white border border-line rounded-md p-5">
                <div class="text-[13px] font-medium text-ink-500">Saldo Kas</div>
                <div class="font-num tabular text-3xl font-bold mt-2 {{ $balance < 0 ? 'text-danger' : 'text-ink' }}">Rp {{ number_format($balance, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white border border-line rounded-md p-5">
                <div class="text-[13px] font-medium text-ink-500">Total Masuk</div>
                <div class="font-num tabular text-2xl font-bold text-success mt-2">Rp {{ number_format($totalIn, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white border border-line rounded-md p-5">
                <div class="text-[13px] font-medium text-ink-500">Total Keluar</div>
                <div class="font-num tabular text-2xl font-bold text-danger mt-2">Rp {{ number_format($totalOut, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-white border border-line rounded-md p-4">
            <h3 class="font-semibold text-ink mb-3">Catat Mutasi Kas Manual</h3>
            <form method="POST" action="{{ route('cash.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <x-input-label for="type" value="Jenis" />
                    <select id="type" name="type" class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]">
                        <option value="out">Kas Keluar</option>
                        <option value="in">Kas Masuk</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="amount" value="Nominal" />
                    <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-40" required />
                </div>
                <div class="flex-1 min-w-[200px]">
                    <x-input-label for="description" value="Keterangan" />
                    <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" placeholder="mis. beli alat, listrik" />
                </div>
                <x-primary-button>Catat</x-primary-button>
            </form>
        </div>

        <form method="GET" class="flex items-end gap-2">
            <div>
                <x-input-label for="typef" value="Filter" />
                <select id="typef" name="type" class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]" onchange="this.form.submit()">
                    @foreach (['semua' => 'Semua', 'in' => 'Masuk', 'out' => 'Keluar'] as $val => $label)
                        <option value="{{ $val }}" @selected($typeFilter === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="bg-white border border-line rounded-md overflow-x-auto">
            <table class="min-w-full font-condensed text-sm">
                <thead class="bg-paper-dim text-ink">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Waktu</th>
                        <th class="px-4 py-3 text-left font-semibold">Jenis</th>
                        <th class="px-4 py-3 text-right font-semibold">Nominal</th>
                        <th class="px-4 py-3 text-left font-semibold">Keterangan</th>
                        <th class="px-4 py-3 text-left font-semibold">Oleh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($mutations as $m)
                        <tr class="hover:bg-paper-dim/60 transition-colors">
                            <td class="px-4 py-2.5 text-ink-600">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2.5">
                                @if ($m->type === 'in')
                                    <span class="badge bg-success-light text-success">✅ Masuk</span>
                                @else
                                    <span class="badge bg-danger-light text-danger">⛔ Keluar</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right tabular text-ink">{{ number_format($m->amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-ink-700">{{ $m->description }}</td>
                            <td class="px-4 py-2.5 text-ink-500">{{ $m->user?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-400">Belum ada mutasi kas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $mutations->links() }}
    </div>
</x-app-layout>