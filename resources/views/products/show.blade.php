@extends('layouts.app')
@section('title', $product->name)
@section('content')
    <x-page-header :title="$product->display_name" :breadcrumbs="['Sản phẩm' => route('products.index'), $product->sku => null]">
        @if (! $product->parent_id)
            @can('create', App\Models\Product::class)<a href="{{ route('products.create', ['parent' => $product->id]) }}" class="btn btn-light"><i class="bi bi-diagram-2 me-1"></i>{{ __('Thêm biến thể') }}</a>@endcan
        @endif
        @can('update', $product)<a href="{{ route('products.edit', $product) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>@endcan
        @can('delete', $product)<x-delete-button :action="route('products.destroy', $product)" class="btn btn-light text-danger" :label="__('Xóa')" />@endcan
    </x-page-header>
    <div class="row g-4">
        <div class="col-lg-4"><div class="card"><div class="card-body text-center">
            @if ($product->image_url)<img src="{{ $product->image_url }}" alt="" class="img-fluid rounded mb-3">@else<div class="empty-icon mx-auto mb-3" style="width:120px;height:120px;border-radius:20px;display:grid;place-items:center;background:var(--crm-primary-light);color:var(--crm-primary);font-size:3rem"><i class="bi bi-box-seam"></i></div>@endif
            <div class="fs-3 fw-bold text-orange">{{ money($product->price) }}</div><div class="text-muted small">/ {{ $product->unit }}</div>
        </div></div></div>
        <div class="col-lg-8">
            <div class="row g-3 mb-4">
                <div class="col-md-6"><x-stat-card icon="bi-cart-check" tone="blue" :label="__('Số lượng đã bán (hóa đơn phát hành)')" :value="number_format((float) $sales->qty, 0, ',', '.')" /></div>
                <div class="col-md-6"><x-stat-card icon="bi-cash" tone="green" :label="__('Doanh số (hóa đơn phát hành)')" :value="money($sales->revenue)" /></div>
            </div>
            <div class="card"><div class="card-body">
                <dl class="detail-list">
                    <dt>ID</dt><dd>{{ $product->id }}</dd>
                    <dt>SKU</dt><dd>{{ $product->sku }}</dd>
                    @if ($product->parent)
                        <dt>{{ __('Biến thể của') }}</dt><dd><a href="{{ route('products.show', $product->parent) }}">{{ $product->parent->sku }} — {{ $product->parent->name }}</a></dd>
                    @endif
                    <dt>{{ __('Loại') }}</dt><dd>{!! badge(App\Models\Product::TYPES, $product->type) !!}</dd>
                    <dt>{{ __('Danh mục') }}</dt><dd>{{ $product->category?->name ?? '—' }}</dd>
                    <dt>{{ __('Nhà cung cấp') }}</dt><dd>@if ($product->supplier) @can('view', $product->supplier)<a href="{{ route('suppliers.show', $product->supplier) }}">{{ $product->supplier->name }}</a>@else {{ $product->supplier->name }} @endcan @else — @endif</dd>
                    @can('update', $product)
                        <dt>{{ __('Giá vốn') }}</dt><dd>{{ $product->purchase_price !== null ? money($product->purchase_price) : '—' }}</dd>
                        <dt title="{{ __('(Giá bán − giá vốn) ÷ giá bán × 100%') }}">{{ __('Lợi nhuận gộp') }}</dt><dd>{{ $product->margin_percent !== null ? number_format($product->margin_percent, 1, ',', '.').'%' : '—' }}</dd>
                    @endcan
                    <dt>{{ __('Trạng thái') }}</dt><dd>{!! badge(App\Models\Product::STATUSES, $product->status) !!}</dd>
                    <dt>{{ __('Ngày tạo') }}</dt><dd>{{ vn_date($product->created_at, true) }}</dd>
                    <dt>{{ __('Cập nhật') }}</dt><dd>{{ vn_date($product->updated_at, true) }}</dd>
                    <dt>{{ __('Mô tả') }}</dt><dd style="white-space: pre-line">{{ $product->description ?: '—' }}</dd>
                </dl>
            </div></div>
            @if ($product->variants->isNotEmpty())
                <div class="card mt-4">
                    <div class="card-header">{{ __('Biến thể') }} <span class="badge badge-soft-secondary ms-1">{{ $product->variants->count() }}</span></div>
                    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>SKU</th><th>{{ __('Biến thể') }}</th><th class="text-end">{{ __('Giá bán') }}</th><th>{{ __('Trạng thái') }}</th></tr></thead>
                        <tbody>
                        @foreach ($product->variants as $v)
                            <tr><td class="small text-muted">{{ $v->sku }}</td><td><a href="{{ route('products.show', $v) }}">{{ $v->variant_name }}</a></td>
                                <td class="text-end">{{ money($v->price) }}</td><td>{!! badge(App\Models\Product::STATUSES, $v->status) !!}</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </div>
            @endif
            @if ($history->isNotEmpty())
                <div class="card mt-4">
                    <div class="card-header">{{ __('Lịch sử thay đổi') }}</div>
                    <ul class="list-group list-group-flush small">
                        @foreach ($history as $log)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between gap-2"><span>{!! badge(App\Models\ActivityLog::ACTIONS, $log->action) !!} {{ $log->user?->name ?? __('Hệ thống') }}</span>
                                    <span class="text-muted text-nowrap">{{ vn_date($log->created_at, true) }}</span></div>
                                @foreach (($log->properties['new'] ?? []) as $field => $new)
                                    <div class="text-muted mt-1"><code>{{ $field }}</code>: {{ is_scalar($log->properties['old'][$field] ?? null) ? $log->properties['old'][$field] : '—' }} → {{ is_scalar($new) ? $new : '—' }}</div>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endsection
