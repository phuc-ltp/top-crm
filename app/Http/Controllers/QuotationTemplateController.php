<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\QuotationTemplate;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;

/** Mẫu báo giá: quản lý = quotations.approve; dùng mẫu khi lập báo giá = quotations.create. */
class QuotationTemplateController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('quotations.approve'), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeManage($request);

        return view('quotation-templates.index', ['templates' => QuotationTemplate::orderBy('name')->get()]);
    }

    /** Lưu một báo giá đang xem làm mẫu. */
    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'quotation_id' => ['required', TenantRule::exists('quotations')],
            'name' => ['required', 'string', 'max:150', TenantRule::unique('quotation_templates', 'name')],
        ], [], ['name' => __('Tên mẫu')]);
        $quotation = Quotation::with('items')->findOrFail($data['quotation_id']);
        $this->authorize('view', $quotation);

        QuotationTemplate::create([
            'name' => $data['name'], 'tax_rate' => $quotation->tax_rate, 'notes' => $quotation->notes, 'created_by' => $request->user()->id,
            'items' => $quotation->items->map(fn ($i) => $i->only(['product_id', 'description', 'quantity', 'unit_price', 'discount_percent']))->values()->all(),
        ]);

        return back()->with('success', __('Đã lưu mẫu báo giá :name.', ['name' => $data['name']]));
    }

    public function update(Request $request, QuotationTemplate $quotationTemplate)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', TenantRule::unique('quotation_templates', 'name')->ignore($quotationTemplate->id)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $quotationTemplate->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Đã cập nhật mẫu báo giá.'));
    }

    public function destroy(Request $request, QuotationTemplate $quotationTemplate)
    {
        $this->authorizeManage($request);
        $quotationTemplate->delete();

        return back()->with('success', __('Đã xóa mẫu báo giá :name.', ['name' => $quotationTemplate->name]));
    }
}
