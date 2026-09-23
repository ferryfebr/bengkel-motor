<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Kas Bengkel') }}</h1>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ $errors->first() }}</div>
        @endif

        {{-- Filter (owner & super_admin) --}}
        @if ($isManager)
            <form method="GET" class="bg-white border border-line rounded-md p-4 flex flex-wrap items-end gap-3">
                <div class="flex items-end gap-2">
                    <a href="{{ route('cash.index', ['period' => 'today']) }}" class="btn-secondary px-4 {{ $period === 'today' ? 'ring-2 ring-ink' : '' }}">Harian</a>
                    <a href="{{ route('cash.index', ['period' => 'week']) }}" class="btn-secondary px-4 {{ $period === 'week' ? 'ring-2 ring-ink' : '' }}">Mingguan</a>
                    <a href="{{ route('cash.index', ['period' => 'month']) }}" class="btn-secondary px-4 {{ $period === 'month' ? 'ring-2 ring-ink' : '' }}">Bulanan</a>
                </div>
                <div>
                    <x-input-label for="from" :value="__('Dari Tanggal')" />
                    <x-text-input id="from" name="from" type="date" class="mt-1 block" :value="$from->toDateString()" />
                </div>
                <div>
                    <x-input-label for="to" :value="__('Sampai Tanggal')" />
                    <x-text-input id="to" name="to" type="date" class="mt-1 block" :value="$to->toDateString()" />
                </div>
                <x-primary-button>Saring</x-primary-button>
            </form>
        @else
            <p class="text-sm text-ink-500">Menampilkan kas masuk & keluar <strong>hari ini</strong> ({{ now()->format('d/m/Y') }}).</p>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white border border-line rounded-md p-5">
                <div class="text-[13px] font-medium text-ink-500">{{ $isManager ? 'Saldo Kas (Keseluruhan)' : 'Kas Bersih Hari Ini' }}</div>
                <div class="font-num tabular text-3xl font-bold mt-2 {{ $balance < 0 ? 'text-danger' : 'text-ink' }}">Rp {{ number_format($balance, 0, ',', '.') }}</div>
                @if ($isManager)
                    <div class="text-xs text-ink-400 mt-1">Kas masuk periode ini: Rp {{ number_format($totalIn, 0, ',', '.') }} · keluar: Rp {{ number_format($totalOut, 0, ',', '.') }}</div>
                @endif
            </div>
            <div class="bg-white border border-line rounded-md p-5">
                <div class="text-[13px] font-medium text-ink-500">{{ $isManager ? 'Total Masuk (Periode)' : 'Kas Masuk Hari Ini' }}</div>
                <div class="font-num tabular text-2xl font-bold text-success mt-2">Rp {{ number_format($totalIn, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white border border-line rounded-md p-5">
                <div class="text-[13px] font-medium text-ink-500">{{ $isManager ? 'Total Keluar (Periode)' : 'Kas Keluar Hari Ini' }}</div>
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
                    <x-input-label for="amount" value="Nominal (Rp)" />
                    <input id="amount" name="amount" type="text" inputmode="numeric" data-rupiah placeholder="0"
                           class="mt-1 block w-44 border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]" required />
                </div>
                <div class="flex-1 min-w-[200px]">
                    <x-input-label for="description" value="Keterangan" />
                    <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" placeholder="mis. beli alat, listrik" />
                </div>
                <x-primary-button>Catat</x-primary-button>
            </form>
        </div>

        @if (auth()->user()->hasRole('owner', 'super_admin'))
            <div class="bg-white border border-line rounded-md p-4">
                <h3 class="font-semibold text-ink mb-1">Penarikan Kas</h3>
                <p class="text-xs text-ink-500 mb-3">Tarik sebagian uang kas bengkel. Tidak boleh melebihi saldo.</p>
                <form method="POST" action="{{ route('cash.withdraw') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <x-input-label for="w_amount" value="Nominal Penarikan (Rp)" />
                        <input id="w_amount" name="amount" type="text" inputmode="numeric" data-rupiah placeholder="0"
                               class="mt-1 block w-48 border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]" required />
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <x-input-label for="w_description" value="Keterangan (opsional)" />
                        <x-text-input id="w_description" name="description" type="text" class="mt-1 block w-full" placeholder="mis. setoran pemilik" />
                    </div>
                    <x-primary-button>Tarik Kas</x-primary-button>
                </form>
            </div>
        @endif

        <form method="GET" class="flex items-end gap-2">
            @if ($isManager)
                <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                <input type="hidden" name="to" value="{{ $to->toDateString() }}">
            @endif
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

    <script>
        (function () {
            function format(el) {
                var digits = el.value.replace(/[^\d]/g, '');
                el.value = digits ? Number(digits).toLocaleString('id-ID') : '';
            }
            document.querySelectorAll('[data-rupiah]').forEach(function (el) {
                el.addEventListener('input', function () { format(el); });
            });
            document.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    form.querySelectorAll('[data-rupiah]').forEach(function (el) {
                        el.value = el.value.replace(/[^\d]/g, '');
                    });
                });
            });
        })();
    </script>
</x-app-layout>