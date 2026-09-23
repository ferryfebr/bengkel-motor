<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\CashMutation;
use App\Models\ImpersonationLog;
use App\Models\MechanicPayout;
use App\Models\StockHistory;
use App\Observers\PreventLogMutation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Append-only enforcement (SECURITY.md bagian 1).
        StockHistory::observe(PreventLogMutation::class);
        ActivityLog::observe(PreventLogMutation::class);
        ImpersonationLog::observe(PreventLogMutation::class);
        CashMutation::observe(PreventLogMutation::class);
        MechanicPayout::observe(PreventLogMutation::class);

        // Rate limit login (SECURITY.md §5).
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                (string) $request->input('username').'|'.$request->ip()
            );
        });
    }
}
