<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function index(): View
    {
        $users = User::where('role', User::ROLE_KASIR)
            ->orderBy('name')
            ->paginate(15);

        return view('manage.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('manage.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['role'] = User::ROLE_KASIR;

        $user = User::create($data);

        $this->activityLog->log('create kasir', $user, new: $user->only(['name', 'username', 'is_active']));

        return redirect()->route('manage.users.index')->with('status', 'Akun kasir berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        abort_unless($user->isKasir(), 404);

        return view('manage.users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->isKasir(), 404);

        $original = $user->only(['name', 'username', 'is_active']);
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        $this->activityLog->logModelUpdate('update kasir', $user, $original, ['password']);

        return redirect()->route('manage.users.index')->with('status', 'Akun kasir berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->isKasir(), 404);
        abort_if($user->id === auth()->id(), 403, 'Tidak dapat menghapus akun sendiri.');

        $this->activityLog->log('delete kasir', $user, old: $user->only(['name', 'username']));
        $user->delete();

        return redirect()->route('manage.users.index')->with('status', 'Akun kasir dihapus.');
    }
}
