<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Quotation;
use App\Notifications\CrmNotification;
use App\Support\Tenancy\CurrentOrganization;

/**
 * Tác vụ bán hàng định kỳ (crm:sales-automation, chạy mỗi giờ qua scheduler), riêng từng tổ chức đang hoạt động:
 *  - Báo giá nháp / đã gửi quá ngày hiệu lực → "Hết hạn", báo người phụ trách.
 */
class SalesAutomationService
{
    public function run(): array
    {
        $totals = ['expired_quotations' => 0, 'due_reminders' => 0, 'overdue_invoices' => 0];
        foreach (Organization::where('status', 'active')->pluck('id') as $orgId) {
            $result = app(CurrentOrganization::class)->run((int) $orgId, fn () => $this->runForCurrentOrganization());
            foreach ($result as $k => $v) {
                $totals[$k] = ($totals[$k] ?? 0) + $v;
            }
        }

        return $totals;
    }

    private function runForCurrentOrganization(): array
    {
        $expired = 0;
        Quotation::with('assignee')->whereIn('status', ['draft', 'sent'])->whereNotNull('valid_until')->where('valid_until', '<', today())
            ->chunkById(200, function ($quotations) use (&$expired) {
                foreach ($quotations as $q) {
                    $q->update(['status' => 'expired']);
                    CrmNotification::send($q->assignee, 'quotation_expired', __('Báo giá :number đã hết hạn (hiệu lực đến :date).', [
                        'number' => $q->number, 'date' => vn_date($q->valid_until),
                    ]), route('quotations.show', $q), 'bi-hourglass-bottom');
                    $expired++;
                }
            });

        // Hóa đơn sắp đến hạn (trong 3 ngày): nhắc người phụ trách 1 lần
        $due = 0;
        \App\Models\Invoice::with('assignee')->where('status', 'issued')->where('payment_status', '!=', 'paid')->whereNull('due_reminder_sent_at')
            ->whereBetween('due_date', [today(), today()->addDays(3)])
            ->chunkById(200, function ($invoices) use (&$due) {
                foreach ($invoices as $inv) {
                    CrmNotification::send($inv->assignee, 'invoice_due', __('Hóa đơn :number sắp đến hạn thanh toán (:date), còn phải thu :amount.', [
                        'number' => $inv->number, 'date' => vn_date($inv->due_date), 'amount' => money($inv->balance),
                    ]), route('invoices.show', $inv), 'bi-calendar-event');
                    $inv->forceFill(['due_reminder_sent_at' => now()])->saveQuietly();
                    $due++;
                }
            });

        // Hóa đơn quá hạn: báo người phụ trách + bộ phận tài chính (xem mọi hóa đơn & được sửa hóa đơn) 1 lần
        $overdue = 0;
        $finance = null;
        \App\Models\Invoice::with('assignee')->where('status', 'issued')->where('payment_status', '!=', 'paid')->whereNull('overdue_notified_at')
            ->whereNotNull('due_date')->where('due_date', '<', today())
            ->chunkById(200, function ($invoices) use (&$overdue, &$finance) {
                $finance ??= \App\Models\User::inOrganization(app(CurrentOrganization::class)->id())->get()
                    ->filter(fn ($u) => $u->can('invoices.view_all') && $u->can('invoices.update'));
                foreach ($invoices as $inv) {
                    $message = __('Hóa đơn :number đã quá hạn thanh toán (:date), còn phải thu :amount.', [
                        'number' => $inv->number, 'date' => vn_date($inv->due_date), 'amount' => money($inv->balance),
                    ]);
                    foreach ($finance->concat([$inv->assignee])->filter()->unique('id') as $u) {
                        CrmNotification::send($u, 'invoice_overdue', $message, route('invoices.show', $inv), 'bi-exclamation-triangle');
                    }
                    $inv->forceFill(['overdue_notified_at' => now()])->saveQuietly();
                    $overdue++;
                }
            });

        return ['expired_quotations' => $expired, 'due_reminders' => $due, 'overdue_invoices' => $overdue];
    }
}
