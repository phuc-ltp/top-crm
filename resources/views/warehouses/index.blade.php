@extends('layouts.app')
@section('title', __('Kho'))
@section('content')
    <x-page-header :title="__('Danh mục kho')" :breadcrumbs="['Kho hàng' => route('inventory.index'), 'Kho' => null]"
                   :subtitle="__('Kho đã có phát sinh không xóa được — chuyển sang ngừng sử dụng.')" />
    @error('warehouse')<div class="alert alert-danger">{{ $message }}</div>@enderror
    @error('is_default')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <div class="row g-4">
        @foreach ($warehouses as $w)
            <form method="POST" action="{{ route('warehouses.update', $w) }}" id="wh-{{ $w->id }}" class="d-none">@csrf @method('PUT')</form>
        @endforeach
        <div class="col-lg-8"><div class="card">
            <div class="table-responsive"><table class="table mb-0 align-middle">
                <thead><tr><th>{{ __('Mã kho') }}</th><th>{{ __('Tên kho') }}</th><th>{{ __('Địa chỉ') }}</th><th class="text-center">{{ __('Mặc định') }}</th><th class="text-center">{{ __('Đang dùng') }}</th><th class="text-end">{{ __('SP có tồn') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                <tbody>
                @foreach ($warehouses as $w)
                    <tr>

                        <td><input form="wh-{{ $w->id }}" name="code" value="{{ $w->code }}" class="form-control form-control-sm" required maxlength="30" aria-label="{{ __('Mã kho') }}"></td>
                        <td><input form="wh-{{ $w->id }}" name="name" value="{{ $w->name }}" class="form-control form-control-sm" required maxlength="150" aria-label="{{ __('Tên kho') }}"></td>
                        <td><input form="wh-{{ $w->id }}" name="address" value="{{ $w->address }}" class="form-control form-control-sm" maxlength="500" aria-label="{{ __('Địa chỉ') }}"></td>
                        <td class="text-center"><input form="wh-{{ $w->id }}" type="checkbox" name="is_default" value="1" class="form-check-input" @checked($w->is_default) aria-label="{{ __('Mặc định') }}"></td>
                        <td class="text-center"><input type="hidden" form="wh-{{ $w->id }}" name="is_active" value="0"><input form="wh-{{ $w->id }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($w->is_active) aria-label="{{ __('Đang dùng') }}"></td>
                        <td class="text-end">{{ $w->products_in_stock }}</td>
                        <td class="table-actions">
                            <button form="wh-{{ $w->id }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Lưu') }}"><i class="bi bi-check2"></i></button>
                            @unless ($w->is_default)<x-delete-button :action="route('warehouses.destroy', $w)" />@endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div></div>
        <div class="col-lg-4"><div class="card">
            <div class="card-header">{{ __('Thêm kho') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('warehouses.store') }}" class="needs-validation" novalidate>
                    @csrf
                    <x-input name="code" :label="__('Mã kho')" required maxlength="30" placeholder="VD: KHO-HN" />
                    <x-input name="name" :label="__('Tên kho')" required maxlength="150" />
                    <x-input name="address" :label="__('Địa chỉ')" maxlength="500" />
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="new_default"><label class="form-check-label" for="new_default">{{ __('Đặt làm kho mặc định') }}</label></div>
                    <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm kho') }}</button>
                </form>
            </div>
        </div></div>
    </div>
@endsection
