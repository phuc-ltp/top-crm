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
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('supplier'), fn ($q) => $q->where('supplier_id', $request->supplier))
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

        $product = new Product(['status' => 'active', 'unit' => 'cái', 'type' => 'product']);
        // "Thêm biến thể" từ trang sản phẩm cha: điền sẵn thông tin của cha
        if ($parent = Product::whereNull('parent_id')->find(request()->integer('parent'))) {
            $product->fill($parent->only(['name', 'type', 'product_category_id', 'supplier_id', 'price', 'purchase_price', 'unit', 'description']) + ['parent_id' => $parent->id]);
        }

        return view('products.form', ['product' => $product, 'categories' => Lookup::categories(), 'suppliers' => $this->supplierOptions(), 'parents' => $this->parentOptions()]);
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
        $product->load('category', 'supplier', 'parent', 'variants');
        $stock = $product->type === 'product' && request()->user()->can('inventory.view')
            ? $product->stockLevels()->with('warehouse:id,name')->get() : collect();
        // Lịch sử thay đổi (giá, giá vốn, trạng thái...) — chỉ người được sửa sản phẩm (có giá vốn)
        $history = request()->user()->can('update', $product)
            ? \App\Models\ActivityLog::with('user:id,name')->where('subject_type', $product->getMorphClass())->where('subject_id', $product->id)
                ->latest('created_at')->limit(30)->get()
            : collect();
        $sales = \App\Models\InvoiceItem::where('product_id', $product->id)
            ->whereHas('invoice', fn ($q) => $q->where('status', 'issued'))
            ->selectRaw('COALESCE(SUM(quantity),0) as qty, COALESCE(SUM(line_total),0) as revenue')->first();

        return view('products.show', compact('product', 'sales', 'history', 'stock'));
    }

    public function edit(Product $product)
    {
        $this->authorize('update', $product);

        return view('products.form', ['product' => $product, 'categories' => Lookup::categories(), 'suppliers' => $this->supplierOptions($product->supplier_id),
            'parents' => $product->variants()->exists() ? [] : $this->parentOptions($product)]);
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

        return redirect()->route('products.index')->with('success', __('Đã xóa sản phẩm :name.', ['name' => $product->name]).' '.__('Có thể khôi phục trong Thùng rác.'));
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', Product::class);
        $rows = $this->filtered($request)->orderBy('sku')->get()->map(fn (Product $p) => [
            $p->id, $p->sku, $p->name, $p->category?->name, (float) $p->price, $p->unit, label_of(Product::STATUSES, $p->status), vn_date($p->created_at),
        ]);

        return $exporter->download('san_pham', ['ID', 'SKU', __('Tên sản phẩm'), __('Danh mục'), __('Giá'), __('Đơn vị'), __('Trạng thái'), __('Ngày tạo')], $rows, $request->input('format', 'xlsx'));
    }

    /** Sản phẩm gốc có thể làm cha (không phải biến thể, không phải chính nó). */
    private function parentOptions(?Product $except = null): array
    {
        return Product::whereNull('parent_id')->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->orderBy('name')->get(['id', 'sku', 'name'])->mapWithKeys(fn ($p) => [$p->id => $p->sku.' — '.$p->name])->all();
    }

    /** Nhà cung cấp đang hợp tác (+ nhà cung cấp hiện tại của sản phẩm dù đã ngừng). */
    private function supplierOptions(?int $current = null): array
    {
        return \App\Models\Supplier::where(fn ($q) => $q->active()->when($current, fn ($w) => $w->orWhere('id', $current)))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_.]+$/', \App\Support\Tenancy\TenantRule::unique('products', 'sku')->ignore($product?->id)],
            'type' => ['sometimes', 'required', Rule::in(array_keys(Product::TYPES))],   // không gửi: tạo mới = sản phẩm, sửa = giữ nguyên
            'supplier_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('suppliers')->whereNull('deleted_at')],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            // Biến thể: 1 cấp — cha phải là sản phẩm gốc (không phải biến thể), không phải chính nó; sản phẩm đang có biến thể không làm biến thể được
            'parent_id' => ['nullable', 'integer', \App\Support\Tenancy\TenantRule::exists('products')->whereNull('parent_id')->whereNull('deleted_at'),
                function ($attr, $value, $fail) use ($product) {
                    if ($product && (int) $value === $product->id) {
                        $fail(__('Sản phẩm không thể là biến thể của chính nó.'));
                    } elseif ($product && $value && $product->variants()->exists()) {
                        $fail(__('Sản phẩm đang có biến thể nên không thể trở thành biến thể của sản phẩm khác.'));
                    }
                }],
            'variant_name' => ['nullable', 'required_with:parent_id', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('product_categories')],
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
