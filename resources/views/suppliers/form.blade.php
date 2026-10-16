@extends('layouts.app')
@section('title', $supplier->exists ? __('Sửa nhà cung cấp') : __('Thêm nhà cung cấp'))
@section('content')
    <x-page-header :title="$supplier->exists ? __('Sửa: :name', ['name' => $supplier->name]) : __('Thêm nhà cung cấp')"
                   :breadcrumbs="['Nhà cung cấp' => route('suppliers.index'), ($supplier->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]" />
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="needs-validation" novalidate>
        @csrf @if ($supplier->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8"><div class="card"><div class="card-body"><div class="row">
                <x-input name="name" :label="__('Tên nhà cung cấp')" :value="$supplier->name" required maxlength="255" col="col-md-8 mb-3" />
                <x-select name="status" :label="__('Trạng thái')" :options="App\Models\Supplier::STATUSES" :value="$supplier->status" required col="col-md-4 mb-3" />
                <x-input name="contact_name" :label="__('Người liên hệ')" :value="$supplier->contact_name" maxlength="255" col="col-md-6 mb-3" />
                <x-input name="tax_code" :label="__('Mã số thuế')" :value="$supplier->tax_code" maxlength="50" col="col-md-6 mb-3" />
                <x-input name="phone" :label="__('Điện thoại')" :value="$supplier->phone" maxlength="30" col="col-md-6 mb-3" />
                <x-input name="email" type="email" label="Email" :value="$supplier->email" maxlength="255" col="col-md-6 mb-3" />
                <x-input name="address" :label="__('Địa chỉ')" :value="$supplier->address" maxlength="500" col="col-md-8 mb-3" />
                <x-input name="website" type="url" label="Website" :value="$supplier->website" maxlength="255" placeholder="https://" col="col-md-4 mb-3" />
                <x-textarea name="notes" :label="__('Ghi chú')" :value="$supplier->notes" rows="4" maxlength="5000" col="col-12 mb-0" />
            </div></div></div></div>
            <div class="col-lg-4">
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>{{ __('Lưu') }}</button>
                    <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                </div>
            </div>
        </div>
    </form>
@endsection
