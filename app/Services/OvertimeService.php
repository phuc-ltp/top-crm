<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Tăng ca (bước 5.3).
 *  - Chỉ trong khung tăng ca của tổ chức (mặc định 18:00–21:00, tối đa 3 giờ / ngày), không chồng giờ với đơn khác cùng ngày.
 *  - Đơn giá / giờ = max(mức theo độ khó công việc, 150% lương giờ của người đó) — Điều 98 Bộ luật Lao động 2019.
 *    Đơn giá được CHỐT lúc duyệt (đổi bậc lương sau đó không làm thay đổi đơn đã duyệt).
 *  - Người duyệt: quản lý trực tiếp của người đề nghị, hoặc người có quyền payroll.manage; không ai tự duyệt đơn của mình.
 */
class OvertimeService
{
    public function __construct(private PayRateService $rates) {}

    /** Đơn giá theo mức độ công việc (đồng / giờ). */
    public static function levelRates(): array
    {
        return ['high' => (string) config('crm.ot_rates.high'), 'medium' => (string) config('crm.ot_rates.medium'), 'low' => (string) config('crm.ot_rates.low')];
    }

    public function rateFor(User $user, string $level): string
    {
        $legalMinimum = Decimal::mul($this->rates->rateFor($user), '1.5');
        $byLevel = self::levelRates()[$level] ?? '0';

        return Decimal::round(Decimal::cmp($byLevel, $legalMinimum) >= 0 ? $byLevel : $legalMinimum, 2);
    }

    /** Kiểm tra khung giờ và tính số giờ (2 chữ số thập phân). */
    public function hours(string $date, string $start, string $end, User $user, ?int $exceptId = null): string
    {
        $tz = crm_timezone();
        $windowStart = Carbon::parse($date.' '.AttendanceService::setting('work_ot_start'), $tz);
        $windowEnd = Carbon::parse($date.' '.AttendanceService::setting('work_ot_end'), $tz);
        $from = Carbon::parse($date.' '.$start, $tz);
        $to = Carbon::parse($date.' '.$end, $tz);
        if ($from->lt($windowStart) || $to->gt($windowEnd)) {
            throw ValidationException::withMessages(['start_time' => __('Giờ tăng ca phải nằm trong khung :a–:b.', ['a' => $windowStart->format('H:i'), 'b' => $windowEnd->format('H:i')])]);
        }
        if (! $to->gt($from)) {
            throw ValidationException::withMessages(['end_time' => __('Giờ kết thúc phải sau giờ bắt đầu.')]);
        }
        $overlap = OvertimeRequest::where('user_id', $user->id)->where('work_date', $date)->whereIn('status', ['pending', 'approved'])
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where('start_time', '<', $to->format('H:i:s'))->where('end_time', '>', $from->format('H:i:s'))->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_time' => __('Trùng giờ với một đơn tăng ca khác trong ngày.')]);
        }

        return Decimal::round(Decimal::div((string) $from->diffInMinutes($to), '60'), 2);
    }

    public function canDecide(User $approver, OvertimeRequest $request): bool
    {
        if ($approver->id === $request->user_id) {
            return false;
        }

        return $approver->can('payroll.manage')
            || (int) EmployeeProfile::where('user_id', $request->user_id)->value('manager_id') === (int) $approver->id;
    }

    /** Người nhận thông báo khi có đơn mới: quản lý trực tiếp; nếu không có → người quản lý lương. */
    public function approversFor(User $user)
    {
        $manager = EmployeeProfile::where('user_id', $user->id)->value('manager_id');
        $ids = $manager ? [$manager] : User::inOrganization()->get()->filter(fn ($u) => $u->can('payroll.manage'))->pluck('id')->all();

        return User::inOrganization()->whereIn('id', $ids)->whereKeyNot($user->id)->get();
    }
}
