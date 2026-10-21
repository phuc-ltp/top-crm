<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Services\AssetService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;

/** Nhóm tài sản (bước 5.5): xem — assets.view; thêm / sửa / xóa — assets.manage. */
class AssetCategoryController extends Controller
{
    public function index(Request $request, AssetService $assets)
    {
        abort_unless($request->user()->can('assets.view'), 403);
        if ($request->user()->can('assets.manage')) {
            $assets->ensureCategories();
        }

        return view('assets.categories', ['categories' => AssetCategory::withCount('assets')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        AssetCategory::create($this->validated($request));

        return back()->with('success', __('Đã thêm nhóm tài sản.'));
    }

    public function update(Request $request, AssetCategory $category)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        $category->update($this->validated($request, $category));

        return back()->with('success', __('Đã cập nhật nhóm tài sản.'));
    }

    public function destroy(Request $request, AssetCategory $category)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        abort_if($category->assets()->withTrashed()->exists(), 422, __('Nhóm đang có tài sản, không xóa được.'));
        $category->delete();

        return back()->with('success', __('Đã xóa nhóm tài sản.'));
    }

    private function validated(Request $request, ?AssetCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', TenantRule::unique('asset_categories', 'name')->ignore($category?->id)],
            'depreciation_months' => ['required', 'integer', 'between:0,600'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], ['name' => __('Tên nhóm'), 'depreciation_months' => __('Số tháng khấu hao')]);
    }
}
