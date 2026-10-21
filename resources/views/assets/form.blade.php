@extends('layouts.app')
@section('title', $asset->exists ? __('Sửa tài sản') : __('Thêm tài sản'))
@section('content')
    <x-page-header :title="$asset->exists ? __('Sửa tài sản :c', ['c' => $asset->code]) : __('Thêm tài sản')"
                   :breadcrumbs="['Tài sản' => route('assets.index'), ($asset->exists ? 'Sửa' : 'Thêm mới') => null]" />

    <form method="POST" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}" class="needs-validation" novalidate>
        @csrf @if ($asset->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-7"><div class="card"><div class="card-body">
                <h6 class="mb-3">{{ __('Thông tin tài sản') }}</h6>
                <div class="row">
                    @if ($asset->exists)
                        <div class="col-md-4 mb-3"><label class="form-label">{{ __('Mã tài sản') }}</label><input class="form-control" value="{{ $asset->code }}" disabled></div>
                    @else
                        <x-input name="code" :label="__('Mã tài sản')" maxlength="40" :help="__('Để trống để tự sinh (TS-000001...).')" col="col-md-4 mb-3" />
                    @endif
                    <x-input name="name" :label="__('Tên tài sản')" :value="$asset->name" required maxlength="255" col="col-md-8 mb-3" />
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="asset_category_id" class="form-label">{{ __('Nhóm tài sản') }}</label>
                        <select name="asset_category_id" id="asset_category_id" class="form-select @error('asset_category_id') is-invalid @enderror">
                            <option value="">{{ __('— Chưa phân nhóm —') }}</option>
                            @foreach ($categories as $c)<option value="{{ $c->id }}" data-months="{{ $c->depreciation_months }}" @selected((int) old('asset_category_id', $asset->asset_category_id) === $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                        @error('asset_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-input name="serial_number" :label="__('Số serial')" :value="$asset->serial_number" maxlength="100" col="col-md-6 mb-3" />
                </div>
                <div class="row">
                    <x-input name="model" :label="__('Model / quy cách')" :value="$asset->model" maxlength="150" col="col-md-6 mb-3" />
                    <x-select name="supplier_id" :label="__('Nhà cung cấp')" :options="$suppliers" :value="$asset->supplier_id" :placeholder="__('— Không chọn —')" :translate="false" col="col-md-6 mb-3" />
                </div>
                <div class="row">
                    <x-select name="branch_id" :label="__('Chi nhánh')" :options="$branches" :value="$asset->branch_id" :placeholder="__('— Không chọn —')" :translate="false" col="col-md-6 mb-3" />
                    <x-input name="location" :label="__('Vị trí đặt')" :value="$asset->location" maxlength="255" :placeholder="__('VD: Tầng 2 — phòng kế toán')" col="col-md-6 mb-3" />
                </div>
                <x-textarea name="description" :label="__('Mô tả / cấu hình')" :value="$asset->description" maxlength="5000" col="mb-0" />
            </div></div></div>

            <div class="col-lg-5"><div class="card"><div class="card-body">
                <h6 class="mb-3">{{ __('Giá trị & khấu hao') }}</h6>
                <div class="row">
                    <x-input name="purchase_date" type="date" :label="__('Ngày mua')" :value="$asset->purchase_date?->toDateString()" :max="now()->toDateString()" col="col-md-6 mb-3" />
                    <x-input name="warranty_until" type="date" :label="__('Bảo hành đến')" :value="$asset->warranty_until?->toDateString()" col="col-md-6 mb-3" />
                </div>
                <x-input name="purchase_cost" type="number" :label="__('Nguyên giá')" :value="$asset->exists ? (float) $asset->purchase_cost : null" required min="0" step="1000" append="₫" />
                <div class="row">
                    <x-input name="salvage_value" type="number" :label="__('Giá trị thu hồi')" :value="(float) $asset->salvage_value" min="0" step="1000" append="₫" col="col-md-6 mb-3" />
                    <x-input name="depreciation_months" type="number" :label="__('Số tháng khấu hao')" :value="$asset->exists ? $asset->depreciation_months : null" min="0" max="600" col="col-md-6 mb-3"
                             :help="__('Trống = theo nhóm; 0 = không khấu hao.')" />
                </div>
                <div class="small text-muted">{{ __('Khấu hao đường thẳng theo tháng: (nguyên giá − giá trị thu hồi) / số tháng, tính từ ngày mua.') }}</div>
            </div></div>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <a href="{{ $asset->exists ? route('assets.show', $asset) : route('assets.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                <button class="btn btn-primary">{{ __('Lưu') }}</button>
            </div></div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.getElementById('asset_category_id')?.addEventListener('change', (e) => {
    const months = e.target.selectedOptions[0]?.dataset.months;
    const input = document.getElementById('depreciation_months');
    if (months !== undefined && input && input.value === '') input.value = months;
});
</script>
@endpush
