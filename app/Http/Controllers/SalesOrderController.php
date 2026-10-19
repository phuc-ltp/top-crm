<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Notifications\CrmNotification;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesOrderController extends SalesDocumentController
{
    protected string $route = 'orders';

    protected string $label = 'Đơn hàng';

    protected string $dateField = 'order_date';

    protected function modelClass(): string
    {
        return SalesOrder::class;
    }

    protected function extraRules(): array
    {
        // Trạng thái "đã duyệt" chỉ đạt được qua nút Duyệt (cần quyền orders.approve)
        return [
            'status' => ['required', Rule::in(['pending', 'processing', 'completed', 'cancelled', 'confirmed'])],
            'warehouse_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('warehouses')->where('is_active', true)],
        ];
    }

    protected function defaults(Request $request): array
    {
        return ['status' => 'pending', 'warehouse_id' => \App\Services\InventoryService::tracksOrders() ? \App\Models\Warehouse::default()?->id : null];
    }

    /** Đơn đã có hóa đơn hoặc đã hủy/hoàn thành thì khóa chỉnh sửa. */
    protected function editable(Model $doc): bool
    {
        return ! in_array($doc->status, ['completed', 'cancelled'], true)
            && $doc->invoices()->where('status', '!=', 'cancelled')->doesntExist();
    }

    protected function showRelations(): array
    {
        return array_merge(parent::showRelations(), ['quotation', 'invoices', 'approver', 'warehouse', 'reservations.product', 'stockDocuments']);
    }

    protected function validated(Request $request, ?Model $existing = null): array
    {
        [$data, $items] = parent::validated($request, $existing);

        // Đơn chưa duyệt chỉ được chuyển sang đã duyệt / đang xử lý / hoàn thành bởi người có quyền duyệt
        // (khi đó coi như người đó duyệt). Trước đây "đang xử lý / hoàn thành" bỏ qua được bước duyệt.
        $wasApproved = $existing && $existing->approved_at;
        if (in_array($data['status'], ['confirmed', 'processing', 'completed'], true) && ! $wasApproved) {
            if (! $request->user()->can('orders.approve')) {
                if ($data['status'] !== 'confirmed') {
                    throw \Illuminate\Validation\ValidationException::withMessages(['status' => __('Đơn hàng cần được duyệt trước khi chuyển sang trạng thái này.')]);
                }
                $data['status'] = 'pending';
            } else {
                $data['approved_by'] = $request->user()->id;
                $data['approved_at'] = now();
            }
        }
        // Đơn tạo trước khi có kho giữ nguyên "không theo dõi kho"
        if ($existing && ! $existing->warehouse_id) {
            unset($data['warehouse_id']);
        }

        return [$data, $items];
    }

    public function approve(Request $request, SalesOrder $order)
    {
        $this->authorize('approve', SalesOrder::class);
        $this->authorize('view', $order);
        abort_unless($order->status === 'pending', 422, __('Chỉ duyệt được đơn hàng đang chờ duyệt.'));

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $request) {
            $order->update(['status' => 'confirmed', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            app(\App\Services\OrderFulfillmentService::class)->sync($order, $request->user());   // giữ hàng; thiếu hàng → không duyệt
        });
        ActivityLogger::log('approved', $order, 'Duyệt đơn hàng '.$order->number);
        if ($order->assignee && $order->assignee->id !== $request->user()->id) {
            CrmNotification::send($order->assignee, 'order_approved', 'Đơn hàng '.$order->number.' đã được duyệt', route('orders.show', $order), 'bi-check2-circle');
        }

        return back()->with('success', __('Đã duyệt đơn hàng :number.', ['number' => $order->number]));
    }

    protected function formData(Request $request): array
    {
        return parent::formData($request) + ['warehouses' => \App\Models\Warehouse::active()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all(),
            'tracksOrders' => \App\Services\InventoryService::tracksOrders()];
    }

    /** Đơn đang giữ hàng phải hủy (trả phần giữ) trước khi xóa. */
    public function destroy($id)
    {
        $order = $this->find($id);
        if (\App\Models\StockReservation::where('sales_order_id', $order->id)->exists()) {
            return back()->withErrors(['status' => __('Đơn hàng đang giữ hàng trong kho — hãy chuyển sang "Đã hủy" trước khi xóa.')]);
        }

        return parent::destroy($id);
    }

    /** Trả hàng sau khi giao: nhập lại kho bằng phiếu nhập hàng trả lại. */
    public function returnGoods(Request $request, SalesOrder $order, \App\Services\OrderFulfillmentService $fulfillment)
    {
        $this->authorize('view', $order);
        abort_unless($request->user()->can('inventory.create'), 403);
        $data = $request->validate([
            'warehouse_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('warehouses')->where('is_active', true)],
            'reason' => ['required', 'string', 'max:500'],
            'items' => ['required', 'array', 'max:200'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], [], ['reason' => __('Lý do trả hàng')]);
        $doc = $fulfillment->processReturn($order, $data['items'], $data['warehouse_id'] ?? null, $data['reason'], $request->user());

        return back()->with('success', __('Đã nhập lại kho hàng trả lại (:number).', ['number' => $doc->number]));
    }

    public function createInvoice(Request $request, SalesOrder $order)
    {
        $this->authorize('view', $order);
        $this->authorize('create', Invoice::class);
        abort_unless(in_array($order->status, ['confirmed', 'processing', 'completed'], true), 422, __('Đơn hàng cần được duyệt trước khi lập hóa đơn.'));

        $invoice = $this->documents->createInvoiceFromOrder($order->load('items'), $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', __('Đã lập hóa đơn nháp :number. Hãy kiểm tra và bấm "Phát hành".', ['number' => $invoice->number]));
    }
}
