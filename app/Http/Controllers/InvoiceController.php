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
            'sales_order_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('sales_orders')],
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

    /** Tổng quan công nợ cho trang danh sách hóa đơn (theo phạm vi xem). */
    protected function indexExtras(Request $request): array
    {
        $open = fn () => Invoice::visibleTo($request->user())->where('status', 'issued')->where('payment_status', '!=', 'paid');
        $remaining = \Illuminate\Support\Facades\DB::raw('total - amount_paid - credited_amount');
        $paidThisMonth = \App\Models\Payment::whereIn('invoice_id', Invoice::visibleTo($request->user())->where('status', 'issued')->select('id'))
            ->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('amount');

        return ['invoiceSummary' => [
            'receivable' => (float) $open()->sum($remaining), 'open_count' => $open()->count(),
            'overdue' => (float) $open()->whereNotNull('due_date')->where('due_date', '<', today())->sum($remaining),
            'overdue_count' => $open()->whereNotNull('due_date')->where('due_date', '<', today())->count(),
            'soon' => (float) $open()->whereBetween('due_date', [today(), today()->addDays(7)])->sum($remaining),
            'soon_count' => $open()->whereBetween('due_date', [today(), today()->addDays(7)])->count(),
            'paid_month' => (float) $paidThisMonth,
        ]];
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
        abort_if($invoice->creditNotes()->exists(), 422, __('Hóa đơn đã có phiếu giảm trừ nên không hủy được.'));
        $invoice->update(['status' => 'cancelled']);
        ActivityLogger::log('updated', $invoice, 'Hủy hóa đơn '.$invoice->number);

        return back()->with('success', __('Đã hủy hóa đơn :number.', ['number' => $invoice->number]));
    }
}
