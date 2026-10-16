@extends('layouts.app')
@section('title', __('Nhà cung cấp'))
@section('content')
    <x-page-header :title="__('Nhà cung cấp')" :breadcrumbs="['Nhà cung cấp' => null]" :subtitle="__('Đơn vị cung cấp hàng hóa, dịch vụ cho doanh nghiệp')">
        @can('export', App\Models\Supplier::class)
            <a href="{{ route('suppliers.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>{{ __('Xuất Excel') }}</a>
        @endcan
        @can('create', App\Models\Supplier::class)
            <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm nhà cung cấp') }}</a>
        @endcan
    </x-page-header>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên, mã, người liên hệ, điện thoại, MST...') }}">
            </div>
            <select name="status" class="form-select" aria-label="{{ __('Trạng thái') }}">
                <option value="">{{ __('Mọi trạng thái') }}</option>
                @foreach (App\Models\Supplier::STATUSES as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($l) }}</option>@endforeach
            </select>
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
            @if (request()->hasAny(['q', 'status']))<a href="{{ route('suppliers.index') }}" class="btn btn-link text-muted">{{ __('Xóa lọc') }}</a>@endif
        </form>
        @if ($suppliers->isEmpty())
            <x-empty-state icon="bi-truck" :title="__('Chưa có nhà cung cấp')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr>
                        <th><x-sort-link column="code" :label="__('Mã')" /></th><th><x-sort-link column="name" :label="__('Tên nhà cung cấp')" /></th>
                        <th>{{ __('Liên hệ') }}</th><th class="text-end"><x-sort-link column="products_count" :label="__('Số sản phẩm')" /></th>
                        <th>{{ __('Trạng thái') }}</th><th class="text-end">{{ __('Thao tác') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($suppliers as $s)
                        <tr>
                            <td class="small text-muted">{{ $s->code }}</td>
                            <td><a href="{{ route('suppliers.show', $s) }}" class="row-link">{{ $s->name }}</a>@if ($s->tax_code)<div class="small text-muted">{{ __('MST') }}: {{ $s->tax_code }}</div>@endif</td>
                            <td class="small">{{ $s->contact_name ?? '—' }}@if ($s->phone)<div>{{ $s->phone }}</div>@endif<div class="text-muted">{{ $s->email }}</div></td>
                            <td class="text-end">{{ $s->products_count }}</td>
                            <td>{!! badge(App\Models\Supplier::STATUSES, $s->status) !!}</td>
                            <td class="table-actions">
                                @can('update', $s)<a href="{{ route('suppliers.edit', $s) }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></a>@endcan
                                @can('delete', $s)<x-delete-button :action="route('suppliers.destroy', $s)" />@endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination-bar :paginator="$suppliers" />
        @endif
    </div>
@endsection
