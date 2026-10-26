@props(['type', 'id', 'phone' => null])
@if (filled($phone))
    <button type="button" class="btn btn-link p-0 ms-1 align-baseline text-body-secondary" data-contact-qr="{{ route('contact-qr', [$type, $id]) }}" title="{{ __('Mã QR liên hệ') }}" aria-label="{{ __('Mã QR liên hệ') }}"><i class="bi bi-qr-code"></i></button>
@endif
