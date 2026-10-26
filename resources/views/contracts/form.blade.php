@extends('layouts.app')
@section('title', $contract->exists ? __('Sửa hợp đồng') : __('Thêm hợp đồng'))
@section('content')
    <x-page-header :title="$contract->exists ? __('Sửa hợp đồng :c', ['c' => $contract->code]) : __('Thêm hợp đồng')"
                   :breadcrumbs="['Hợp đồng' => route('contracts.index'), ($contract->exists ? 'Sửa' : 'Thêm mới') => null]" />

    <form method="POST" action="{{ $contract->exists ? route('contracts.update', $contract) : route('contracts.store') }}" class="needs-validation" novalidate>
        @csrf @if ($contract->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-8"><div class="card"><div class="card-body">
                <x-input name="title" :label="__('Tên hợp đồng')" :value="$contract->title" required maxlength="255" :placeholder="__('VD: Hợp đồng cung cấp phần mềm CRM năm 2026')" />
                <div class="row">
                    <x-select name="customer_id" :label="__('Khách hàng')" :options="$customers" :value="$contract->customer_id" required :translate="false" :placeholder="__('— Chọn khách hàng —')" col="col-md-6 mb-3" />
                    <div class="col-md-6 mb-3">
                        <label for="contact_id" class="form-label">{{ __('Người đại diện / liên hệ') }}</label>
                        <select name="contact_id" id="contact_id" class="form-select @error('contact_id') is-invalid @enderror">
                            <option value="">{{ __('— Không chọn —') }}</option>
                            @foreach ($contacts as $ct)<option value="{{ $ct->id }}" data-customer="{{ $ct->customer_id }}" @selected((int) old('contact_id', $contract->contact_id) === $ct->id)>{{ $ct->full_name }}</option>@endforeach
                        </select>
                        @error('contact_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <x-select name="type" :label="__('Loại hợp đồng')" :options="App\Models\Contract::TYPES" :value="$contract->type" required col="col-md-4 mb-3" />
                    <x-input name="value" type="number" :label="__('Giá trị hợp đồng')" :value="$contract->exists || $contract->value ? (float) $contract->value : null" required min="0" step="1000" append="₫" col="col-md-4 mb-3" />
                    <x-select name="opportunity_id" :label="__('Cơ hội liên quan')" :options="$opportunities" :value="$contract->opportunity_id" :translate="false" :placeholder="__('— Không chọn —')" col="col-md-4 mb-3" />
                </div>
                <x-textarea name="terms" :label="__('Điều khoản chính / ghi chú')" :value="$contract->terms" rows="6" maxlength="20000" col="mb-0"
                            :help="__('Bản hợp đồng đã ký (PDF, ảnh scan) đính kèm ở trang chi tiết sau khi lưu.')" />
            </div></div></div>

            <div class="col-lg-4"><div class="card"><div class="card-body">
                <x-select name="status" :label="__('Trạng thái')" :options="collect(App\Models\Contract::STATUSES)->except('terminated')->all()" :value="$contract->status" required />
                <x-input name="signed_date" type="date" :label="__('Ngày ký')" :value="$contract->signed_date?->toDateString()" :max="now()->toDateString()" :help="__('Bắt buộc khi trạng thái là Đã ký.')" />
                <div class="row">
                    <x-input name="start_date" type="date" :label="__('Hiệu lực từ')" :value="$contract->start_date?->toDateString()" col="col-6 mb-3" />
                    <x-input name="end_date" type="date" :label="__('Ngày hết hạn')" :value="$contract->end_date?->toDateString()" col="col-6 mb-3" />
                </div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="auto_renew" value="1" id="auto_renew" @checked(old('auto_renew', $contract->auto_renew))>
                    <label class="form-check-label" for="auto_renew">{{ __('Tự động gia hạn (ghi nhận điều khoản; vẫn nhắc trước khi hết hạn)') }}</label></div>
                <x-select name="assigned_to" :label="__('Người phụ trách')" :options="$users" :value="$contract->assigned_to" :translate="false" :placeholder="__('— Chưa phân công —')" col="mb-0" />
            </div></div>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <a href="{{ $contract->exists ? route('contracts.show', $contract) : route('contracts.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                <button class="btn btn-primary">{{ __('Lưu') }}</button>
            </div></div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(() => {
    const customer = document.getElementById('customer_id');
    const contact = document.getElementById('contact_id');
    const filter = () => {
        contact.querySelectorAll('option[data-customer]').forEach((o) => { o.hidden = customer.value !== '' && o.dataset.customer !== customer.value; });
        if (contact.selectedOptions[0]?.hidden) contact.value = '';
    };
    customer?.addEventListener('change', filter);
    if (customer && contact) filter();
})();
</script>
@endpush
