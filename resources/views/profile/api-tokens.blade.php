@extends('layouts.app')
@section('title', __('Khóa API'))
@section('content')
    <x-page-header :title="__('Khóa API')" :breadcrumbs="['Hồ sơ cá nhân' => route('profile.edit'), 'Khóa API' => null]"
                   :subtitle="__('Cho phép phần mềm khác (kế toán, website, Zapier...) kết nối với dữ liệu của tổ chức :org.', ['org' => $organization?->name])" />

    @if ($plainToken)
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Khóa API mới — chỉ hiển thị một lần') }}</div>
            <div class="small mb-2">{{ __('Sao chép và lưu vào phần mềm cần kết nối ngay bây giờ. Hệ thống chỉ lưu bản băm nên không thể xem lại khóa này. Không gửi khóa qua tin nhắn, email hay đưa lên mạng.') }}</div>
            <div class="input-group">
                <input type="text" class="form-control font-monospace" value="{{ $plainToken }}" readonly id="plainToken" aria-label="{{ __('Khóa API') }}">
                <button class="btn btn-dark" type="button" data-copy-token><i class="bi bi-clipboard me-1"></i>{{ __('Sao chép') }}</button>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header"><span><i class="bi bi-key me-2 text-orange"></i>{{ __('Khóa của bạn') }}</span></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>{{ __('Tên khóa') }}</th><th>{{ __('Phạm vi') }}</th><th>{{ __('Dùng gần nhất') }}</th><th>{{ __('Hết hạn') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($tokens as $t)
                            @include('profile._api-token-row', ['t' => $t, 'showOwner' => false])
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="bi-key" :title="__('Chưa có khóa API nào')" /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($canManageAll)
                <div class="card">
                    <div class="card-header"><span><i class="bi bi-building-lock me-2 text-orange"></i>{{ __('Khóa của các thành viên khác trong tổ chức') }}</span></div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>{{ __('Tên khóa') }}</th><th>{{ __('Phạm vi') }}</th><th>{{ __('Dùng gần nhất') }}</th><th>{{ __('Hết hạn') }}</th><th></th></tr></thead>
                            <tbody>
                            @forelse ($orgTokens as $t)
                                @include('profile._api-token-row', ['t' => $t, 'showOwner' => true])
                            @empty
                                <tr><td colspan="5"><x-empty-state icon="bi-key" :title="__('Không có khóa nào khác')" /></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            @if ($canCreate)
                <div class="card mb-4">
                    <div class="card-header"><span><i class="bi bi-plus-circle me-2 text-orange"></i>{{ __('Tạo khóa mới') }}</span></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('api-tokens.store') }}" class="needs-validation" novalidate>
                            @csrf
                            <x-input name="name" :label="__('Tên khóa')" required maxlength="100" :placeholder="__('VD: Website bán hàng')" />
                            <div class="mb-3">
                                <label class="form-label">{{ __('Phạm vi truy cập') }} <span class="req">*</span></label>
                                @foreach (\App\Models\ApiToken::SCOPES as $scope => $label)
                                    @php($can = in_array($scope, $grantable, true))
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $scope }}" id="scope_{{ $loop->index }}"
                                               @checked(in_array($scope, old('scopes', []), true)) @disabled(! $can)>
                                        <label class="form-check-label {{ $can ? '' : 'text-muted' }}" for="scope_{{ $loop->index }}">
                                            {{ __($label) }} <code class="small">{{ $scope }}</code>
                                            @unless ($can)<span class="small">({{ __('bạn không có quyền này') }})</span>@endunless
                                        </label>
                                    </div>
                                @endforeach
                                @error('scopes')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                @error('scopes.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="expires_in">{{ __('Thời hạn') }}</label>
                                <select class="form-select" name="expires_in" id="expires_in">
                                    @foreach (\App\Models\ApiToken::EXPIRY_DAYS as $days)
                                        <option value="{{ $days }}" @selected((int) old('expires_in', \App\Models\ApiToken::DEFAULT_EXPIRY_DAYS) === $days)>{{ __(':days ngày', ['days' => $days]) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-input name="password" type="password" :label="__('Mật khẩu hiện tại')" required autocomplete="current-password" />
                            <button class="btn btn-primary w-100"><i class="bi bi-key me-1"></i>{{ __('Tạo khóa API') }}</button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><span><i class="bi bi-info-circle me-2 text-orange"></i>{{ __('Cách sử dụng') }}</span></div>
                <div class="card-body small">
                    <p class="mb-2">{{ __('Gửi khóa trong header của mỗi yêu cầu:') }}</p>
                    <pre class="bg-body-tertiary border rounded p-2 small mb-3"><code>Authorization: Bearer topcrm_...
Accept: application/json

GET {{ url('/api/v1/customers') }}</code></pre>
                    <ul class="ps-3 mb-0">
                        <li class="mb-1">{{ __('Khóa chỉ truy cập dữ liệu của tổ chức đang chọn khi tạo khóa.') }}</li>
                        <li class="mb-1">{{ __('Khóa không bao giờ có nhiều quyền hơn tài khoản của bạn; quyền của bạn bị giảm thì khóa cũng bị giảm theo.') }}</li>
                        <li class="mb-1">{{ __('Bị khóa tài khoản, gỡ khỏi tổ chức hoặc khóa hết hạn: khóa ngừng hoạt động ngay.') }}</li>
                        <li>{{ __('Nghi ngờ lộ khóa: bấm Thu hồi và tạo khóa mới.') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelector('[data-copy-token]')?.addEventListener('click', async () => {
        const input = document.getElementById('plainToken');
        try { await navigator.clipboard.writeText(input.value); toast('success', t('Đã sao chép khóa API.')); }
        catch (e) { input.select(); toast('error', t('Không sao chép được, hãy chép thủ công.')); }
    });
</script>
@endpush
