{{-- Cửa sổ mã QR liên hệ (dùng chung): nút [data-contact-qr] lấy dữ liệu từ máy chủ (đã kiểm tra quyền xem) --}}
<div class="modal fade" id="contactQrModal" tabindex="-1" aria-hidden="true" aria-labelledby="contactQrTitle"><div class="modal-dialog modal-dialog-centered" style="max-width: 440px"><div class="modal-content">
    <div class="modal-header">
        <div><h5 class="modal-title" id="contactQrTitle"><i class="bi bi-qr-code me-2"></i>{{ __('Mã QR liên hệ') }}</h5>
            <div class="small text-muted" data-qr-hint="tel">{{ __('Quét mã QR để gọi hoặc lưu số điện thoại vào danh bạ') }}</div></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button>
    </div>
    <div class="modal-body text-center">
        <div class="fw-semibold fs-5" data-qr-name></div>
        <div class="small text-muted text-uppercase mb-3" data-qr-company></div>
        <div class="btn-group btn-group-sm mb-3" role="group" aria-label="{{ __('Loại mã QR') }}">
            <input type="radio" class="btn-check" name="qrMode" id="qrModeTel" value="tel" checked><label class="btn btn-outline-primary" for="qrModeTel"><i class="bi bi-telephone me-1"></i>{{ __('Gọi điện') }}</label>
            <input type="radio" class="btn-check" name="qrMode" id="qrModeVcard" value="vcard"><label class="btn btn-outline-primary" for="qrModeVcard"><i class="bi bi-person-vcard me-1"></i>{{ __('Lưu danh bạ') }}</label>
        </div>
        <div class="pay-qr-stage mb-2" data-qr-stage><span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span>
            <img data-qr-img alt="{{ __('Mã QR liên hệ') }}" width="240" height="240"><span class="scan-line" aria-hidden="true"></span></div>
        <div class="rounded-3 bg-body-tertiary py-2 px-3 mb-2 fs-4 fw-bold text-primary"><i class="bi bi-telephone-fill me-2"></i><span data-qr-phone></span></div>
        <div class="small text-muted" data-qr-help="tel">{{ __('Mở camera trên điện thoại và quét mã QR để gọi số này') }}</div>
        <div class="small text-muted d-none" data-qr-help="vcard">{{ __('Quét mã để lưu tên, công ty, số điện thoại và email vào danh bạ') }}</div>
        <div class="text-danger small d-none" data-qr-error>{{ __('Không lấy được mã QR. Vui lòng thử lại.') }}</div>
    </div>
    <div class="modal-footer d-grid gap-2" style="grid-template-columns: 1fr 1fr">
        <a class="btn btn-primary" data-qr-call href="#"><i class="bi bi-telephone me-1"></i>{{ __('Gọi') }}</a>
        <button type="button" class="btn btn-light" data-qr-copy><i class="bi bi-clipboard me-1"></i><span>{{ __('Sao chép số') }}</span></button>
    </div>
</div></div></div>
<script>
(() => {
    const modalEl = document.getElementById('contactQrModal');
    if (!modalEl) return;
    let data = null;
    const $ = (s) => modalEl.querySelector(s);
    const render = () => {
        if (!data) return;
        const mode = modalEl.querySelector('input[name="qrMode"]:checked').value;
        const img = $('[data-qr-img]');
        img.src = mode === 'vcard' ? data.vcard_qr : data.tel_qr;
        img.style.animation = 'none'; void img.offsetWidth; img.style.animation = '';   // phát lại hiệu ứng hiện mã khi đổi chế độ
        modalEl.querySelectorAll('[data-qr-help]').forEach((el) => el.classList.toggle('d-none', el.dataset.qrHelp !== mode));
    };
    modalEl.querySelectorAll('input[name="qrMode"]').forEach((r) => r.addEventListener('change', render));
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-contact-qr]');
        if (!btn) return;
        e.preventDefault();
        data = null;
        $('[data-qr-error]').classList.add('d-none');
        $('[data-qr-img]').removeAttribute('src');
        ['name', 'company', 'phone'].forEach((k) => { $('[data-qr-' + k + ']').textContent = ''; });
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        try {
            const res = await fetch(btn.dataset.contactQr, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error(res.status);
            data = await res.json();
            $('[data-qr-name]').textContent = data.name || '';
            $('[data-qr-company]').textContent = data.company || '';
            $('[data-qr-phone]').textContent = data.phone;
            $('[data-qr-call]').href = 'tel:' + data.dial;
            document.getElementById('qrModeTel').checked = true;
            render();
        } catch (err) { $('[data-qr-error]').classList.remove('d-none'); }
    });
    $('[data-qr-copy]').addEventListener('click', async () => {
        if (!data) return;
        const icon = $('[data-qr-copy] i');
        try { await navigator.clipboard.writeText(data.phone); icon.className = 'bi bi-check2 me-1'; setTimeout(() => { icon.className = 'bi bi-clipboard me-1'; }, 1500); }
        catch (err) { window.prompt('', data.phone); }   // trình duyệt chặn clipboard (HTTP): hiện số để tự sao chép
    });
})();
</script>
