<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockDocument;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phiếu kho: lập (ghi sổ ngay) và xem. Không sửa / xóa — phiếu là chứng từ bất biến.
 * Quyền: xem = inventory.view; nhập / xuất / chuyển = inventory.create; điều chỉnh kiểm kê = inventory.adjust.
 */
class StockDocumentController extends Controller
{
    private function canCreate(Request $request, string $type): bool
    {
        return $request->user()->can($type === 'adjustment' ? 'inventory.adjust' : 'inventory.create');
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('inventory.view'), 403);
        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(StockDocument::TYPES))],
            'warehouse' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $documents = StockDocument::with(['warehouse:id,name', 'toWarehouse:id,name', 'supplier:id,name', 'creator:id,name'])->withCount('items')
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($data['warehouse'] ?? null, fn ($q, $w) => $q->where(fn ($x) => $x->where('warehouse_id', $w)->orWhere('to_warehouse_id', $w)))
            ->when($data['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', $this->like($t))->orWhere('reference', 'like', $this->like($t))))
            ->orderByDesc('document_date')->orderByDesc('id')->paginate($this->perPage($request))->withQueryString();

        return view('stock-documents.index', ['documents' => $documents, 'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id')]);
    }

    public function create(Request $request)
    {
        $type = $request->input('type', 'receipt');
        abort_unless(in_array($type, StockDocument::manualTypes(), true), 404);
        abort_unless($this->canCreate($request, $type), 403);
        $warehouses = Warehouse::active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']);

        return view('stock-documents.form', [
            'type' => $type,
            'warehouses' => $warehouses,
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'products' => Product::active()->where('type', 'product')->orderBy('name')->orderBy('variant_name')->get(['id', 'sku', 'name', 'variant_name', 'unit', 'average_cost', 'purchase_price']),
            // Tồn hiện tại theo kho để hiện ngay trên dòng phiếu (đặc biệt khi kiểm kê)
            'levels' => StockLevel::whereIn('warehouse_id', $warehouses->pluck('id'))->get(['warehouse_id', 'product_id', 'quantity', 'reserved'])
                ->groupBy('warehouse_id')->map(fn ($rows) => $rows->mapWithKeys(fn ($l) => [$l->product_id => ['q' => (float) $l->quantity, 'r' => (float) $l->reserved]])),
            'canCost' => $request->user()->can('inventory.manage'),
        ]);
    }

    public function store(Request $request, InventoryService $inventory)
    {
        $type = (string) $request->input('type');
        abort_unless(in_array($type, StockDocument::manualTypes(), true), 422);
        abort_unless($this->canCreate($request, $type), 403);

        $activeWarehouse = TenantRule::exists('warehouses')->where('is_active', true);
        $data = $request->validate([
            'type' => ['required', Rule::in(StockDocument::manualTypes())],
            'warehouse_id' => ['required', $activeWarehouse],
            'to_warehouse_id' => [Rule::requiredIf($type === 'transfer'), 'nullable', 'different:warehouse_id', $activeWarehouse],
            'supplier_id' => ['nullable', Rule::prohibitedIf($type !== 'receipt'), TenantRule::exists('suppliers')->whereNull('deleted_at')],
            'document_date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'distinct', TenantRule::exists('products')->whereNull('deleted_at')->where('type', 'product')],
            'items.*.quantity' => [Rule::requiredIf($type !== 'adjustment'), 'nullable', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.counted_quantity' => [Rule::requiredIf($type === 'adjustment'), 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], ['items.*.product_id.distinct' => __('Mỗi sản phẩm chỉ xuất hiện một lần trong phiếu.')], [
            'warehouse_id' => __('Kho'), 'to_warehouse_id' => __('Kho nhận'), 'document_date' => __('Ngày chứng từ'),
        ]);

        $lines = collect($data['items'])->map(fn ($i) => array_filter([
            'product_id' => (int) $i['product_id'],
            'quantity' => $i['quantity'] ?? null,
            'counted_quantity' => $i['counted_quantity'] ?? null,
            // Đơn giá nhập chỉ có ý nghĩa với phiếu nhập; người không xem được giá vốn để trống → dùng giá vốn hiện tại
            'unit_cost' => $type === 'receipt' && $request->user()->can('inventory.manage') ? ($i['unit_cost'] ?? null) : null,
        ], fn ($v) => $v !== null && $v !== ''))->all();
        unset($data['items']);

        $document = $inventory->post($data, $lines, $request->user());

        return redirect()->route('stock-documents.show', $document)->with('success', __('Đã ghi sổ :doc :number.', [
            'doc' => mb_strtolower(__(StockDocument::TYPES[$type][0])), 'number' => $document->number,
        ]));
    }

    public function show(Request $request, StockDocument $stockDocument)
    {
        abort_unless($request->user()->can('inventory.view'), 403);
        $stockDocument->load(['items.product', 'warehouse', 'toWarehouse', 'supplier', 'creator', 'salesOrder']);

        return view('stock-documents.show', ['doc' => $stockDocument, 'canCost' => $request->user()->can('inventory.manage')]);
    }
}
