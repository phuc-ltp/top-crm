<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bảng giá theo nhóm khách hàng. Quản lý: người được sửa sản phẩm (products.update).
 * Giá trong bảng giá được điền sẵn khi lập chứng từ cho khách hàng gắn bảng giá đó, và được máy chủ dùng
 * khi dòng chứng từ để trống đơn giá (SalesDocumentService::calculate).
 */
class PriceListController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('products.update'), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeManage($request);
        $lists = PriceList::withCount(['items', 'customers'])->orderBy('name')->get();

        return view('price-lists.index', compact('lists'));
    }

    public function create(Request $request)
    {
        $this->authorizeManage($request);

        return view('price-lists.form', ['list' => new PriceList(['is_active' => true]), 'rows' => [], 'products' => $this->productOptions()]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        [$data, $items] = $this->validated($request);
        $list = DB::transaction(function () use ($data, $items) {
            $list = PriceList::create($data);
            $this->syncItems($list, $items);

            return $list;
        });

        return redirect()->route('price-lists.edit', $list)->with('success', __('Đã tạo bảng giá :name.', ['name' => $list->name]));
    }

    public function edit(Request $request, PriceList $priceList)
    {
        $this->authorizeManage($request);
        $rows = $priceList->items()->get(['product_id', 'price'])->map(fn ($i) => ['product_id' => $i->product_id, 'price' => (float) $i->price])->all();

        return view('price-lists.form', ['list' => $priceList, 'rows' => $rows, 'products' => $this->productOptions(),
            'customers' => $priceList->customers()->orderBy('name')->limit(100)->get(['id', 'name', 'code'])]);
    }

    public function update(Request $request, PriceList $priceList)
    {
        $this->authorizeManage($request);
        [$data, $items] = $this->validated($request, $priceList);
        DB::transaction(function () use ($priceList, $data, $items) {
            $priceList->update($data);
            $this->syncItems($priceList, $items);
        });

        return redirect()->route('price-lists.edit', $priceList)->with('success', __('Đã lưu bảng giá.'));
    }

    public function destroy(Request $request, PriceList $priceList)
    {
        $this->authorizeManage($request);
        $customers = $priceList->customers()->count();
        $priceList->delete();   // khách hàng đang gắn → về giá chuẩn (khóa ngoại SET NULL)

        return redirect()->route('price-lists.index')->with('success', __('Đã xóa bảng giá :name (:count khách hàng chuyển về giá chuẩn).', ['name' => $priceList->name, 'count' => $customers]));
    }

    /** AJAX cho form chứng từ: giá theo bảng giá của khách hàng (chỉ khách hàng người dùng được xem). */
    public function forCustomer(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('quotations.create') || $user->can('orders.create') || $user->can('invoices.create'), 403);
        $data = $request->validate(['customer_id' => ['required', 'integer']]);
        $customer = Customer::visibleTo($user)->with('priceList')->findOrFail($data['customer_id']);
        $list = $customer->priceList?->is_active ? $customer->priceList : null;

        return response()->json(['price_list' => $list?->name, 'prices' => $list ? $list->priceMap() : (object) []]);
    }

    private function productOptions()
    {
        return Product::with('parent:id,name')->active()->orderBy('name')->get(['id', 'parent_id', 'sku', 'name', 'variant_name', 'price']);
    }

    private function validated(Request $request, ?PriceList $list = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', TenantRule::unique('price_lists', 'name')->ignore($list?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array', 'max:2000'],
            'items.*.product_id' => ['required', 'distinct', TenantRule::exists('products')->whereNull('deleted_at')],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ], ['items.*.product_id.distinct' => __('Một sản phẩm chỉ có một giá trong bảng giá.')], ['name' => __('Tên bảng giá')]);
        $items = $data['items'] ?? [];
        unset($data['items']);
        $data['is_active'] = $request->boolean('is_active');

        return [$data, $items];
    }

    private function syncItems(PriceList $list, array $items): void
    {
        $list->items()->delete();
        foreach ($items as $item) {
            $list->items()->create(['product_id' => $item['product_id'], 'price' => \App\Support\Decimal::round($item['price'])]);
        }
    }
}
