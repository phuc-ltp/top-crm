@extends('layouts.app')
@section('title', __('Người liên hệ'))
@section('content')
    <x-page-header :title="__('Người liên hệ')" :breadcrumbs="['Người liên hệ' => null]" :subtitle="__('Đầu mối liên hệ của khách hàng và doanh nghiệp')">
        @can('export', App\Models\Contact::class)
            <a href="{{ route('contacts.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>{{ __('Xuất Excel') }}</a>
        @endcan
        @can('create', App\Models\Contact::class)
            <a href="{{ route('contacts.import') }}" class="btn btn-light"><i class="bi bi-upload me-1"></i>{{ __('Nhập CSV / Excel') }}</a>
            <a href="{{ route('contacts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm người liên hệ') }}</a>
        @endcan
    </x-page-header>

    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="input-group search-input">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên, email, điện thoại, công ty...') }}">
            </div>
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
            @if (request()->filled('q'))<a href="{{ route('contacts.index') }}" class="btn btn-link text-muted">{{ __('Xóa lọc') }}</a>@endif
        </form>
        @if ($contacts->isEmpty())
            <x-empty-state icon="bi-person-lines-fill" :title="__('Chưa có người liên hệ')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th><x-sort-link column="full_name" :label="__('Họ tên')" /></th><th>{{ __('Chức vụ') }}</th><th>{{ __('Liên hệ') }}</th><th>{{ __('labels.customer') }}</th><th><x-sort-link column="created_at" :label="__('Ngày tạo')" /></th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                    <tbody>
                    @foreach ($contacts as $c)
                        <tr>
                            <td><a href="{{ route('contacts.show', $c) }}" class="row-link">{{ $c->full_name }}</a><div class="small text-muted">{{ $c->company }}</div></td>
                            <td class="small">{{ $c->position ?? '—' }}</td>
                            <td class="small">@if ($c->phone)<div>{{ $c->phone }}</div>@endif<div class="text-muted">{{ $c->email }}</div></td>
                            <td class="small">@if ($c->customer)<a href="{{ route('customers.show', $c->customer) }}">{{ $c->customer->name }}</a>@else — @endif</td>
                            <td class="small text-muted">{{ vn_date($c->created_at) }}</td>
                            <td class="table-actions">
                                @can('update', $c)<a href="{{ route('contacts.edit', $c) }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></a>@endcan
                                @can('delete', $c)<x-delete-button :action="route('contacts.destroy', $c)" />@endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination-bar :paginator="$contacts" />
        @endif
    </div>
@endsection
