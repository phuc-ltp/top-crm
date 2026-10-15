@extends('layouts.app')
@section('title', __('Bảo mật tài khoản'))
@section('content')
    <x-page-header :title="__('Bảo mật tài khoản')" :breadcrumbs="['Hồ sơ cá nhân' => route('profile.edit'), 'Bảo mật tài khoản' => null]">
        <a href="{{ route('profile.sessions') }}" class="btn btn-light"><i class="bi bi-laptop me-1"></i>{{ __('Phiên đăng nhập') }}</a>
        <a href="{{ route('profile.edit') }}#password" class="btn btn-light"><i class="bi bi-key me-1"></i>{{ __('Đổi mật khẩu') }}</a>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-7">
            {{-- Xác thực 2 lớp --}}
            <div class="card mb-4" id="two-factor">
                <div class="card-header">
                    <span><i class="bi bi-shield-lock me-2 text-orange"></i>{{ __('Xác thực 2 lớp') }}</span>
                    @if ($user->hasTwoFactorEnabled())
                        <span class="badge badge-soft-success">{{ __('Đang bật') }}</span>
                    @elseif ($setup)
                        <span class="badge badge-soft-warning">{{ __('Đang thiết lập') }}</span>
                    @else
                        <span class="badge badge-soft-secondary">{{ __('Chưa bật') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($recoveryCodes)
                        <div class="alert alert-warning">
                            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Mã khôi phục — chỉ hiển thị một lần') }}</div>
                            <div class="small mb-3">{{ __('Mỗi mã dùng được một lần để đăng nhập khi mất điện thoại. Hãy in ra hoặc lưu vào trình quản lý mật khẩu; không gửi qua tin nhắn hay email.') }}</div>
                            <div class="row g-2 font-monospace" id="recoveryCodes">
                                @foreach ($recoveryCodes as $code)
                                    <div class="col-6 col-md-3"><code class="d-block text-center p-2 rounded bg-body border">{{ $code }}</code></div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-sm btn-light mt-3" data-copy-codes><i class="bi bi-clipboard me-1"></i>{{ __('Sao chép tất cả') }}</button>
                        </div>
                    @endif

                    @if ($user->hasTwoFactorEnabled())
                        <p class="text-muted small">{{ __('Mỗi lần đăng nhập, sau mật khẩu bạn cần nhập mã 6 số từ ứng dụng xác thực. Kẻ gian biết mật khẩu cũng không vào được tài khoản.') }}</p>
                        <dl class="detail-list small mb-4">
                            <dt>{{ __('Bật từ') }}</dt><dd>{{ vn_date($user->two_factor_confirmed_at, true) }}</dd>
                            <dt>{{ __('Mã khôi phục còn lại') }}</dt>
                            <dd><span class="{{ $remainingCodes <= 2 ? 'text-danger fw-semibold' : '' }}">{{ $remainingCodes }} / {{ \App\Support\Security\TwoFactor::RECOVERY_CODE_COUNT }}</span></dd>
                        </dl>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <form method="POST" action="{{ route('two-factor.recovery-codes') }}" class="needs-validation border rounded-3 p-3 h-100" novalidate>
                                    @csrf
                                    <div class="fw-semibold small mb-2">{{ __('Tạo bộ mã khôi phục mới') }}</div>
                                    <x-input name="regen_password" type="password" :label="__('Mật khẩu hiện tại')" required autocomplete="current-password" />
                                    <button class="btn btn-light w-100"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Tạo mã mới') }}</button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form method="POST" action="{{ route('two-factor.disable') }}" class="needs-validation border border-danger-subtle rounded-3 p-3 h-100" novalidate
                                      data-confirm="{{ __('Tắt xác thực 2 lớp sẽ làm tài khoản kém an toàn hơn. Tiếp tục?') }}">
                                    @csrf @method('DELETE')
                                    <div class="fw-semibold small mb-2 text-danger">{{ __('Tắt xác thực 2 lớp') }}</div>
                                    <x-input name="password" type="password" :label="__('Mật khẩu hiện tại')" required autocomplete="current-password" />
                                    <x-input name="code" :label="__('Mã 6 số hoặc mã khôi phục')" required maxlength="30" autocomplete="one-time-code" />
                                    <button class="btn btn-outline-danger w-100"><i class="bi bi-shield-x me-1"></i>{{ __('Tắt') }}</button>
                                </form>
                            </div>
                        </div>
                    @elseif ($setup)
                        <ol class="small ps-3 mb-3">
                            <li>{{ __('Cài ứng dụng xác thực trên điện thoại: Google Authenticator, Microsoft Authenticator hoặc Authy.') }}</li>
                            <li>{{ __('Trong ứng dụng, chọn thêm tài khoản và quét mã QR bên dưới.') }}</li>
                            <li>{{ __('Nhập mã 6 số ứng dụng hiển thị để hoàn tất.') }}</li>
                        </ol>
                        <div class="d-flex flex-wrap gap-4 align-items-center mb-3">
                            <div class="p-2 bg-white rounded-3 border" style="width:216px;height:216px" aria-label="{{ __('Mã QR thiết lập xác thực 2 lớp') }}">{!! $setup['qr'] !!}</div>
                            <div class="small">
                                <div class="text-muted mb-1">{{ __('Không quét được? Nhập khóa này vào ứng dụng:') }}</div>
                                <code class="fs-6 user-select-all">{{ $setup['secret'] }}</code>
                                <div class="text-muted mt-2">{{ __('Loại: theo thời gian (TOTP), 6 số, 30 giây.') }}</div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="needs-validation row g-2 align-items-start" novalidate>
                            @csrf
                            <x-input name="code" :label="__('Mã 6 số từ ứng dụng')" required inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" col="col-sm-6" autofocus />
                            <div class="col-sm-6 pt-sm-4 mt-sm-2"><button class="btn btn-primary w-100"><i class="bi bi-check2-circle me-1"></i>{{ __('Xác nhận và bật') }}</button></div>
                        </form>
                        <hr>
                        <form method="POST" action="{{ route('two-factor.disable') }}" class="needs-validation row g-2 align-items-start" novalidate>
                            @csrf @method('DELETE')
                            <x-input name="password" type="password" :label="__('Mật khẩu hiện tại')" required autocomplete="current-password" col="col-sm-6" />
                            <div class="col-sm-6 pt-sm-4 mt-sm-2"><button class="btn btn-light w-100">{{ __('Hủy thiết lập') }}</button></div>
                        </form>
                    @else
                        <p class="text-muted small">{{ __('Thêm một lớp bảo vệ: ngoài mật khẩu, mỗi lần đăng nhập cần mã 6 số từ ứng dụng trên điện thoại của bạn. Rất nên bật với tài khoản quản trị.') }}</p>
                        <form method="POST" action="{{ route('two-factor.enable') }}" class="needs-validation row g-2 align-items-start" novalidate>
                            @csrf
                            <x-input name="password" type="password" :label="__('Mật khẩu hiện tại')" required autocomplete="current-password" col="col-sm-6" />
                            <div class="col-sm-6 pt-sm-4 mt-sm-2"><button class="btn btn-primary w-100"><i class="bi bi-shield-plus me-1"></i>{{ __('Bật xác thực 2 lớp') }}</button></div>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Lịch sử đăng nhập --}}
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-clock-history me-2 text-orange"></i>{{ __('Lịch sử đăng nhập') }}</span>
                    <span class="small text-muted">{{ __('30 lần gần nhất · lưu :days ngày', ['days' => $retentionDays]) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>{{ __('Thời gian') }}</th><th>{{ __('Kết quả') }}</th><th>{{ __('Thiết bị') }}</th><th>{{ __('Địa chỉ IP') }}</th></tr></thead>
                        <tbody>
                        @forelse ($events as $e)
                            <tr>
                                <td class="small text-nowrap">{{ vn_date($e->created_at, true) }}</td>
                                <td>
                                    {!! badge(\App\Models\LoginEvent::EVENTS, $e->event) !!}
                                    @if ($e->method)<div class="small-xs text-muted">{{ __(\App\Models\LoginEvent::METHODS[$e->method] ?? $e->method) }}</div>@endif
                                </td>
                                <td class="small">{{ \App\Support\Security\UserAgent::describe($e->user_agent) }}
                                    @if ($e->is_new_device)<span class="badge badge-soft-warning ms-1">{{ __('Thiết bị mới') }}</span>@endif</td>
                                <td class="small">{{ $e->ip_address ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty-state icon="bi-clock-history" :title="__('Chưa có lịch sử đăng nhập')" /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            {{-- Xác minh email --}}
            <div class="card mb-4">
                <div class="card-header"><span><i class="bi bi-envelope-check me-2 text-orange"></i>{{ __('Xác minh email') }}</span>
                    @if ($user->hasVerifiedEmail())
                        <span class="badge badge-soft-success">{{ __('Đã xác minh') }}</span>
                    @else
                        <span class="badge badge-soft-warning">{{ __('Chưa xác minh') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="fw-semibold mb-1">{{ $user->email }}</div>
                    @if ($user->hasVerifiedEmail())
                        <div class="small text-muted">{{ __('Xác minh lúc :time', ['time' => vn_date($user->email_verified_at, true)]) }}</div>
                    @else
                        <p class="small text-muted">{{ __('Xác minh email giúp bạn nhận được cảnh báo đăng nhập và liên kết đặt lại mật khẩu.') }}</p>
                        <form method="POST" action="{{ route('verification.send') }}">@csrf
                            <button class="btn btn-light w-100"><i class="bi bi-send me-1"></i>{{ __('Gửi email xác minh') }}</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span><i class="bi bi-lightbulb me-2 text-orange"></i>{{ __('Lời khuyên bảo mật') }}</span></div>
                <div class="card-body small">
                    <ul class="ps-3 mb-0">
                        <li class="mb-2">{{ __('Dùng mật khẩu riêng cho hệ thống này, không dùng lại mật khẩu của trang khác.') }}</li>
                        <li class="mb-2">{{ __('Bật xác thực 2 lớp và cất mã khôi phục ở nơi an toàn.') }}</li>
                        <li class="mb-2">{{ __('Nhận được cảnh báo đăng nhập lạ: đổi mật khẩu ngay và đăng xuất các thiết bị khác.') }}</li>
                        <li>{{ __('Không chia sẻ tài khoản; mỗi nhân viên dùng một tài khoản riêng.') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelector('[data-copy-codes]')?.addEventListener('click', async (e) => {
        const codes = [...document.querySelectorAll('#recoveryCodes code')].map((c) => c.textContent.trim()).join('\n');
        try { await navigator.clipboard.writeText(codes); toast('success', t('Đã sao chép mã khôi phục.')); }
        catch (err) { toast('error', t('Không sao chép được, hãy chép thủ công.')); }
    });
</script>
@endpush
