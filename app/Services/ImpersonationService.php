<?php

namespace App\Services;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use RuntimeException;

/**
 * Sesi "Login Sebagai" (impersonation). Lihat SECURITY.md §3.
 *
 * Setiap sesi WAJIB tercatat di impersonation_logs, dan setiap aksi selama
 * sesi aktif menyimpan `impersonated_by` (lihat middleware TrackImpersonation).
 */
class ImpersonationService
{
    private const SESSION_LOG_ID = 'impersonation_log_id';

    private const SESSION_ADMIN_ID = 'impersonating_admin_id';

    /**
     * Mulai sesi impersonation: catat log, simpan sesi, login sebagai target.
     */
    public function start(User $admin, User $targetUser): ImpersonationLog
    {
        if (! $admin->canImpersonate()) {
            throw new RuntimeException('Anda tidak berhak melakukan impersonation.');
        }

        if (! $targetUser->canBeImpersonated()) {
            throw new RuntimeException('User target tidak dapat di-impersonate.');
        }

        if ($targetUser->hasRole(User::ROLE_SUPER_ADMIN)) {
            throw new RuntimeException('Akun Super Admin tidak dapat di-impersonate.');
        }

        if ($this->isActive()) {
            throw new RuntimeException('Sesi impersonation lain sedang aktif.');
        }

        $log = ImpersonationLog::create([
            'admin_id' => $admin->id,
            'target_user_id' => $targetUser->id,
            'started_at' => now(),
        ]);

        // Ingat admin asli & id log di sesi SEBELUM login sebagai target.
        Session::put(self::SESSION_LOG_ID, $log->id);
        Session::put(self::SESSION_ADMIN_ID, $admin->id);

        Auth::login($targetUser);

        return $log;
    }

    /**
     * Akhiri sesi impersonation: isi ended_at (satu-satunya kolom yang boleh
     * di-update), lalu kembalikan sesi ke admin asli.
     */
    public function end(): void
    {
        $logId = Session::get(self::SESSION_LOG_ID);
        $adminId = Session::get(self::SESSION_ADMIN_ID);

        Session::forget([self::SESSION_LOG_ID, self::SESSION_ADMIN_ID]);

        if ($logId) {
            $log = ImpersonationLog::find($logId);
            if ($log && $log->ended_at === null) {
                $log->update(['ended_at' => now()]);
            }
        }

        $admin = $adminId ? User::find($adminId) : null;

        if ($admin) {
            Auth::login($admin);
        } else {
            Auth::logout();
        }
    }

    public function isActive(): bool
    {
        return Session::has(self::SESSION_LOG_ID);
    }

    public function adminId(): ?int
    {
        return Session::get(self::SESSION_ADMIN_ID);
    }
}
