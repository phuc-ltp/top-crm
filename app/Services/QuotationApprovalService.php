<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\Decimal;
use App\Support\Tenancy\CurrentOrganization;

/**
 * Duyệt báo giá có chiết khấu vượt ngưỡng (Cài đặt › Báo giá › ngưỡng chiết khấu %, trống = tắt).
 * Tổng chiết khấu % = (Σ SL × đơn giá − (tạm tính − chiết khấu chung)) ÷ Σ SL × đơn giá × 100.
 * Người có quyền quotations.approve lập / sửa báo giá vượt ngưỡng → coi như tự duyệt.
 * Người không có quyền → "chờ duyệt": trạng thái giữ ở Nháp, không tạo đơn hàng được cho tới khi được duyệt.
 */
class QuotationApprovalService
{
    public static function threshold(): ?string
    {
        $value = trim((string) Setting::get('quotation_discount_threshold', ''));

        return $value === '' || ! is_numeric($value) ? null : $value;
    }

    /** Tổng chiết khấu % của báo giá (null khi không có giá trị gộp). */
    public static function discountPercent(Quotation $quotation): ?float
    {
        $gross = Decimal::sum($quotation->items->map(fn ($i) => Decimal::mul($i->quantity, $i->unit_price)));
        if (Decimal::cmp($gross, 0) <= 0) {
            return null;
        }
        $net = Decimal::sub($quotation->subtotal, $quotation->discount_amount);

        return (float) Decimal::round(Decimal::div(Decimal::mul(Decimal::sub($gross, $net), 100), $gross), 2);
    }

    /** Sau khi lưu: cập nhật trạng thái duyệt. Trả về true nếu báo giá đang chờ duyệt. */
    public function evaluate(Quotation $quotation, User $actor): bool
    {
        $quotation->load('items');
        $threshold = self::threshold();
        $pct = self::discountPercent($quotation);
        $needs = $threshold !== null && $pct !== null && Decimal::cmp($pct, $threshold) > 0;

        if (! $needs) {
            if ($quotation->approval_status !== null) {
                $quotation->update(['approval_status' => null, 'approval_note' => null]);
            }

            return false;
        }
        if ($actor->can('quotations.approve')) {
            $quotation->update(['approval_status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'approval_note' => null]);

            return false;
        }

        $wasPending = $quotation->approval_status === 'pending';
        $quotation->update([
            'approval_status' => 'pending', 'approved_by' => null, 'approved_at' => null,
            'status' => in_array($quotation->status, ['sent', 'accepted'], true) ? 'draft' : $quotation->status,
        ]);
        if (! $wasPending) {
            $approvers = User::inOrganization(app(CurrentOrganization::class)->id())->get()->filter(fn (User $u) => $u->id !== $actor->id && $u->can('quotations.approve'));
            foreach ($approvers as $u) {
                CrmNotification::send($u, 'quotation_approval', __('Báo giá :number cần duyệt: chiết khấu :pct% vượt ngưỡng :limit%.', [
                    'number' => $quotation->number, 'pct' => rtrim(rtrim(number_format($pct, 2, ',', '.'), '0'), ','), 'limit' => (float) $threshold,
                ]), route('quotations.show', $quotation), 'bi-shield-check');
            }
        }

        return true;
    }

    public function decide(Quotation $quotation, User $approver, bool $approve, ?string $note): void
    {
        $quotation->update([
            'approval_status' => $approve ? 'approved' : 'rejected',
            'approved_by' => $approver->id, 'approved_at' => now(), 'approval_note' => $note,
        ]);
        ActivityLogger::log($approve ? 'approved' : 'updated', $quotation, ($approve ? 'Duyệt ' : 'Từ chối duyệt ').$quotation->activityLabel().($note ? ': '.$note : ''));
        foreach (collect([$quotation->creator, $quotation->assignee])->filter()->unique('id') as $u) {
            if ($u->id !== $approver->id) {
                CrmNotification::send($u, 'quotation_approval', $approve
                    ? __('Báo giá :number đã được duyệt.', ['number' => $quotation->number])
                    : __('Báo giá :number bị từ chối duyệt: :note', ['number' => $quotation->number, 'note' => $note]),
                    route('quotations.show', $quotation), $approve ? 'bi-check2-circle' : 'bi-x-circle');
            }
        }
    }
}
