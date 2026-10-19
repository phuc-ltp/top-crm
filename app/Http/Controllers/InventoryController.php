<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Tồn kho và sổ kho (chỉ xem). Giá vốn & giá trị tồn kho chỉ hiện cho người có inventory.manage. */
class InventoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('inventory.view'), 403);
        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get();
        $data = $request->validate([
            'warehouse' => ['nullable', Rule::in($warehouses->pluck('id')->all())],
            'stock' => ['nullable', Rule::in(['out', 'low', 'in'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $levels = DB::table('stock_levels')->where('organization_id', app(\App\Support\Tenancy\CurrentOrganization::class)->id())
            ->when($data['warehouse'] ?? null, fn ($q, $w) => $q->where('warehouse_id', $w))
            ->groupBy('product_id')->selectRaw('product_id, SUM(quantity) as qty, SUM(reserved) as reserved');

        $query = Product::query()->where('products.type', 'product')->with('parent:id,name')
            ->leftJoinSub($levels, 'lv', 'lv.product_id', '=', 'products.id')
            ->select('products.*', DB::raw('COALESCE(lv.qty, 0) as stock_qty'), DB::raw('COALESCE(lv.reserved, 0) as stock_reserved'))
            ->when($data['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('products.name', 'like', $this->like($t))->orWhere('products.sku', 'like', $this->like($t))))
            ->when(($data['stock'] ?? null) === 'out', fn ($q) => $q->whereRaw('COALESCE(lv.qty, 0) - COALESCE(lv.reserved, 0) <= 0'))
            ->when(($data['stock'] ?? null) === 'low', fn ($q) => $q->whereNotNull('products.reorder_level')
                ->whereRaw('COALESCE(lv.qty, 0) - COALESCE(lv.reserved, 0) <= products.reorder_level'))
            ->when(($data['stock'] ?? null) === 'in', fn ($q) => $q->whereRaw('COALESCE(lv.qty, 0) - COALESCE(lv.reserved, 0) > 0'));

        $canCost = $request->user()->can('inventory.manage');
        $totalValue = $canCost ? (clone $query)->get()->sum(fn ($p) => (float) $p->stock_qty * (float) ($p->average_cost ?? 0)) : null;
        $products = $query->orderBy('products.name')->orderBy('products.variant_name')->paginate($this->perPage($request))->withQueryString();

        return view('inventory.index', compact('products', 'warehouses', 'canCost', 'totalValue'));
    }

    public function movements(Request $request)
    {
        abort_unless($request->user()->can('inventory.view'), 403);
        $warehouses = Warehouse::orderBy('name')->get();
        $data = $request->validate([
            'warehouse' => ['nullable', Rule::in($warehouses->pluck('id')->all())],
            'type' => ['nullable', Rule::in(array_keys(StockMovement::TYPES))],
            'product' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $movements = StockMovement::with(['product:id,sku,name,variant_name', 'warehouse:id,name', 'document:id,number,type', 'creator:id,name'])
            ->when($data['warehouse'] ?? null, fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($data['product'] ?? null, fn ($q, $p) => $q->where('product_id', $p))
            ->when($data['q'] ?? null, fn ($q, $t) => $q->whereHas('product', fn ($p) => $p->withTrashed()->where(fn ($w) => $w->where('name', 'like', $this->like($t))->orWhere('sku', 'like', $this->like($t)))))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($d)->startOfDay()))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($d)->endOfDay()))
            ->orderByDesc('id')->paginate($this->perPage($request))->withQueryString();

        return view('inventory.movements', ['movements' => $movements, 'warehouses' => $warehouses, 'canCost' => $request->user()->can('inventory.manage')]);
    }
}
