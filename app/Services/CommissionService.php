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
        [$mechanicPercentage, $bengkelPercentage] = $this->resolvePercentages($mechanicOrRatio);

        $mechanicFee = round($servicePrice * $mechanicPercentage / 100, 2);
        $bengkelFee = round($servicePrice - $mechanicFee, 2);

        return [
            'mechanic_fee' => $mechanicFee,
            'bengkel_fee' => $bengkelFee,
            'bengkel_percentage' => $bengkelPercentage,
        ];
    }

    /**
     * @return array{0: float, 1: float} [rasio mekanik, rasio bengkel]
     */
    private function resolvePercentages(Mechanic|float|null $value): array
    {
        if ($value instanceof Mechanic) {
            $mechanic = (float) $value->mechanic_percentage;
            $bengkel = $value->bengkel_percentage !== null
                ? (float) $value->bengkel_percentage
                : round(100 - $mechanic, 2);

            return [$mechanic, $bengkel];
        }

        if (is_float($value) || is_int($value)) {
            return [(float) $value, round(100 - (float) $value, 2)];
        }

        // Fallback: rasio bengkel global.
        $bengkel = Setting::bengkelPercentage();

        return [round(100 - $bengkel, 2), $bengkel];
    }
}
