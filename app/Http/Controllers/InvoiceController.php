<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class InvoiceController extends SalesDocumentController
{
    protected string $route = 'invoices';

    protected string $label = 'Hóa đơn';

    protected string $dateField = 'issue_date';

    protected function modelClass(): string
    {
        return Invoice::class;
    }

    protected function extraRules(): array
    {
        return [
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
        ];
    }

    protected function defaults(Request $request): array
    {
        return ['status' => 'draft', 'payment_status' => 'unpaid', 'due_date' => today()->addDays(15)];
    }

    /** Chỉ hóa đơn nháp được sửa/xóa. Hóa đơn đã phát hành chỉ có thể hủy. */
    protected function editable(Model $doc): bool
    {
        return $doc->status === 'draft';
    }

    protected function showRelations(): array
    {
        return array_merge(parent::showRelations(), ['salesOrder', 'payments.recorder:id,name']);
    }

    protected function validated(Request $request, ?Model $existing = null): array
    {
        [$data, $items] = parent::validated($request, $existing);
        $data['status'] = 'draft';
        if (! empty($data['sales_order_id'])) {
            abort_unless(SalesOrder::where('id', $data['sales_order_id'])->where('customer_id', $data['customer_id'])->exists(), 422, __('Đơn hàng không thuộc khách hàng đã chọn.'));
        }

        return [$data, $items];
    }

    public function issue(Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        abort_unless($invoice->status === 'draft', 422, __('Chỉ phát hành được hóa đơn nháp.'));
        $invoice->update(['status' => 'issued']);

        return back()->with('success', __('Đã phát hành hóa đơn :number. Có thể ghi nhận thanh toán.', ['number' => $invoice->number]));
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        abort_if($invoice->status === 'cancelled', 422, __('Hóa đơn đã bị hủy.'));
        abort_if($invoice->payments()->exists(), 422, __('Hóa đơn đã có thanh toán. Hãy xóa các khoản thanh toán trước khi hủy.'));
        $invoice->update(['status' => 'cancelled']);
        ActivityLogger::log('updated', $invoice, 'Hủy hóa đơn '.$invoice->number);

        return back()->with('success', __('Đã hủy hóa đơn :number.', ['number' => $invoice->number]));
    }
}
