{{-- Đơn hàng ↔ kho: kho xuất, hàng đang giữ, phiếu xuất giao hàng, hàng trả lại (bước 3.3) --}}
@php
    $Inv = App\Services\InventoryService::class;
    $qty = app(App\Services\OrderFulfillmentService::class)->quantities($doc);
    $demand = app(App\Services\OrderFulfillmentService::class)->demand($doc);
    $names = App\Models\Product::withTrashed()->whereIn('id', array_keys($demand + $qty['delivered']))->get()->keyBy('id');
    $returnable = collect($qty['delivered'])->map(fn ($d, $pid) => (float) $d - (float) ($qty['returned'][$pid] ?? 0))->filter(fn ($left) => $left > 0);
@endphp
<div class="card mb-4">
    <div class="card-header"><span><i class="bi bi-boxes me-1"></i>{{ __('Kho & giao hàng') }}</span><span class="small text-muted">{{ __('Kho xuất') }}: <strong>{{ $doc->warehouse?->name ?? '—' }}</strong></span></div>
    <div class="card-body">
        @error('status')<div class="alert alert-danger small">{{ $message }}</div>@enderror
        @if ($doc->reservations->isNotEmpty())
            <div class="small fw-semibold mb-1">{{ __('Đang giữ trong kho') }}</div>
            <ul class="small mb-3">@foreach ($doc->reservations as $res)<li>{{ $res->product?->display_name }}: {{ $Inv::qty($res->quantity) }} {{ $res->product?->unit }}</li>@endforeach</ul>
        @elseif (in_array($doc->status, ['pending']))
            <p class="small text-muted mb-3">{{ __('Hàng sẽ được giữ khi đơn hàng được duyệt.') }}</p>
        @endif

        @if ($doc->stockDocuments->isNotEmpty())
            <div class="small fw-semibold mb-1">{{ __('Phiếu kho') }}</div>
            <ul class="small mb-3">
                @foreach ($doc->stockDocuments as $sd)
                    <li>{!! badge(App\Models\StockDocument::TYPES, $sd->type) !!} @can('inventory.view')<a href="{{ route('stock-documents.show', $sd) }}">{{ $sd->number }}</a>@else {{ $sd->number }} @endcan · {{ vn_date($sd->document_date) }}@if ($sd->type === 'return' && $sd->notes) — {{ $sd->notes }}@endif</li>
                @endforeach
            </ul>
        @elseif ($doc->status === 'completed')
            <p class="small text-muted mb-3">{{ __('Đơn hàng không có sản phẩm theo dõi tồn kho.') }}</p>
        @endif

        @if ($doc->status === 'completed' && $returnable->isNotEmpty())
            @can('inventory.create')
                <details>
                    <summary class="small fw-semibold text-orange">{{ __('Trả hàng (nhập lại kho)') }}</summary>
                    <form method="POST" action="{{ route('orders.return', $doc) }}" class="mt-2 needs-validation" novalidate>
                        @csrf
                        <table class="table table-sm align-middle">
                            <thead><tr><th>{{ __('Sản phẩm') }}</th><th class="text-end">{{ __('Có thể trả') }}</th><th style="width:140px">{{ __('Số lượng trả') }}</th></tr></thead>
                            <tbody>
                            @foreach ($returnable as $pid => $left)
                                <tr><td class="small">{{ $names[$pid]?->display_name }}</td><td class="text-end small">{{ $Inv::qty($left) }}</td>
                                    <td><input type="hidden" name="items[{{ $loop->index }}][product_id]" value="{{ $pid }}">
                                        <input type="number" name="items[{{ $loop->index }}][quantity]" class="form-control form-control-sm" min="0" max="{{ $left }}" step="0.001" aria-label="{{ __('Số lượng trả') }}"></td></tr>
                            @endforeach
                            </tbody>
                        </table>
                        <x-input name="reason" :label="__('Lý do trả hàng')" required maxlength="500" />
                        <button class="btn btn-soft-primary btn-sm"><i class="bi bi-arrow-return-left me-1"></i>{{ __('Nhập lại kho') }}</button>
                    </form>
                </details>
            @endcan
        @endif
    </div>
</div>
