<?php

namespace App\Services;

use App\Models\CashMutation;
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
 */
class ReportService
{
    /**
     * Ringkasan omset untuk rentang tanggal (inklusif).
     *
     * @return array{gross_revenue: float, net_revenue: float, total_transactions: int, total_cash_in: float, total_cash_out: float}
     */
    public function revenue(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = $this->finalTransactions($from, $to);

        $gross = (float) (clone $base)->sum('grand_total');
        $productSales = (float) (clone $base)->sum('subtotal_products');

        $cogs = (float) DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.work_status', Transaction::WORK_SELESAI)
            ->where('transactions.payment_status', Transaction::PAY_LUNAS)
            ->whereBetween('transactions.created_at', [$this->startOfDay($from), $this->endOfDay($to)])
            ->selectRaw('COALESCE(SUM(transaction_details.purchase_price * transaction_details.qty), 0) as total')
            ->value('total');

        $bengkelFee = (float) DB::table('transaction_services')
            ->join('transactions', 'transactions.id', '=', 'transaction_services.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.work_status', Transaction::WORK_SELESAI)
            ->where('transactions.payment_status', Transaction::PAY_LUNAS)
            ->whereBetween('transactions.created_at', [$this->startOfDay($from), $this->endOfDay($to)])
            ->sum('transaction_services.bengkel_fee');

        $cash = $this->cashTotals($from, $to);

        return [
            'gross_revenue' => round($gross, 2),
            'net_revenue' => round(($productSales - $cogs) + $bengkelFee, 2),
            'total_transactions' => (clone $base)->count(),
            'total_cash_in' => $cash['in'],
            'total_cash_out' => $cash['out'],
        ];
    }

    /**
     * Akumulasi komisi per mekanik (dari snapshot shares) untuk rentang tanggal.
     *
     * @return Collection<int, array{mechanic_id: int, mechanic_name: string, total_jobs: int, total_share: float}>
     */
    public function mechanicCommission(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('transaction_mechanic_shares as s')
            ->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->join('mechanics as m', 'm.id', '=', 's.mechanic_id')
            ->whereNull('t.deleted_at')
            ->where('t.work_status', Transaction::WORK_SELESAI)
            ->where('t.payment_status', Transaction::PAY_LUNAS)
            ->whereBetween('t.created_at', [$this->startOfDay($from), $this->endOfDay($to)])
            ->groupBy('m.id', 'm.name')
            ->orderByDesc(DB::raw('SUM(s.share_amount)'))
            ->get([
                'm.id as mechanic_id',
                'm.name as mechanic_name',
                DB::raw('COUNT(DISTINCT s.transaction_id) as total_jobs'),
                DB::raw('SUM(s.share_amount) as total_share'),
            ])
            ->map(fn ($row) => [
                'mechanic_id' => (int) $row->mechanic_id,
                'mechanic_name' => $row->mechanic_name,
                'total_jobs' => (int) $row->total_jobs,
                'total_share' => round((float) $row->total_share, 2),
            ]);
    }

    /**
     * Deret harian untuk grafik/ringkasan, dibaca dari daily_summaries bila ada
     * dan dihitung ulang untuk tanggal yang belum diringkas.
     *
     * @return Collection<int, array{date: string, gross_revenue: float, net_revenue: float, total_transactions: int}>
     */
    public function dailySeries(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $series = collect();

        for ($date = $this->startOfDay($from)->toDateString();
            $date <= $this->endOfDay($to)->toDateString();
            $date = Carbon::parse($date)->addDay()->toDateString()) {

            $row = $this->revenue(Carbon::parse($date), Carbon::parse($date));

            $series->push([
                'date' => $date,
                'gross_revenue' => $row['gross_revenue'],
                'net_revenue' => $row['net_revenue'],
                'total_transactions' => $row['total_transactions'],
            ]);
        }

        return $series;
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
