@extends('layouts.app')
@section('title', $campaign->exists ? __('Sửa chiến dịch') : __('Tạo chiến dịch'))
@section('content')
    <x-page-header :title="$campaign->exists ? __('Sửa: :name', ['name' => $campaign->name]) : __('Tạo chiến dịch marketing')" :breadcrumbs="['Marketing' => route('campaigns.index'), ($campaign->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]" />
    <div class="alert alert-info small" style="max-width:900px"><i class="bi bi-info-circle"></i> {{ __('Hệ thống không tự động gửi email hàng loạt. Chiến dịch dùng để theo dõi ngân sách, lead và hiệu quả.') }}</div>
    <form method="POST" action="{{ $campaign->exists ? route('campaigns.update', $campaign) : route('campaigns.store') }}" class="needs-validation" novalidate>
        @csrf @if ($campaign->exists) @method('PUT') @endif
        <div class="card" style="max-width:900px"><div class="card-body"><div class="row">
            <x-input name="name" :label="__('Tên chiến dịch')" :value="$campaign->name" required maxlength="255" col="col-md-8 mb-3" />
            <x-select name="type" :label="__('Loại chiến dịch')" :options="App\Models\Campaign::TYPES" :value="$campaign->type" required col="col-md-4 mb-3" />
            <x-input name="start_date" type="date" :label="__('Ngày bắt đầu')" :value="optional($campaign->start_date)->format('Y-m-d')" col="col-md-4 mb-3" />
            <x-input name="end_date" type="date" :label="__('Ngày kết thúc')" :value="optional($campaign->end_date)->format('Y-m-d')" col="col-md-4 mb-3" />
            <x-select name="status" :label="__('Trạng thái')" :options="App\Models\Campaign::STATUSES" :value="$campaign->status" required col="col-md-4 mb-3" />
            <x-input name="budget" type="number" :label="__('Ngân sách (₫)')" :value="(int) $campaign->budget" required min="0" step="100000" col="col-md-4 mb-3" />
            <x-input name="target_leads" type="number" :label="__('Số lead mục tiêu')" :value="(int) $campaign->target_leads" required min="0" col="col-md-4 mb-3" />
            <x-select name="owner_id" :label="__('Người phụ trách')" :options="$users" :value="$campaign->owner_id" :placeholder="__('— Chọn —')" col="col-md-4 mb-3" />
            <x-textarea name="description" :label="__('Mô tả')" :value="$campaign->description" rows="4" maxlength="5000" col="col-12 mb-0" />
        </div></div>
        <div class="card-footer bg-transparent d-flex justify-content-end gap-2 py-3">
            <a href="{{ $campaign->exists ? route('campaigns.show', $campaign) : route('campaigns.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Lưu') }}</button>
        </div></div>
    </form>
@endsection
