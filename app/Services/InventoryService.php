<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;
use App\Models\StockDocument;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\Decimal;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mọi thay đổi tồn kho đi qua đây:
 *  - trong MỘT giao dịch; dòng tồn kho được khóa (SELECT ... FOR UPDATE) theo thứ tự cố định (kho, sản phẩm)
 *    để hai thao tác đồng thời không làm sai số tồn và không khóa chéo nhau;
 *  - mỗi thay đổi ghi một dòng sổ kho bất biến kèm số dư sau phát sinh;
 *  - không cho tồn kho (có thể dùng) âm nếu tổ chức không cho phép (Cài đặt › inventory_allow_negative);
 *  - giá vốn bình quân gia quyền (toàn tổ chức, mọi kho) cập nhật mỗi lần nhập kho:
 *      giá vốn mới = (tồn hiện có × giá vốn cũ + SL nhập × đơn giá nhập) ÷ (tồn hiện có + SL nhập).
 */
class InventoryService
{
    /** Đơn hàng mới tự gắn kho mặc định (giữ hàng khi duyệt, xuất kho khi hoàn thành). Mặc định tắt. */
    public static function tracksOrders(): bool
    {
        return Setting::get('inventory_track_orders', '0') === '1';
    }

    public static function allowsNegative(): bool
    {
        return Setting::get('inventory_allow_negative', '0') === '1';
    }

    /**
     * Lập và ghi sổ một phiếu kho.
     *
     * @param  array{type: string, warehouse_id: int, to_warehouse_id?: int|null, supplier_id?: int|null, document_date: string, reference?: string|null, notes?: string|null}  $header
     * @param  array<int, array{product_id: int, quantity?: mixed, counted_quantity?: mixed, unit_cost?: mixed}>  $lines
     */
    public function post(array $header, array $lines, User $actor): StockDocument
    {
        $type = $header['type'];
        $lowStock = [];

        $document = DB::transaction(function () use ($header, $lines, $actor, $type, &$lowStock) {
            $document = StockDocument::create($header + ['created_by' => $actor->id]);
            $products = Product::whereIn('id', collect($lines)->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');

            // Khóa theo thứ tự sản phẩm tăng dần để tránh khóa chéo giữa hai phiếu đồng thời
            usort($lines, fn ($a, $b) => $a['product_id'] <=> $b['product_id']);
            foreach ($lines as $line) {
                $product = $products[$line['product_id']] ?? null;
                if (! $product || $product->type !== 'product') {
                    throw ValidationException::withMessages(['items' => __('Chỉ sản phẩm (không phải dịch vụ) mới có tồn kho.')]);
                }
                match ($type) {
                    'receipt' => $this->receive($document, $product, $line, $actor),
                    // Hàng trả lại nhập theo giá vốn bình quân hiện tại (không làm đổi giá vốn)
                    'return' => $this->receive($document, $product, ['unit_cost' => $product->average_cost ?? $product->purchase_price ?? 0] + $line, $actor, 'return'),
                    'issue' => $this->move($document, $product, $document->warehouse_id, Decimal::mul($line['quantity'], -1), 'issue', $actor, $line),
                    'adjustment' => $this->adjust($document, $product, $line, $actor),
                    'transfer' => $this->transfer($document, $product, $line, $actor),
                };
                $lowStock[$product->id] = $product;
            }

            return $document;
        });

        // Sau khi ghi sổ thành công: cảnh báo tồn thấp (ngoài giao dịch, lỗi gửi không ảnh hưởng phiếu)
        foreach ($lowStock as $product) {
            $this->alertIfLow($product->fresh());
        }

        return $document->load('items.product', 'warehouse', 'toWarehouse', 'supplier');
    }

    private function receive(StockDocument $doc, Product $product, array $line, User $actor, string $movementType = 'receipt'): void
    {
        $qty = Decimal::of($line['quantity']);
        $cost = Decimal::round($line['unit_cost'] ?? ($product->average_cost ?? $product->purchase_price ?? 0));

        // Tồn toàn tổ chức TRƯỚC khi nhập (đã khóa các dòng tồn của sản phẩm này)
        $onHand = Decimal::sum(StockLevel::where('product_id', $product->id)->lockForUpdate()->pluck('quantity'));
        $base = Decimal::cmp($onHand, 0) > 0 ? $onHand : '0';
        $oldCost = $product->average_cost ?? $cost;
        $newTotal = Decimal::add($base, $qty);
        $product->average_cost = Decimal::cmp($newTotal, 0) > 0
            ? Decimal::round(Decimal::div(Decimal::add(Decimal::mul($base, $oldCost), Decimal::mul($qty, $cost)), $newTotal))
            : $cost;
        $product->saveQuietly();

        $this->move($doc, $product, $doc->warehouse_id, $qty, $movementType, $actor, ['unit_cost' => $cost] + $line);
    }

    private function adjust(StockDocument $doc, Product $product, array $line, User $actor): void
    {
        $level = $this->lockLevel($doc->warehouse_id, $product->id);
        $diff = Decimal::sub($line['counted_quantity'], $level->quantity);
        if (Decimal::cmp($diff, 0) === 0) {
            $doc->items()->create(['product_id' => $product->id, 'quantity' => 0, 'counted_quantity' => $line['counted_quantity'], 'unit_cost' => $product->average_cost]);

            return;
        }
        $this->move($doc, $product, $doc->warehouse_id, $diff, Decimal::cmp($diff, 0) > 0 ? 'adjustment_in' : 'adjustment_out', $actor,
            $line + ['unit_cost' => $product->average_cost], allowNegative: true);
    }

    private function transfer(StockDocument $doc, Product $product, array $line, User $actor): void
    {
        // Khóa cả hai kho theo thứ tự id kho để hai phiếu chuyển ngược chiều không khóa chéo
        foreach (collect([$doc->warehouse_id, $doc->to_warehouse_id])->sort() as $warehouseId) {
            $this->lockLevel($warehouseId, $product->id);
        }
        $qty = Decimal::of($line['quantity']);
        $this->move($doc, $product, $doc->warehouse_id, Decimal::mul($qty, -1), 'transfer_out', $actor, $line + ['unit_cost' => $product->average_cost]);
        $this->move($doc, $product, $doc->to_warehouse_id, $qty, 'transfer_in', $actor, $line + ['unit_cost' => $product->average_cost], recordItem: false);
    }

    /** Ghi một phát sinh: cập nhật tồn (đã khóa), kiểm tra tồn âm, ghi sổ kho và dòng phiếu. */
    private function move(StockDocument $doc, Product $product, int $warehouseId, string $qty, string $movementType, User $actor, array $line,
        bool $allowNegative = false, bool $recordItem = true): void
    {
        $level = $this->lockLevel($warehouseId, $product->id);
        $newQty = Decimal::add($level->quantity, $qty);
        $available = Decimal::sub($newQty, $level->reserved);
        if (Decimal::cmp($qty, 0) < 0 && Decimal::cmp($available, 0) < 0 && ! $allowNegative && ! self::allowsNegative()) {
            throw ValidationException::withMessages(['items' => __('Không đủ tồn kho cho :product tại :warehouse: có thể dùng :available, cần :need.', [
                'product' => $product->display_name, 'warehouse' => $doc->warehouse_id === $warehouseId ? $doc->warehouse?->name : '',
                'available' => self::qty(Decimal::sub($level->quantity, $level->reserved)), 'need' => self::qty(Decimal::mul($qty, -1)),
            ])]);
        }
        $level->quantity = $newQty;
        $level->save();

        StockMovement::create([
            'product_id' => $product->id, 'warehouse_id' => $warehouseId, 'stock_document_id' => $doc->id, 'type' => $movementType,
            'quantity' => $qty, 'unit_cost' => $line['unit_cost'] ?? null, 'balance_after' => $newQty, 'created_by' => $actor->id,
        ]);
        if ($recordItem) {
            $doc->items()->create(['product_id' => $product->id, 'quantity' => Decimal::cmp($qty, 0) < 0 && $movementType !== 'adjustment_out' ? Decimal::mul($qty, -1) : $qty,
                'counted_quantity' => $line['counted_quantity'] ?? null, 'unit_cost' => $line['unit_cost'] ?? null]);
        }
    }

    /** Lấy (tạo nếu chưa có) và KHÓA dòng tồn kho của sản phẩm tại kho. */
    private function lockLevel(int $warehouseId, int $productId): StockLevel
    {
        DB::table('stock_levels')->insertOrIgnore([
            'organization_id' => app(CurrentOrganization::class)->id(), 'warehouse_id' => $warehouseId, 'product_id' => $productId,
            'quantity' => 0, 'reserved' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return StockLevel::where('warehouse_id', $warehouseId)->where('product_id', $productId)->lockForUpdate()->firstOrFail();
    }

    // ---------------------------------------------------------------- tra cứu

    /** Tồn của sản phẩm: ['quantity' => tồn thực, 'reserved' => đang giữ, 'available' => có thể dùng] (mọi kho hoặc một kho). */
    public static function stockOf(Product $product, ?int $warehouseId = null): array
    {
        $row = StockLevel::where('product_id', $product->id)->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('COALESCE(SUM(quantity),0) as q, COALESCE(SUM(reserved),0) as r')->first();

        return ['quantity' => (string) $row->q, 'reserved' => (string) $row->r, 'available' => Decimal::sub($row->q, $row->r)];
    }

    /** Định dạng số lượng: bỏ phần thập phân thừa (12.500 → 12,5). */
    public static function qty(string|float|int|null $value): string
    {
        $v = rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');

        return $v === '-0' ? '0' : $v;
    }

    /** Cảnh báo người quản lý kho khi tồn có thể dùng ≤ mức tối thiểu (tối đa 1 lần / sản phẩm / ngày). */
    private function alertIfLow(Product $product): void
    {
        if ($product->reorder_level === null || Setting::get('notify_low_stock', '1') !== '1') {
            return;
        }
        $available = self::stockOf($product)['available'];
        if (Decimal::cmp($available, $product->reorder_level) > 0) {
            return;
        }
        $orgId = app(CurrentOrganization::class)->id();
        if (! cache()->add("crm.low_stock.$orgId.{$product->id}.".today()->toDateString(), 1, now()->endOfDay())) {
            return;
        }
        $managers = User::inOrganization($orgId)->get()->filter(fn (User $u) => $u->can('inventory.manage'));
        foreach ($managers as $u) {
            CrmNotification::send($u, 'low_stock', __('Tồn kho thấp: :product còn :qty (mức tối thiểu :min).', [
                'product' => $product->display_name, 'qty' => self::qty($available), 'min' => self::qty($product->reorder_level),
            ]), route('products.show', $product), 'bi-box-seam');
        }
    }
}
