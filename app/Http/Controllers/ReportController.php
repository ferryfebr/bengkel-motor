<?php

namespace App\Http\Controllers;

use App\Services\CsvExportService;
use App\Services\DailySummaryService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly DailySummaryService $dailySummaryService,
        private readonly CsvExportService $csvExport,
    ) {}

    /**
     * Laporan Omset Kotor - read-only untuk semua role (termasuk kasir).
     */
    public function gross(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reports.gross', [
            'from' => $from,
            'to' => $to,
            'summary' => $this->reportService->revenue($from, $to),
            'series' => $this->reportService->dailySeries($from, $to),
            'canViewNet' => $request->user()->hasRole('owner', 'super_admin'),
        ]);
    }

    /**
     * Laporan Omset Bersih & profit - hanya Owner & Super Admin.
     */
    public function net(Request $request): View
    {
        abort_unless($request->user()->hasRole('owner', 'super_admin'), 403);

        [$from, $to] = $this->range($request);

        return view('reports.net', [
            'from' => $from,
            'to' => $to,
            'summary' => $this->reportService->revenue($from, $to),
            'series' => $this->reportService->dailySeries($from, $to),
        ]);
    }

    /**
     * Laporan Komisi Mekanik - read-only untuk semua role (mekanik bukan user).
     */
    public function mechanics(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reports.mechanics', [
            'from' => $from,
            'to' => $to,
            'commissions' => $this->reportService->mechanicCommission($from, $to),
        ]);
    }

    /**
     * Export CSV riwayat transaksi final (rentang tanggal). Owner/Kasir (H6).
     */
    public function exportTransactions(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasRole('kasir', 'owner', 'super_admin'), 403);

        [$from, $to] = $this->range($request);

        return $this->csvExport->streamTransactions($from, $to);
    }

    /**
     * Bangun ulang ringkasan harian untuk rentang tanggal (owner/super_admin).
     */
    public function rebuildSummaries(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('owner', 'super_admin'), 403);

        [$from, $to] = $this->range($request);

        $count = $this->dailySummaryService->buildRange($from, $to);

        return back()->with('status', "Ringkasan harian dibangun ulang untuk {$count} hari.");
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : Carbon::today()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : Carbon::today();

        return [$from, $to];
    }
}
