<?php

namespace App\Http\Controllers;

use App\Models\ImpersonationLog;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ImpersonationController extends Controller
{
    public function __construct(
        private readonly ImpersonationService $impersonation,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Daftar user yang bisa di-impersonate (owner/kasir aktif, kecuali diri sendiri).
     */
    public function index(Request $request): View
    {
        $this->authorizeImpersonator($request);

        $users = User::where('is_active', true)
            ->whereKeyNot($request->user()->id)
            ->whereIn('role', [User::ROLE_OWNER, User::ROLE_KASIR])
            ->orderBy('name')
            ->get();

        return view('impersonation.index', [
            'users' => $users,
            'activeLog' => $this->impersonation->isActive()
                ? ImpersonationLog::with(['admin', 'targetUser'])->find(session('impersonation_log_id'))
                : null,
        ]);
    }

    /**
     * Mulai "Login Sebagai" user target.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeImpersonator($request);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $target = User::findOrFail($validated['user_id']);
        $admin = $request->user();

        try {
            $this->impersonation->start($admin, $target);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Catat atas nama ADMIN asli (bukan target), karena Auth sudah beralih ke target.
        $this->activityLog->log('impersonate start', $target, new: [
            'target_user_id' => $target->id,
        ], userId: $admin->id, impersonatedBy: $admin->id);

        return redirect()->route('dashboard')
            ->with('status', "Anda masuk sebagai {$target->name}.");
    }

    /**
     * Akhiri sesi impersonation & kembali ke admin asli.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if (! $this->impersonation->isActive()) {
            return redirect()->route('dashboard');
        }

        $logId = session('impersonation_log_id');
        $adminId = session('impersonating_admin_id');
        $targetId = $request->user()->id;

        $this->impersonation->end();

        $this->activityLog->log('impersonate end', User::class, $targetId, old: [
            'impersonation_log_id' => $logId,
        ], impersonatedBy: $adminId);

        return redirect()->route('dashboard')
            ->with('status', 'Anda kembali ke akun asli.');
    }

    private function authorizeImpersonator(Request $request): void
    {
        abort_unless($request->user()?->canImpersonate(), 403, 'Anda tidak berhak melakukan impersonation.');
    }
}
