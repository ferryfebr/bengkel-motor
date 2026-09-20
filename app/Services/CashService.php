<?php

namespace App\Services;

use App\Models\CashMutation;
use App\Models\Transaction;
use App\Models\User;

class CashService
{
    public function record(
        string $type,
        float $amount,
        ?string $description,
        User $user,
        ?Transaction $transaction = null,
        ?int $impersonatedBy = null,
    ): CashMutation {
        return CashMutation::create([
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'transaction_id' => $transaction?->id,
            'user_id' => $user->id,
            'impersonated_by' => $impersonatedBy,
        ]);
    }

    /**
     * Kas keluar otomatis dari HPP produk luar saat transaksi final.
     */
    public function recordExternalPurchase(Transaction $transaction, User $user, ?int $impersonatedBy = null): void
    {
        $transaction->loadMissing('details');

        $totalHpp = (float) $transaction->details
            ->where('is_external', true)
            ->sum(fn ($d) => (float) $d->purchase_price * $d->qty);

        if ($totalHpp <= 0) {
            return;
        }

        $this->record(
            CashMutation::TYPE_OUT,
            $totalHpp,
            'Pembelian produk luar - '.$transaction->invoice_number,
            $user,
            $transaction,
            $impersonatedBy,
        );
    }
}
