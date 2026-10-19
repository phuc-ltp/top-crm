@extends('layouts.app')
@section('title', __('Sổ kho'))
@section('content')
    @php($Inv = App\Services\InventoryService::class)
    <x-page-header :title="__('Sổ kho')" :breadcrumbs="['Kho hàng' => route('inventory.index'), 'Sổ kho' => null]"
                   :subtitle="__('Nhật ký mọi thay đổi tồn kho — không sửa, không xóa. Sai sót được sửa bằng phiếu điều chỉnh.')" />
    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            @if (request('product'))<input type="hidden" name="product" value="{{ request('product') }}">@endif
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên hoặc SKU...') }}"></div>
            <select name="warehouse" class="form-select" aria-label="{{ __('Kho') }}"><option value="">{{ __('Mọi kho') }}</option>
                @foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected(request('warehouse') == $w->id)>{{ $w->name }}</option>@endforeach
            </select>
            <select name="type" class="form-select" aria-label="{{ __('Loại phát sinh') }}"><option value="">{{ __('Mọi phát sinh') }}</option>
                @foreach (App\Models\StockMovement::TYPES as $k => [$l])<option value="{{ $k }}" @selected(request('type') === $k)>{{ __($l) }}</option>@endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="{{ __('Từ ngày') }}" style="max-width:160px">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="{{ __('Đến ngày') }}" style="max-width:160px">
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
            @if (request()->hasAny(['q', 'warehouse', 'type', 'from', 'to', 'product']))<a href="{{ route('inventory.movements') }}" class="btn btn-link text-muted">{{ __('Xóa lọc') }}</a>@endif
        </form>
        @if ($movements->isEmpty())
            <x-empty-state icon="bi-journal-text" :title="__('Chưa có phát sinh')" />
        @else
            <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
                <thead><tr><th>{{ __('Thời gian') }}</th><th>{{ __('Chứng từ') }}</th><th>{{ __('Sản phẩm') }}</th><th>{{ __('Kho') }}</th><th>{{ __('Phát sinh') }}</th>
                    <th class="text-end">{{ __('Số lượng') }}</th><th class="text-end">{{ __('Tồn sau') }}</th>@if ($canCost)<th class="text-end">{{ __('Đơn giá') }}</th>@endif<th>{{ __('Người lập') }}</th></tr></thead>
                <tbody>
                @foreach ($movements as $m)
                    <tr>
                        <td class="small text-nowrap">{{ vn_date($m->created_at, true) }}</td>
                        <td class="small">@if ($m->document)<a href="{{ route('stock-documents.show', $m->document) }}">{{ $m->document->number }}</a>@else — @endif</td>
                        <td class="small"><span class="text-muted">{{ $m->product?->sku }}</span> {{ $m->product?->display_name }}</td>
                        <td class="small">{{ $m->warehouse?->name }}</td>
                        <td>{!! badge(App\Models\StockMovement::TYPES, $m->type) !!}</td>
                        <td class="text-end fw-semibold {{ (float) $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ (float) $m->quantity > 0 ? '+' : '' }}{{ $Inv::qty($m->quantity) }}</td>
                        <td class="text-end">{{ $Inv::qty($m->balance_after) }}</td>
                        @if ($canCost)<td class="text-end small">{{ $m->unit_cost !== null ? money($m->unit_cost) : '—' }}</td>@endif
                        <td class="small text-muted">{{ $m->creator?->name ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <x-pagination-bar :paginator="$movements" />
        @endif
    </div>
@endsection
