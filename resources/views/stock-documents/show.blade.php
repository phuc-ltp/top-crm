@extends('layouts.app')
@section('title', $doc->number)
@section('content')
    @php($Inv = App\Services\InventoryService::class)
    <x-page-header :title="__(App\Models\StockDocument::TYPES[$doc->type][0]).' '.$doc->number" :breadcrumbs="['Phiếu kho' => route('stock-documents.index'), $doc->number => null]" />
    <div class="row g-4">
        <div class="col-lg-4"><div class="card"><div class="card-body">
            <dl class="detail-list">
                <dt>{{ __('Loại') }}</dt><dd>{!! badge(App\Models\StockDocument::TYPES, $doc->type) !!}</dd>
                <dt>{{ __('Ngày chứng từ') }}</dt><dd>{{ vn_date($doc->document_date) }}</dd>
                <dt>{{ $doc->type === 'transfer' ? __('Kho xuất') : __('Kho') }}</dt><dd>{{ $doc->warehouse?->name }}</dd>
                @if ($doc->toWarehouse)<dt>{{ __('Kho nhận') }}</dt><dd>{{ $doc->toWarehouse->name }}</dd>@endif
                @if ($doc->supplier)<dt>{{ __('Nhà cung cấp') }}</dt><dd>@can('view', $doc->supplier)<a href="{{ route('suppliers.show', $doc->supplier) }}">{{ $doc->supplier->name }}</a>@else {{ $doc->supplier->name }} @endcan</dd>@endif
                <dt>{{ __('Số tham chiếu') }}</dt><dd>{{ $doc->reference ?? '—' }}</dd>
                <dt>{{ __('Ghi chú') }}</dt><dd style="white-space: pre-line">{{ $doc->notes ?: '—' }}</dd>
                <dt>{{ __('Người lập') }}</dt><dd>{{ $doc->creator?->name ?? '—' }}</dd>
                <dt>{{ __('Ghi sổ lúc') }}</dt><dd>{{ vn_date($doc->created_at, true) }}</dd>
            </dl>
            <div class="small text-muted"><i class="bi bi-lock"></i> {{ __('Phiếu đã ghi sổ không sửa / xóa được. Sai sót được sửa bằng phiếu điều chỉnh.') }}</div>
        </div></div></div>
        <div class="col-lg-8"><div class="card">
            <div class="card-header">{{ __('Hàng hóa') }}</div>
            <div class="table-responsive"><table class="table mb-0 align-middle">
                <thead><tr><th>SKU</th><th>{{ __('Sản phẩm') }}</th>
                    @if ($doc->type === 'adjustment')<th class="text-end">{{ __('Số đếm thực tế') }}</th><th class="text-end">{{ __('Chênh lệch') }}</th>@else<th class="text-end">{{ __('Số lượng') }}</th>@endif
                    @if ($canCost)<th class="text-end">{{ __('Đơn giá') }}</th><th class="text-end">{{ __('Thành tiền') }}</th>@endif</tr></thead>
                <tbody>
                @foreach ($doc->items as $item)
                    <tr>
                        <td class="small text-muted">{{ $item->product?->sku }}</td>
                        <td>{{ $item->product?->display_name }} <span class="small text-muted">{{ $item->product?->unit }}</span></td>
                        @if ($doc->type === 'adjustment')
                            <td class="text-end">{{ $Inv::qty($item->counted_quantity) }}</td>
                            <td class="text-end fw-semibold {{ (float) $item->quantity < 0 ? 'text-danger' : ((float) $item->quantity > 0 ? 'text-success' : '') }}">{{ (float) $item->quantity > 0 ? '+' : '' }}{{ $Inv::qty($item->quantity) }}</td>
                        @else
                            <td class="text-end fw-semibold">{{ $Inv::qty($item->quantity) }}</td>
                        @endif
                        @if ($canCost)
                            <td class="text-end small">{{ $item->unit_cost !== null ? money($item->unit_cost) : '—' }}</td>
                            <td class="text-end small">{{ $item->unit_cost !== null ? money(abs((float) $item->quantity) * (float) $item->unit_cost) : '—' }}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div></div>
    </div>
@endsection
