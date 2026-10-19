@extends('layouts.app')
@section('title', __('Bảng giá'))
@section('content')
    <x-page-header :title="__('Bảng giá')" :breadcrumbs="['Sản phẩm' => route('products.index'), 'Bảng giá' => null]"
                   :subtitle="__('Giá riêng cho từng nhóm khách hàng. Sản phẩm không có trong bảng giá dùng giá bán chuẩn.')">
        <a href="{{ route('price-lists.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo bảng giá') }}</a>
    </x-page-header>
    <div class="card">
        @if ($lists->isEmpty())
            <x-empty-state icon="bi-tags" :title="__('Chưa có bảng giá')" :message="__('Tạo bảng giá (VD: Đại lý, Khách VIP) rồi gắn vào khách hàng ở trang sửa khách hàng.')" />
        @else
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>{{ __('Tên bảng giá') }}</th><th class="text-end">{{ __('Số sản phẩm') }}</th><th class="text-end">{{ __('Khách hàng áp dụng') }}</th><th>{{ __('Trạng thái') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                <tbody>
                @foreach ($lists as $l)
                    <tr>
                        <td><a href="{{ route('price-lists.edit', $l) }}" class="row-link">{{ $l->name }}</a>@if ($l->description)<div class="small text-muted">{{ $l->description }}</div>@endif</td>
                        <td class="text-end">{{ $l->items_count }}</td>
                        <td class="text-end">{{ $l->customers_count }}</td>
                        <td>@if ($l->is_active)<span class="badge badge-soft-success">{{ __('Đang dùng') }}</span>@else<span class="badge badge-soft-secondary">{{ __('Đang tắt') }}</span>@endif</td>
                        <td class="table-actions">
                            <a href="{{ route('price-lists.edit', $l) }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></a>
                            <x-delete-button :action="route('price-lists.destroy', $l)" />
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
@endsection
