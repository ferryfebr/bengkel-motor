<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=barlow:400,500,600,700,800&family=barlow-condensed:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    @php
        $user = Auth::user();

        $items = [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'pattern' => 'dashboard', 'roles' => ['kasir', 'owner', 'super_admin'], 'icon' => 'home'],
            ['route' => 'work-orders.index', 'label' => 'Work Order', 'pattern' => ['work-orders.index', 'work-orders.create', 'work-orders.show', 'work-orders.completed'], 'roles' => ['kasir', 'owner', 'super_admin'], 'icon' => 'clipboard'],
            ['route' => 'work-orders.queue', 'label' => 'Daftar Antrean', 'pattern' => 'work-orders.queue', 'roles' => ['kasir', 'owner', 'super_admin'], 'icon' => 'queue'],
            ['route' => 'cash.index', 'label' => 'Kas Bengkel', 'pattern' => 'cash.*', 'roles' => ['kasir', 'owner', 'super_admin'], 'icon' => 'cash'],
            ['route' => 'manage.index', 'label' => 'Manajemen', 'pattern' => 'manage.*', 'roles' => ['kasir', 'owner', 'super_admin'], 'icon' => 'box'],
            ['route' => 'reports.gross', 'label' => 'Laporan', 'pattern' => 'reports.*', 'roles' => ['kasir', 'owner', 'super_admin'], 'icon' => 'chart'],
            ['route' => 'activity.index', 'label' => 'Aktivitas', 'pattern' => 'activity.*', 'roles' => ['owner', 'super_admin'], 'icon' => 'activity'],
            ['route' => 'impersonation.index', 'label' => 'Login Sebagai', 'pattern' => 'impersonation.*', 'roles' => ['owner', 'super_admin'], 'icon' => 'switch'],
            ['route' => 'system.index', 'label' => 'Panel Sistem', 'pattern' => 'system.*', 'roles' => ['super_admin'], 'icon' => 'server'],
        ];
    @endphp

    <body x-data="{ open: false }" class="font-sans text-ink antialiased min-h-screen bg-paper-dim">
        {{-- Overlay mobile --}}
        <div x-show="open" x-transition.opacity @click="open = false"
             class="fixed inset-0 z-30 bg-ink/60 lg:hidden" style="display: none;"></div>

        {{-- Sidebar --}}
        <aside
            :class="open ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-40 w-64 transform bg-ink text-paper transition-transform duration-200 ease-out lg:translate-x-0 flex flex-col">

            {{-- Brand --}}
            <div class="flex items-center gap-3 h-16 px-5 border-b border-white/10 shrink-0">
                <span class="flex items-center justify-center w-9 h-9 rounded-md bg-signal text-ink font-black text-lg">B</span>
                <div class="leading-tight">
                    <div class="font-bold text-paper">Bengkel Motor</div>
                    <div class="text-[11px] tracking-wider text-signal">Point of Sale</div>
                </div>
            </div>

            {{-- Menu --}}
            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                @foreach ($items as $item)
                    @if ($user->hasRole(...$item['roles']))
                        @php $active = request()->routeIs($item['pattern']); @endphp
                        <a href="{{ route($item['route']) }}"
                           class="group flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition
                                  {{ $active ? 'bg-signal text-ink' : 'text-paper-dim hover:bg-white/10 hover:text-paper' }}">
                            <span class="shrink-0 {{ $active ? 'text-ink' : 'text-ink-soft group-hover:text-signal' }}">
                                @switch($item['icon'])
                                    @case('home')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5M5 9.5V21h14V9.5"/></svg>
                                        @break
                                    @case('clipboard')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5h6M9 5a2 2 0 0 0-2 2v0a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v0a2 2 0 0 0-2-2M7 7H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-1"/></svg>
                                        @break
                                    @case('cash')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18v10H3zM7 12h.01M12 12h.01M17 12h.01"/></svg>
                                        @break
                                    @case('box')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8 12 3 3 8l9 5 9-5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 8v8l9 5 9-5V8M12 13v8"/></svg>
                                        @break
                                    @case('chart')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
                                        @break
                                    @case('activity')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2M12 3a9 9 0 1 0 9 9"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 3v6h-6"/></svg>
                                        @break
                                    @case('queue')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
                                        @break
                                    @case('switch')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M21 16v3a2 2 0 0 1-2 2h-3M3 8V5a2 2 0 0 1 2-2h3M9 12h6"/></svg>
                                        @break
                                    @case('server')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v6H4zM4 14h16v6H4zM8 7h.01M8 17h.01"/></svg>
                                        @break
                                @endswitch
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>

            {{-- User --}}
            <div class="border-t border-white/10 p-3 shrink-0">
                <div class="flex items-center gap-3 px-2 py-2">
                    <span class="flex items-center justify-center w-9 h-9 rounded-full bg-signal/20 text-signal font-semibold">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 leading-tight">
                        <div class="text-sm font-medium text-paper truncate">{{ $user->name }}</div>
                        <div class="text-[11px] tracking-wider text-ink-soft">{{ str_replace('_', ' ', $user->role) }}</div>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Konten --}}
        <div class="lg:pl-64">
            {{-- Topbar --}}
            <header class="sticky top-0 z-20 h-16 bg-paper border-b border-line">
                <div class="h-full px-4 sm:px-6 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <button @click="open = true" class="lg:hidden -ml-1 p-2 rounded-md text-ink-soft hover:bg-paper-dim">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div class="min-w-0">
                            @if (isset($header))
                                {{ $header }}
                            @else
                                <h1 class="text-lg font-bold text-ink truncate">Dashboard</h1>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-1 sm:gap-2">
                        @if ($user->canImpersonate())
                            <a href="{{ route('reports.gross') }}" title="Laporan"
                               class="hidden sm:inline-flex p-2 rounded-md text-ink-soft hover:bg-paper-dim">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
                            </a>
                        @endif

                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-paper-dim transition">
                                    <span class="hidden sm:block text-sm font-medium text-ink">{{ $user->name }}</span>
                                    <svg class="w-4 h-4 text-ink-soft" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">{{ __('Profil') }}</x-dropdown-link>
                                @if ($user->canImpersonate())
                                    <x-dropdown-link :href="route('impersonation.index')">{{ __('Login Sebagai') }}</x-dropdown-link>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault(); this.closest('form').submit();">
                                        {{ __('Keluar') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
            </header>

            {{-- Impersonation banner --}}
            @if (session()->has('impersonation_log_id'))
                <div class="bg-signal text-ink">
                    <div class="px-4 sm:px-6 py-2 flex items-center justify-between gap-4">
                        <div class="text-sm font-medium">
                            Mode "Login Sebagai" aktif — Anda bertindak sebagai <strong>{{ Auth::user()->name }}</strong>.
                        </div>
                        <form method="POST" action="{{ route('impersonation.stop') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-semibold underline hover:no-underline">Kembali</button>
                        </form>
                    </div>
                </div>
            @endif

            <main class="p-4 sm:p-6">
                {{ $slot }}
            </main>

            @stack('scripts')
        </div>
    </body>
</html>