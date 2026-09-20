<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="20">
    <title>Queue Board - Antrean Servis</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-ink text-white min-h-screen">
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8 border-b border-white/15 pb-5">
            <h1 class="text-3xl font-black tracking-wide">BengkelOS <span class="text-signal">Antrean Servis</span></h1>
            <div class="text-sm text-ink-300">Auto-refresh 20s &middot; <span class="font-num tabular text-signal text-lg">{{ now()->format('H:i') }}</span></div>
        </div>

        @if ($ongoing->isEmpty())
            <div class="text-center text-ink-300 py-24 text-2xl">Belum ada motor masuk hari ini.</div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($ongoing as $wo)
                    <div class="rounded-md p-6 {{ $wo->work_status === 'proses' ? 'bg-signal/15 border-2 border-signal' : 'bg-white/5 border border-white/15' }}">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-num tabular text-3xl font-bold tracking-wider">{{ $wo->plate_number }}</span>
                            <span class="badge {{ $wo->work_status === 'proses' ? 'bg-signal text-ink' : 'bg-white/15 text-white' }}">
                                {{ $wo->work_status === 'proses' ? '🔧' : '🟡' }} {{ ucfirst($wo->work_status) }}
                            </span>
                        </div>
                        <div class="mt-3 text-ink-300 text-sm font-mono">{{ $wo->invoice_number }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>