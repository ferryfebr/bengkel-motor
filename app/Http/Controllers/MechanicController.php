<?php

namespace App\Http\Controllers;

use App\Http\Requests\Mechanic\StoreMechanicRequest;
use App\Http\Requests\Mechanic\UpdateMechanicRequest;
use App\Models\Mechanic;
use App\Models\Setting;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MechanicController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function index(): View
    {
        $mechanics = Mechanic::orderBy('name')->paginate(15);
        $bengkelPercentage = Setting::bengkelPercentage();

        return view('manage.mechanics.index', compact('mechanics', 'bengkelPercentage'));
    }

    public function create(): View
    {
        return view('manage.mechanics.create');
    }

    public function store(StoreMechanicRequest $request): RedirectResponse
    {
        $mechanic = Mechanic::create($request->validated());
        $this->activityLog->log('create', $mechanic, new: $request->validated());

        return redirect()->route('manage.mechanics.index')->with('status', 'Mekanik berhasil ditambahkan.');
    }

    public function edit(Mechanic $mechanic): View
    {
        return view('manage.mechanics.edit', compact('mechanic'));
    }

    public function update(UpdateMechanicRequest $request, Mechanic $mechanic): RedirectResponse
    {
        $original = $mechanic->getOriginal();
        $mechanic->update($request->validated());

        if ((float) ($original['mechanic_percentage'] ?? 0) !== (float) $mechanic->mechanic_percentage) {
            $this->activityLog->log('update mechanic_ratio', $mechanic, null,
                ['mechanic_percentage' => $original['mechanic_percentage'] ?? null],
                ['mechanic_percentage' => $mechanic->mechanic_percentage],
            );
        }

        $this->activityLog->logModelUpdate('update', $mechanic, $original, ['mechanic_percentage']);

        return redirect()->route('manage.mechanics.index')->with('status', 'Mekanik berhasil diperbarui.');
    }

    public function destroy(Mechanic $mechanic): RedirectResponse
    {
        $this->activityLog->log('delete', $mechanic, old: ['name' => $mechanic->name]);
        $mechanic->delete();

        return redirect()->route('manage.mechanics.index')->with('status', 'Mekanik dihapus.');
    }

    public function updateBengkelPercentage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bengkel_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $old = Setting::bengkelPercentage();
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, $validated['bengkel_percentage']);

        $this->activityLog->log('update bengkel_ratio', Setting::class, null,
            ['bengkel_percentage' => $old],
            ['bengkel_percentage' => (float) $validated['bengkel_percentage']],
        );

        return redirect()->route('manage.mechanics.index')->with('status', 'Rasio bengkel diperbarui.');
    }
}
