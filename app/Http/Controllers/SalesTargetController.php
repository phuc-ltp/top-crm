<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SalesTarget;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Chỉ tiêu doanh số theo tháng cho từng nhân viên.
 * Thực đạt = tiền đã thu (payments) của các hóa đơn do nhân viên phụ trách, trong tháng đó.
 */
class SalesTargetController extends Controller
{
    private function canManage(Request $request): bool
    {
        return $request->user()->can('invoices.view_all') && $request->user()->can('reports.export');
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('reports.view'), 403);
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        $targets = SalesTarget::with('user:id,name,avatar')->where('year', $year)->where('month', $month)
            ->when(! $this->canManage($request), fn ($q) => $q->where('user_id', $request->user()->id))
            ->get();

        $actual = Payment::join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->where('invoices.status', 'issued')->whereNull('invoices.deleted_at')
            ->whereYear('payments.payment_date', $year)->whereMonth('payments.payment_date', $month)
            ->whereIn('invoices.assigned_to', $targets->pluck('user_id'))
            ->selectRaw('invoices.assigned_to as uid, SUM(payments.amount) as total')
            ->groupBy('invoices.assigned_to')->pluck('total', 'uid');

        return view('targets.index', [
            'targets' => $targets, 'actual' => $actual, 'year' => $year, 'month' => $month,
            'users' => Lookup::users(), 'canManage' => $this->canManage($request),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->canManage($request), 403);
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'target_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        SalesTarget::updateOrCreate(
            ['user_id' => $data['user_id'], 'year' => $data['year'], 'month' => $data['month']],
            ['target_amount' => $data['target_amount']],
        );

        return redirect()->route('targets.index', ['year' => $data['year'], 'month' => $data['month']])->with('success', __('Đã lưu chỉ tiêu.'));
    }

    public function destroy(Request $request, SalesTarget $target)
    {
        abort_unless($this->canManage($request), 403);
        $target->delete();

        return back()->with('success', __('Đã xóa chỉ tiêu.'));
    }
}
