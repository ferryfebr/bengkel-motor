<?php

namespace App\Services;

use App\Models\DailySummary;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Mengisi tabel `daily_summaries` agar laporan tidak menghitung ulang seluruh
 * riwayat tiap dibuka (RESIKO_HOSTING.md #10, RINGKASAN §K9).
 */
class DailySummaryService
{
    public function __construct(private readonly ReportService $reportService) {}

    /**
     * Hitung ulang & simpan ringkasan satu hari.
     */
    public function build(CarbonInterface $date): DailySummary
    {
        $date = Carbon::parse($date)->startOfDay();

        $revenue = $this->reportService->revenue($date, $date);

        return DailySummary::updateOrCreate(
            ['summary_date' => $date->toDateString()],
            [
                'gross_revenue' => $revenue['gross_revenue'],
                'net_revenue' => $revenue['net_revenue'],
                'total_transactions' => $revenue['total_transactions'],
                'total_cash_in' => $revenue['total_cash_in'],
                'total_cash_out' => $revenue['total_cash_out'],
            ],
        );
    }

    /**
     * Bangun ulang ringkasan untuk rentang tanggal (inklusif).
     */
    public function buildRange(CarbonInterface $from, CarbonInterface $to): int
    {
        $count = 0;

        for ($date = Carbon::parse($from)->startOfDay();
            $date->lte(Carbon::parse($to)->endOfDay());
            $date->addDay()) {
            $this->build($date->copy());
            $count++;
        }

        return $count;
    }

    /**
     * Bangun ulang ringkasan hari ini (dipanggil bila perlu penyegaran cepat).
     */
    public function buildToday(): DailySummary
    {
        return $this->build(Carbon::today());
    }
}
