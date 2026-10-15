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
        return ['status' => ['required', Rule::in(['pending', 'processing', 'completed', 'cancelled', 'confirmed'])]];
    }

    protected function defaults(Request $request): array
    {
        return ['status' => 'pending'];
    }

    /** Đơn đã có hóa đơn hoặc đã hủy/hoàn thành thì khóa chỉnh sửa. */
    protected function editable(Model $doc): bool
    {
        return ! in_array($doc->status, ['completed', 'cancelled'], true)
            && $doc->invoices()->where('status', '!=', 'cancelled')->doesntExist();
    }

    protected function showRelations(): array
    {
        return array_merge(parent::showRelations(), ['quotation', 'invoices', 'approver']);
    }

    protected function validated(Request $request, ?Model $existing = null): array
    {
        [$data, $items] = parent::validated($request, $existing);

        // Người không có quyền duyệt không được tự đặt trạng thái đã duyệt
        $wasApproved = $existing && $existing->approved_at;
        if ($data['status'] === 'confirmed' && ! $wasApproved && ! $request->user()->can('orders.approve')) {
            $data['status'] = 'pending';
        } elseif ($data['status'] === 'confirmed' && ! $wasApproved) {
            $data['approved_by'] = $request->user()->id;
            $data['approved_at'] = now();
        }

        return [$data, $items];
    }

    public function approve(Request $request, SalesOrder $order)
    {
        $this->authorize('approve', SalesOrder::class);
        $this->authorize('view', $order);
        abort_unless($order->status === 'pending', 422, __('Chỉ duyệt được đơn hàng đang chờ duyệt.'));

        $order->update(['status' => 'confirmed', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        ActivityLogger::log('approved', $order, 'Duyệt đơn hàng '.$order->number);
        if ($order->assignee && $order->assignee->id !== $request->user()->id) {
            CrmNotification::send($order->assignee, 'order_approved', 'Đơn hàng '.$order->number.' đã được duyệt', route('orders.show', $order), 'bi-check2-circle');
        }

        return back()->with('success', __('Đã duyệt đơn hàng :number.', ['number' => $order->number]));
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
