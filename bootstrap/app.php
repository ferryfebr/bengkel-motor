<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\TrackImpersonation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Saat tunnel lokal (Cloudflare/ngrok), permintaan masuk lewat proxy.
        // Percayai proxy hanya di lokal agar URL/HTTPS terdeteksi benar.
        if (env('APP_ENV') === 'local') {
            $middleware->trustProxies(at: '*');
        }

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'track.impersonation' => TrackImpersonation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
