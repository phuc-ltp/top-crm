<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Setting;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Bảng lương (bước 5.4).
 *  - Lương giờ làm = phút công trong ca (bảng công) / 60 × đơn giá giờ (PayRateService) — chốt vào phiếu lúc tính.
 *  - Tăng ca = tổng (giờ × đơn giá đã chốt lúc duyệt) của các đơn đã duyệt trong tháng.
 *    Phần chênh so với lương giờ thường được miễn thuế TNCN.
 *  - Thu nhập = lương giờ làm + tăng ca + phụ cấp + thưởng.
 *  - Bảo hiểm người lao động (bật / tắt): BHXH 8%, BHYT 1,5%, BHTN 1% trên lương giờ làm, tối đa mức trần.
 *  - Thuế TNCN (bật / tắt): biểu lũy tiến từng phần trên thu nhập tính thuế
 *    = thu nhập − phần tăng ca miễn thuế − bảo hiểm − giảm trừ bản thân − người phụ thuộc × mức giảm trừ.
 *  - Thực nhận = thu nhập − bảo hiểm − thuế − khấu trừ khác.
 * Các mức giảm trừ, trần bảo hiểm, biểu thuế là tham số (cài đặt / config): cần kế toán xác nhận theo quy định hiện hành.
 */
class PayrollService
{
    public const DEFAULTS = [
        'payroll_insurance_enabled' => '1',
        'payroll_pit_enabled' => '1',
        'payroll_personal_deduction' => '15500000',
        'payroll_dependent_deduction' => '6200000',
        'payroll_insurance_cap' => '46800000',   // 20 × lương cơ sở 2.340.000
    ];

    public function __construct(private PayRateService $rates) {}

    public static function setting(string $key): string
    {
        $value = Setting::get($key);

        return (string) ($value === null || $value === '' ? self::DEFAULTS[$key] : $value);
    }

    /** Thuế TNCN lũy tiến từng phần theo config('crm.payroll.pit_brackets') = [[ngưỡng trên hoặc null, % thuế], ...]. */
    public static function progressiveTax(string $taxable): string
    {
        $tax = '0';
        $lower = '0';
        foreach (config('crm.payroll.pit_brackets') as [$upper, $rate]) {
            if (Decimal::cmp($taxable, $lower) <= 0) {
                break;
            }
            $top = $upper === null || Decimal::cmp($taxable, (string) $upper) < 0 ? $taxable : (string) $upper;
            $tax = Decimal::add($tax, Decimal::div(Decimal::mul(Decimal::sub($top, $lower), (string) $rate), '100'));
            if ($upper === null) {
                break;
            }
            $lower = (string) $upper;
        }

        return Decimal::round($tax, 0);
    }

    /** Người được lập phiếu: thành viên đang hoạt động có hồ sơ chưa nghỉ việc, hoặc có công / tăng ca đã duyệt trong tháng. */
    public function employeesFor(PayrollPeriod $period)
    {
        [$from, $to] = $this->range($period);
        $withData = AttendanceRecord::whereBetween('work_date', [$from, $to])->where('worked_minutes', '>', 0)->pluck('user_id')
            ->merge(OvertimeRequest::whereBetween('work_date', [$from, $to])->where('status', 'approved')->pluck('user_id'))
            ->merge(EmployeeProfile::whereIn('employment_status', ['probation', 'active'])->pluck('user_id'))
            ->unique();

        return User::inOrganization()->whereIn('id', $withData)->orderBy('name')->get();
    }

    /** Tính (lại) toàn bộ phiếu của kỳ nháp. Giữ phụ cấp / thưởng / khấu trừ / ghi chú đã nhập tay. */
    public function calculate(PayrollPeriod $period): int
    {
        abort_if($period->status !== 'draft', 422, __('Chỉ tính lại được kỳ lương ở trạng thái nháp.'));

        return DB::transaction(function () use ($period) {
            $users = $this->employeesFor($period);
            $period->payslips()->whereNotIn('user_id', $users->pluck('id'))->delete();
            foreach ($users as $user) {
                $slip = Payslip::firstOrNew(['payroll_period_id' => $period->id, 'user_id' => $user->id]);
                $this->fill($slip, $user, $period);
                $slip->save();
            }
            $period->forceFill(['calculated_at' => now()])->save();

            return $users->count();
        });
    }

    /** Điền số liệu tự động (công, đơn giá, tăng ca) rồi tính các khoản. */
    public function fill(Payslip $slip, User $user, PayrollPeriod $period): Payslip
    {
        [$from, $to] = $this->range($period);
        $records = AttendanceRecord::where('user_id', $user->id)->whereBetween('work_date', [$from, $to])->get(['worked_minutes', 'late_minutes']);
        $ots = OvertimeRequest::where('user_id', $user->id)->whereBetween('work_date', [$from, $to])->where('status', 'approved')->get(['hours', 'rate']);

        $slip->hourly_rate = $this->rates->rateFor($user);
        $paid = app(LeaveService::class)->paidMinutes($user, $from, $to);
        $slip->paid_leave_minutes = $paid['leave_minutes'] + $paid['holiday_minutes'];
        $slip->meta = array_merge($slip->meta ?? [], ['paid_leave_days' => $paid['leave_days'], 'holiday_days' => $paid['holiday_days']]);
        $slip->worked_minutes = (int) $records->sum('worked_minutes');
        $slip->work_days = $records->where('worked_minutes', '>', 0)->count();
        $slip->late_minutes = (int) $records->sum('late_minutes');
        $slip->ot_hours = Decimal::sum($ots->pluck('hours'));
        $slip->ot_pay = Decimal::round(Decimal::sum($ots->map(fn ($o) => Decimal::mul($o->hours, $o->rate))), 0);
        $slip->dependents = (int) EmployeeProfile::where('user_id', $user->id)->value('dependents');

        return $this->compute($slip);
    }

    /** Tính các khoản từ số liệu đã có trên phiếu (dùng cả khi sửa phụ cấp / thưởng / khấu trừ). */
    public function compute(Payslip $slip): Payslip
    {
        $insuranceOn = self::setting('payroll_insurance_enabled') === '1';
        $pitOn = self::setting('payroll_pit_enabled') === '1';
        $rates = config('crm.payroll.insurance');
        $cap = self::setting('payroll_insurance_cap');
        $personal = self::setting('payroll_personal_deduction');
        $perDependent = self::setting('payroll_dependent_deduction');

        $slip->base_pay = Decimal::round(Decimal::div(Decimal::mul((string) $slip->worked_minutes, $slip->hourly_rate), '60'), 0);
        $slip->paid_leave_pay = Decimal::round(Decimal::div(Decimal::mul((string) (int) $slip->paid_leave_minutes, $slip->hourly_rate), '60'), 0);
        $otNormal = Decimal::mul($slip->ot_hours, $slip->hourly_rate);
        $slip->ot_exempt = Decimal::round(Decimal::clamp(Decimal::sub($slip->ot_pay, $otNormal), '0'), 0);
        $slip->gross_pay = Decimal::sum([$slip->base_pay, $slip->paid_leave_pay, $slip->ot_pay, $slip->allowance, $slip->bonus]);

        $slip->insurance_base = $insuranceOn ? Decimal::clamp(Decimal::add($slip->base_pay, $slip->paid_leave_pay), '0', $cap) : '0';
        $pct = fn ($rate) => Decimal::round(Decimal::div(Decimal::mul($slip->insurance_base, (string) $rate), '100'), 0);
        $slip->social_insurance = $pct($rates['social']);
        $slip->health_insurance = $pct($rates['health']);
        $slip->unemployment_insurance = $pct($rates['unemployment']);
        $insurance = Decimal::sum([$slip->social_insurance, $slip->health_insurance, $slip->unemployment_insurance]);

        $family = Decimal::add($personal, Decimal::mul($perDependent, (string) $slip->dependents));
        $slip->taxable_income = $pitOn
            ? Decimal::clamp(Decimal::sub(Decimal::sub(Decimal::sub($slip->gross_pay, $slip->ot_exempt), $insurance), $family), '0')
            : '0';
        $slip->personal_income_tax = $pitOn ? self::progressiveTax($slip->taxable_income) : '0';
        $slip->net_pay = Decimal::sub(Decimal::sub(Decimal::sub($slip->gross_pay, $insurance), $slip->personal_income_tax), $slip->other_deduction);
        $slip->meta = array_merge($slip->meta ?? [], [
            'insurance_enabled' => $insuranceOn, 'pit_enabled' => $pitOn, 'insurance_rates' => $rates, 'insurance_cap' => $cap,
            'personal_deduction' => $personal, 'dependent_deduction' => $perDependent, 'family_deduction' => $family,
        ]);

        return $slip;
    }

    /** Các dòng hiển thị trên phiếu lương (web + PDF): [nhãn, số tiền, chi tiết, loại: income|deduction|subtotal|total]. */
    public static function lines(Payslip $s): array
    {
        $m = $s->meta ?? [];
        $r = $m['insurance_rates'] ?? config('crm.payroll.insurance');
        $lines = [
            [__('Lương giờ làm'), $s->base_pay, __(':h giờ × :r', ['h' => $s->worked_hours, 'r' => money($s->hourly_rate)]), 'income'],
            ...((float) $s->paid_leave_pay ? [[__('Nghỉ phép / lễ có lương'), $s->paid_leave_pay, __(':h giờ (phép :p ngày, lễ :l ngày)', ['h' => round($s->paid_leave_minutes / 60, 2),
                'p' => $m['paid_leave_days'] ?? 0, 'l' => $m['holiday_days'] ?? 0]), 'income']] : []),
            [__('Tăng ca'), $s->ot_pay, __(':h giờ (đơn giá đã duyệt)', ['h' => (float) $s->ot_hours]), 'income'],
            [__('Phụ cấp'), $s->allowance, null, 'income'],
            [__('Thưởng'), $s->bonus, null, 'income'],
            [__('Tổng thu nhập'), $s->gross_pay, null, 'subtotal'],
        ];
        if ($m['insurance_enabled'] ?? true) {
            $base = __('trên :b', ['b' => money($s->insurance_base)]);
            $lines[] = [__('BHXH (:p%)', ['p' => $r['social']]), $s->social_insurance, $base, 'deduction'];
            $lines[] = [__('BHYT (:p%)', ['p' => $r['health']]), $s->health_insurance, $base, 'deduction'];
            $lines[] = [__('BHTN (:p%)', ['p' => $r['unemployment']]), $s->unemployment_insurance, $base, 'deduction'];
        }
        if ($m['pit_enabled'] ?? true) {
            $lines[] = [__('Thuế TNCN'), $s->personal_income_tax, __('Thu nhập tính thuế :t (giảm trừ gia cảnh :f, :d người phụ thuộc; tăng ca miễn thuế :o)', [
                't' => money($s->taxable_income), 'f' => money($m['family_deduction'] ?? 0), 'd' => $s->dependents, 'o' => money($s->ot_exempt)]), 'deduction'];
        }
        $lines[] = [__('Khấu trừ khác'), $s->other_deduction, $s->note, 'deduction'];
        $lines[] = [__('Thực nhận'), $s->net_pay, null, 'total'];

        return $lines;
    }

    /** Tổng cộng của kỳ. */
    public function totals(PayrollPeriod $period): array
    {
        $slips = $period->payslips()->get();
        $sum = fn ($f) => Decimal::sum($slips->pluck($f));

        return [
            'count' => $slips->count(), 'gross' => $sum('gross_pay'), 'ot' => $sum('ot_pay'), 'tax' => $sum('personal_income_tax'), 'net' => $sum('net_pay'),
            'insurance' => Decimal::sum([$sum('social_insurance'), $sum('health_insurance'), $sum('unemployment_insurance')]),
        ];
    }

    private function range(PayrollPeriod $period): array
    {
        return [$period->period->copy()->startOfMonth()->toDateString(), $period->period->copy()->endOfMonth()->toDateString()];
    }
}
