<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tính tiền và lưu chứng từ bán hàng (báo giá, đơn hàng, hóa đơn).
 * Mọi số tiền được tính lại ở backend — không tin giá trị tổng do trình duyệt gửi lên.
 */
class SalesDocumentService
{
    /**
     * @param  array<int, array{product_id?:int|null, description?:string, quantity:float|string, unit_price?:float|string|null, discount_percent?:float|string|null}>  $items
     */
    public function calculate(array $items, float $discountAmount = 0, float $taxRate = 0, ?int $customerId = null): array
    {
        $productIds = collect($items)->pluck('product_id')->filter()->unique();
        $products = $productIds->isEmpty() ? collect() : Product::withTrashed()->whereIn('id', $productIds)->get()->keyBy('id');
        // Bảng giá của khách hàng (đang hoạt động): dùng khi dòng để trống đơn giá
        $listPrices = $customerId ? (\App\Models\Customer::find($customerId)?->priceList?->priceMap() ?? []) : [];

        // Tính bằng số thập phân chính xác (bcmath). Số lượng / đơn giá / % giữ 2 chữ số; TIỀN (thành tiền, chiết khấu,
        // thuế, tổng) làm tròn theo đơn vị nhỏ nhất của tiền tệ — VND: tới đồng (không có xu, tránh "còn nợ 0,40 ₫").
        $m = PaymentService::scale();
        $lines = [];
        $subtotal = '0';

        foreach ($items as $item) {
            $product = isset($item['product_id']) && $item['product_id'] ? $products->get($item['product_id']) : null;
            $quantity = Decimal::round(Decimal::clamp(self::number($item['quantity'] ?? 0), 0));
            if (Decimal::cmp($quantity, 0) <= 0) {
                continue;
            }
            $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== '' && $item['unit_price'] !== null
                ? Decimal::round(Decimal::clamp(self::number($item['unit_price']), 0))
                : Decimal::round($product ? ($listPrices[$product->id] ?? $product->price) : 0);
            $discount = Decimal::round(Decimal::clamp(self::number($item['discount_percent'] ?? 0), 0, 100));
            $lineTotal = Decimal::round(Decimal::div(Decimal::mul(Decimal::mul($quantity, $unitPrice), Decimal::sub(100, $discount)), 100), $m);

            $lines[] = [
                'product_id' => $product?->id,
                'description' => trim((string) ($item['description'] ?? '')) ?: ($product?->name ?? __('Sản phẩm/dịch vụ')),
                'quantity' => (float) $quantity,
                'unit_price' => (float) $unitPrice,
                'discount_percent' => (float) $discount,
                'line_total' => (float) $lineTotal,
            ];
            $subtotal = Decimal::add($subtotal, $lineTotal);
        }

        $subtotal = Decimal::round($subtotal, $m);
        $discountAmount = Decimal::round(Decimal::clamp(self::number($discountAmount), 0, $subtotal), $m);
        $taxRate = Decimal::round(Decimal::clamp(self::number($taxRate), 0, 100));
        $taxAmount = Decimal::round(Decimal::div(Decimal::mul(Decimal::sub($subtotal, $discountAmount), $taxRate), 100), $m);

        return [
            'items' => $lines,
            'subtotal' => (float) $subtotal,
            'discount_amount' => (float) $discountAmount,
            'tax_rate' => (float) $taxRate,
            'tax_amount' => (float) $taxAmount,
            'total' => (float) Decimal::round(Decimal::add(Decimal::sub($subtotal, $discountAmount), $taxAmount), $m),
        ];
    }

    /** Giá trị số từ form (chuỗi không phải số → 0, giống cách ép kiểu cũ). */
    private static function number(mixed $value): string|float|int
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = trim((string) $value);

        return preg_match('/^-?\d+(\.\d+)?$/', $value) ? $value : (is_numeric($value) ? (float) $value : 0);
    }

    /** Lưu chứng từ + chi tiết trong một transaction. */
    public function save(Model $document, array $attributes, array $items): Model
    {
        $calc = $this->calculate($items, (float) ($attributes['discount_amount'] ?? 0), (float) ($attributes['tax_rate'] ?? 0), isset($attributes['customer_id']) ? (int) $attributes['customer_id'] : null);

        if (empty($calc['items'])) {
            throw ValidationException::withMessages(['items' => __('Chứng từ phải có ít nhất một dòng sản phẩm hợp lệ.')]);
        }

        return DB::transaction(function () use ($document, $attributes, $calc) {
            // Lịch sử sửa dòng chứng từ (đơn hàng, báo giá, hóa đơn): ghi nhật ký khi dòng thay đổi
            $before = $document->exists ? $this->itemSummary($document->items()->get()->toArray()) : null;
            $document->fill(array_merge($attributes, collect($calc)->except('items')->all()));
            $document->save();

            $document->items()->delete();
            $document->items()->createMany($calc['items']);
            $after = $this->itemSummary($calc['items']);
            if ($before !== null && $before !== $after) {
                ActivityLogger::log('updated', $document, 'Sửa dòng '.$document->activityLabel(), ['old' => ['items' => implode('; ', $before)], 'new' => ['items' => implode('; ', $after)]]);
            }

            // Đơn hàng: giữ / giao / trả phần giữ theo trạng thái (lỗi không đủ hàng → hủy cả thao tác lưu)
            if ($document instanceof SalesOrder) {
                $actor = auth()->user() ?? User::find($document->created_by);
                if ($actor) {
                    app(OrderFulfillmentService::class)->sync($document->fresh(), $actor);
                }
            }

            if ($document instanceof Invoice) {
                app(PaymentService::class)->refreshInvoice($document);
            }

            return $document->load('items');
        });
    }

    /** Tóm tắt dòng để so sánh / ghi nhật ký: "SL × mô tả @ đơn giá (-x%)". */
    private function itemSummary(array $items): array
    {
        return array_map(fn ($i) => rtrim(rtrim(number_format((float) $i['quantity'], 2, '.', ''), '0'), '.').' × '.$i['description']
            .' @ '.number_format((float) $i['unit_price'], 0, ',', '.').((float) $i['discount_percent'] > 0 ? ' (-'.(float) $i['discount_percent'].'%)' : ''), $items);
    }

    public function convertQuotationToOrder(Quotation $quotation, User $user): SalesOrder
    {
        if (in_array($quotation->status, ['rejected', 'expired'], true)) {
            throw ValidationException::withMessages(['status' => __('Không thể tạo đơn hàng từ báo giá bị từ chối hoặc hết hạn.')]);
        }

        return DB::transaction(function () use ($quotation, $user) {
            $order = $this->save(new SalesOrder, [
                'customer_id' => $quotation->customer_id,
                'quotation_id' => $quotation->id,
                'warehouse_id' => InventoryService::tracksOrders() ? \App\Models\Warehouse::default()?->id : null,
                'order_date' => today(),
                'status' => 'pending',
                'discount_amount' => $quotation->discount_amount,
                'tax_rate' => $quotation->tax_rate,
                'notes' => $quotation->notes,
                'created_by' => $user->id,
                'assigned_to' => $quotation->assigned_to ?? $user->id,
                'is_demo' => $quotation->is_demo,
            ], $this->itemsOf($quotation));

            $quotation->update(['status' => 'accepted']);

            return $order;
        });
    }

    public function createInvoiceFromOrder(SalesOrder $order, User $user): Invoice
    {
        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => __('Đơn hàng đã hủy, không thể lập hóa đơn.')]);
        }
        if ($order->invoices()->where('status', '!=', 'cancelled')->exists()) {
            throw ValidationException::withMessages(['status' => __('Đơn hàng này đã có hóa đơn.')]);
        }

        return $this->save(new Invoice, [
            'customer_id' => $order->customer_id,
            'sales_order_id' => $order->id,
            'issue_date' => today(),
            'due_date' => today()->addDays(15),
            'status' => 'draft',
            'discount_amount' => $order->discount_amount,
            'tax_rate' => $order->tax_rate,
            'notes' => $order->notes,
            'created_by' => $user->id,
            'assigned_to' => $order->assigned_to ?? $user->id,
            'is_demo' => $order->is_demo,
        ], $this->itemsOf($order));
    }

    private function itemsOf(Model $document): array
    {
        return $document->items->map(fn ($i) => $i->only(['product_id', 'description', 'quantity', 'unit_price', 'discount_percent']))->all();
    }
}
