<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Danh mục kho (inventory.manage). Kho đã có phát sinh không xóa được — chuyển sang "ngừng sử dụng". */
class WarehouseController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('inventory.manage'), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeManage($request);
        $warehouses = Warehouse::withCount(['levels as products_in_stock' => fn ($q) => $q->where('quantity', '!=', 0)])
            ->orderByDesc('is_default')->orderBy('name')->get();

        return view('warehouses.index', compact('warehouses'));
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            if ($data['is_default']) {
                Warehouse::where('is_default', true)->update(['is_default' => false]);
            }
            Warehouse::create($data);
        });

        return back()->with('success', __('Đã thêm kho :name.', ['name' => $data['name']]));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $this->authorizeManage($request);
        $data = $this->validated($request, $warehouse);
        if ($warehouse->is_default && (! $data['is_default'] || ! $data['is_active'])) {
            return back()->withErrors(['is_default' => __('Hãy chọn kho mặc định khác trước khi bỏ mặc định hoặc ngừng kho này.')]);
        }
        DB::transaction(function () use ($warehouse, $data) {
            if ($data['is_default']) {
                Warehouse::where('is_default', true)->whereKeyNot($warehouse->id)->update(['is_default' => false]);
            }
            $warehouse->update($data);
        });

        return back()->with('success', __('Đã cập nhật kho.'));
    }

    public function destroy(Request $request, Warehouse $warehouse)
    {
        $this->authorizeManage($request);
        if ($warehouse->is_default || StockMovement::where('warehouse_id', $warehouse->id)->exists()) {
            return back()->withErrors(['warehouse' => __('Kho mặc định hoặc kho đã có phát sinh không xóa được — hãy chuyển sang "Ngừng sử dụng".')]);
        }
        $warehouse->levels()->delete();
        $warehouse->delete();

        return back()->with('success', __('Đã xóa kho :name.', ['name' => $warehouse->name]));
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-_.]+$/', TenantRule::unique('warehouses', 'code')->ignore($warehouse?->id)],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
        ], [], ['code' => __('Mã kho'), 'name' => __('Tên kho')]);
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $data['is_default'] || $request->boolean('is_active', true);

        return $data;
    }
}
