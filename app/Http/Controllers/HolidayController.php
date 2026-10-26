<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Setting;
use App\Services\LeaveService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;

/** Ngày lễ & quỹ phép năm của tổ chức (payroll.manage). */
class HolidayController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = $request->integer('year') ?: now()->year;

        return view('leave.holidays', ['year' => $year, 'holidays' => Holiday::whereYear('date', $year)->orderBy('date')->get(),
            'annualDays' => LeaveService::setting('leave_annual_days'), 'fixed' => LeaveService::fixedSolarHolidays($year)]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $data = $request->validate([
            'date' => ['required', 'date', TenantRule::unique('holidays', 'date')],
            'name' => ['required', 'string', 'max:150'],
            'is_paid' => ['nullable', 'boolean'],
        ], ['date.unique' => __('Ngày này đã có trong danh sách ngày lễ.')], ['date' => __('Ngày'), 'name' => __('Tên ngày lễ')]);
        Holiday::create(['date' => $data['date'], 'name' => $data['name'], 'is_paid' => $request->boolean('is_paid', true)]);

        return back()->with('success', __('Đã thêm ngày lễ.'));
    }

    /** Thêm nhanh các ngày lễ dương lịch cố định của năm (bỏ qua ngày đã có). */
    public function addFixed(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $year = (int) $request->validate(['year' => ['required', 'integer', 'between:2000,2100']])['year'];
        $added = 0;
        foreach (LeaveService::fixedSolarHolidays($year) as [$date, $name]) {
            if (! Holiday::whereDate('date', $date)->exists()) {
                Holiday::create(['date' => $date, 'name' => $name, 'is_paid' => true]);
                $added++;
            }
        }

        return back()->with('success', __('Đã thêm :n ngày lễ dương lịch. Hãy bổ sung Tết Âm lịch, Giỗ Tổ và ngày nghỉ liền kề Quốc khánh theo thông báo của năm.', ['n' => $added]));
    }

    public function destroy(Request $request, Holiday $holiday)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $holiday->delete();

        return back()->with('success', __('Đã xóa ngày lễ.'));
    }

    public function settings(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $data = $request->validate(['leave_annual_days' => ['required', 'numeric', 'between:0,60']], [], ['leave_annual_days' => __('Số ngày phép năm')]);
        Setting::put(['leave_annual_days' => (string) (float) $data['leave_annual_days']], 'leave');

        return back()->with('success', __('Đã lưu quỹ phép năm mặc định.'));
    }
}
