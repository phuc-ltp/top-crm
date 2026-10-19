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
        $totals = ['expired_quotations' => 0];
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

        return ['expired_quotations' => $expired];
    }
}
