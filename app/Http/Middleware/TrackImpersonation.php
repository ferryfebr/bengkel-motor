<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kalau sesi impersonation aktif, suntikkan admin asli ke request context
 * sebagai `impersonated_by`. Service layer membaca attribute ini untuk
 * menyimpan jejak pelaku sebenarnya. Lihat SECURITY.md §3.
 */
class TrackImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('impersonation_log_id')) {
            $request->attributes->set('impersonated_by', $request->session()->get('impersonating_admin_id'));
        }

        return $next($request);
    }
}
