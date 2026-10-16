<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Nhà cung cấp: danh sách / tìm / lọc / sắp xếp / xuất / tạo / xem / sửa / xóa (vào thùng rác). */
class SupplierController extends Controller
{
    private function filtered(Request $request)
    {
        return Supplier::withCount('products')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('contact_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)->orWhere('email', 'like', $term)->orWhere('tax_code', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Supplier::class);
        $request->validate(['status' => ['nullable', Rule::in(array_keys(Supplier::STATUSES))]]);
        $suppliers = $this->applySort($this->filtered($request), $request, ['code', 'name', 'products_count', 'created_at'], 'name')
            ->paginate($this->perPage($request))->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.form', ['supplier' => new Supplier(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Supplier::class);
        $supplier = Supplier::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Đã thêm nhà cung cấp :name.', ['name' => $supplier->name]));
    }

    public function show(Supplier $supplier)
    {
        $this->authorize('view', $supplier);
        $products = $supplier->products()->orderBy('name')->limit(50)->get(['id', 'sku', 'name', 'price', 'purchase_price', 'unit', 'status']);

        return view('suppliers.show', compact('supplier', 'products'));
    }

    public function edit(Supplier $supplier)
    {
        $this->authorize('update', $supplier);

        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);
        $supplier->update($this->validated($request));

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Đã cập nhật nhà cung cấp.'));
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('delete', $supplier);
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('Đã xóa nhà cung cấp :name.', ['name' => $supplier->name]).' '.__('Có thể khôi phục trong Thùng rác.'));
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', Supplier::class);
        $rows = $this->filtered($request)->orderBy('name')->limit(20000)->get()->map(fn (Supplier $s) => [
            $s->code, $s->name, $s->contact_name, $s->phone, $s->email, $s->tax_code, $s->address, label_of(Supplier::STATUSES, $s->status), $s->products_count,
        ]);

        return $exporter->download('nha_cung_cap', [__('Mã'), __('Tên nhà cung cấp'), __('Người liên hệ'), __('Điện thoại'), 'Email', __('Mã số thuế'), __('Địa chỉ'), __('Trạng thái'), __('Số sản phẩm')],
            $rows, $request->input('format', 'xlsx'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'tax_code' => ['nullable', 'string', 'max:50', 'regex:/^[0-9A-Za-z\-]+$/'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(array_keys(Supplier::STATUSES))],
        ], [], ['name' => __('Tên nhà cung cấp'), 'tax_code' => __('Mã số thuế')]);
    }
}
