<?php

namespace App\Services;

use App\Models\CashMutation;
use App\Models\DailySummary;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Omset Kotor/Bersih & komisi mekanik.
 *
 * Rumus (PRD §3.2, RINGKASAN §H):
 * - Omset Kotor  = penjualan produk (stok + luar) + total tarif jasa.
 * - Omset Bersih = (penjualan produk - HPP produk) + porsi bengkel dari jasa.
 *
 * HPP diambil dari snapshot `transaction_details.purchase_price`, bukan master
 * produk, sehingga aman dari perubahan harga & tidak bergantung akses viewHpp.
 *
 * Hari lampau dibaca dari `daily_summaries` (RESIKO_HOSTING.md #10) agar retensi
 * transaksi tidak membuat laporan lama hilang. Hari ini / tanggal tanpa ringkasan
 * dihitung live lalu disimpan (warm cache) untuk hari lampau.
 */
class ReportService
{
    /**
     * Ringkasan omset untuk rentang tanggal (inklusif), hemat query via daily_summaries.
     *
     * @return array{gross_revenue: float, net_revenue: float, total_transactions: int, total_cash_in: float, total_cash_out: float, refund_total: float, cogs: float, mechanic_fee: float, bengkel_fee: float}
     */
    public function revenue(CarbonInterface $from, CarbonInterface $to): array
    {
        $gross = 0.0;
        $net = 0.0;
        $count = 0;
        $cashIn = 0.0;
        $cashOut = 0.0;
        $refund = 0.0;
        $cogs = 0.0;
        $mechanicFee = 0.0;
        $bengkelFee = 0.0;

        foreach ($this->dayRows($from, $to) as $row) {
            $gross += $row['gross_revenue'];
            $net += $row['net_revenue'];
            $count += $row['total_transactions'];
            $cashIn += $row['total_cash_in'];
            $cashOut += $row['total_cash_out'];
            $refund += $row['refund_total'];
            $cogs += $row['cogs'];
            $mechanicFee += $row['mechanic_fee'];
            $bengkelFee += $row['bengkel_fee'];
        }

        return [
            'gross_revenue' => round($gross, 2),
            'net_revenue' => round($net, 2),
            'total_transactions' => $count,
            'total_cash_in' => round($cashIn, 2),
            'total_cash_out' => round($cashOut, 2),
            'refund_total' => round($refund, 2),
            'cogs' => round($cogs, 2),
            'mechanic_fee' => round($mechanicFee, 2),
            'bengkel_fee' => round($bengkelFee, 2),
        ];
    }

    /**
     * Hitung omset langsung dari data mentah (tanpa daily_summaries).
     * Dipakai untuk rebuild ringkasan & untuk hari yang belum diringkas.
     *
     * Omset Bersih = Omset Kotor − Refund − HPP (bersih) − Gaji Mekanik.
     *
     * @return array{gross_revenue: float, net_revenue: float, total_transactions: int, total_cash_in: float, total_cash_out: float, refund_total: float, cogs: float, mechanic_fee: float, bengkel_fee: float}
     */
    public function computeRevenue(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $this->startOfDay($from);
        $end = $this->endOfDay($to);
        $base = $this->finalTransactions($from, $to);

        $gross = (float) (clone $base)->sum('grand_total');

        $cogs = (float) DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.work_status', Transaction::WORK_SELESAI)
            ->where('transactions.payment_status', Transaction::PAY_LUNAS)
            ->whereBetween('transactions.created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(transaction_details.purchase_price * transaction_details.qty), 0) as total')
            ->value('total');

        // Gaji mekanik = nominal yang benar-benar dibagikan (share), bukan porsi kotor.
        $mechanicFee = (float) DB::table('transaction_mechanic_shares as s')
            ->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->whereNull('t.deleted_at')
            ->where('t.work_status', Transaction::WORK_SELESAI)
            ->where('t.payment_status', Transaction::PAY_LUNAS)
            ->whereBetween('t.created_at', [$start, $end])
            ->sum('s.share_amount');

        $bengkelFee = (float) DB::table('transaction_services')
            ->join('transactions', 'transactions.id', '=', 'transaction_services.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.work_status', Transaction::WORK_SELESAI)
            ->where('transactions.payment_status', Transaction::PAY_LUNAS)
            ->whereBetween('transactions.created_at', [$start, $end])
            ->sum('transaction_services.bengkel_fee');

        // Refund (produk stok) mengurangi penjualan; HPP barang yang kembali dipulihkan.
        $refundTotal = (float) DB::table('transaction_returns as r')
            ->join('transactions as t', 't.id', '=', 'r.transaction_id')
            ->whereNull('t.deleted_at')
            ->where('t.work_status', Transaction::WORK_SELESAI)
            ->where('t.payment_status', Transaction::PAY_LUNAS)
            ->whereBetween('t.created_at', [$start, $end])
            ->sum('r.total');

        $cogsRefund = (float) DB::table('transaction_return_items as ri')
            ->join('transaction_returns as r', 'r.id', '=', 'ri.transaction_return_id')
            ->join('transactions as t', 't.id', '=', 'r.transaction_id')
            ->leftJoin('transaction_details as d', 'd.id', '=', 'ri.transaction_detail_id')
            ->whereNull('t.deleted_at')
            ->where('t.work_status', Transaction::WORK_SELESAI)
            ->where('t.payment_status', Transaction::PAY_LUNAS)
            ->whereBetween('t.created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ri.qty * COALESCE(d.purchase_price, 0)), 0) as total')
            ->value('total');

        $netCogs = round(max(0, $cogs - (float) $cogsRefund), 2);
        $cash = $this->cashTotals($from, $to);

        return [
            'gross_revenue' => round($gross, 2),
            'net_revenue' => round(($gross - $refundTotal) - $netCogs - $mechanicFee, 2),
            'total_transactions' => (clone $base)->count(),
            'total_cash_in' => $cash['in'],
            'total_cash_out' => $cash['out'],
            'refund_total' => round($refundTotal, 2),
            'cogs' => $netCogs,
            'mechanic_fee' => round($mechanicFee, 2),
            'bengkel_fee' => round($bengkelFee, 2),
        ];
    }

    /**
     * Baris per hari untuk rentang (inklusif). Hari lampau pakai daily_summaries;
     * missing/today dihitung live (dan disimpan untuk hari lampau).
     *
     * @return array<int, array{date: string, gross_revenue: float, net_revenue: float, total_transactions: int, total_cash_in: float, total_cash_out: float}>
     */
    private function dayRows(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $this->startOfDay($from);
        $end = $this->endOfDay($to);

        $summaries = DailySummary::query()
            ->whereDate('summary_date', '>=', $start->toDateString())
            ->whereDate('summary_date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (DailySummary $s) => $s->summary_date->toDateString());

        $rows = [];
        $today = Carbon::today();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();

            if ($date->isToday() || ! $summaries->has($key)) {
                $data = $this->computeRevenue($date, $date);

                if (! $date->isToday() && $date->lt($today)) {
                    $this->persistSummary($date, $data);
                }
            } else {
                $s = $summaries->get($key);
                $data = [
                    'gross_revenue' => (float) $s->gross_revenue,
                    'net_revenue' => (float) $s->net_revenue,
                    'total_transactions' => (int) $s->total_transactions,
                    'total_cash_in' => (float) $s->total_cash_in,
                    'total_cash_out' => (float) $s->total_cash_out,
                    'refund_total' => (float) $s->refund_total,
                    'cogs' => (float) $s->cogs,
                    'mechanic_fee' => (float) $s->mechanic_fee,
                    'bengkel_fee' => (float) $s->bengkel_fee,
                ];
            }

            $rows[] = ['date' => $key] + $data;
        }

        return $rows;
    }

    private function persistSummary(Carbon $date, array $data): void
    {
        DailySummary::storeForDate($date, [
            'gross_revenue' => $data['gross_revenue'],
            'net_revenue' => $data['net_revenue'],
            'total_transactions' => $data['total_transactions'],
            'total_cash_in' => $data['total_cash_in'],
            'total_cash_out' => $data['total_cash_out'],
            'refund_total' => $data['refund_total'],
            'cogs' => $data['cogs'],
            'mechanic_fee' => $data['mechanic_fee'],
            'bengkel_fee' => $data['bengkel_fee'],
        ]);
    }

    /**
     * Akumulasi komisi per mekanik (dari snapshot shares) untuk rentang tanggal.
     *
     * @return Collection<int, array{mechanic_id: int, mechanic_name: string, total_jobs: int, total_share: float}>
     */
    public function mechanicCommission(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $start = $this->startOfDay($from);
        $end = $this->endOfDay($to);

        // Hari yang sudah diringkas (transaksi mungkin sudah dihapus retensi).
        $summaries = DB::table('mechanic_daily_summaries as ms')
            ->join('mechanics as m', 'm.id', '=', 'ms.mechanic_id')
            ->whereDate('ms.summary_date', '>=', $start->toDateString())
            ->whereDate('ms.summary_date', '<=', $end->toDateString())
            ->groupBy('m.id', 'm.name')
            ->get([
                'm.id as mechanic_id',
                'm.name as mechanic_name',
                DB::raw('SUM(ms.total_jobs) as total_jobs'),
                DB::raw('SUM(ms.total_share) as total_share'),
            ]);

        // Hari yang belum diringkas (hitung live dari share).
        $live = DB::table('transaction_mechanic_shares as s')
            ->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->join('mechanics as m', 'm.id', '=', 's.mechanic_id')
            ->whereNull('t.deleted_at')
            ->where('t.work_status', Transaction::WORK_SELESAI)
            ->where('t.payment_status', Transaction::PAY_LUNAS)
            ->whereBetween('t.created_at', [$start, $end])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('mechanic_daily_summaries as ms')
                    ->whereRaw('DATE(ms.summary_date) = DATE(t.created_at)');
            })
            ->groupBy('m.id', 'm.name')
            ->get([
                'm.id as mechanic_id',
                'm.name as mechanic_name',
                DB::raw('COUNT(DISTINCT s.transaction_id) as total_jobs'),
                DB::raw('SUM(s.share_amount) as total_share'),
            ]);

        return $summaries->concat($live)
            ->groupBy('mechanic_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'mechanic_id' => (int) $first->mechanic_id,
                    'mechanic_name' => $first->mechanic_name,
                    'total_jobs' => (int) $rows->sum('total_jobs'),
                    'total_share' => round((float) $rows->sum('total_share'), 2),
                ];
            })
            ->sortByDesc('total_share')
            ->values();
    }

    /**
     * Total komisi (pendapatan) satu mekanik untuk rentang tanggal.
     * Tetap akurat walau transaksi lama sudah dihapus retensi (pakai ringkasan).
     */
    public function mechanicEarned(int $mechanicId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): float
    {
        $summarySum = (float) DB::table('mechanic_daily_summaries')
            ->where('mechanic_id', $mechanicId)
            ->when($from, fn ($q) => $q->whereDate('summary_date', '>=', Carbon::parse($from)->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('summary_date', '<=', Carbon::parse($to)->toDateString()))
            ->sum('total_share');

        $live = (float) DB::table('transaction_mechanic_shares as s')
            ->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->where('s.mechanic_id', $mechanicId)
            ->whereNull('t.deleted_at')
            ->where('t.work_status', Transaction::WORK_SELESAI)
            ->where('t.payment_status', Transaction::PAY_LUNAS)
            ->when($from, fn ($q) => $q->where('t.created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn ($q) => $q->where('t.created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('mechanic_daily_summaries as ms')
                    ->whereRaw('DATE(ms.summary_date) = DATE(t.created_at)');
            })
            ->sum('s.share_amount');

        return round($summarySum + $live, 2);
    }

    /**
     * Deret harian untuk grafik/ringkasan (hemat: pakai daily_summaries).
     *
     * @return Collection<int, array{date: string, gross_revenue: float, net_revenue: float, total_transactions: int}>
     */
    public function dailySeries(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return collect($this->dayRows($from, $to))->map(fn ($row) => [
            'date' => $row['date'],
            'gross_revenue' => $row['gross_revenue'],
            'net_revenue' => $row['net_revenue'],
            'total_transactions' => $row['total_transactions'],
        ]);
    }

    /**
     * @return Builder<Transaction>
     */
    private function finalTransactions(CarbonInterface $from, CarbonInterface $to)
    {
        return Transaction::query()
            ->where('work_status', Transaction::WORK_SELESAI)
            ->where('payment_status', Transaction::PAY_LUNAS)
            ->whereBetween('created_at', [$this->startOfDay($from), $this->endOfDay($to)]);
    }

    /**
     * @return array{in: float, out: float}
     */
    private function cashTotals(CarbonInterface $from, CarbonInterface $to): array
    {
        $in = (float) CashMutation::where('type', CashMutation::TYPE_IN)
            ->whereBetween('created_at', [$this->startOfDay($from), $this->endOfDay($to)])
            ->sum('amount');

        $out = (float) CashMutation::where('type', CashMutation::TYPE_OUT)
            ->whereBetween('created_at', [$this->startOfDay($from), $this->endOfDay($to)])
            ->sum('amount');

        return ['in' => round($in, 2), 'out' => round($out, 2)];
    }

    private function startOfDay(CarbonInterface $date): Carbon
    {
        return Carbon::parse($date)->startOfDay();
    }

    private function endOfDay(CarbonInterface $date): Carbon
    {
        return Carbon::parse($date)->endOfDay();
    }
}
