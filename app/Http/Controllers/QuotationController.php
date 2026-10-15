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
            'opportunity_id' => ['nullable', 'exists:opportunities,id'],
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
        return array_merge(parent::showRelations(), ['opportunity', 'salesOrders']);
    }

    protected function formData(Request $request): array
    {
        return parent::formData($request) + [
            'opportunities' => Opportunity::visibleTo($request->user())->open()->orderBy('title')->pluck('title', 'id')->all(),
        ];
    }

    public function convert(Request $request, Quotation $quotation)
    {
        $this->authorize('view', $quotation);
        $this->authorize('create', \App\Models\SalesOrder::class);

        $order = $this->documents->convertQuotationToOrder($quotation->load('items'), $request->user());

        return redirect()->route('orders.show', $order)->with('success', 'Đã tạo đơn hàng '.$order->number.' từ báo giá '.$quotation->number.'.');
    }
}
