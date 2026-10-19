@extends('layouts.app')
@section('title', __('Cài đặt tổ chức'))
@section('content')
    <x-page-header :title="__('Cài đặt tổ chức')" :breadcrumbs="['Cài đặt tổ chức' => null]"
                   :subtitle="__('Áp dụng riêng cho tổ chức :org. Các tổ chức khác có cài đặt của mình.', ['org' => $organization?->name])" />
    @include('settings._tabs')
    @php($C = App\Http\Controllers\SettingController::class)
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4"><div class="card-header">{{ __('Thông tin công ty') }}</div><div class="card-body"><div class="row">
                    <x-input name="company_name" :label="__('Tên công ty')" :value="$s['company_name']" required maxlength="150" col="col-md-12 mb-3" />
                    <x-input name="company_email" type="email" :label="__('Email liên hệ')" :value="$s['company_email']" maxlength="150" col="col-md-6 mb-3" />
                    <x-input name="company_phone" :label="__('Số điện thoại')" :value="$s['company_phone']" maxlength="30" col="col-md-6 mb-3" />
                    <x-input name="company_address" :label="__('Địa chỉ')" :value="$s['company_address']" maxlength="300" col="col-md-8 mb-3" />
                    <x-input name="company_tax_code" :label="__('Mã số thuế công ty')" :value="$s['company_tax_code'] ?? ''" maxlength="50" col="col-md-4 mb-3" />
                    <div class="col-12"><div class="small fw-semibold mb-2">{{ __('Thông tin trên chứng từ (hóa đơn / PDF)') }}</div></div>
                    <x-input name="bank_name" :label="__('Ngân hàng')" :value="$s['bank_name'] ?? ''" maxlength="150" col="col-md-4 mb-3" />
                    <x-input name="bank_account_number" :label="__('Số tài khoản')" :value="$s['bank_account_number'] ?? ''" maxlength="50" col="col-md-4 mb-3" />
                    <x-input name="bank_account_name" :label="__('Chủ tài khoản')" :value="$s['bank_account_name'] ?? ''" maxlength="150" col="col-md-4 mb-3" />
                    <x-textarea name="invoice_terms" :label="__('Điều khoản thanh toán in trên hóa đơn')" :value="$s['invoice_terms'] ?? ''" rows="2" maxlength="1000" col="col-12 mb-0" />
                </div></div></div>

                <div class="card mb-4"><div class="card-header">{{ __('Khu vực & định dạng') }}</div><div class="card-body"><div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('Ngôn ngữ') }}</label><input class="form-control" value="{{ config('crm.locales')[config('app.locale')] ?? config('app.locale') }}" disabled><div class="form-text">{{ __('Ngôn ngữ mặc định cho người dùng chưa chọn. Mỗi người có thể đổi ngôn ngữ riêng ở thanh trên cùng.') }}</div></div>
                    <x-select name="timezone" :label="__('Múi giờ')" :options="array_combine($C::TIMEZONES, $C::TIMEZONES)" :value="$s['timezone']" required col="col-md-6 mb-3" />
                    <x-select name="currency" :label="__('Đơn vị tiền tệ')" :options="$C::CURRENCIES" :value="$s['currency']" required col="col-md-4 mb-3" />
                    <x-select name="date_format" :label="__('Định dạng ngày')" :options="$C::DATE_FORMATS" :value="$s['date_format']" required col="col-md-4 mb-3" />
                    <x-input name="default_tax_rate" type="number" :label="__('Thuế VAT mặc định (%)')" :value="$s['default_tax_rate']" required min="0" max="100" step="0.5" col="col-md-4 mb-3" />
                    <div class="col-12 small text-muted"><i class="bi bi-info-circle"></i> {{ __('Múi giờ dùng để hiển thị ngày giờ trong tổ chức này. Dữ liệu vẫn được lưu theo giờ máy chủ (:tz).', ['tz' => config('app.timezone')]) }}</div>
                </div></div></div>

                <div class="card"><div class="card-header">{{ __('Cấu hình thông báo nội bộ') }}</div><div class="card-body">
                    @foreach (['notify_lead_assigned' => __('Khi được phân công lead / khách hàng'), 'notify_task_due' => __('Công việc được giao, sắp đến hạn, quá hạn'), 'notify_deal_stage' => __('Cơ hội thay đổi giai đoạn'), 'notify_ticket_new' => __('Ticket hỗ trợ mới'), 'notify_low_stock' => __('Tồn kho xuống dưới mức tối thiểu (gửi người quản lý kho)')] as $key => $label)
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="{{ $key }}" value="1" id="{{ $key }}" @checked(($s[$key] ?? '1') === '1')>
                            <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    <div class="small text-muted mt-2">{{ __('Thông báo hiển thị trong chuông thông báo. Hệ thống không gửi email hàng loạt.') }}</div>
                </div></div>

                <div class="card mt-4"><div class="card-header"><span><i class="bi bi-file-earmark-text me-2 text-orange"></i>{{ __('Báo giá') }}</span></div><div class="card-body"><div class="row">
                    <x-input name="tax_rates" :label="__('Các mức thuế VAT dùng trên chứng từ (%)')" :value="$s['tax_rates'] ?? ''" maxlength="100" placeholder="0, 5, 8, 10" col="col-md-6 mb-2"
                             :help="__('Phân cách bằng dấu phẩy. Để trống: nhập tự do. Thuế mặc định ở trên phải thuộc danh sách.')" />
                    <x-input name="quotation_discount_threshold" type="number" :label="__('Ngưỡng chiết khấu cần duyệt (%)')" :value="$s['quotation_discount_threshold'] ?? ''" min="0" max="100" step="0.5" col="col-md-6 mb-2"
                             :help="__('Báo giá có tổng chiết khấu vượt ngưỡng do nhân viên lập phải được người có quyền duyệt báo giá chấp thuận trước khi gửi khách / tạo đơn hàng. Để trống: không cần duyệt.')" />
                </div></div></div>

                <div class="card mt-4"><div class="card-header"><span><i class="bi bi-boxes me-2 text-orange"></i>{{ __('Kho hàng') }}</span></div><div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="inventory_allow_negative" value="1" id="inventory_allow_negative" @checked(($s['inventory_allow_negative'] ?? '0') === '1')>
                        <label class="form-check-label" for="inventory_allow_negative">{{ __('Cho phép xuất kho khi không đủ tồn (tồn kho âm)') }}</label>
                    </div>
                    <div class="small text-muted mb-3">{{ __('Mặc định tắt: phiếu xuất / chuyển vượt số có thể dùng sẽ bị từ chối.') }}</div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="inventory_track_orders" value="1" id="inventory_track_orders" @checked(($s['inventory_track_orders'] ?? '0') === '1')>
                        <label class="form-check-label" for="inventory_track_orders">{{ __('Đơn hàng giữ hàng & xuất kho tự động') }}</label>
                    </div>
                    <div class="small text-muted">{{ __('Bật khi đã nhập tồn kho: đơn hàng mới gắn kho mặc định — duyệt đơn giữ hàng (thiếu hàng thì không duyệt được), hoàn thành tự lập phiếu xuất kho, hủy trả phần giữ. Đơn hàng đã có không bị ảnh hưởng.') }}</div>
                </div></div>

                @can('platform')
                <div class="card mt-4"><div class="card-header"><span><i class="bi bi-shield-lock me-2 text-orange"></i>{{ __('Bảo mật đăng nhập') }}</span></div><div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="require_2fa_admins" value="1" id="require_2fa_admins" @checked(($s['require_2fa_admins'] ?? '0') === '1')>
                        <label class="form-check-label" for="require_2fa_admins">{{ __('Bắt buộc xác thực 2 lớp cho tài khoản quản trị') }}</label>
                    </div>
                    <div class="small text-muted">{{ __('Áp dụng cho quản trị nền tảng, chủ sở hữu và quản trị viên tổ chức. Người chưa bật sẽ được chuyển tới trang thiết lập sau khi đăng nhập (không bị khóa tài khoản).') }}</div>
                </div></div>
                @endcan
            </div>
            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-header">{{ __('Thương hiệu') }}</div><div class="card-body">
                    @if ($s['company_logo'])
                        <img src="{{ Storage::disk('public')->url($s['company_logo']) }}" alt="Logo" class="mb-3 rounded" style="max-height:80px">
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo"><label class="form-check-label" for="remove_logo">{{ __('Xóa logo') }}</label></div>
                    @endif
                    <x-input name="company_logo" type="file" label="Logo" accept="image/png,image/jpeg,image/webp" :help="__('PNG/JPG/WEBP, tối đa 1MB, nên dùng ảnh vuông.')" />
                    <label class="form-label" for="brand_color">{{ __('Màu thương hiệu') }}</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="color" class="form-control form-control-color" id="brand_color_picker" value="{{ $s['brand_color'] }}" aria-label="{{ __('Chọn màu') }}">
                        <input type="text" name="brand_color" id="brand_color" class="form-control" value="{{ old('brand_color', $s['brand_color']) }}" required pattern="#[0-9A-Fa-f]{6}">
                    </div>
                    <div class="form-text">{{ __('Mặc định #D9823B (cam đồng TOP CRM).') }}</div>
                </div></div>
                <button class="btn btn-primary w-100"><i class="bi bi-check2 me-1"></i>{{ __('Lưu cài đặt') }}</button>
            </div>
        </div>
    </form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const picker = document.getElementById('brand_color_picker'), text = document.getElementById('brand_color');
    picker.addEventListener('input', () => text.value = picker.value.toUpperCase());
    text.addEventListener('input', () => { if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) picker.value = text.value; });
});
</script>
@endpush
