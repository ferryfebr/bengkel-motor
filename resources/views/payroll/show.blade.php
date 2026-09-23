<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <h1 class="text-lg font-bold text-ink truncate">Gaji — {{ $mechanic->name }}</h1>
            <a href="{{ route('payroll.index') }}" class="btn-secondary shrink-0">← Gaji Karyawan</a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ $errors->first() }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-stat-card label="Gaji Terkumpul (Periode)" value="Rp {{ number_format($earned, 0, ',', '.') }}" />
            <x-stat-card label="Gaji Terkumpul (Keseluruhan)" value="Rp {{ number_format($earnedAll, 0, ',', '.') }}" />
            <x-stat-card label="Total Ditarik" value="Rp {{ number_format($withdrawn, 0, ',', '.') }}" />
            <x-stat-card label="Saldo Gaji" value="Rp {{ number_format($balance, 0, ',', '.') }}" :tone="$balance < 0 ? 'danger' : 'success'" />
        </div>

        @if ($balance < 0)
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">
                Saldo gaji minus Rp {{ number_format(abs($balance), 0, ',', '.') }} (kasbon / hutang mekanik ke bengkel).
            </div>
        @endif

        {{-- Filter periode --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('payroll.show', ['mechanic' => $mechanic, 'period' => 'today']) }}" class="btn-secondary px-4 {{ $period === 'today' ? 'ring-2 ring-ink' : '' }}">Harian</a>
            <a href="{{ route('payroll.show', ['mechanic' => $mechanic, 'period' => 'week']) }}" class="btn-secondary px-4 {{ $period === 'week' ? 'ring-2 ring-ink' : '' }}">Mingguan</a>
            <a href="{{ route('payroll.show', ['mechanic' => $mechanic, 'period' => 'month']) }}" class="btn-secondary px-4 {{ $period === 'month' ? 'ring-2 ring-ink' : '' }}">Bulanan</a>
        </div>

        <form method="GET" class="bg-white border border-line rounded-md p-4 flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="from" value="Dari Tanggal" />
                <x-text-input id="from" name="from" type="date" class="mt-1 block" :value="$from->toDateString()" />
            </div>
            <div>
                <x-input-label for="to" value="Sampai Tanggal" />
                <x-text-input id="to" name="to" type="date" class="mt-1 block" :value="$to->toDateString()" />
            </div>
            <x-primary-button>Tampilkan</x-primary-button>
        </form>

        {{-- Form ambil gaji --}}
        <div class="bg-white border border-line rounded-md p-4">
            <h3 class="font-semibold text-ink mb-1">Ambil Gaji</h3>
            <p class="text-xs text-ink-500 mb-3">Boleh menarik melebihi saldo — selisihnya menjadi kasbon (saldo minus).</p>
            <form method="POST" action="{{ route('payroll.payout', $mechanic) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <x-input-label for="amount" value="Nominal Penarikan (Rp)" />
                    <input id="amount" name="amount" type="text" inputmode="numeric" data-rupiah placeholder="0"
                           class="mt-1 block w-48 border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]" required />
                </div>
                <div class="flex-1 min-w-[200px]">
                    <x-input-label for="description" value="Keterangan (opsional)" />
                    <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" placeholder="mis. gaji minggu ke-2" />
                </div>
                <x-primary-button>Catat Penarikan</x-primary-button>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Rincian komisi --}}
            <div class="bg-white border border-line rounded-md overflow-hidden">
                <div class="px-4 py-3 border-b border-line font-semibold text-ink">Rincian Komisi (Periode)</div>
                <table class="min-w-full font-condensed text-sm">
                    <thead class="bg-paper-dim text-ink">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                            <th class="px-4 py-3 text-left font-semibold">Invoice</th>
                            <th class="px-4 py-3 text-right font-semibold">Komisi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($shares as $s)
                            <tr class="hover:bg-paper-dim/60 transition-colors">
                                <td class="px-4 py-2.5 text-ink-600 whitespace-nowrap">{{ $s->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-ink-500">{{ $s->transaction?->invoice_number ?? '-' }}</td>
                                <td class="px-4 py-2.5 text-right tabular text-ink">Rp {{ number_format($s->share_amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-ink-400">Belum ada komisi pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($shares->isNotEmpty())
                        <tfoot>
                            <tr class="bg-paper-dim font-semibold text-ink">
                                <td colspan="2" class="px-4 py-3">Total</td>
                                <td class="px-4 py-3 text-right tabular">Rp {{ number_format($earned, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            {{-- Riwayat penarikan --}}
            <div class="bg-white border border-line rounded-md overflow-hidden">
                <div class="px-4 py-3 border-b border-line font-semibold text-ink">Riwayat Penarikan Gaji</div>
                <table class="min-w-full font-condensed text-sm">
                    <thead class="bg-paper-dim text-ink">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                            <th class="px-4 py-3 text-left font-semibold">Keterangan</th>
                            <th class="px-4 py-3 text-left font-semibold">Oleh</th>
                            <th class="px-4 py-3 text-right font-semibold">Nominal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($payouts as $p)
                            <tr class="hover:bg-paper-dim/60 transition-colors">
                                <td class="px-4 py-2.5 text-ink-600 whitespace-nowrap">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2.5 text-ink-700">{{ $p->description ?: '-' }}</td>
                                <td class="px-4 py-2.5 text-ink-500">{{ $p->user?->name }}</td>
                                <td class="px-4 py-2.5 text-right tabular text-danger">Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-ink-400">Belum ada penarikan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
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