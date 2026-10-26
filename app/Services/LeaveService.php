<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Nghỉ phép & ngày lễ.
 *  - Số ngày nghỉ = số ngày làm việc trong khoảng (bỏ cuối tuần theo cài đặt chấm công và ngày lễ); nửa ngày = 0,5.
 *  - Quỹ phép năm: employee_profiles.annual_leave_days, trống = cài đặt leave_annual_days (mặc định 12 — Điều 113 BLLĐ 2019).
 *    Kiểm tra quỹ khi gửi đơn (đã duyệt + đang chờ) và khi duyệt.
 *  - Người duyệt: quản lý trực tiếp hoặc payroll.manage; không tự duyệt.
 *  - Bảng lương: phép có lương + ngày lễ có lương rơi vào ngày làm việc → trả theo giờ chuẩn của ngày đó.
 */
class LeaveService
{
    public const DEFAULTS = ['leave_annual_days' => '12'];

    public function __construct(private AttendanceService $attendance) {}

    public static function setting(string $key): string
    {
        $v = Setting::get($key);

        return (string) ($v === null || $v === '' ? self::DEFAULTS[$key] : $v);
    }

    /** Ngày lễ (Y-m-d => Holiday) trong khoảng. */
    public function holidays(string $from, string $to)
    {
        return Holiday::whereBetween('date', [$from, $to])->get()->keyBy(fn ($h) => $h->date->toDateString());
    }

    /** Số ngày làm việc nghỉ (bước 0,5). */
    public function workingDays(string $start, string $end, string $part = 'full'): string
    {
        $tz = crm_timezone();
        $holidays = $this->holidays($start, $end);
        $days = 0;
        for ($d = Carbon::parse($start, $tz); $d->lte(Carbon::parse($end, $tz)); $d->addDay()) {
            if ($this->attendance->isWorkDay($d) && ! $holidays->has($d->toDateString())) {
                $days++;
            }
        }

        return $part !== 'full' && $days > 0 ? '0.5' : Decimal::round((string) $days, 1);
    }

    public function quota(User $user): string
    {
        $own = EmployeeProfile::where('user_id', $user->id)->value('annual_leave_days');

        return Decimal::round($own ?? self::setting('leave_annual_days'), 1);
    }

    /** Ngày phép năm đã dùng trong năm $year (theo ngày bắt đầu nghỉ). */
    public function used(User $user, int $year, array $statuses = ['approved'], ?int $exceptId = null): string
    {
        return Decimal::round(Decimal::sum(LeaveRequest::where('user_id', $user->id)->where('type', 'annual')->whereIn('status', $statuses)
            ->whereYear('start_date', $year)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->pluck('days')), 1);
    }

    /** Kiểm tra và trả về số ngày nghỉ của đơn mới. */
    public function validateRequest(User $user, string $type, string $start, string $end, string $part, ?int $exceptId = null): string
    {
        if ($part !== 'full' && $start !== $end) {
            throw ValidationException::withMessages(['part' => __('Nghỉ nửa ngày chỉ áp dụng khi nghỉ trong một ngày.')]);
        }
        if (Carbon::parse($start)->year !== Carbon::parse($end)->year) {
            throw ValidationException::withMessages(['end_date' => __('Đơn nghỉ không được kéo sang năm sau — hãy tách thành hai đơn.')]);
        }
        $days = $this->workingDays($start, $end, $part);
        if (Decimal::cmp($days, '0') <= 0) {
            throw ValidationException::withMessages(['start_date' => __('Khoảng ngày đã chọn không có ngày làm việc nào (cuối tuần / ngày lễ).')]);
        }
        $overlap = LeaveRequest::where('user_id', $user->id)->whereIn('status', ['pending', 'approved'])
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where('start_date', '<=', $end)->where('end_date', '>=', $start)->get()
            ->reject(fn ($l) => $start === $end && $l->start_date->toDateString() === $start && $l->end_date->toDateString() === $start
                && $part !== 'full' && $l->part !== 'full' && $l->part !== $part);   // sáng + chiều cùng ngày: hợp lệ
        if ($overlap->isNotEmpty()) {
            throw ValidationException::withMessages(['start_date' => __('Trùng với một đơn nghỉ khác đang chờ hoặc đã duyệt.')]);
        }
        if ($type === 'annual') {
            $this->assertBalance($user, (int) Carbon::parse($start)->year, $days, ['approved', 'pending'], $exceptId);
        }

        return $days;
    }

    public function assertBalance(User $user, int $year, string $days, array $statuses, ?int $exceptId = null): void
    {
        $remaining = Decimal::sub($this->quota($user), $this->used($user, $year, $statuses, $exceptId));
        if (Decimal::cmp($days, $remaining) > 0) {
            throw ValidationException::withMessages(['start_date' => __('Vượt quỹ phép năm: còn :r ngày, đơn này :d ngày.', ['r' => (float) $remaining, 'd' => (float) $days])]);
        }
    }

    public function canDecide(User $approver, LeaveRequest $leave): bool
    {
        if ($approver->id === $leave->user_id) {
            return false;
        }

        return $approver->can('payroll.manage') || (int) EmployeeProfile::where('user_id', $leave->user_id)->value('manager_id') === (int) $approver->id;
    }

    public function approversFor(User $user)
    {
        $manager = EmployeeProfile::where('user_id', $user->id)->value('manager_id');
        $ids = $manager ? [$manager] : User::inOrganization()->get()->filter(fn ($u) => $u->can('payroll.manage'))->pluck('id')->all();

        return User::inOrganization()->whereIn('id', $ids)->whereKeyNot($user->id)->get();
    }

    /**
     * Giờ nghỉ có lương trong khoảng ngày (cho bảng lương).
     * @return array{leave_minutes: int, holiday_minutes: int, leave_days: float, holiday_days: int}
     */
    public function paidMinutes(User $user, string $from, string $to): array
    {
        $tz = crm_timezone();
        $holidays = $this->holidays($from, $to)->where('is_paid', true);
        $hire = EmployeeProfile::where('user_id', $user->id)->value('hire_date');
        $leaveMinutes = 0;
        $leaveDays = 0.0;
        $leaves = LeaveRequest::where('user_id', $user->id)->where('status', 'approved')->whereIn('type', LeaveRequest::paidTypes())
            ->where('start_date', '<=', $to)->where('end_date', '>=', $from)->get();
        foreach ($leaves as $l) {
            for ($d = Carbon::parse(max($l->start_date->toDateString(), $from), $tz); $d->lte(Carbon::parse(min($l->end_date->toDateString(), $to), $tz)); $d->addDay()) {
                if (! $this->attendance->isWorkDay($d) || $holidays->has($d->toDateString())) {
                    continue;
                }
                $windows = $this->attendance->windows($d);
                $pick = match ($l->part) { 'am' => array_slice($windows, 0, 1), 'pm' => array_slice($windows, 1, 1), default => $windows };
                $leaveMinutes += array_sum(array_map(fn ($w) => (int) $w[0]->diffInMinutes($w[1]), $pick));
                $leaveDays += $l->part === 'full' ? 1 : 0.5;
            }
        }
        $holidayMinutes = 0;
        $holidayDays = 0;
        foreach ($holidays as $date => $h) {
            $d = Carbon::parse($date, $tz);
            if ($this->attendance->isWorkDay($d) && (! $hire || Carbon::parse($hire)->lte($d))) {
                $holidayMinutes += $this->attendance->scheduledMinutes($d);
                $holidayDays++;
            }
        }

        return ['leave_minutes' => $leaveMinutes, 'holiday_minutes' => $holidayMinutes, 'leave_days' => $leaveDays, 'holiday_days' => $holidayDays];
    }

    /** Ngày lễ dương lịch cố định của năm (để thêm nhanh; lễ âm lịch nhập tay vì đổi theo năm). */
    public static function fixedSolarHolidays(int $year): array
    {
        return [
            ["$year-01-01", __('Tết Dương lịch')],
            ["$year-04-30", __('Ngày Chiến thắng (30/4)')],
            ["$year-05-01", __('Ngày Quốc tế Lao động (1/5)')],
            ["$year-09-02", __('Quốc khánh (2/9)')],
        ];   // ngày nghỉ liền kề Quốc khánh (1/9 hoặc 3/9) và Tết Âm lịch, Giỗ Tổ: thay đổi theo năm → nhập tay
    }
}
