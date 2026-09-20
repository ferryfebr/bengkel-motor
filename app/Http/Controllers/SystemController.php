<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\TransactionArchive;
use App\Services\CsvExportService;
use App\Services\DataRetentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemController extends Controller
{
    public function __construct(
        private readonly DataRetentionService $retention,
        private readonly CsvExportService $csvExport,
    ) {}

    /**
     * Panel sistem Super Admin: status disk, kuota transaksi, arsip, retensi.
     */
    public function index(): View
    {
        return view('system.index', [
            'finalCount' => $this->retention->finalTransactionCount(),
            'quota' => DataRetentionService::QUOTA_FINAL_TRANSACTIONS,
            'diskPercent' => $this->retention->diskUsagePercent(),
            'diskWarning' => $this->retention->diskUsageWarning(),
            'activityLogCount' => ActivityLog::count(),
            'activityLogMax' => DataRetentionService::ACTIVITY_LOG_MAX_ROWS,
            'archives' => TransactionArchive::orderByDesc('created_at')->paginate(15),
        ]);
    }

    /**
     * Jalankan retensi manual (fallback bila cron tidak andal — R3).
     */
    public function runRetention(): RedirectResponse
    {
        $result = $this->retention->runAll();

        return back()->with('status', sprintf(
            'Retensi dijalankan: %d transaksi diarsipkan, %d file arsip dihapus, %d activity log diarsipkan.',
            $result['transactions']['archived'],
            $result['archive_files']['deleted_files'],
            $result['activity_logs']['archived'],
        ));
    }

    /**
     * Export CSV activity_logs (retensi J9).
     */
    public function exportActivityLogs(Request $request): StreamedResponse
    {
        $before = $request->filled('before') ? Carbon::parse($request->input('before')) : null;

        return $this->csvExport->streamActivityLogs($before);
    }
}
