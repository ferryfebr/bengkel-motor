<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function index(): View
    {
        $categories = Category::withCount('products')->orderBy('name')->paginate(15);

        return view('manage.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('manage.categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());
        $this->activityLog->log('create', $category, new: $request->validated());

        return redirect()->route('manage.categories.index')->with('status', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category): View
    {
        return view('manage.categories.edit', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $original = $category->getOriginal();
        $category->update($request->validated());
        $this->activityLog->logModelUpdate('update', $category, $original);

        return redirect()->route('manage.categories.index')->with('status', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        abort_unless(request()->user()->hasRole('owner', 'super_admin'), 403);

        if ($category->products()->exists()) {
            return back()->with('error', 'Kategori masih dipakai produk, tidak bisa dihapus.');
        }

        $category->delete();

        $this->activityLog->log('delete', $category, old: ['name' => $category->name]);

        return redirect()->route('manage.categories.index')->with('status', 'Kategori dihapus.');
    }
}
