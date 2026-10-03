<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Transaction;
use App\Models\TransactionArchive;
use App\Services\CsvExportService;
use App\Services\DiskUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SystemController extends Controller
{
    public function __construct(
        private readonly CsvExportService $csvExport,
        private readonly DiskUsageService $diskUsage,
    ) {}

    /**
     * Panel sistem Super Admin + status disk & retensi.
     */
    public function index(): View
    {
        return view('system.index', [
            'finalCount' => Transaction::query()
                ->where('work_status', Transaction::WORK_SELESAI)
                ->where('payment_status', Transaction::PAY_LUNAS)
                ->count(),
            'activityLogCount' => ActivityLog::count(),
            'archiveCount' => TransactionArchive::count(),
            'retention' => [
                'transactions' => (int) config('retention.transactions', 8000),
                'activity_max' => (int) config('retention.activity_max', 3000),
                'activity_months' => (int) config('retention.activity_months', 3),
                'purchase_orders' => (int) config('retention.purchase_orders', 2000),
            ],
            'disk' => $this->diskUsage->report(),
        ]);
    }

    /**
     * Export CSV activity_logs.
     */
    public function exportActivityLogs(Request $request): StreamedResponse
    {
        $before = $request->filled('before') ? Carbon::parse($request->input('before')) : null;

        return $this->csvExport->streamActivityLogs($before);
    }

    /**
     * Jalankan retensi sekarang (fallback bila cron hosting tidak jalan).
     */
    public function runRetention(): RedirectResponse
    {
        $outputs = [];

        foreach (['transactions:retain', 'activity:prune', 'purchase-orders:retain', 'sessions:prune', 'logs:rotate'] as $command) {
            try {
                Artisan::call($command);
                $outputs[] = trim(Artisan::output());
            } catch (Throwable $e) {
                $outputs[] = "{$command} gagal: ".$e->getMessage();
            }
        }

        // Segarkan indikator disk setelah retensi.
        $this->diskUsage->forget();

        return redirect()->route('system.index')
            ->with('status', 'Retensi dijalankan. '.implode(' ', array_filter($outputs)));
    }
}
