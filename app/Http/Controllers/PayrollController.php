<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Models\Setting;
use App\Notifications\CrmNotification;
use App\Services\ActivityLogger;
use App\Services\ExportService;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Kỳ lương tháng (bước 5.4): tạo & tính từ bảng công + tăng ca đã duyệt, quy trình nháp → chờ duyệt → đã duyệt → đã trả,
 * xuất Excel, cài đặt bảo hiểm / thuế. Xem: payroll.view_all; thao tác: payroll.manage.
 */
class PayrollController extends Controller
{
    /** Chuyển trạng thái hợp lệ: hành động => [trạng thái hiện tại, trạng thái mới]. */
    private const TRANSITIONS = [
        'submit' => ['draft', 'submitted'],
        'reopen' => ['submitted', 'draft'],
        'approve' => ['submitted', 'approved'],
        'pay' => ['approved', 'paid'],
    ];

    public function __construct(private PayrollService $payroll) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('payroll.view_all'), 403);
        $periods = PayrollPeriod::withCount('payslips')->withSum('payslips', 'net_pay')->orderByDesc('period')->paginate($this->perPage($request));
        $settings = collect(array_keys(PayrollService::DEFAULTS))->mapWithKeys(fn ($k) => [$k => PayrollService::setting($k)])->all();

        return view('payroll.index', ['periods' => $periods, 's' => $settings, 'brackets' => config('crm.payroll.pit_brackets'), 'rates' => config('crm.payroll.insurance')]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')]], [], ['month' => __('Tháng')]);
        $period = Carbon::createFromFormat('Y-m-d', $data['month'].'-01')->toDateString();
        if (PayrollPeriod::whereDate('period', $period)->exists()) {
            return back()->withErrors(['month' => __('Kỳ lương tháng này đã tồn tại.')])->withInput();
        }
        $p = PayrollPeriod::create(['period' => $period, 'status' => 'draft']);
        $p->forceFill(['created_by' => $request->user()->id])->save();
        $count = $this->payroll->calculate($p);

        return redirect()->route('payroll.show', $p)->with('success', __('Đã tạo kỳ lương và tính :n phiếu lương.', ['n' => $count]));
    }

    public function show(Request $request, PayrollPeriod $payroll)
    {
        abort_unless($request->user()->can('payroll.view_all'), 403);
        $slips = $payroll->payslips()->with('user:id,name,avatar')->get()->sortBy(fn ($s) => $s->user?->name)->values();

        return view('payroll.show', ['period' => $payroll, 'slips' => $slips, 'totals' => $this->payroll->totals($payroll)]);
    }

    public function recalculate(Request $request, PayrollPeriod $payroll)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $count = $this->payroll->calculate($payroll);
        ActivityLogger::log('updated', $payroll, 'Tính lại bảng lương '.$payroll->label);

        return back()->with('success', __('Đã tính lại :n phiếu lương theo bảng công và tăng ca mới nhất.', ['n' => $count]));
    }

    public function transition(Request $request, PayrollPeriod $payroll, string $action)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        abort_unless(isset(self::TRANSITIONS[$action]), 404);
        [$from, $to] = self::TRANSITIONS[$action];
        abort_unless($payroll->status === $from, 422, __('Kỳ lương không ở trạng thái phù hợp cho thao tác này.'));
        abort_if($action === 'submit' && ! $payroll->payslips()->exists(), 422, __('Kỳ lương chưa có phiếu lương nào.'));

        $stamp = ['submit' => ['submitted_at' => now()], 'approve' => ['approved_at' => now(), 'approved_by' => $request->user()->id], 'pay' => ['paid_at' => now()],
            'reopen' => ['submitted_at' => null]][$action];
        $payroll->forceFill(['status' => $to] + $stamp)->save();

        if ($action === 'approve') {
            foreach ($payroll->payslips()->with('user')->get() as $slip) {
                CrmNotification::send($slip->user, 'payslip_ready', __('Phiếu lương :p đã sẵn sàng.', ['p' => $payroll->label]), route('payslips.show', $slip), 'bi-cash-stack');
            }
        }
        $messages = ['submit' => __('Đã gửi duyệt bảng lương.'), 'reopen' => __('Đã trả bảng lương về nháp.'), 'approve' => __('Đã duyệt bảng lương. Nhân viên đã xem được phiếu lương.'), 'pay' => __('Đã đánh dấu đã trả lương.')];

        return back()->with('success', $messages[$action]);
    }

    public function destroy(Request $request, PayrollPeriod $payroll)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        abort_unless($payroll->status === 'draft', 422, __('Chỉ xóa được kỳ lương nháp.'));
        $payroll->delete();

        return redirect()->route('payroll.index')->with('success', __('Đã xóa kỳ lương nháp.'));
    }

    public function export(Request $request, PayrollPeriod $payroll, ExportService $export)
    {
        abort_unless($request->user()->can('payroll.view_all'), 403);
        $slips = $payroll->payslips()->with('user:id,name,email')->get()->sortBy(fn ($s) => $s->user?->name);
        $headings = [__('Nhân viên'), __('Email'), __('Ngày công'), __('Giờ công'), __('Đơn giá giờ'), __('Lương giờ làm'), __('Giờ tăng ca'), __('Tiền tăng ca'),
            __('Phụ cấp'), __('Thưởng'), __('Tổng thu nhập'), __('BHXH'), __('BHYT'), __('BHTN'), __('Người phụ thuộc'), __('Thu nhập tính thuế'), __('Thuế TNCN'),
            __('Khấu trừ khác'), __('Thực nhận'), __('Ghi chú')];
        $rows = $slips->map(fn ($s) => [$s->user?->name, $s->user?->email, $s->work_days, $s->worked_hours, (float) $s->hourly_rate, (float) $s->base_pay, (float) $s->ot_hours,
            (float) $s->ot_pay, (float) $s->allowance, (float) $s->bonus, (float) $s->gross_pay, (float) $s->social_insurance, (float) $s->health_insurance,
            (float) $s->unemployment_insurance, $s->dependents, (float) $s->taxable_income, (float) $s->personal_income_tax, (float) $s->other_deduction, (float) $s->net_pay, $s->note]);

        return $export->download('bang-luong-'.$payroll->period->format('Y-m'), $headings, $rows);
    }

    public function settings(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $data = $request->validate([
            'payroll_insurance_enabled' => ['nullable', 'boolean'],
            'payroll_pit_enabled' => ['nullable', 'boolean'],
            'payroll_personal_deduction' => ['required', 'integer', 'between:0,1000000000'],
            'payroll_dependent_deduction' => ['required', 'integer', 'between:0,1000000000'],
            'payroll_insurance_cap' => ['required', 'integer', 'between:0,10000000000'],
        ], [], ['payroll_personal_deduction' => __('Giảm trừ bản thân'), 'payroll_dependent_deduction' => __('Giảm trừ mỗi người phụ thuộc'), 'payroll_insurance_cap' => __('Trần lương đóng bảo hiểm')]);
        $data['payroll_insurance_enabled'] = $request->boolean('payroll_insurance_enabled') ? '1' : '0';
        $data['payroll_pit_enabled'] = $request->boolean('payroll_pit_enabled') ? '1' : '0';
        Setting::put(array_map(fn ($v) => (string) $v, $data), 'payroll');

        return back()->with('success', __('Đã lưu cài đặt bảng lương. Áp dụng cho các lần tính lương tiếp theo.'));
    }
}
