@extends('layouts.app')
@section('title', $supplier->name)
@section('content')
    <x-page-header :title="$supplier->name" :breadcrumbs="['Nhà cung cấp' => route('suppliers.index'), $supplier->code => null]">
        @can('update', $supplier)<a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>@endcan
        @can('delete', $supplier)<x-delete-button :action="route('suppliers.destroy', $supplier)" class="btn btn-light text-danger" :label="__('Xóa')" />@endcan
    </x-page-header>
    <div class="row g-4">
        <div class="col-lg-5"><div class="card"><div class="card-body">
            <dl class="detail-list">
                <dt>{{ __('Mã') }}</dt><dd>{{ $supplier->code }}</dd>
                <dt>{{ __('Trạng thái') }}</dt><dd>{!! badge(App\Models\Supplier::STATUSES, $supplier->status) !!}</dd>
                <dt>{{ __('Người liên hệ') }}</dt><dd>{{ $supplier->contact_name ?? '—' }}</dd>
                <dt>{{ __('Điện thoại') }}</dt><dd>{{ $supplier->phone ?? '—' }}</dd>
                <dt>Email</dt><dd>{{ $supplier->email ?? '—' }}</dd>
                <dt>{{ __('Mã số thuế') }}</dt><dd>{{ $supplier->tax_code ?? '—' }}</dd>
                <dt>{{ __('Địa chỉ') }}</dt><dd>{{ $supplier->address ?? '—' }}</dd>
                <dt>Website</dt><dd>@if ($supplier->website)<a href="{{ $supplier->website }}" target="_blank" rel="noopener noreferrer">{{ $supplier->website }}</a>@else — @endif</dd>
                <dt>{{ __('Ghi chú') }}</dt><dd style="white-space: pre-line">{{ $supplier->notes ?: '—' }}</dd>
                <dt>{{ __('Ngày tạo') }}</dt><dd>{{ vn_date($supplier->created_at, true) }}</dd>
            </dl>
        </div></div></div>
        <div class="col-lg-7"><div class="card">
            <div class="card-header">{{ __('Sản phẩm từ nhà cung cấp này') }} <span class="badge badge-soft-secondary ms-1">{{ $products->count() }}</span></div>
            @if ($products->isEmpty())
                <x-empty-state icon="bi-box-seam" :title="__('Chưa có sản phẩm nào gắn với nhà cung cấp này')" />
            @else
                <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>SKU</th><th>{{ __('Tên sản phẩm') }}</th><th class="text-end">{{ __('Giá vốn') }}</th><th class="text-end">{{ __('Giá bán') }}</th></tr></thead>
                    <tbody>
                    @foreach ($products as $p)
                        <tr>
                            <td class="small text-muted">{{ $p->sku }}</td>
                            <td>@can('view', $p)<a href="{{ route('products.show', $p) }}">{{ $p->name }}</a>@else{{ $p->name }}@endcan</td>
                            <td class="text-end small">{{ $p->purchase_price !== null ? money($p->purchase_price) : '—' }}</td>
                            <td class="text-end small fw-semibold">{{ money($p->price) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div></div>
    </div>
@endsection
