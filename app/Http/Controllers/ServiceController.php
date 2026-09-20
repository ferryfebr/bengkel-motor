<?php

namespace App\Http\Controllers;

use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function index(): View
    {
        $services = Service::orderBy('name')->paginate(15);

        return view('manage.services.index', compact('services'));
    }

    public function create(): View
    {
        return view('manage.services.create');
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $service = Service::create($request->validated());
        $this->activityLog->log('create', $service, new: $request->validated());

        return redirect()->route('manage.services.index')->with('status', 'Jasa berhasil ditambahkan.');
    }

    public function edit(Service $service): View
    {
        return view('manage.services.edit', compact('service'));
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $original = $service->getOriginal();
        $service->update($request->validated());
        $this->activityLog->logModelUpdate('update', $service, $original);

        return redirect()->route('manage.services.index')->with('status', 'Jasa berhasil diperbarui.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->activityLog->log('delete', $service, old: ['name' => $service->name]);
        $service->delete();

        return redirect()->route('manage.services.index')->with('status', 'Jasa dihapus.');
    }
}
