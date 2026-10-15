@extends('layouts.app')
@section('title', __('Quản lý tổ chức'))
@section('content')
    <x-page-header :title="__('Quản lý tổ chức')" :breadcrumbs="['Quản lý tổ chức' => null]" :subtitle="__('Khu vực quản trị nền tảng — các doanh nghiệp đang sử dụng hệ thống')">
        <a href="{{ route('platform.organizations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo tổ chức') }}</a>
    </x-page-header>

    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="input-group search-input">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên hoặc mã tổ chức...') }}">
            </div>
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
        </form>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr>
                <th>{{ __('Tổ chức') }}</th><th>{{ __('Chủ sở hữu') }}</th><th class="text-end">{{ __('Thành viên') }}</th>
                <th>{{ __('Trạng thái') }}</th><th>{{ __('Ngày tạo') }}</th><th class="text-end">{{ __('Thao tác') }}</th>
            </tr></thead>
            <tbody>
            @forelse ($organizations as $org)
                <tr>
                    <td><div class="fw-semibold">{{ $org->name }}</div><div class="small-xs text-muted">{{ $org->slug }}@if ($org->id === App\Models\Organization::DEFAULT_ID) · {{ __('Tổ chức mặc định') }}@endif</div></td>
                    <td class="small">{{ $org->owner?->name ?? '—' }}<div class="small-xs text-muted">{{ $org->owner?->email }}</div></td>
                    <td class="text-end">{{ $org->members_count }}</td>
                    <td>{!! badge(App\Models\Organization::STATUSES, $org->status) !!}</td>
                    <td class="small text-nowrap">{{ vn_date($org->created_at) }}</td>
                    <td class="table-actions">
                        <a href="{{ route('platform.organizations.edit', $org) }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="bi-buildings" :title="__('Chưa có tổ chức')" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <x-pagination-bar :paginator="$organizations" />
    </div>
@endsection
