@can('update', $doc)
    @if ($doc->status === 'draft')
        <form method="POST" action="{{ route('invoices.issue', $doc) }}" data-confirm="Phát hành hóa đơn {{ $doc->number }}? Sau khi phát hành sẽ không thể sửa nội dung." data-confirm-button="Phát hành">
            @csrf<button class="btn btn-primary"><i class="bi bi-send me-1"></i>Phát hành</button>
        </form>
    @endif
    @if ($doc->status !== 'cancelled' && $doc->payments->isEmpty())
        <form method="POST" action="{{ route('invoices.cancel', $doc) }}" data-confirm="Hủy hóa đơn {{ $doc->number }}?" data-confirm-button="Hủy hóa đơn">
            @csrf<button class="btn btn-light text-danger"><i class="bi bi-x-circle me-1"></i>Hủy hóa đơn</button>
        </form>
    @endif
@endcan
