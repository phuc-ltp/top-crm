<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuotationController extends SalesDocumentController
{
    protected string $route = 'quotations';

    protected string $label = 'Báo giá';

    protected string $dateField = 'issue_date';

    protected function modelClass(): string
    {
        return Quotation::class;
    }

    protected function extraRules(): array
    {
        return [
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'status' => ['required', Rule::in(array_keys(Quotation::STATUSES))],
            'opportunity_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('opportunities')],
        ];
    }

    protected function defaults(Request $request): array
    {
        $opportunityId = $request->integer('opportunity_id') ?: null;
        if ($opportunityId && ! Opportunity::visibleTo($request->user())->whereKey($opportunityId)->exists()) {
            $opportunityId = null;
        }

        return ['status' => 'draft', 'valid_until' => today()->addDays(30), 'opportunity_id' => $opportunityId];
    }

    /** Báo giá đã được chấp nhận (đã thành đơn hàng) thì không sửa nữa. */
    protected function editable(Model $doc): bool
    {
        return $doc->status !== 'accepted' || $doc->salesOrders()->doesntExist();
    }

    protected function showRelations(): array
    {
        return array_merge(parent::showRelations(), ['opportunity', 'salesOrders', 'approver']);
    }

    protected function formData(Request $request): array
    {
        return parent::formData($request) + [
            'opportunities' => Opportunity::visibleTo($request->user())->open()->orderBy('title')->pluck('title', 'id')->all(),
            'templates' => \App\Models\QuotationTemplate::active()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    /** Lập báo giá từ mẫu: ?template=ID */
    protected function prefill(Request $request): array
    {
        $template = $request->filled('template') ? \App\Models\QuotationTemplate::active()->find($request->integer('template')) : null;
        if (! $template || ! $template->items) {
            return [];
        }

        return ['attributes' => array_filter(['tax_rate' => $template->tax_rate, 'notes' => $template->notes], fn ($v) => $v !== null), 'items' => $template->items];
    }

    /** Chiết khấu vượt ngưỡng → chờ duyệt (người có quyền duyệt thì tự duyệt). */
    protected function afterSave(Model $doc, Request $request): void
    {
        if (app(\App\Services\QuotationApprovalService::class)->evaluate($doc, $request->user())) {
            session()->flash('warning', __('Chiết khấu vượt ngưỡng của công ty: báo giá đang chờ quản lý duyệt — tạm giữ ở trạng thái Nháp.'));
        }
    }

    public function approve(Request $request, Quotation $quotation, \App\Services\QuotationApprovalService $approvals)
    {
        abort_unless($request->user()->can('quotations.approve'), 403);
        $this->authorize('view', $quotation);
        abort_unless($quotation->approval_status === 'pending', 422, __('Báo giá không ở trạng thái chờ duyệt.'));
        $approvals->decide($quotation, $request->user(), true, $request->input('note'));

        return back()->with('success', __('Đã duyệt báo giá :number.', ['number' => $quotation->number]));
    }

    public function reject(Request $request, Quotation $quotation, \App\Services\QuotationApprovalService $approvals)
    {
        abort_unless($request->user()->can('quotations.approve'), 403);
        $this->authorize('view', $quotation);
        abort_unless($quotation->approval_status === 'pending', 422, __('Báo giá không ở trạng thái chờ duyệt.'));
        $data = $request->validate(['note' => ['required', 'string', 'max:500']], [], ['note' => __('Lý do')]);
        $approvals->decide($quotation, $request->user(), false, $data['note']);

        return back()->with('success', __('Đã từ chối duyệt báo giá :number.', ['number' => $quotation->number]));
    }

    public function convert(Request $request, Quotation $quotation)
    {
        $this->authorize('view', $quotation);
        $this->authorize('create', \App\Models\SalesOrder::class);
        if (in_array($quotation->approval_status, ['pending', 'rejected'], true)) {
            return back()->withErrors(['status' => __('Báo giá chưa được duyệt chiết khấu — chưa thể tạo đơn hàng.')]);
        }

        $order = $this->documents->convertQuotationToOrder($quotation->load('items'), $request->user());

        return redirect()->route('orders.show', $order)->with('success', __('Đã tạo đơn hàng :order từ báo giá :quote.', ['order' => $order->number, 'quote' => $quotation->number]));
    }
}
