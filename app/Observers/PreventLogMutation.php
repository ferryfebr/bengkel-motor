<?php

namespace App\Observers;

use App\Models\ImpersonationLog;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Blokir UPDATE/DELETE pada tabel log (append-only). Lihat SECURITY.md bagian 1.
 *
 * Pengecualian: ImpersonationLog boleh meng-update HANYA kolom `ended_at`
 * (dipakai saat sesi impersonation berakhir).
 */
class PreventLogMutation
{
    public function updating(Model $model): void
    {
        if ($model instanceof ImpersonationLog) {
            // Izinkan hanya bila seluruh perubahan terbatas pada ended_at.
            $dirty = array_keys($model->getDirty());
            if ($dirty === ['ended_at'] || $dirty === []) {
                return;
            }
        }

        throw new RuntimeException(class_basename($model).' bersifat append-only dan tidak boleh diubah.');
    }

    public function deleting(Model $model): void
    {
        throw new RuntimeException(class_basename($model).' bersifat append-only dan tidak boleh dihapus.');
    }
}
