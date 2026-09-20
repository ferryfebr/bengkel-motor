<?php

namespace App\Services;

use App\Models\Mechanic;
use App\Models\TransactionMechanicShare;
use App\Models\TransactionService;
use RuntimeException;

class MechanicShareService
{
    /**
     * Simpan pembagian nominal manual oleh kasir ke tiap mekanik.
     *
     * Aturan (RINGKASAN_SISTEM_v3.md §3.F):
     * - Σ nominal mekanik ≤ porsi mekanik total.
     * - Sisa yang tidak dibagi otomatis menjadi milik bengkel.
     *
     * @param  array<int, array{mechanic_id: int, amount: float}>  $shares
     * @return float Total nominal yang dibagikan ke mekanik.
     */
    public function distribute(TransactionService $service, array $shares): float
    {
        $porsiMekanik = (float) $service->mechanic_fee;
        $total = round(array_sum(array_map(fn ($s) => (float) $s['amount'], $shares)), 2);

        if ($total > $porsiMekanik + 0.001) {
            throw new RuntimeException(
                'Total pembagian mekanik ('.number_format($total, 0, ',', '.').
                ') melebihi porsi mekanik ('.number_format($porsiMekanik, 0, ',', '.').').'
            );
        }

        foreach ($shares as $share) {
            $mechanic = Mechanic::findOrFail($share['mechanic_id']);

            TransactionMechanicShare::create([
                'transaction_id' => $service->transaction_id,
                'transaction_service_id' => $service->id,
                'mechanic_id' => $mechanic->id,
                'mechanic_ratio' => $mechanic->mechanic_percentage,
                'share_amount' => (float) $share['amount'],
            ]);
        }

        // Sisa porsi mekanik yang tidak dibagi menjadi milik bengkel.
        $sisa = round($porsiMekanik - $total, 2);
        if ($sisa > 0) {
            $service->update([
                'bengkel_fee' => (float) $service->bengkel_fee + $sisa,
            ]);
        }

        return $total;
    }
}
