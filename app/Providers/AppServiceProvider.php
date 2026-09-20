<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\CashMutation;
use App\Models\ImpersonationLog;
use App\Models\StockHistory;
use App\Observers\PreventLogMutation;
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
    }
}
