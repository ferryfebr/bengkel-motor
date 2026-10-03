<?php

namespace App\Services;

use App\Models\CashMutation;
use App\Models\Mechanic;
use App\Models\MechanicPayout;
use App\Models\User;
use Illuminate\Support\Carbon;

class MechanicPayrollService
{
    public function __construct(
        private readonly CashService $cashService,
        private readonly ReportService $reportService,
    ) {}

    /**
     * Total gaji (komisi) mekanik pada rentang tanggal. Tanpa rentang = seumur hidup.
     * Baca ringkasan harian agar tetap utuh setelah retensi menghapus transaksi lama.
     */
    public function earned(int $mechanicId, ?Carbon $from = null, ?Carbon $to = null): float
    {
        return $this->reportService->mechanicEarned($mechanicId, $from, $to);
    }

    /**
     * Total gaji yang sudah ditarik (seumur hidup).
     */
    public function withdrawn(int $mechanicId): float
    {
        return (float) MechanicPayout::where('mechanic_id', $mechanicId)->sum('amount');
    }

    /**
     * Saldo gaji = total komisi seumur hidup − total penarikan. Bisa minus (kasbon).
     */
    public function balance(int $mechanicId): float
    {
        return round($this->earned($mechanicId) - $this->withdrawn($mechanicId), 2);
    }

    /**
     * Catat penarikan gaji mekanik + kas keluar. Boleh melebihi saldo (minus = kasbon).
     */
    public function payout(Mechanic $mechanic, float $amount, ?string $description, User $user, ?int $impersonatedBy = null): MechanicPayout
    {
        $payout = MechanicPayout::create([
            'mechanic_id' => $mechanic->id,
            'amount' => round($amount, 2),
            'description' => $description,
            'user_id' => $user->id,
            'impersonated_by' => $impersonatedBy,
        ]);

        $this->cashService->record(
            CashMutation::TYPE_OUT,
            round($amount, 2),
            'Gaji mekanik - '.$mechanic->name.($description ? ' ('.$description.')' : ''),
            $user,
            null,
            $impersonatedBy,
            CashMutation::CATEGORY_MECHANIC_PAYOUT,
        );

        return $payout;
    }
}
