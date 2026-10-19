@if (in_array($doc->status, ['draft', 'sent', 'accepted']) && $doc->salesOrders->isEmpty() && ! in_array($doc->approval_status, ['pending', 'rejected'], true))
    @can('create', App\Models\SalesOrder::class)
        <form method="POST" action="{{ route('quotations.convert', $doc) }}" data-confirm="{{ __('Tạo đơn hàng từ báo giá :number?', ['number' => $doc->number]) }}" data-confirm-button="{{ __('Tạo đơn hàng') }}">
            @csrf<button class="btn btn-primary"><i class="bi bi-bag-check me-1"></i>{{ __('Tạo đơn hàng') }}</button>
        </form>
    @endcan
@endif
