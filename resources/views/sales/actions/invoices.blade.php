@can('update', $doc)
    @if ($doc->status === 'draft')
        <form method="POST" action="{{ route('invoices.issue', $doc) }}" data-confirm="{{ __('Phát hành hóa đơn :number? Sau khi phát hành sẽ không thể sửa nội dung.', ['number' => $doc->number]) }}" data-confirm-button="{{ __('Phát hành') }}">
            @csrf<button class="btn btn-primary"><i class="bi bi-send me-1"></i>{{ __('Phát hành') }}</button>
        </form>
    @endif
    @if ($doc->status !== 'cancelled' && $doc->payments->isEmpty())
        <form method="POST" action="{{ route('invoices.cancel', $doc) }}" data-confirm="{{ __('Hủy hóa đơn :number?', ['number' => $doc->number]) }}" data-confirm-button="{{ __('Hủy hóa đơn') }}">
            @csrf<button class="btn btn-light text-danger"><i class="bi bi-x-circle me-1"></i>{{ __('Hủy hóa đơn') }}</button>
        </form>
    @endif
@endcan
