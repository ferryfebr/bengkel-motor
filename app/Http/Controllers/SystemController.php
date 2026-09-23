<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Transaction;
use App\Models\TransactionArchive;
use App\Services\CsvExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemController extends Controller
{
    public function __construct(
        private readonly CsvExportService $csvExport,
    ) {}

    /**
     * Panel sistem Super Admin (read-only).
     * Tidak ada fitur retensi/auto-hapus data — sistem append-only (SECURITY.md).
     */
    public function index(): View
    {
        return view('system.index', [
            'finalCount' => Transaction::query()
                ->where('work_status', Transaction::WORK_SELESAI)
                ->where('payment_status', Transaction::PAY_LUNAS)
                ->count(),
            'activityLogCount' => ActivityLog::count(),
            'archives' => TransactionArchive::orderByDesc('created_at')->paginate(15),
        ]);
    }

    /**
     * Export CSV activity_logs (backup manual, tanpa menghapus data).
     */
    public function exportActivityLogs(Request $request): StreamedResponse
    {
        $before = $request->filled('before') ? Carbon::parse($request->input('before')) : null;

        return $this->csvExport->streamActivityLogs($before);
    }
}