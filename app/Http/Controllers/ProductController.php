<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ExportService;
use App\Services\UploadService;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private function filtered(Request $request)
    {
        return Product::with('category:id,name')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('product_category_id', $request->category))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('min_price'), fn ($q) => $q->where('price', '>=', (float) $request->min_price))
            ->when($request->filled('max_price'), fn ($q) => $q->where('price', '<=', (float) $request->max_price));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);
        $products = $this->applySort($this->filtered($request), $request, ['sku', 'name', 'price', 'created_at'])
            ->paginate($this->perPage($request))->withQueryString();

        return view('products.index', ['products' => $products, 'categories' => Lookup::categories()]);
    }

    public function create()
    {
        $this->authorize('create', Product::class);

        return view('products.form', ['product' => new Product(['status' => 'active', 'unit' => 'cái']), 'categories' => Lookup::categories()]);
    }

    public function store(Request $request, UploadService $uploads)
    {
        $this->authorize('create', Product::class);
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image'] = $uploads->replacePublicImage($request->file('image'), 'products');
        }
        $product = Product::create($data);

        return redirect()->route('products.show', $product)->with('success', __('Đã thêm sản phẩm :name.', ['name' => $product->name]));
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);
        $product->load('category');
        $sales = \App\Models\InvoiceItem::where('product_id', $product->id)
            ->whereHas('invoice', fn ($q) => $q->where('status', 'issued'))
            ->selectRaw('COALESCE(SUM(quantity),0) as qty, COALESCE(SUM(line_total),0) as revenue')->first();

        return view('products.show', compact('product', 'sales'));
    }

    public function edit(Product $product)
    {
        $this->authorize('update', $product);

        return view('products.form', ['product' => $product, 'categories' => Lookup::categories()]);
    }

    public function update(Request $request, Product $product, UploadService $uploads)
    {
        $this->authorize('update', $product);
        $data = $this->validated($request, $product);
        if ($request->hasFile('image')) {
            $data['image'] = $uploads->replacePublicImage($request->file('image'), 'products', $product->image);
        } elseif ($request->boolean('remove_image')) {
            $uploads->deletePublic($product->image);
            $data['image'] = null;
        }
        $product->update($data);

        return redirect()->route('products.show', $product)->with('success', __('Đã cập nhật sản phẩm.'));
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);
        $product->delete(); // xóa mềm, chứng từ cũ vẫn giữ tham chiếu

        return redirect()->route('products.index')->with('success', __('Đã xóa sản phẩm :name.', ['name' => $product->name]));
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', Product::class);
        $rows = $this->filtered($request)->orderBy('sku')->get()->map(fn (Product $p) => [
            $p->id, $p->sku, $p->name, $p->category?->name, (float) $p->price, $p->unit, label_of(Product::STATUSES, $p->status), vn_date($p->created_at),
        ]);

        return $exporter->download('san_pham', ['ID', 'SKU', __('Tên sản phẩm'), __('Danh mục'), __('Giá'), __('Đơn vị'), __('Trạng thái'), __('Ngày tạo')], $rows, $request->input('format', 'xlsx'));
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_.]+$/', Rule::unique('products', 'sku')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'unit' => ['required', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_keys(Product::STATUSES))],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ], ['sku.regex' => __('Mã SKU chỉ gồm chữ, số, dấu gạch ngang, gạch dưới, dấu chấm.')], ['name' => __('Tên sản phẩm')]);
        unset($data['image'], $data['remove_image']);

        return $data;
    }
}
