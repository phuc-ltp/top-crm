@extends('layouts.app')
@section('title', __('Tồn kho'))
@section('content')
    @php($Inv = App\Services\InventoryService::class)
    <x-page-header :title="__('Tồn kho')" :breadcrumbs="['Kho hàng' => null, 'Tồn kho' => null]"
                   :subtitle="__('Có thể dùng = tồn thực − đang giữ cho đơn hàng. Dịch vụ không theo dõi tồn kho.')">
        @can('inventory.create')
            <a href="{{ route('stock-documents.create', ['type' => 'receipt']) }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-down me-1"></i>{{ __('Nhập kho') }}</a>
            <a href="{{ route('stock-documents.create', ['type' => 'issue']) }}" class="btn btn-light"><i class="bi bi-box-arrow-up me-1"></i>{{ __('Xuất kho') }}</a>
        @endcan
        @can('inventory.adjust')
            <a href="{{ route('stock-documents.create', ['type' => 'adjustment']) }}" class="btn btn-light"><i class="bi bi-clipboard-check me-1"></i>{{ __('Kiểm kê') }}</a>
        @endcan
    </x-page-header>

    @if ($canCost)
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4"><x-stat-card icon="bi-cash-stack" tone="green" :label="__('Giá trị tồn kho (theo bộ lọc)')" :value="money($totalValue)" /></div>
        </div>
    @endif

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên hoặc SKU...') }}"></div>
            <select name="warehouse" class="form-select" aria-label="{{ __('Kho') }}"><option value="">{{ __('Mọi kho') }}</option>
                @foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected(request('warehouse') == $w->id)>{{ $w->name }}{{ $w->is_active ? '' : ' ('.__('ngừng').')' }}</option>@endforeach
            </select>
            <select name="stock" class="form-select" aria-label="{{ __('Tình trạng') }}"><option value="">{{ __('Mọi tình trạng') }}</option>
                <option value="in" @selected(request('stock') === 'in')>{{ __('Còn hàng') }}</option>
                <option value="low" @selected(request('stock') === 'low')>{{ __('Tồn thấp') }}</option>
                <option value="out" @selected(request('stock') === 'out')>{{ __('Hết hàng') }}</option>
            </select>
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
        </form>
        @if ($products->isEmpty())
            <x-empty-state icon="bi-boxes" :title="__('Không có sản phẩm phù hợp')" />
        @else
            <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
                <thead><tr>
                    <th>SKU</th><th>{{ __('Sản phẩm') }}</th><th class="text-end">{{ __('Tồn thực') }}</th><th class="text-end">{{ __('Đang giữ') }}</th>
                    <th class="text-end">{{ __('Có thể dùng') }}</th><th class="text-end">{{ __('Mức tối thiểu') }}</th>
                    @if ($canCost)<th class="text-end">{{ __('Giá vốn BQ') }}</th><th class="text-end">{{ __('Giá trị') }}</th>@endif
                    <th></th>
                </tr></thead>
                <tbody>
                @foreach ($products as $p)
                    @php($available = (float) $p->stock_qty - (float) $p->stock_reserved)
                    <tr>
                        <td class="small text-muted">{{ $p->sku }}</td>
                        <td><a href="{{ route('products.show', $p) }}" class="row-link">{{ $p->display_name }}</a>
                            @if ($available <= 0)<span class="badge badge-soft-danger ms-1">{{ __('Hết hàng') }}</span>
                            @elseif ($p->reorder_level !== null && $available <= (float) $p->reorder_level)<span class="badge badge-soft-warning ms-1">{{ __('Tồn thấp') }}</span>@endif</td>
                        <td class="text-end">{{ $Inv::qty($p->stock_qty) }} <span class="small text-muted">{{ $p->unit }}</span></td>
                        <td class="text-end text-muted">{{ $Inv::qty($p->stock_reserved) }}</td>
                        <td class="text-end fw-semibold {{ $available <= 0 ? 'text-danger' : '' }}">{{ $Inv::qty($available) }}</td>
                        <td class="text-end small text-muted">{{ $p->reorder_level !== null ? $Inv::qty($p->reorder_level) : '—' }}</td>
                        @if ($canCost)
                            <td class="text-end small">{{ $p->average_cost !== null ? money($p->average_cost) : '—' }}</td>
                            <td class="text-end small fw-semibold">{{ money((float) $p->stock_qty * (float) ($p->average_cost ?? 0)) }}</td>
                        @endif
                        <td class="text-end"><a href="{{ route('inventory.movements', ['product' => $p->id]) }}" class="btn btn-light btn-sm" title="{{ __('Sổ kho') }}"><i class="bi bi-journal-text"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <x-pagination-bar :paginator="$products" />
        @endif
    </div>
@endsection
