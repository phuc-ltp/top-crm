<?php

namespace App\Http\Controllers;

use App\Models\PayGrade;
use App\Services\PayRateService;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Bậc lương theo giờ (bước 5.1) — quyền payroll.manage. Đơn giá trong khoảng crm.pay_rate_min … crm.pay_rate_max. */
class PayGradeController extends Controller
{
    public function index(Request $request, PayRateService $rates)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $rates->ensureDefaults();

        return view('pay-grades.index', ['grades' => PayGrade::withCount('profiles')->orderByDesc('hourly_rate')->get(), 'roles' => PermissionCatalog::ORG_ROLES]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        PayGrade::create($this->validated($request));

        return back()->with('success', __('Đã thêm bậc lương.'));
    }

    public function update(Request $request, PayGrade $payGrade)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $payGrade->update($this->validated($request, $payGrade));

        return back()->with('success', __('Đã cập nhật bậc lương.'));
    }

    public function destroy(Request $request, PayGrade $payGrade)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        abort_if($payGrade->profiles()->exists(), 422, __('Bậc lương đang được gán cho nhân viên, hãy chuyển họ sang bậc khác trước.'));
        $payGrade->delete();

        return back()->with('success', __('Đã xóa bậc lương.'));
    }

    private function validated(Request $request, ?PayGrade $grade = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', TenantRule::unique('pay_grades', 'name')->ignore($grade?->id)],
            'hourly_rate' => ['required', 'numeric', 'min:'.config('crm.pay_rate_min'), 'max:'.config('crm.pay_rate_max')],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(PermissionCatalog::ORG_ROLES)],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => __('Tên bậc'), 'hourly_rate' => __('Đơn giá / giờ')]);

        // Mỗi vai trò chỉ thuộc một bậc mặc định: gỡ vai trò khỏi các bậc khác
        $roles = array_values(array_unique($data['roles'] ?? []));
        foreach (PayGrade::when($grade, fn ($q) => $q->whereKeyNot($grade->id))->get() as $other) {
            if (array_intersect($other->roles ?? [], $roles)) {
                $other->update(['roles' => array_values(array_diff($other->roles, $roles))]);
            }
        }

        return ['name' => $data['name'], 'hourly_rate' => $data['hourly_rate'], 'roles' => $roles, 'description' => $data['description'] ?? null];
    }
}
