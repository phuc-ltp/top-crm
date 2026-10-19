<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockDocument;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Đơn hàng ↔ kho (chỉ đơn hàng có kho; đơn tạo trước khi có kho không phát sinh kho):
 *  - Đã duyệt / đang xử lý → GIỮ HÀNG theo dòng sản phẩm (tính lại khi sửa dòng / đổi kho);
 *    không đủ hàng có thể dùng → từ chối (trừ khi tổ chức cho phép tồn âm).
 *  - Hoàn thành → trả phần giữ và lập PHIẾU XUẤT KHO giao hàng (một lần).
 *  - Hủy / về chờ duyệt → trả phần giữ.
 *  - Trả hàng (sau khi giao) → PHIẾU NHẬP HÀNG TRẢ LẠI, không vượt số đã giao trừ số đã trả.
 * Gọi trong giao dịch của thao tác lưu đơn hàng — lỗi ở đây hủy toàn bộ thao tác.
 */
class OrderFulfillmentService
{
    private const HOLD = ['confirmed', 'processing'];

    public function __construct(private InventoryService $inventory) {}

    public function sync(SalesOrder $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $order = SalesOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->warehouse_id) {
                $this->releaseAll($order);

                return;
            }
            if (in_array($order->status, self::HOLD, true)) {
                $this->rebuild($order);
            } elseif ($order->status === 'completed') {
                $this->releaseAll($order);
                $this->deliver($order, $actor);
            } else {
                $this->releaseAll($order);
            }
        });
    }

    /** Số lượng cần cho đơn: [product_id => số lượng] (chỉ sản phẩm có tồn kho). */
    public function demand(SalesOrder $order): array
    {
        $order->loadMissing('items');
        $ids = $order->items->pluck('product_id')->filter()->unique();
        $stocked = Product::withTrashed()->whereIn('id', $ids)->where('type', 'product')->pluck('id')->flip();
        $need = [];
        foreach ($order->items as $item) {
            if ($item->product_id && isset($stocked[$item->product_id])) {
                $need[$item->product_id] = Decimal::add($need[$item->product_id] ?? 0, $item->quantity);
            }
        }
        ksort($need);

        return $need;
    }

    private function rebuild(SalesOrder $order): void
    {
        $this->releaseAll($order);
        foreach ($this->demand($order) as $productId => $qty) {
            $level = $this->lockLevel($order->warehouse_id, $productId);
            $available = Decimal::sub($level->quantity, $level->reserved);
            if (Decimal::cmp($available, $qty) < 0 && ! InventoryService::allowsNegative()) {
                $product = Product::withTrashed()->find($productId);
                throw ValidationException::withMessages(['status' => __('Không đủ hàng để giữ cho :product tại :warehouse: có thể dùng :available, cần :need.', [
                    'product' => $product?->display_name, 'warehouse' => $order->warehouse?->name,
                    'available' => InventoryService::qty($available), 'need' => InventoryService::qty($qty),
                ])]);
            }
            $level->reserved = Decimal::add($level->reserved, $qty);
            $level->save();
            StockReservation::create(['sales_order_id' => $order->id, 'warehouse_id' => $order->warehouse_id, 'product_id' => $productId, 'quantity' => $qty]);
        }
    }

    public function releaseAll(SalesOrder $order): void
    {
        foreach (StockReservation::where('sales_order_id', $order->id)->orderBy('warehouse_id')->orderBy('product_id')->get() as $r) {
            $level = $this->lockLevel($r->warehouse_id, $r->product_id);
            $level->reserved = Decimal::clamp(Decimal::sub($level->reserved, $r->quantity), 0);
            $level->save();
            $r->delete();
        }
    }

    private function deliver(SalesOrder $order, User $actor): void
    {
        if (StockDocument::where('sales_order_id', $order->id)->where('type', 'issue')->exists()) {
            return;   // đã giao
        }
        $lines = collect($this->demand($order))->map(fn ($qty, $pid) => ['product_id' => $pid, 'quantity' => $qty])->values()->all();
        if ($lines === []) {
            return;   // đơn chỉ có dịch vụ / dòng tự nhập
        }
        $this->inventory->post(['type' => 'issue', 'warehouse_id' => $order->warehouse_id, 'sales_order_id' => $order->id,
            'document_date' => today()->toDateString(), 'reference' => $order->number, 'notes' => __('Giao hàng cho đơn :number', ['number' => $order->number])], $lines, $actor);
    }

    /** Đã giao / đã trả theo sản phẩm: ['delivered' => [pid => qty], 'returned' => [pid => qty]]. */
    public function quantities(SalesOrder $order): array
    {
        $sum = fn (string $type) => \App\Models\StockDocumentItem::query()
            ->whereIn('stock_document_id', StockDocument::where('sales_order_id', $order->id)->where('type', $type)->select('id'))
            ->selectRaw('product_id, SUM(quantity) as q')->groupBy('product_id')->pluck('q', 'product_id')->map(fn ($q) => (string) $q)->all();

        return ['delivered' => $sum('issue'), 'returned' => $sum('return')];
    }

    /** Trả hàng: [['product_id' => .., 'quantity' => ..]] — không vượt số đã giao − đã trả. */
    public function processReturn(SalesOrder $order, array $lines, ?int $warehouseId, string $reason, User $actor): StockDocument
    {
        return DB::transaction(function () use ($order, $lines, $warehouseId, $reason, $actor) {
            SalesOrder::whereKey($order->id)->lockForUpdate()->first();
            ['delivered' => $delivered, 'returned' => $returned] = $this->quantities($order);
            $clean = [];
            foreach ($lines as $line) {
                $qty = Decimal::of($line['quantity'] ?? 0);
                if (Decimal::cmp($qty, 0) <= 0) {
                    continue;
                }
                $left = Decimal::sub($delivered[$line['product_id']] ?? 0, $returned[$line['product_id']] ?? 0);
                if (Decimal::cmp($qty, $left) > 0) {
                    throw ValidationException::withMessages(['items' => __('Số lượng trả vượt số đã giao chưa trả (còn :left).', ['left' => InventoryService::qty($left)])]);
                }
                $clean[] = ['product_id' => (int) $line['product_id'], 'quantity' => $qty];
            }
            if ($clean === []) {
                throw ValidationException::withMessages(['items' => __('Nhập số lượng trả cho ít nhất một sản phẩm.')]);
            }

            return $this->inventory->post(['type' => 'return', 'warehouse_id' => $warehouseId ?: $order->warehouse_id, 'sales_order_id' => $order->id,
                'document_date' => today()->toDateString(), 'reference' => $order->number, 'notes' => $reason], $clean, $actor);
        });
    }

    private function lockLevel(int $warehouseId, int $productId): StockLevel
    {
        DB::table('stock_levels')->insertOrIgnore([
            'organization_id' => app(CurrentOrganization::class)->id(), 'warehouse_id' => $warehouseId, 'product_id' => $productId,
            'quantity' => 0, 'reserved' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return StockLevel::where('warehouse_id', $warehouseId)->where('product_id', $productId)->lockForUpdate()->firstOrFail();
    }
}
