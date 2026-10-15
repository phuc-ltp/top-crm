@extends('layouts.app')
@section('title', $opportunity->exists ? __('Sửa cơ hội') : __('Tạo cơ hội'))
@section('content')
    <x-page-header :title="$opportunity->exists ? __('Sửa: :name', ['name' => $opportunity->title]) : __('Tạo cơ hội bán hàng')" :breadcrumbs="['Pipeline' => route('opportunities.index'), ($opportunity->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]" />
    <form method="POST" action="{{ $opportunity->exists ? route('opportunities.update', $opportunity) : route('opportunities.store') }}" class="needs-validation" novalidate>
        @csrf @if ($opportunity->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card"><div class="card-header">{{ __('Thông tin cơ hội') }}</div><div class="card-body"><div class="row">
                    <x-input name="title" :label="__('Tên cơ hội')" :value="$opportunity->title" required maxlength="255" col="col-12 mb-3" />
                    <x-select name="customer_id" :label="__('labels.customer')" :options="$customers" :value="$opportunity->customer_id" required :placeholder="__('— Chọn khách hàng —')" col="col-md-6 mb-3" />
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="contact_id">{{ __('Người liên hệ') }}</label>
                        <select name="contact_id" id="contact_id" class="form-select @error('contact_id') is-invalid @enderror">
                            <option value="">{{ __('— Không chọn —') }}</option>
                            @foreach ($contacts as $ct)
                                <option value="{{ $ct->id }}" data-customer="{{ $ct->customer_id }}" @selected(old('contact_id', $opportunity->contact_id) == $ct->id)>{{ $ct->full_name }}</option>
                            @endforeach
                        </select>
                        @error('contact_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-input name="value" type="number" :label="__('Giá trị (₫)')" :value="(int) $opportunity->value" required min="0" step="1000" col="col-md-6 mb-3" />
                    <x-input name="expected_close_date" type="date" :label="__('Ngày dự kiến chốt')" :value="optional($opportunity->expected_close_date)->format('Y-m-d')" col="col-md-6 mb-3" />
                    <x-textarea name="notes" :label="__('Ghi chú')" :value="$opportunity->notes" maxlength="5000" col="col-12 mb-0" />
                </div></div></div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-header">{{ __('Giai đoạn') }}</div><div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="pipeline_stage_id">{{ __('Giai đoạn') }} <span class="req">*</span></label>
                        <select name="pipeline_stage_id" id="pipeline_stage_id" class="form-select" required>
                            @foreach ($stages as $s)
                                <option value="{{ $s->id }}" data-prob="{{ $s->probability }}" data-lost="{{ $s->is_lost ? 1 : 0 }}" @selected(old('pipeline_stage_id', $opportunity->pipeline_stage_id) == $s->id)>{{ $s->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-input name="probability" type="number" :label="__('Xác suất thành công (%)')" :value="$opportunity->probability" required min="0" max="100" />
                    <div id="lostReasonBox"><x-input name="lost_reason" :label="__('Lý do thất bại')" :value="$opportunity->lost_reason" maxlength="500" /></div>
                    @can('opportunities.assign')
                        <x-select name="assigned_to" :label="__('Nhân viên phụ trách')" :options="$users" :value="$opportunity->assigned_to" :placeholder="__('— Chưa phân công —')" />
                    @endcan
                </div></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>{{ __('Lưu cơ hội') }}</button>
                    <a href="{{ $opportunity->exists ? route('opportunities.show', $opportunity) : route('opportunities.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                </div>
            </div>
        </div>
    </form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const customer = document.getElementById('customer_id');
    const contact = document.getElementById('contact_id');
    const stage = document.getElementById('pipeline_stage_id');
    const filterContacts = () => {
        Array.from(contact.options).forEach(o => { if (o.value) o.hidden = o.dataset.customer !== customer.value; });
        if (contact.selectedOptions[0]?.hidden) contact.value = '';
    };
    const syncStage = (setProb) => {
        const opt = stage.selectedOptions[0];
        if (setProb) document.getElementById('probability').value = opt.dataset.prob;
        document.getElementById('lostReasonBox').style.display = opt.dataset.lost === '1' ? '' : 'none';
    };
    customer.addEventListener('change', filterContacts);
    stage.addEventListener('change', () => syncStage(true));
    filterContacts(); syncStage(false);
});
</script>
@endpush
