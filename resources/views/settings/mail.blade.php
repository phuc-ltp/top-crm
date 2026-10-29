@extends('layouts.app')
@section('title', __('Email gửi đi'))
@section('content')
    <x-page-header :title="__('Email gửi đi')" :breadcrumbs="['Hệ thống' => route('settings.edit'), 'Email gửi đi' => null]"
                   :subtitle="__('Máy chủ email dùng để gửi quên mật khẩu, thông báo, nhắc việc, phiếu lương… cho toàn hệ thống. Chỉ quản trị nền tảng chỉnh được.')" />

    <div class="alert {{ $active ? 'alert-success' : 'alert-warning' }} py-2 d-flex align-items-center gap-2">
        <i class="bi {{ $active ? 'bi-check-circle' : 'bi-exclamation-triangle' }}"></i>
        @if ($active)
            {{ __('Đang gửi email thật qua :h.', ['h' => $s['mail_host']]) }}
        @else
            {{ __('Email chưa được gửi đi thật (máy chủ đang dùng chế độ “:m” — thư chỉ ghi vào nhật ký). Nhập cấu hình SMTP bên dưới để bật.', ['m' => $envMailer]) }}
        @endif
    </div>

    <div class="row g-3">
        <div class="col-lg-7"><div class="card"><div class="card-body">
            <form method="POST" action="{{ route('settings.mail.update') }}" class="needs-validation" novalidate autocomplete="off" id="mailForm">@csrf @method('PUT')
                <div class="mb-3">
                    <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="mail_mailer" id="mmEnv" value="env" @checked(old('mail_mailer', $s['mail_mailer']) !== 'smtp')>
                        <label class="form-check-label" for="mmEnv">{{ __('Theo cấu hình máy chủ (.env)') }}</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="mail_mailer" id="mmSmtp" value="smtp" @checked(old('mail_mailer', $s['mail_mailer']) === 'smtp')>
                        <label class="form-check-label" for="mmSmtp">{{ __('Dùng SMTP nhập tại đây') }}</label></div>
                </div>
                <fieldset id="smtpFields">
                    <div class="mb-3">
                        <label class="form-label" for="preset">{{ __('Nhà cung cấp') }}</label>
                        <select id="preset" class="form-select">
                            <option value="">{{ __('— Chọn để điền sẵn máy chủ / cổng —') }}</option>
                            @foreach ($presets as $k => [$label, $host, $port, $enc, $note])<option value="{{ $k }}" data-host="{{ $host }}" data-port="{{ $port }}" data-enc="{{ $enc }}" data-note="{{ $note }}">{{ $label }}</option>@endforeach
                        </select>
                        <div class="form-text d-none" id="presetNote"></div>
                    </div>
                    <div class="row">
                        <x-input name="mail_host" :label="__('Máy chủ SMTP')" :value="$s['mail_host']" maxlength="190" placeholder="smtp.gmail.com" col="col-md-6 mb-3" />
                        <x-input name="mail_port" type="number" :label="__('Cổng')" :value="$s['mail_port'] ?: 587" min="1" max="65535" col="col-6 col-md-3 mb-3" />
                        <x-select name="mail_encryption" :label="__('Mã hóa')" :options="['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => __('Không')]" :value="$s['mail_encryption'] ?: 'tls'" :translate="false" col="col-6 col-md-3 mb-3" />
                    </div>
                    <div class="row">
                        <x-input name="mail_username" :label="__('Tên đăng nhập SMTP')" :value="$s['mail_username']" maxlength="190" :placeholder="__('Thường là địa chỉ email')" col="col-md-6 mb-3" />
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="mail_password">{{ __('Mật khẩu / mật khẩu ứng dụng') }}</label>
                            <input type="password" name="mail_password" id="mail_password" class="form-control @error('mail_password') is-invalid @enderror" maxlength="255" autocomplete="new-password"
                                   placeholder="{{ $hasPassword ? __('Đã lưu — để trống để giữ nguyên') : '' }}">
                            @if ($hasPassword)<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_password" value="1" id="clear_password"><label class="form-check-label small" for="clear_password">{{ __('Xóa mật khẩu đã lưu') }}</label></div>@endif
                            <div class="form-text">{{ __('Được mã hóa khi lưu, không hiển thị lại.') }}</div>
                        </div>
                    </div>
                    <div class="row">
                        <x-input name="mail_from_address" type="email" :label="__('Email người gửi')" :value="$s['mail_from_address']" maxlength="190" placeholder="no-reply@congty.vn" col="col-md-6 mb-3" />
                        <x-input name="mail_from_name" :label="__('Tên người gửi')" :value="$s['mail_from_name']" maxlength="150" :placeholder="__('VD: TOP CRM')" col="col-md-6 mb-3" />
                    </div>
                </fieldset>
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Lưu cấu hình') }}</button>
            </form>
        </div></div></div>

        <div class="col-lg-5">
            <div class="card mb-3"><div class="card-body">
                <h6 class="mb-3">{{ __('Gửi email thử') }}</h6>
                <form method="POST" action="{{ route('settings.mail.test') }}" class="needs-validation" novalidate>@csrf
                    <x-input name="to" type="email" :label="__('Gửi tới')" :value="auth()->user()->email" required maxlength="190" />
                    <button class="btn btn-light w-100" @disabled(! $active)><i class="bi bi-send me-1"></i>{{ __('Gửi email thử') }}</button>
                    @unless ($active)<div class="small text-muted mt-2">{{ __('Lưu cấu hình SMTP trước, sau đó gửi thử.') }}</div>@endunless
                </form>
            </div></div>
            <div class="card"><div class="card-body small">
                <h6 class="mb-2">{{ __('Dùng Gmail miễn phí') }}</h6>
                <ol class="ps-3 mb-0">
                    <li>{{ __('Đăng nhập Gmail của công ty → Quản lý tài khoản Google → Bảo mật → bật Xác minh 2 bước.') }}</li>
                    <li>{{ __('Mở myaccount.google.com/apppasswords → tạo mật khẩu ứng dụng tên “TOP CRM” → sao chép 16 ký tự.') }}</li>
                    <li>{{ __('Tại đây: chọn Gmail, nhập email vào Tên đăng nhập và Email người gửi, dán mật khẩu ứng dụng → Lưu → Gửi email thử.') }}</li>
                </ol>
                <div class="text-muted mt-2">{{ __('Gmail cá nhân gửi tối đa khoảng 500 thư / ngày — đủ cho doanh nghiệp nhỏ.') }}</div>
            </div></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('mailForm');
    const fields = document.getElementById('smtpFields');
    const sync = () => { fields.disabled = !document.getElementById('mmSmtp').checked; };
    form.querySelectorAll('input[name="mail_mailer"]').forEach((r) => r.addEventListener('change', sync));
    sync();
    document.getElementById('preset').addEventListener('change', (e) => {
        const o = e.target.selectedOptions[0];
        const note = document.getElementById('presetNote');
        if (!o.value) { note.classList.add('d-none'); return; }
        if (o.dataset.host) document.getElementById('mail_host').value = o.dataset.host;
        document.getElementById('mail_port').value = o.dataset.port;
        document.getElementById('mail_encryption').value = o.dataset.enc;
        note.textContent = o.dataset.note; note.classList.remove('d-none');
    });
})();
</script>
@endpush
