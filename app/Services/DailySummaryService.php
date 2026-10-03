<?php

namespace App\Services;

use App\Models\DailySummary;
use App\Models\MechanicDailySummary;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi `daily_summaries` (omset) & `mechanic_daily_summaries` (komisi per
 * mekanik) agar laporan tidak menghitung ulang seluruh riwayat tiap dibuka
 * dan tetap utuh setelah transaksi lama dihapus retensi (RESIKO_HOSTING.md).
 */
class DailySummaryService
{
    public function __construct(private readonly ReportService $reportService) {}

    /**
     * Hitung ulang & simpan ringkasan satu hari (omset + komisi mekanik).
     */
    public function build(CarbonInterface $date): DailySummary
    {
        $date = Carbon::parse($date)->startOfDay();

        // Selalu hitung ulang dari data mentah (bukan baca ringkasan lama).
        $revenue = $this->reportService->computeRevenue($date, $date);

        $summary = DailySummary::storeForDate($date, [
            'gross_revenue' => $revenue['gross_revenue'],
            'net_revenue' => $revenue['net_revenue'],
            'total_transactions' => $revenue['total_transactions'],
            'total_cash_in' => $revenue['total_cash_in'],
            'total_cash_out' => $revenue['total_cash_out'],
            'refund_total' => $revenue['refund_total'],
            'cogs' => $revenue['cogs'],
            'mechanic_fee' => $revenue['mechanic_fee'],
            'bengkel_fee' => $revenue['bengkel_fee'],
        ]);

        $this->buildMechanics($date);

        return $summary;
    }

    /**
     * Hitung ulang ringkasan komisi per mekanik untuk satu hari.
     */
    public function buildMechanics(CarbonInterface $date): void
    {
        $date = Carbon::parse($date)->startOfDay();
        $dateString = $date->toDateString();

        $rows = DB::table('transaction_mechanic_shares as s')
            ->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->whereNull('t.deleted_at')
            ->where('t.work_status', Transaction::WORK_SELESAI)
            ->where('t.payment_status', Transaction::PAY_LUNAS)
            ->whereDate('t.created_at', $dateString)
            ->groupBy('s.mechanic_id')
            ->get([
                's.mechanic_id',
                DB::raw('COUNT(DISTINCT s.transaction_id) as total_jobs'),
                DB::raw('SUM(s.share_amount) as total_share'),
            ]);

        // Ganti data tanggal ini.
        MechanicDailySummary::whereDate('summary_date', $dateString)->delete();

        foreach ($rows as $row) {
            MechanicDailySummary::create([
                'mechanic_id' => (int) $row->mechanic_id,
                'summary_date' => $dateString,
                'total_jobs' => (int) $row->total_jobs,
                'total_share' => round((float) $row->total_share, 2),
            ]);
        }
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
