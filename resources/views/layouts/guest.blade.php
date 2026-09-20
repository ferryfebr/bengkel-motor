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
    <body class="font-sans text-ink antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-paper-dim">
            <div class="flex flex-col items-center">
                <span class="flex items-center justify-center w-16 h-16 rounded-md bg-ink text-signal font-black text-3xl">B</span>
                <div class="mt-3 text-center">
                    <div class="font-bold text-ink text-lg leading-tight">Bengkel Motor</div>
                    <div class="text-[11px] tracking-[0.2em] text-ink-500">Point of Sale</div>
                </div>
            </div>

            <div class="w-full sm:max-w-md mt-8 overflow-hidden sm:rounded-md border border-line bg-white">
                <div class="h-1 bg-signal"></div>
                <div class="px-6 py-6">
                    {{ $slot }}
                </div>
            </div>

            <p class="mt-6 text-xs text-ink-500">Sistem Informasi Manajemen &amp; POS Bengkel Motor</p>
        </div>
    </body>
</html>