<?php

namespace App\Services;

use App\Models\Mechanic;
use App\Models\Setting;

class CommissionService
{
    /**
     * Hitung porsi mekanik & bengkel dari satu nominal jasa.
     *
     * Aturan (RINGKASAN_SISTEM_v3.md §3.F):
     * - Porsi mekanik = rasio mekanik × jasa (rasio per mekanik, bukan flat).
     * - Porsi bengkel = jasa − porsi mekanik.
     *
     * @return array{mechanic_fee: float, bengkel_fee: float, bengkel_percentage: float}
     */
    public function split(float $servicePrice, Mechanic|float|null $mechanicOrRatio = null): array
    {
        $mechanicPercentage = $this->resolveMechanicPercentage($mechanicOrRatio);

        $mechanicFee = round($servicePrice * $mechanicPercentage / 100, 2);
        $bengkelFee = round($servicePrice - $mechanicFee, 2);

        return [
            'mechanic_fee' => $mechanicFee,
            'bengkel_fee' => $bengkelFee,
            'bengkel_percentage' => round(100 - $mechanicPercentage, 2),
        ];
    }

    private function resolveMechanicPercentage(Mechanic|float|null $value): float
    {
        if ($value instanceof Mechanic) {
            return (float) $value->mechanic_percentage;
        }

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        // Fallback: 100 − rasio bengkel global.
        return round(100 - Setting::bengkelPercentage(), 2);
    }
}
