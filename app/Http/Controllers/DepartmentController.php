<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $this->authorize('manage', User::class);

        return view('departments.index', ['departments' => Department::withCount('users')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage', User::class);
        Department::create($this->validated($request));

        return back()->with('success', __('Đã thêm phòng ban.'));
    }

    public function update(Request $request, Department $department)
    {
        $this->authorize('manage', User::class);
        $department->update($this->validated($request, $department));

        return back()->with('success', __('Đã cập nhật phòng ban.'));
    }

    public function destroy(Department $department)
    {
        $this->authorize('manage', User::class);
        abort_if($department->users()->exists(), 422, __('Phòng ban còn nhân viên, hãy chuyển họ sang phòng khác trước.'));
        $department->delete();

        return back()->with('success', __('Đã xóa phòng ban.'));
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', \App\Support\Tenancy\TenantRule::unique('departments', 'code')->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], ['name' => __('Tên phòng ban'), 'code' => __('Mã phòng ban')]);
    }
}
