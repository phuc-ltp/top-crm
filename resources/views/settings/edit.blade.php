@extends('layouts.app')
@section('title', 'Cài đặt hệ thống')
@section('content')
    <x-page-header title="Cài đặt hệ thống" :breadcrumbs="['Cài đặt hệ thống' => null]" />
    @include('settings._tabs')
    @php($C = App\Http\Controllers\SettingController::class)
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4"><div class="card-header">Thông tin công ty</div><div class="card-body"><div class="row">
                    <x-input name="company_name" label="Tên công ty" :value="$s['company_name']" required maxlength="150" col="col-md-12 mb-3" />
                    <x-input name="company_email" type="email" label="Email liên hệ" :value="$s['company_email']" maxlength="150" col="col-md-6 mb-3" />
                    <x-input name="company_phone" label="Số điện thoại" :value="$s['company_phone']" maxlength="30" col="col-md-6 mb-3" />
                    <x-input name="company_address" label="Địa chỉ" :value="$s['company_address']" maxlength="300" col="col-12 mb-0" />
                </div></div></div>

                <div class="card mb-4"><div class="card-header">Khu vực & định dạng</div><div class="card-body"><div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Ngôn ngữ</label><input class="form-control" value="Tiếng Việt" disabled></div>
                    <x-select name="timezone" label="Múi giờ" :options="array_combine($C::TIMEZONES, $C::TIMEZONES)" :value="$s['timezone']" required col="col-md-6 mb-3" />
                    <x-select name="currency" label="Đơn vị tiền tệ" :options="$C::CURRENCIES" :value="$s['currency']" required col="col-md-4 mb-3" />
                    <x-select name="date_format" label="Định dạng ngày" :options="$C::DATE_FORMATS" :value="$s['date_format']" required col="col-md-4 mb-3" />
                    <x-input name="default_tax_rate" type="number" label="Thuế VAT mặc định (%)" :value="$s['default_tax_rate']" required min="0" max="100" step="0.5" col="col-md-4 mb-3" />
                    <div class="col-12 small text-muted"><i class="bi bi-info-circle"></i> Múi giờ máy chủ được cấu hình trong tệp .env (APP_TIMEZONE, hiện tại: {{ config('app.timezone') }}).</div>
                </div></div></div>

                <div class="card"><div class="card-header">Cấu hình thông báo nội bộ</div><div class="card-body">
                    @foreach (['notify_lead_assigned' => 'Khi được phân công lead / khách hàng', 'notify_task_due' => 'Công việc được giao, sắp đến hạn, quá hạn', 'notify_deal_stage' => 'Cơ hội thay đổi giai đoạn', 'notify_ticket_new' => 'Ticket hỗ trợ mới'] as $key => $label)
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="{{ $key }}" value="1" id="{{ $key }}" @checked(($s[$key] ?? '1') === '1')>
                            <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    <div class="small text-muted mt-2">Thông báo hiển thị trong chuông thông báo. Hệ thống không gửi email hàng loạt.</div>
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-header">Thương hiệu</div><div class="card-body">
                    @if ($s['company_logo'])
                        <img src="{{ Storage::disk('public')->url($s['company_logo']) }}" alt="Logo" class="mb-3 rounded" style="max-height:80px">
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo"><label class="form-check-label" for="remove_logo">Xóa logo</label></div>
                    @endif
                    <x-input name="company_logo" type="file" label="Logo" accept="image/png,image/jpeg,image/webp" help="PNG/JPG/WEBP, tối đa 1MB, nên dùng ảnh vuông." />
                    <label class="form-label" for="brand_color">Màu thương hiệu</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="color" class="form-control form-control-color" id="brand_color_picker" value="{{ $s['brand_color'] }}" aria-label="Chọn màu">
                        <input type="text" name="brand_color" id="brand_color" class="form-control" value="{{ old('brand_color', $s['brand_color']) }}" required pattern="#[0-9A-Fa-f]{6}">
                    </div>
                    <div class="form-text">Mặc định #D9823B (cam đồng TOP CRM).</div>
                </div></div>
                <button class="btn btn-primary w-100"><i class="bi bi-check2 me-1"></i>Lưu cài đặt</button>
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
