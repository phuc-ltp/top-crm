@extends('layouts.app')
@section('title', $campaign->exists ? 'Sửa chiến dịch' : 'Tạo chiến dịch')
@section('content')
    <x-page-header :title="$campaign->exists ? 'Sửa: '.$campaign->name : 'Tạo chiến dịch marketing'" :breadcrumbs="['Marketing' => route('campaigns.index'), ($campaign->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]" />
    <div class="alert alert-info small" style="max-width:900px"><i class="bi bi-info-circle"></i> Hệ thống không tự động gửi email hàng loạt. Chiến dịch dùng để theo dõi ngân sách, lead và hiệu quả.</div>
    <form method="POST" action="{{ $campaign->exists ? route('campaigns.update', $campaign) : route('campaigns.store') }}" class="needs-validation" novalidate>
        @csrf @if ($campaign->exists) @method('PUT') @endif
        <div class="card" style="max-width:900px"><div class="card-body"><div class="row">
            <x-input name="name" label="Tên chiến dịch" :value="$campaign->name" required maxlength="255" col="col-md-8 mb-3" />
            <x-select name="type" label="Loại chiến dịch" :options="App\Models\Campaign::TYPES" :value="$campaign->type" required col="col-md-4 mb-3" />
            <x-input name="start_date" type="date" label="Ngày bắt đầu" :value="optional($campaign->start_date)->format('Y-m-d')" col="col-md-4 mb-3" />
            <x-input name="end_date" type="date" label="Ngày kết thúc" :value="optional($campaign->end_date)->format('Y-m-d')" col="col-md-4 mb-3" />
            <x-select name="status" label="Trạng thái" :options="App\Models\Campaign::STATUSES" :value="$campaign->status" required col="col-md-4 mb-3" />
            <x-input name="budget" type="number" label="Ngân sách (₫)" :value="(int) $campaign->budget" required min="0" step="100000" col="col-md-4 mb-3" />
            <x-input name="target_leads" type="number" label="Số lead mục tiêu" :value="(int) $campaign->target_leads" required min="0" col="col-md-4 mb-3" />
            <x-select name="owner_id" label="Người phụ trách" :options="$users" :value="$campaign->owner_id" placeholder="— Chọn —" col="col-md-4 mb-3" />
            <x-textarea name="description" label="Mô tả" :value="$campaign->description" rows="4" maxlength="5000" col="col-12 mb-0" />
        </div></div>
        <div class="card-footer bg-transparent d-flex justify-content-end gap-2 py-3">
            <a href="{{ $campaign->exists ? route('campaigns.show', $campaign) : route('campaigns.index') }}" class="btn btn-light">Hủy</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Lưu</button>
        </div></div>
    </form>
@endsection
