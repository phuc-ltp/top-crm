@extends('layouts.app')
@section('title', $lead->exists ? __('Sửa lead') : __('Tạo lead'))
@section('content')
    <x-page-header :title="$lead->exists ? 'Sửa lead: '.$lead->name : 'Tạo lead'" :breadcrumbs="['Leads' => route('leads.index'), ($lead->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]" />
    <form method="POST" action="{{ $lead->exists ? route('leads.update', $lead) : route('leads.store') }}" class="needs-validation" novalidate>
        @csrf @if ($lead->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">{{ __('Thông tin lead') }}</div>
                    <div class="card-body"><div class="row">
                        <x-input name="name" :label="__('Họ tên / Tên lead')" :value="$lead->name" required maxlength="255" col="col-md-6 mb-3" />
                        <x-input name="company" :label="__('Công ty')" :value="$lead->company" maxlength="255" col="col-md-6 mb-3" />
                        <x-input name="email" type="email" label="Email" :value="$lead->email" maxlength="255" col="col-md-6 mb-3" />
                        <x-input name="phone" :label="__('Số điện thoại')" :value="$lead->phone" maxlength="30" pattern="[0-9+\-\s().]{6,30}" col="col-md-6 mb-3" />
                        <x-select name="lead_source_id" :label="__('Nguồn lead')" :options="$sources" :value="$lead->lead_source_id" :placeholder="__('— Chọn nguồn —')" col="col-md-6 mb-3" />
                        <x-input name="estimated_value" type="number" :label="__('Giá trị ước tính (₫)')" :value="$lead->estimated_value ? (int) $lead->estimated_value : null" min="0" step="1000" col="col-md-6 mb-3" />
                        <x-select name="campaign_ids[]" id="campaign_ids" :label="__('Chiến dịch marketing liên quan')" :options="$campaigns" :value="$lead->exists ? $lead->campaigns->pluck('id')->all() : []" multiple size="4" col="col-12 mb-3" />
                        <x-textarea name="notes" :label="__('Ghi chú')" :value="$lead->notes" maxlength="5000" col="col-12 mb-0" />
                    </div></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">{{ __('Trạng thái & chấm điểm') }}</div>
                    <div class="card-body">
                        @if ($lead->isConverted())
                            <input type="hidden" name="status" value="converted">
                            <p>{!! badge(App\Models\Lead::STATUSES, 'converted') !!}</p>
                        @else
                            <x-select name="status" :label="__('Trạng thái')" :options="collect(App\Models\Lead::STATUSES)->except('converted')->all()" :value="$lead->status" required />
                        @endif
                        @can('leads.assign')
                            <x-select name="assigned_to" :label="__('Nhân viên phụ trách')" :options="$users" :value="$lead->assigned_to" :placeholder="__('— Chưa phân công —')" />
                        @endcan
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="auto_score" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="auto_score" value="1" id="auto_score" @checked(old('auto_score', ! $lead->exists ? '1' : '0') === '1')>
                            <label class="form-check-label" for="auto_score">{{ __('Tự động chấm điểm') }}</label>
                        </div>
                        <x-input name="score" type="number" :label="__('Điểm thủ công (0–100)')" :value="$lead->score" min="0" max="100" :help="__('Dùng khi tắt tự động chấm điểm.')" />
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>{{ __('Lưu lead') }}</button>
                    <a href="{{ $lead->exists ? route('leads.show', $lead) : route('leads.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                </div>
            </div>
        </div>
    </form>
@endsection
