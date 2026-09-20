<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    /**
     * Hapus nilai HPP dari snapshot log bila aktor tidak berhak melihatnya.
     * Lihat SECURITY.md §2: purchase_price produk stok hanya untuk owner & super_admin.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    public function redactHpp(?array $values, ?User $user = null): ?array
    {
        if ($values === null || ! array_key_exists('purchase_price', $values)) {
            return $values;
        }

        $user ??= auth()->user();

        if ($user instanceof User && ! $user->can('viewHpp', Product::class)) {
            unset($values['purchase_price']);
        }

        return $values;
    }

    /**
     * Catat diff perubahan atribut model, menyembunyikan HPP dari aktor non-owner.
     *
     * @param  array<string, mixed>  $except
     */
    public function logModelUpdate(string $action, Model $model, array $original, array $except = []): ?ActivityLog
    {
        $keys = array_keys($model->getChanges());

        $old = [];
        $new = [];
        foreach ($keys as $key) {
            if (in_array($key, $except, true)) {
                continue;
            }
            $old[$key] = $original[$key] ?? null;
            $new[$key] = $model->getAttribute($key);
        }

        if ($old === [] && $new === []) {
            return null;
        }

        return $this->log($action, $model, $model->getKey(), $this->redactHpp($old), $this->redactHpp($new));
    }

    /**
     * Catat perubahan data non-transaksional ke activity_logs.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(
        string $action,
        Model|string $model,
        ?int $modelId = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
        ?int $impersonatedBy = null,
    ): ActivityLog {
        $modelType = $model instanceof Model ? $model->getMorphClass() : $model;
        $modelId ??= $model instanceof Model ? $model->getKey() : null;

        return ActivityLog::create([
            'user_id' => $userId ?? auth()->id(),
            'impersonated_by' => $impersonatedBy ?? request()->attributes->get('impersonated_by'),
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    /**
     * Bandingkan atribut model sebelum & sesudah, lalu catat bila ada perubahan.
     */
    public function logChanges(string $action, Model $model, array $oldValues, array $newValues): ?ActivityLog
    {
        if ($oldValues === $newValues) {
            return null;
        }

        return $this->log($action, $model, $model->getKey(), $oldValues, $newValues);
    }
}
