<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Aktivitas') }}</h1>
    </x-slot>

    <div class="space-y-4">
        {{-- Kategori --}}
        <div class="flex flex-wrap gap-2 border-b border-line">
            @foreach ($categories as $key => $label)
                <a href="{{ route('activity.index', ['category' => $key, 'period' => $period ?: null]) }}"
                   class="px-4 py-2.5 -mb-px text-sm font-medium border-b-2 transition
                          {{ $category === $key ? 'border-ink text-ink' : 'border-transparent text-ink-500 hover:text-ink hover:border-line' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Shortcut periode --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('activity.index', ['category' => $category, 'period' => 'today']) }}" class="btn-secondary px-4 {{ $period === 'today' ? 'ring-2 ring-ink' : '' }}">Harian</a>
            <a href="{{ route('activity.index', ['category' => $category, 'period' => 'week']) }}" class="btn-secondary px-4 {{ $period === 'week' ? 'ring-2 ring-ink' : '' }}">Mingguan</a>
            <a href="{{ route('activity.index', ['category' => $category, 'period' => 'month']) }}" class="btn-secondary px-4 {{ $period === 'month' ? 'ring-2 ring-ink' : '' }}">Bulanan</a>
        </div>

        <form method="GET" class="bg-white border border-line rounded-md p-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="category" value="{{ $category }}">
            <div>
                <x-input-label for="from" value="Dari Tanggal" />
                <x-text-input id="from" name="from" type="date" class="mt-1 block" :value="$from->toDateString()" />
            </div>
            <div>
                <x-input-label for="to" value="Sampai Tanggal" />
                <x-text-input id="to" name="to" type="date" class="mt-1 block" :value="$to->toDateString()" />
            </div>
            <div>
                <x-input-label for="user_id" value="Aktor" />
                <select id="user_id" name="user_id" class="mt-1 border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]">
                    <option value="">Semua aktor</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected($userId === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[180px]">
                <x-input-label for="q" value="Cari" />
                <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$q" placeholder="invoice / nama / plat" />
            </div>
            <x-primary-button>Saring</x-primary-button>
        </form>

        @if ($category === 'transaksi')
            <div class="bg-white border border-line rounded-md overflow-x-auto">
                <table class="min-w-full font-condensed text-sm">
                    <thead class="bg-paper-dim text-ink">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Invoice</th>
                            <th class="px-4 py-3 text-left font-semibold">Customer</th>
                            <th class="px-4 py-3 text-left font-semibold">Plat</th>
                            <th class="px-4 py-3 text-left font-semibold">Jenis Motor</th>
                            <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                            <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($transactions as $t)
                            <tr class="hover:bg-paper-dim/60 transition-colors">
                                <td class="px-4 py-2.5 font-mono text-xs text-ink-500">{{ $t->invoice_number }}</td>
                                <td class="px-4 py-2.5 text-ink-700">{{ $t->customer_name ?: '-' }}</td>
                                <td class="px-4 py-2.5 font-semibold text-ink">{{ $t->plate_number }}</td>
                                <td class="px-4 py-2.5 text-ink-700">{{ $t->motor_type ?: '-' }}</td>
                                <td class="px-4 py-2.5 text-ink-500 whitespace-nowrap">{{ $t->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <a href="{{ route('activity.show', $t) }}" class="btn-secondary px-3">Lihat Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-ink-400">Belum ada transaksi pada rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $transactions->links() }}
        @else
            <div class="bg-white border border-line rounded-md overflow-x-auto">
                <table class="min-w-full font-condensed text-sm">
                    <thead class="bg-paper-dim text-ink">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Waktu</th>
                            <th class="px-4 py-3 text-left font-semibold">Aktor</th>
                            <th class="px-4 py-3 text-left font-semibold">Aktivitas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-paper-dim/60 transition-colors align-top">
                                <td class="px-4 py-2.5 text-ink-600 whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2.5 text-ink">
                                    {{ $log->user?->name ?? '-' }}
                                    @if ($log->impersonated_by)
                                        <span class="block text-xs text-ink-400">via impersonation</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-ink-700">
                                    {{ \App\Support\ActivityPresenter::label($log->action) }}
                                    <span class="block text-xs text-ink-500">{{ \App\Support\ActivityPresenter::describe($log) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-10 text-center text-ink-400">Belum ada aktivitas pada kategori & rentang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $logs->links() }}
        @endif
    </div>
</x-app-layout>