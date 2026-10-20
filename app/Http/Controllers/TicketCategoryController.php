<?php

namespace App\Http\Controllers;

use App\Models\TicketCategory;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;

/** Danh mục ticket hỗ trợ (bước 4.3) — thêm / sửa / xóa trên một trang, quyền tickets.assign (quản lý / nhân viên CSKH). */
class TicketCategoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('tickets.assign'), 403);

        return view('tickets.categories', ['categories' => TicketCategory::withCount('tickets')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('tickets.assign'), 403);
        TicketCategory::create($this->validated($request));

        return back()->with('success', __('Đã thêm danh mục.'));
    }

    public function update(Request $request, TicketCategory $category)
    {
        abort_unless($request->user()->can('tickets.assign'), 403);
        $category->update($this->validated($request, $category));

        return back()->with('success', __('Đã cập nhật danh mục.'));
    }

    /** Xóa danh mục: ticket thuộc danh mục chuyển về "Chưa phân loại" (khóa ngoại nullOnDelete). */
    public function destroy(Request $request, TicketCategory $category)
    {
        abort_unless($request->user()->can('tickets.assign'), 403);
        $category->delete();

        return back()->with('success', __('Đã xóa danh mục.'));
    }

    private function validated(Request $request, ?TicketCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', TenantRule::unique('ticket_categories', 'name')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], ['name' => __('Tên danh mục')]);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
