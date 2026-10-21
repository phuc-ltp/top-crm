@extends('layouts.app')
@section('title', $license->exists ? __('Sửa giấy phép') : __('Thêm giấy phép'))
@section('content')
    <x-page-header :title="$license->exists ? __('Sửa giấy phép') : __('Thêm giấy phép')"
                   :breadcrumbs="['Bản quyền phần mềm' => route('licenses.index'), ($license->exists ? 'Sửa' : 'Thêm mới') => null]" />

    <form method="POST" action="{{ $license->exists ? route('licenses.update', $license) : route('licenses.store') }}" class="needs-validation" novalidate autocomplete="off">
        @csrf @if ($license->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-7"><div class="card"><div class="card-body">
                <x-input name="name" :label="__('Tên phần mềm')" :value="$license->name" required maxlength="255" :placeholder="__('VD: Microsoft 365 Business Standard')" />
                <div class="row">
                    <x-input name="vendor" :label="__('Nhà phát hành')" :value="$license->vendor" maxlength="150" col="col-md-6 mb-3" />
                    <x-input name="edition" :label="__('Phiên bản / gói')" :value="$license->edition" maxlength="150" col="col-md-6 mb-3" />
                </div>
                <div class="row">
                    <x-select name="license_type" :label="__('Loại giấy phép')" :options="App\Models\SoftwareLicense::TYPES" :value="$license->license_type" required col="col-md-6 mb-3" />
                    <x-input name="seats" type="number" :label="__('Số chỗ (người / máy)')" :value="$license->seats" required min="1" max="100000" col="col-md-6 mb-3" />
                </div>
                <div class="mb-3">
                    <label for="license_key" class="form-label">{{ __('Khóa bản quyền') }}</label>
                    <textarea name="license_key" id="license_key" rows="2" maxlength="5000" class="form-control font-monospace @error('license_key') is-invalid @enderror" spellcheck="false"
                              placeholder="{{ $license->exists && filled($license->license_key) ? __('Đã lưu khóa — để trống để giữ nguyên') : 'XXXXX-XXXXX-XXXXX-XXXXX' }}"></textarea>
                    @error('license_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('Được mã hóa khi lưu. Chỉ người quản lý bản quyền xem được, mỗi lần xem đều ghi nhật ký.') }}</div>
                    @if ($license->exists && filled($license->license_key))
                        <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_key" value="1" id="clear_key"><label class="form-check-label small" for="clear_key">{{ __('Xóa khóa đã lưu') }}</label></div>
                    @endif
                </div>
                <x-textarea name="notes" :label="__('Ghi chú')" :value="$license->notes" maxlength="5000" rows="2" col="mb-0" />
            </div></div></div>

            <div class="col-lg-5"><div class="card"><div class="card-body">
                <div class="row">
                    <x-input name="purchase_date" type="date" :label="__('Ngày mua')" :value="$license->purchase_date?->toDateString()" :max="now()->toDateString()" col="col-md-6 mb-3" />
                    <x-input name="expires_at" type="date" :label="__('Ngày hết hạn')" :value="$license->expires_at?->toDateString()" :help="__('Trống = không thời hạn.')" col="col-md-6 mb-3" />
                </div>
                <div class="row">
                    <x-input name="cost" type="number" :label="__('Chi phí')" :value="$license->exists ? (float) $license->cost : null" min="0" step="1000" append="₫" col="col-md-6 mb-3" />
                    <x-select name="billing_cycle" :label="__('Chu kỳ thanh toán')" :options="App\Models\SoftwareLicense::CYCLES" :value="$license->billing_cycle" required col="col-md-6 mb-3" />
                </div>
                <x-select name="supplier_id" :label="__('Nhà cung cấp')" :options="$suppliers" :value="$license->supplier_id" :placeholder="__('— Không chọn —')" :translate="false" />
                <x-select name="owner_id" :label="__('Người phụ trách gia hạn')" :options="$users" :value="$license->owner_id" :placeholder="__('— Người quản lý bản quyền —')" :translate="false" col="mb-0"
                          :help="__('Người nhận nhắc hết hạn; trống = mọi người quản lý bản quyền.')" />
            </div></div>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <a href="{{ $license->exists ? route('licenses.show', $license) : route('licenses.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                <button class="btn btn-primary">{{ __('Lưu') }}</button>
            </div></div>
        </div>
    </form>
@endsection
