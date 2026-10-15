<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
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
    public function calculate(array $items, float $discountAmount = 0, float $taxRate = 0): array
    {
        $productIds = collect($items)->pluck('product_id')->filter()->unique();
        $products = $productIds->isEmpty() ? collect() : Product::withTrashed()->whereIn('id', $productIds)->get()->keyBy('id');

        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $product = isset($item['product_id']) && $item['product_id'] ? $products->get($item['product_id']) : null;
            $quantity = max(0, round((float) ($item['quantity'] ?? 0), 2));
            if ($quantity <= 0) {
                continue;
            }
            $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== '' && $item['unit_price'] !== null
                ? max(0, round((float) $item['unit_price'], 2))
                : (float) ($product?->price ?? 0);
            $discount = min(100, max(0, round((float) ($item['discount_percent'] ?? 0), 2)));
            $lineTotal = round($quantity * $unitPrice * (1 - $discount / 100), 2);

            $lines[] = [
                'product_id' => $product?->id,
                'description' => trim((string) ($item['description'] ?? '')) ?: ($product?->name ?? __('Sản phẩm/dịch vụ')),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discount,
                'line_total' => $lineTotal,
            ];
            $subtotal += $lineTotal;
        }

        $subtotal = round($subtotal, 2);
        $discountAmount = min($subtotal, max(0, round($discountAmount, 2)));
        $taxRate = min(100, max(0, round($taxRate, 2)));
        $taxAmount = round(($subtotal - $discountAmount) * $taxRate / 100, 2);

        return [
            'items' => $lines,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => round($subtotal - $discountAmount + $taxAmount, 2),
        ];
    }

    /** Lưu chứng từ + chi tiết trong một transaction. */
    public function save(Model $document, array $attributes, array $items): Model
    {
        $calc = $this->calculate($items, (float) ($attributes['discount_amount'] ?? 0), (float) ($attributes['tax_rate'] ?? 0));

        if (empty($calc['items'])) {
            throw ValidationException::withMessages(['items' => __('Chứng từ phải có ít nhất một dòng sản phẩm hợp lệ.')]);
        }

        return DB::transaction(function () use ($document, $attributes, $calc) {
            $document->fill(array_merge($attributes, collect($calc)->except('items')->all()));
            $document->save();

            $document->items()->delete();
            $document->items()->createMany($calc['items']);

            if ($document instanceof Invoice) {
                app(PaymentService::class)->refreshInvoice($document);
            }

            return $document->load('items');
        });
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
