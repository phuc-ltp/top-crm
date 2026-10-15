@extends('layouts.app')
@section('title', $doc->exists ? __('Sửa :doc', ['doc' => mb_strtolower($cfg['doc'])]) : __('Tạo :doc', ['doc' => mb_strtolower($cfg['doc'])]))
@section('content')
    @php($r = $cfg['route'])
    <x-page-header :title="$doc->exists ? __('Sửa :doc', ['doc' => mb_strtolower($cfg['doc'])]).' '.$doc->number : __('Tạo :doc', ['doc' => mb_strtolower($cfg['doc'])])"
                   :breadcrumbs="[$cfg['label'] => route($r.'.index'), ($doc->exists ? $doc->number : 'Tạo mới') => null]" />

    <form method="POST" action="{{ $doc->exists ? route($r.'.update', $doc) : route($r.'.store') }}" class="needs-validation" novalidate>
        @csrf @if ($doc->exists) @method('PUT') @endif
        <div class="card mb-4"><div class="card-header">{{ __('Thông tin chung') }}</div><div class="card-body"><div class="row">
            <x-select name="customer_id" :label="__('labels.customer')" :options="$customers" :value="$doc->customer_id" required :placeholder="__('— Chọn khách hàng —')" col="col-md-6 mb-3" />
            <x-input :name="$cfg['dateField']" type="date" :label="$r === 'orders' ? __('Ngày đặt hàng') : __('Ngày lập')" :value="optional($doc->{$cfg['dateField']})->format('Y-m-d')" required col="col-md-3 mb-3" />
            @if ($r === 'quotations')
                <x-input name="valid_until" type="date" :label="__('Hiệu lực đến')" :value="optional($doc->valid_until)->format('Y-m-d')" col="col-md-3 mb-3" />
                <x-select name="status" :label="__('Trạng thái')" :options="App\Models\Quotation::STATUSES" :value="$doc->status" required col="col-md-4 mb-3" />
                <x-select name="opportunity_id" :label="__('Cơ hội liên quan')" :options="$opportunities" :value="$doc->opportunity_id" :placeholder="__('— Không —')" col="col-md-4 mb-3" />
            @elseif ($r === 'orders')
                <x-select name="status" :label="__('Trạng thái')" :options="auth()->user()->can('orders.approve') || $doc->approved_at ? App\Models\SalesOrder::STATUSES : collect(App\Models\SalesOrder::STATUSES)->except('confirmed')->all()" :value="$doc->status" required col="col-md-3 mb-3" />
            @else
                <x-input name="due_date" type="date" :label="__('Hạn thanh toán')" :value="optional($doc->due_date)->format('Y-m-d')" col="col-md-3 mb-3" />
            @endif
            @can($cfg['class']::permissionModule().'.view_all')
                <x-select name="assigned_to" :label="__('Nhân viên phụ trách')" :options="$users" :value="$doc->assigned_to" :placeholder="__('— Chọn —')" col="col-md-4 mb-3" />
            @endcan
        </div></div></div>

        <div class="card mb-4">
            <div class="card-header"><span>{{ __('Sản phẩm / dịch vụ') }}</span><button type="button" class="btn btn-soft-primary btn-sm" data-add-row><i class="bi bi-plus"></i> {{ __('Thêm dòng') }}</button></div>
            <div class="table-responsive">
                <table class="table line-items" data-line-items>
                    <thead><tr><th style="min-width:220px">{{ __('Sản phẩm') }}</th><th style="min-width:200px">{{ __('Mô tả') }}</th><th style="width:100px">{{ __('SL') }}</th><th style="width:150px">{{ __('Đơn giá') }}</th><th style="width:90px">{{ __('% giảm') }}</th><th class="text-end" style="width:140px">{{ __('Thành tiền') }}</th><th style="width:44px"></th></tr></thead>
                    <tbody>
                    @foreach (old('items', $items) as $i => $item)
                        @include('sales._item_row', ['index' => $i, 'item' => $item])
                    @endforeach
                    </tbody>
                </table>
            </div>
            @error('items')<div class="text-danger small px-3 pb-2">{{ $message }}</div>@enderror
            <template id="lineItemTemplate">@include('sales._item_row', ['index' => '__INDEX__', 'item' => ['quantity' => 1]])</template>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card"><div class="card-body"><x-textarea name="notes" :label="__('Ghi chú / điều khoản')" :value="$doc->notes" rows="5" maxlength="5000" col="mb-0" /></div></div>
            </div>
            <div class="col-lg-5">
                <div class="card"><div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ __('Tạm tính') }}</span><strong id="sumSubtotal">0 ₫</strong></div>
                    <div class="d-flex justify-content-between align-items-center mb-2 gap-2"><label for="discount_amount" class="text-muted">{{ __('Chiết khấu (₫)') }}</label>
                        <input type="number" name="discount_amount" id="discount_amount" class="form-control form-control-sm text-end" style="max-width:160px" min="0" step="1000" value="{{ old('discount_amount', (int) $doc->discount_amount) }}"></div>
                    <div class="d-flex justify-content-between align-items-center mb-2 gap-2"><label for="tax_rate" class="text-muted">{{ __('Thuế VAT (%)') }}</label>
                        <input type="number" name="tax_rate" id="tax_rate" class="form-control form-control-sm text-end" style="max-width:160px" min="0" max="100" step="0.5" required value="{{ old('tax_rate', (float) $doc->tax_rate) }}"></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ __('Tiền thuế') }}</span><strong id="sumTax">0 ₫</strong></div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5"><span class="fw-bold">{{ __('Tổng cộng') }}</span><span class="fw-bold text-orange" id="sumTotal">0 ₫</span></div>
                    <div class="small text-muted mt-2"><i class="bi bi-shield-check"></i> {{ __('Số tiền sẽ được hệ thống tính lại khi lưu.') }}</div>
                </div></div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>{{ __('Lưu :doc', ['doc' => mb_strtolower($cfg['doc'])]) }}</button>
                    <a href="{{ $doc->exists ? route($r.'.show', $doc) : route($r.'.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                </div>
            </div>
        </div>
    </form>
@endsection
