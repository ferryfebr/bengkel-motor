<?php

namespace App\Services;

use App\Models\RetentionRunLog;
use Illuminate\Support\Carbon;

/**
 * Mencatat jejak proses retensi ke retention_run_logs (append-only).
 */
class RetentionLogService
{
    /**
     * @param  array<string, mixed>  $details  Rincian jumlah baris per tabel.
     */
    public function record(
        string $runType,
        string $trigger,
        ?int $userId,
        int $archivedCount,
        int $deletedCount,
        array $details = [],
        string $status = RetentionRunLog::STATUS_SUCCESS,
        ?string $message = null,
        ?Carbon $startedAt = null,
    ): RetentionRunLog {
        return RetentionRunLog::create([
            'run_type' => $runType,
            'trigger' => $trigger === RetentionRunLog::TRIGGER_MANUAL
                ? RetentionRunLog::TRIGGER_MANUAL
                : RetentionRunLog::TRIGGER_CRON,
            'user_id' => $userId,
            'status' => $status,
            'archived_count' => max(0, $archivedCount),
            'deleted_count' => max(0, $deletedCount),
            'details' => $details !== [] ? $details : null,
            'message' => $message,
            'started_at' => $startedAt ?? now(),
            'finished_at' => now(),
        ]);
    }
}
