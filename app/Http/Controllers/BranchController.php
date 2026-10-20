<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;

/** Chi nhánh / văn phòng (bước 4.2) — thêm / sửa / xóa trên một trang, quyền teams.manage. */
class BranchController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('teams.manage'), 403);

        return view('branches.index', ['branches' => Branch::withCount('profiles')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('teams.manage'), 403);
        Branch::create($this->validated($request));

        return back()->with('success', __('Đã thêm chi nhánh.'));
    }

    public function update(Request $request, Branch $branch)
    {
        abort_unless($request->user()->can('teams.manage'), 403);
        $branch->update($this->validated($request, $branch));

        return back()->with('success', __('Đã cập nhật chi nhánh.'));
    }

    public function destroy(Request $request, Branch $branch)
    {
        abort_unless($request->user()->can('teams.manage'), 403);
        abort_if($branch->profiles()->exists(), 422, __('Chi nhánh còn nhân viên, hãy chuyển họ sang chi nhánh khác trước.'));
        $branch->delete();

        return back()->with('success', __('Đã xóa chi nhánh.'));
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', TenantRule::unique('branches', 'name')->ignore($branch?->id)],
            'code' => ['nullable', 'string', 'max:30', 'alpha_dash'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['name' => __('Tên chi nhánh')]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
