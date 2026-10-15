@extends('layouts.app')
@section('title', __('Trang đăng nhập & thương hiệu'))
@section('content')
    <x-page-header :title="__('Trang đăng nhập & thương hiệu')" :breadcrumbs="['Cài đặt tổ chức' => route('settings.edit'), 'Trang đăng nhập & thương hiệu' => null]" :subtitle="__('Dùng chung cho toàn hệ thống (mọi tổ chức). Chỉ quản trị nền tảng chỉnh sửa được.')">
        <a href="{{ route('pages.terms') }}" target="_blank" class="btn btn-light"><i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Xem trang điều khoản') }}</a>
    </x-page-header>
    @include('settings._tabs')

    <form method="POST" action="{{ route('settings.branding.update') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4"><div class="card-header">{{ __('Thương hiệu') }}</div><div class="card-body"><div class="row">
                    <x-input name="brand_name" :label="__('Tên thương hiệu (chữ lớn cạnh logo)')" :value="$s['brand_name']" required maxlength="30" col="col-md-4 mb-3" />
                    <x-input name="brand_tagline" :label="__('Dòng phụ dưới tên')" :value="$s['brand_tagline']" maxlength="60" col="col-md-8 mb-3" />
                    <x-input name="brand_slogan" :label="__('Khẩu hiệu nhỏ')" :value="$s['brand_slogan']" maxlength="120" col="col-12 mb-0" />
                </div></div></div>

                <div class="card mb-4"><div class="card-header">{{ __('Nội dung trang đăng nhập') }}</div><div class="card-body"><div class="row">
                    <x-input name="login_headline" :label="__('Tiêu đề (dòng trắng)')" :value="$s['login_headline']" required maxlength="60" col="col-md-6 mb-3" />
                    <x-input name="login_headline_accent" :label="__('Tiêu đề (dòng màu cam)')" :value="$s['login_headline_accent']" maxlength="60" col="col-md-6 mb-3" />
                    <x-textarea name="login_description" :label="__('Đoạn giới thiệu')" :value="$s['login_description']" rows="3" maxlength="300" col="col-12 mb-3" />
                    <x-textarea name="login_stats" :label="__('Dải chỉ số (tối đa 4 dòng, mỗi dòng: giá trị|nhãn)')" :value="$s['login_stats']" rows="4" maxlength="500" col="col-12 mb-0"
                                :help="__('Ví dụ: 10K+|Khách hàng tin dùng. Đây là chữ hiển thị — hãy dùng số liệu đúng với doanh nghiệp. Để trống để ẩn dải chỉ số.')" />
                </div></div></div>

                <div class="card mb-4"><div class="card-header">{{ __('Chân trang & các trang thông tin') }}</div><div class="card-body"><div class="row">
                    <x-input name="copyright_text" :label="__('Dòng bản quyền')" :value="$s['copyright_text']" required maxlength="120" col="col-md-6 mb-3" />
                    <x-input name="copyright_owner" :label="__('Chủ sở hữu bản quyền')" :value="$s['copyright_owner']" maxlength="120" col="col-md-6 mb-3" />
                    <x-textarea name="page_terms" :label="__('Điều khoản sử dụng')" :value="$s['page_terms']" rows="5" col="col-12 mb-3" :help="__('Để trống để dùng nội dung mẫu có sẵn.')" />
                    <x-textarea name="page_privacy" :label="__('Chính sách bảo mật')" :value="$s['page_privacy']" rows="5" col="col-12 mb-3" :help="__('Để trống để dùng nội dung mẫu có sẵn.')" />
                    <x-textarea name="page_support" :label="__('Hỗ trợ')" :value="$s['page_support']" rows="4" col="col-12 mb-0" :help="__('Để trống để tự hiện thông tin liên hệ của tổ chức mặc định.')" />
                </div></div></div>

                <div class="card"><div class="card-header"><span><i class="bi bi-person-badge me-2 text-orange"></i>{{ __('Đăng nhập bằng Google / Facebook') }}</span></div><div class="card-body">
                    <p class="small text-muted">{{ __('Chỉ nhân viên đã được cấp tài khoản (trùng email) mới đăng nhập được — hệ thống không tự tạo tài khoản mới. Khóa bí mật được mã hóa trước khi lưu. Để trống ô khóa bí mật nếu muốn giữ khóa cũ.') }}</p>
                    @foreach (['google' => ['Google', 'https://console.cloud.google.com/apis/credentials'], 'facebook' => ['Facebook', 'https://developers.facebook.com/apps']] as $p => [$label, $consoleUrl])
                        <div class="border rounded-3 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>{{ $label }}</strong>
                                @if ($social[$p]['enabled'])<span class="badge badge-soft-success">{{ __('Đang bật') }}</span>@else<span class="badge badge-soft-secondary">{{ __('Chưa bật') }}</span>@endif
                            </div>
                            <div class="row">
                                <x-input :name="$p.'_client_id'" label="Client ID / App ID" :value="$s[$p.'_client_id']" maxlength="255" col="col-md-6 mb-2" autocomplete="off" />
                                <x-input :name="$p.'_client_secret'" type="password" label="Client Secret / App Secret" maxlength="255" col="col-md-6 mb-2" autocomplete="new-password"
                                         :placeholder="$social[$p]['has_secret'] ? '•••••••• ('.__('đã lưu').')' : ''" />
                            </div>
                            <div class="small">{{ __('URL chuyển hướng cần khai báo tại') }} <a href="{{ $consoleUrl }}" target="_blank" rel="noopener">{{ __('trang quản lý :provider', ['provider' => $label]) }}</a>:
                                <code class="user-select-all">{{ $social[$p]['callback'] }}</code></div>
                            @if ($social[$p]['enabled'])
                                <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="clear_{{ $p }}" value="1" id="clear_{{ $p }}"><label class="form-check-label small" for="clear_{{ $p }}">{{ __('Tắt đăng nhập :provider (xóa khóa)', ['provider' => $label]) }}</label></div>
                            @endif
                        </div>
                    @endforeach
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-header">{{ __('Ảnh nền trang đăng nhập') }}</div><div class="card-body">
                    <img src="{{ $s['login_background'] ? Storage::disk('public')->url($s['login_background']) : asset('images/login-bg.jpg') }}" alt="{{ __('Ảnh nền') }}" class="img-fluid rounded mb-3" style="max-height:220px;width:100%;object-fit:cover">
                    @if ($s['login_background'])
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remove_background" value="1" id="remove_background"><label class="form-check-label" for="remove_background">{{ __('Dùng lại ảnh mặc định') }}</label></div>
                    @endif
                    <x-input name="login_background" type="file" accept="image/jpeg,image/png,image/webp" :help="__('JPG/PNG/WEBP, tối đa 4MB. Nên dùng ảnh dọc hoặc vuông, tông ấm.')" />
                    <div class="small text-muted">{{ __('Logo lấy từ cài đặt của tổ chức mặc định.') }} <a href="{{ route('settings.edit') }}">{{ __('Cài đặt tổ chức') }}</a></div>
                </div></div>
                <button class="btn btn-primary w-100 mb-2"><i class="bi bi-check2 me-1"></i>{{ __('Lưu cài đặt') }}</button>
                <div class="small text-muted"><i class="bi bi-info-circle"></i> {{ __('Để xem trang đăng nhập sau khi lưu, mở cửa sổ ẩn danh (Ctrl + Shift + N) và truy cập địa chỉ CRM.') }}</div>
            </div>
        </div>
    </form>
@endsection
