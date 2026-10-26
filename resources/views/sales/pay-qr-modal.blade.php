{{-- Cửa sổ thanh toán bằng VietQR (hóa đơn còn nợ). $payQr từ App\Support\VietQr::forInvoice(). --}}
<div class="modal fade" id="payQrModal" tabindex="-1" aria-hidden="true" aria-labelledby="payQrTitle"><div class="modal-dialog modal-dialog-centered" style="max-width: 460px"><div class="modal-content">
    <div class="modal-header">
        <div><h5 class="modal-title" id="payQrTitle"><i class="bi bi-qr-code-scan me-2"></i>{{ __('Thanh toán bằng QR') }}</h5>
            <div class="small text-muted">{{ __('Mở app ngân hàng → Quét QR → kiểm tra thông tin → xác nhận chuyển khoản') }}</div></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button>
    </div>
    <div class="modal-body text-center" data-pay-body>
        <div class="pay-qr-stage" data-pay-stage>
            <span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span>
            <img src="{{ $payQr['image'] }}" alt="{{ __('Mã VietQR thanh toán') }}" width="240" height="240">
            <span class="scan-line" aria-hidden="true"></span>
        </div>
        <div class="small text-muted mt-1">{{ __('Số tiền cần thanh toán') }}</div>
        <div class="pay-amount mb-3" data-countup="{{ $payQr['amount'] }}" data-format="vnd">{{ money($payQr['amount']) }}</div>
        <div class="text-start">
            <div class="pay-row"><span class="small text-muted">{{ __('Ngân hàng') }}</span><strong class="text-end">{{ $payQr['bank'] }}</strong></div>
            <div class="pay-row"><span class="small text-muted">{{ __('Số tài khoản') }}</span><span class="d-flex align-items-center gap-2"><strong class="font-monospace">{{ $payQr['account'] }}</strong>
                <button type="button" class="btn btn-sm btn-light btn-copy" data-copy-text="{{ $payQr['account'] }}" title="{{ __('Sao chép') }}" aria-label="{{ __('Sao chép số tài khoản') }}"><i class="bi bi-clipboard"></i></button></span></div>
            <div class="pay-row"><span class="small text-muted">{{ __('Chủ tài khoản') }}</span><strong class="text-end">{{ $payQr['holder'] }}</strong></div>
            <div class="pay-row"><span class="small text-muted">{{ __('Nội dung chuyển khoản') }}</span><span class="d-flex align-items-center gap-2"><strong class="font-monospace">{{ $payQr['memo'] }}</strong>
                <button type="button" class="btn btn-sm btn-light btn-copy" data-copy-text="{{ $payQr['memo'] }}" title="{{ __('Sao chép') }}" aria-label="{{ __('Sao chép nội dung chuyển khoản') }}"><i class="bi bi-clipboard"></i></button></span></div>
        </div>
    </div>
    <div class="modal-footer d-grid gap-2" style="grid-template-columns: 1fr 1fr">
        <a class="btn btn-light" href="{{ $payQr['image'] }}" download="VietQR-{{ $payQr['memo'] }}.png"><i class="bi bi-download me-1"></i>{{ __('Tải ảnh QR') }}</a>
        <button type="button" class="btn btn-primary btn-copy" data-copy-text="{{ $payQr['amount'] }}"><i class="bi bi-clipboard me-1"></i>{{ __('Sao chép số tiền') }}</button>
    </div>
</div></div></div>

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('payQrModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', () => {
        // Phát lại hiệu ứng mỗi lần mở: gỡ rồi gắn lại phần thân để animation CSS chạy từ đầu
        const body = modal.querySelector('[data-pay-body]');
        body.replaceWith(body.cloneNode(true));
    });
    modal.addEventListener('shown.bs.modal', () => {
        const amount = modal.querySelector('[data-countup]');
        if (amount && window.crmMotion) window.crmMotion.countUp(amount, 1100);
    });
    modal.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-copy-text]');
        if (!btn) return;
        const icon = btn.querySelector('i');
        try {
            await navigator.clipboard.writeText(btn.dataset.copyText);
            btn.classList.add('copied'); icon.classList.replace('bi-clipboard', 'bi-check2');
            setTimeout(() => { btn.classList.remove('copied'); icon.classList.replace('bi-check2', 'bi-clipboard'); }, 1500);
        } catch (err) { window.prompt('', btn.dataset.copyText); }   // trình duyệt chặn clipboard (HTTP): hiện để tự sao chép
    });
})();
</script>
@endpush
