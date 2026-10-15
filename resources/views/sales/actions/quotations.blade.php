@if (in_array($doc->status, ['draft', 'sent', 'accepted']) && $doc->salesOrders->isEmpty())
    @can('create', App\Models\SalesOrder::class)
        <form method="POST" action="{{ route('quotations.convert', $doc) }}" data-confirm="Tạo đơn hàng từ báo giá {{ $doc->number }}?" data-confirm-button="Tạo đơn hàng">
            @csrf<button class="btn btn-primary"><i class="bi bi-bag-check me-1"></i>Tạo đơn hàng</button>
        </form>
    @endcan
@endif
