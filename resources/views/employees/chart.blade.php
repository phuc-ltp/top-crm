@extends('layouts.app')
@section('title', __('Sơ đồ tổ chức'))
@section('content')
    <x-page-header :title="__('Sơ đồ tổ chức')" :breadcrumbs="['Nhân viên' => route('employees.index'), 'Sơ đồ tổ chức' => null]" :subtitle="__('Theo quan hệ quản lý trực tiếp — :count người', ['count' => $total])" />
    <div class="card"><div class="card-body">
        @if ($children->get(0, collect())->isEmpty())
            <x-empty-state icon="bi-diagram-3" :title="__('Chưa có dữ liệu')" />
        @else
            <ul class="org-tree">
                @include('employees._tree', ['nodes' => $children->get(0), 'children' => $children, 'depth' => 0])
            </ul>
            <div class="form-text mt-3">{{ __('Người chưa có quản lý trực tiếp nằm ở cấp cao nhất. Cập nhật quản lý trong hồ sơ nhân viên.') }}</div>
        @endif
    </div></div>
@endsection
