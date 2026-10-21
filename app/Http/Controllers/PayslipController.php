<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Phiếu lương (bước 5.4). Nhân viên chỉ xem phiếu của chính mình khi kỳ đã duyệt; người có payroll.view_all xem mọi phiếu;
 * phụ cấp / thưởng / khấu trừ sửa được khi kỳ còn nháp (payroll.manage). Phiếu người khác → 404 để không lộ sự tồn tại.
 */
class PayslipController extends Controller
{
    public function mine(Request $request)
    {
        $slips = Payslip::with('period')->where('user_id', $request->user()->id)
            ->whereHas('period', fn ($q) => $q->whereIn('status', ['approved', 'paid']))
            ->get()->sortByDesc(fn ($s) => $s->period->period)->values();

        return view('payroll.mine', ['slips' => $slips]);
    }

    public function show(Request $request, Payslip $payslip)
    {
        $this->authorizeView($request, $payslip);

        return view('payroll.payslip', ['slip' => $payslip->load('period', 'user'), 'profile' => $payslip->user->employeeProfile]);
    }

    public function pdf(Request $request, Payslip $payslip)
    {
        $this->authorizeView($request, $payslip);
        $payslip->load('period', 'user');
        ActivityLogger::log('exported', $payslip->period, 'Tải phiếu lương PDF '.$payslip->user->name.' — '.$payslip->period->label);

        return Pdf::loadView('pdf.payslip', ['slip' => $payslip, 'profile' => $payslip->user->employeeProfile, 'settings' => Setting::allCached()])
            ->setPaper('a4')->download('phieu-luong-'.$payslip->period->period->format('Y-m').'-'.$payslip->user_id.'.pdf');
    }

    public function update(Request $request, Payslip $payslip, PayrollService $payroll)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        abort_unless($payslip->period->status === 'draft', 422, __('Kỳ lương đã gửi duyệt hoặc đã khóa, không sửa được phiếu.'));
        $data = $request->validate([
            'allowance' => ['nullable', 'integer', 'between:0,1000000000'],
            'bonus' => ['nullable', 'integer', 'between:0,1000000000'],
            'other_deduction' => ['nullable', 'integer', 'between:0,1000000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['allowance' => __('Phụ cấp'), 'bonus' => __('Thưởng'), 'other_deduction' => __('Khấu trừ khác')]);
        $payslip->fill(['allowance' => $data['allowance'] ?? 0, 'bonus' => $data['bonus'] ?? 0, 'other_deduction' => $data['other_deduction'] ?? 0, 'note' => $data['note'] ?? null]);
        $payroll->compute($payslip)->save();
        ActivityLogger::log('updated', $payslip->period, 'Sửa phụ cấp / thưởng / khấu trừ phiếu lương '.$payslip->user?->name.' — '.$payslip->period->label);

        return back()->with('success', __('Đã cập nhật phiếu lương.'));
    }

    private function authorizeView(Request $request, Payslip $payslip): void
    {
        $user = $request->user();
        $own = $payslip->user_id === $user->id && $payslip->period->isPublished();
        abort_unless($own || $user->can('payroll.view_all'), 404);
    }
}
