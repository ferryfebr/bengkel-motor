<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Login Sebagai') }}</h1>
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-4">
        @if (session('status'))
            <div class="bg-success-light border border-success/40 text-success px-4 py-3 rounded-md text-sm font-medium">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm font-medium">{{ session('error') }}</div>
        @endif

        @if ($activeLog)
            <div class="bg-signal-100 border border-signal text-ink-700 px-4 py-3 rounded-md text-sm font-medium">
                Sesi impersonation aktif: <strong>{{ $activeLog->admin?->name }}</strong>
                menyamar sebagai <strong>{{ $activeLog->targetUser?->name }}</strong>
                sejak {{ $activeLog->started_at?->format('d M Y H:i') }}.
            </div>
        @endif

        <div class="bg-white border border-line rounded-md p-6">
            <h3 class="font-semibold text-ink mb-1">Pilih user untuk disamarkan</h3>
            <p class="text-sm text-ink-500 mb-4">
                Hanya user yang bisa login (Owner/Kasir) yang dapat di-impersonate. Setiap sesi tercatat di <code class="text-xs bg-paper-dim px-1 rounded">impersonation_logs</code>.
            </p>

            @if ($users->isEmpty())
                <p class="text-sm text-ink-400">Tidak ada user lain yang bisa di-impersonate.</p>
            @else
                <table class="min-w-full font-condensed text-sm">
                    <thead class="bg-paper-dim text-ink">
                        <tr>
                            <th class="py-3 px-3 text-left font-semibold">Nama</th>
                            <th class="py-3 px-3 text-left font-semibold">Username</th>
                            <th class="py-3 px-3 text-left font-semibold">Role</th>
                            <th class="py-3 px-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($users as $user)
                            <tr class="hover:bg-paper-dim/60 transition-colors">
                                <td class="py-2.5 px-3 font-medium text-ink">{{ $user->name }}</td>
                                <td class="py-2.5 px-3 text-ink-600">{{ $user->username }}</td>
                                <td class="py-2.5 px-3 text-ink-600">{{ strtoupper(str_replace('_', ' ', $user->role)) }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    <form method="POST" action="{{ route('impersonation.store') }}">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                                        <x-secondary-button type="submit">Login Sebagai</x-secondary-button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>