@extends('layouts.app')
@section('title', __('Phiếu kho'))
@section('content')
    <x-page-header :title="__('Phiếu kho')" :breadcrumbs="['Kho hàng' => route('inventory.index'), 'Phiếu kho' => null]"
                   :subtitle="__('Phiếu đã ghi sổ không sửa / xóa được. Sai sót được sửa bằng phiếu điều chỉnh.')">
        @foreach (array_intersect_key(App\Models\StockDocument::TYPES, array_flip(App\Models\StockDocument::manualTypes())) as $k => [$label])
            @if ($k === 'adjustment' ? auth()->user()->can('inventory.adjust') : auth()->user()->can('inventory.create'))
                <a href="{{ route('stock-documents.create', ['type' => $k]) }}" class="btn {{ $k === 'receipt' ? 'btn-primary' : 'btn-light' }}"><i class="bi bi-plus-lg me-1"></i>{{ __($label) }}</a>
            @endif
        @endforeach
    </x-page-header>
    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Số phiếu, tham chiếu...') }}"></div>
            <select name="type" class="form-select" aria-label="{{ __('Loại phiếu') }}"><option value="">{{ __('Mọi loại phiếu') }}</option>
                @foreach (App\Models\StockDocument::TYPES as $k => [$l])<option value="{{ $k }}" @selected(request('type') === $k)>{{ __($l) }}</option>@endforeach
            </select>
            <select name="warehouse" class="form-select" aria-label="{{ __('Kho') }}"><option value="">{{ __('Mọi kho') }}</option>
                @foreach ($warehouses as $id => $name)<option value="{{ $id }}" @selected(request('warehouse') == $id)>{{ $name }}</option>@endforeach
            </select>
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>{{ __('Lọc') }}</button>
        </form>
        @if ($documents->isEmpty())
            <x-empty-state icon="bi-file-earmark-ruled" :title="__('Chưa có phiếu kho')" />
        @else
            <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
                <thead><tr><th>{{ __('Số phiếu') }}</th><th>{{ __('Loại') }}</th><th>{{ __('Ngày') }}</th><th>{{ __('Kho') }}</th><th>{{ __('Đối tác / tham chiếu') }}</th><th class="text-end">{{ __('Số dòng') }}</th><th>{{ __('Người lập') }}</th></tr></thead>
                <tbody>
                @foreach ($documents as $d)
                    <tr>
                        <td><a href="{{ route('stock-documents.show', $d) }}" class="row-link">{{ $d->number }}</a></td>
                        <td>{!! badge(App\Models\StockDocument::TYPES, $d->type) !!}</td>
                        <td class="small">{{ vn_date($d->document_date) }}</td>
                        <td class="small">{{ $d->warehouse?->name }}@if ($d->toWarehouse) <i class="bi bi-arrow-right"></i> {{ $d->toWarehouse->name }}@endif</td>
                        <td class="small">{{ $d->supplier?->name }}@if ($d->reference)<div class="text-muted">{{ $d->reference }}</div>@endif</td>
                        <td class="text-end">{{ $d->items_count }}</td>
                        <td class="small text-muted">{{ $d->creator?->name }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <x-pagination-bar :paginator="$documents" />
        @endif
    </div>
@endsection
