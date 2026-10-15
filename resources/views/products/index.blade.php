@extends('layouts.app')
@section('title', __('Sản phẩm & Dịch vụ'))
@section('content')
    <x-page-header :title="__('Sản phẩm & Dịch vụ')" :breadcrumbs="['Sản phẩm' => null]">
        @can('export', App\Models\Product::class)<a href="{{ route('products.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-download me-1"></i>{{ __('Xuất Excel') }}</a>@endcan
        @can('create', App\Models\Product::class)<a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm sản phẩm') }}</a>@endcan
    </x-page-header>
    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên hoặc SKU...') }}"></div>
            <select name="category" class="form-select"><option value="">{{ __('Mọi danh mục') }}</option>
                @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected(request('category') == $id)>{{ $name }}</option>@endforeach
            </select>
            <select name="status" class="form-select"><option value="">{{ __('Mọi trạng thái') }}</option>
                @foreach (App\Models\Product::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($s[0]) }}</option>@endforeach
            </select>
            <input type="number" name="min_price" value="{{ request('min_price') }}" class="form-control" placeholder="{{ __('Giá từ') }}" min="0" style="max-width:130px">
            <input type="number" name="max_price" value="{{ request('max_price') }}" class="form-control" placeholder="{{ __('Giá đến') }}" min="0" style="max-width:130px">
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
        </form>
        @if ($products->isEmpty())
            <x-empty-state icon="bi-box-seam" :title="__('Chưa có sản phẩm')" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th></th><th><x-sort-link column="sku" label="SKU" /></th><th><x-sort-link column="name" :label="__('Tên sản phẩm')" /></th><th>{{ __('Danh mục') }}</th>
                    <th class="text-end"><x-sort-link column="price" :label="__('Giá')" /></th><th>{{ __('Trạng thái') }}</th><th><x-sort-link column="created_at" :label="__('Ngày tạo')" /></th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                <tbody>
                @foreach ($products as $p)
                    <tr>
                        <td style="width:56px">@if ($p->image_url)<img src="{{ $p->image_url }}" alt="" class="rounded" style="width:40px;height:40px;object-fit:cover">@else<span class="avatar tone-orange" style="border-radius:8px"><i class="bi bi-box"></i></span>@endif</td>
                        <td class="small text-muted">{{ $p->sku }}</td>
                        <td><a href="{{ route('products.show', $p) }}" class="row-link">{{ $p->name }}</a></td>
                        <td class="small">{{ $p->category?->name ?? '—' }}</td>
                        <td class="text-end fw-semibold">{{ money($p->price) }}<div class="small text-muted fw-normal">/ {{ $p->unit }}</div></td>
                        <td>{!! badge(App\Models\Product::STATUSES, $p->status) !!}</td>
                        <td class="small text-muted">{{ vn_date($p->created_at) }}</td>
                        <td class="table-actions">
                            @can('update', $p)<a href="{{ route('products.edit', $p) }}" class="btn btn-light btn-icon btn-sm"><i class="bi bi-pencil"></i></a>@endcan
                            @can('delete', $p)<x-delete-button :action="route('products.destroy', $p)" />@endcan
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$products" />
        @endif
    </div>
@endsection
