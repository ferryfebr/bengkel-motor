<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::with('category')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->string('q');
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('code_sku', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('manage.products.index', [
            'products' => $products,
            'canViewHpp' => $request->user()->can('viewHpp', Product::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Product::class);

        return view('manage.products.create', [
            'categories' => Category::orderBy('name')->get(),
            'canManageHpp' => $request->user()->can('updateHpp', Product::class),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('purchase_price');
        if ($request->user()->can('updateHpp', Product::class)) {
            $data['purchase_price'] = $request->input('purchase_price');
        }

        $product = Product::create($data);

        // Activity: create product. HPP tercatat sebagai diff privat (owner saja).
        $this->activityLog->log('create', $product, new: $this->activityLog->redactHpp($data));

        return redirect()->route('manage.products.index')->with('status', 'Produk berhasil ditambahkan.');
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorize('update', $product);

        return view('manage.products.edit', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            'canManageHpp' => $request->user()->can('updateHpp', Product::class),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->safe()->except('purchase_price');
        if ($request->user()->can('updateHpp', Product::class)) {
            $data['purchase_price'] = $request->input('purchase_price');
        }

        $original = $product->getOriginal();
        $product->update($data);

        $this->logProductChanges($product, $original);

        return redirect()->route('manage.products.index')->with('status', 'Produk berhasil diperbarui.');
    }

    /**
     * Catat perubahan produk dengan action terpisah untuk harga jual, HPP, dan stok
     * (lihat RINGKASAN_SISTEM_v3.md §4.1).
     *
     * @param  array<string, mixed>  $original
     */
    private function logProductChanges(Product $product, array $original): void
    {
        $changes = $product->getChanges();

        if (array_key_exists('purchase_price', $changes)) {
            $this->activityLog->log('update product_hpp', $product, null,
                $this->activityLog->redactHpp(['purchase_price' => $original['purchase_price'] ?? null]),
                $this->activityLog->redactHpp(['purchase_price' => $product->purchase_price]),
            );
        }

        if (array_key_exists('selling_price', $changes)) {
            $this->activityLog->log('update product_price', $product, null,
                ['old_selling_price' => $original['selling_price'] ?? null],
                ['new_selling_price' => $product->selling_price],
            );
        }

        if (array_key_exists('stock', $changes)) {
            $this->activityLog->log('update stock', $product, null,
                ['stock' => $original['stock'] ?? null],
                ['stock' => $product->stock],
            );
        }

        $other = array_diff_key($changes, array_flip(['purchase_price', 'selling_price', 'stock', 'updated_at']));
        if ($other !== []) {
            $old = [];
            $new = [];
            foreach (array_keys($other) as $key) {
                $old[$key] = $original[$key] ?? null;
                $new[$key] = $product->getAttribute($key);
            }
            $this->activityLog->log('update', $product, null, $old, $new);
        }
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->activityLog->log('delete', $product, old: $this->activityLog->redactHpp($product->only(['name', 'code_sku'])));

        $product->delete();

        return redirect()->route('manage.products.index')->with('status', 'Produk dihapus.');
    }

    /**
     * Endpoint JSON untuk POS/scan barcode. Field HPP otomatis terfilter
     * sesuai ability viewHpp (lihat SECURITY.md §2).
     */
    public function lookup(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::with('category')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->string('q');
                $query->where(function ($sub) use ($q) {
                    $sub->where('code_sku', $q)
                        ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return ProductResource::collection($products);
    }
}
