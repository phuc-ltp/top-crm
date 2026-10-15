@if ($doc->status === 'pending')
    @can('approve', App\Models\SalesOrder::class)
        <form method="POST" action="{{ route('orders.approve', $doc) }}" data-confirm="Duyệt đơn hàng {{ $doc->number }}?" data-confirm-button="Duyệt">
            @csrf<button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Duyệt đơn</button>
        </form>
    @endcan
@endif
@if (in_array($doc->status, ['confirmed', 'processing', 'completed']) && $doc->invoices->where('status', '!=', 'cancelled')->isEmpty())
    @can('create', App\Models\Invoice::class)
        <form method="POST" action="{{ route('orders.invoice', $doc) }}" data-confirm="Lập hóa đơn cho đơn hàng {{ $doc->number }}?" data-confirm-button="Lập hóa đơn">
            @csrf<button class="btn btn-primary"><i class="bi bi-receipt me-1"></i>Lập hóa đơn</button>
        </form>
    @endcan
@endif
