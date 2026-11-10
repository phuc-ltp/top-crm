@extends('layouts.app')
@section('title', __('Liên kết SePay'))
@section('content')
    <x-page-header :title="__('Liên kết SePay')" :breadcrumbs="['Hệ thống' => route('settings.edit'), 'Liên kết SePay' => null]"
                   :subtitle="__('Khách chuyển khoản theo mã VietQR trên hóa đơn → SePay báo về → CRM tự ghi nhận thanh toán vào đúng hóa đơn.')" />

    <div class="alert {{ $enabled && $hasKey ? 'alert-success' : 'alert-warning' }} py-2 d-flex align-items-center gap-2">
        <i class="bi {{ $enabled && $hasKey ? 'bi-check-circle' : 'bi-exclamation-triangle' }}"></i>
        {{ $enabled && $hasKey ? __('Đang nhận giao dịch từ SePay.') : __('Chưa bật — nhập API Key và bật liên kết bên dưới.') }}
    </div>
    @unless ($bankReady)
        <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>{{ __('Chưa khai báo tài khoản nhận tiền (Cài đặt tổ chức → Ngân hàng nhận tiền) nên hóa đơn chưa có mã VietQR.') }}</div>
    @endunless

    <div class="row g-3 mb-4">
        <div class="col-lg-6"><div class="card h-100"><div class="card-body">
            <form method="POST" action="{{ route('settings.sepay.update') }}" class="needs-validation" novalidate autocomplete="off">@csrf @method('PUT')
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="sepay_enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="sepay_enabled" id="sepay_enabled" value="1" @checked($enabled)>
                    <label class="form-check-label fw-semibold" for="sepay_enabled">{{ __('Bật nhận giao dịch từ SePay') }}</label>
                </div>
                <x-input name="sepay_api_key" type="password" label="API Key" autocomplete="new-password" maxlength="200"
                         :placeholder="$hasKey ? __('Đã lưu — để trống nếu không đổi') : __('Dán API Key tạo ở SePay')"
                         :help="__('Khóa được mã hóa, không hiển thị lại. Đây là khóa bạn tự đặt cho webhook trong SePay (mục Xác thực: API Key).')" />
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Lưu') }}</button>
            </form>
        </div></div></div>

        <div class="col-lg-6"><div class="card h-100"><div class="card-body">
            <h6 class="fw-bold mb-2">{{ __('Địa chỉ webhook') }}</h6>
            @if ($webhookUrl)
                <div class="input-group mb-2">
                    <input type="text" class="form-control font-monospace small" value="{{ $webhookUrl }}" readonly id="webhookUrl" aria-label="{{ __('Địa chỉ webhook') }}">
                    <button class="btn btn-dark" type="button" data-copy-webhook><i class="bi bi-clipboard me-1"></i>{{ __('Sao chép') }}</button>
                </div>
                @if (! str_starts_with($webhookUrl, 'https://'))
                    <div class="small text-danger mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Địa chỉ này chưa phải https công khai — SePay chỉ gọi được khi CRM chạy trên hosting có tên miền.') }}</div>
                @endif
                <form method="POST" action="{{ route('settings.sepay.regenerate') }}" class="d-inline" data-confirm="{{ __('Tạo địa chỉ webhook mới? Địa chỉ cũ ngừng hoạt động ngay, bạn phải cập nhật lại trong SePay.') }}" data-confirm-button="{{ __('Tạo mới') }}">@csrf
                    <button class="btn btn-light btn-sm"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Tạo địa chỉ mới') }}</button>
                </form>
            @else
                <p class="small text-muted mb-0">{{ __('Bấm Lưu một lần để tạo địa chỉ webhook riêng của tổ chức.') }}</p>
            @endif
            <hr>
            <ol class="small mb-0 ps-3">
                <li>{{ __('Vào my.sepay.vn → Tích hợp WebHooks → Thêm webhook.') }}</li>
                <li>{{ __('Sự kiện: Có tiền vào. Chọn đúng tài khoản ngân hàng nhận tiền.') }}</li>
                <li>{{ __('Gọi đến URL: dán địa chỉ webhook ở trên. Kiểu chứng thực: API Key — dán cùng khóa đã nhập ở đây.') }}</li>
                <li>{{ __('Nên bật cấu trúc mã thanh toán với tiền tố HD, hậu tố 5 chữ số (VD HD00010) để SePay nhận diện số hóa đơn.') }}</li>
                <li>{{ __('Khách quét mã VietQR trên hóa đơn (nội dung = số hóa đơn) → CRM tự ghi nhận. Giao dịch không khớp hiện ở danh sách dưới để xử lý tay.') }}</li>
            </ol>
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <span class="me-auto"><i class="bi bi-bank me-2 text-orange"></i>{{ __('Giao dịch nhận được') }}</span>
            <a href="{{ route('settings.sepay') }}" class="btn btn-sm {{ $status ? 'btn-light' : 'btn-dark' }}">{{ __('Tất cả') }}</a>
            @foreach (\App\Models\BankTransaction::statuses() as $k => [$label, $color])
                <a href="{{ route('settings.sepay', ['status' => $k]) }}" class="btn btn-sm {{ $status === $k ? 'btn-dark' : 'btn-light' }}">{{ __($label) }} ({{ $counts[$k] ?? 0 }})</a>
            @endforeach
        </div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>{{ __('Thời gian') }}</th><th class="text-end">{{ __('Số tiền') }}</th><th>{{ __('Nội dung') }}</th><th>{{ __('Trạng thái') }}</th><th>{{ __('Hóa đơn') }}</th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                @php([$label, $color] = \App\Models\BankTransaction::statuses()[$t->status] ?? [$t->status, 'secondary'])
                <tr>
                    <td class="small text-nowrap">{{ vn_date($t->transaction_at ?? $t->created_at, true) }}<div class="text-muted">{{ $t->gateway }}</div></td>
                    <td class="text-end fw-semibold text-nowrap">{{ money($t->amount) }}</td>
                    <td class="small" style="min-width: 220px">{{ $t->content }}@if ($t->note)<div class="text-muted fst-italic">{{ $t->note }}</div>@endif</td>
                    <td><span class="badge text-bg-{{ $color }}">{{ __($label) }}</span></td>
                    <td class="small">@if ($t->invoice)<a href="{{ route('invoices.show', $t->invoice_id) }}">{{ $t->invoice->number }}</a>@else — @endif</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state icon="bi-bank" :title="__('Chưa có giao dịch nào')" :message="__('Khi SePay gửi giao dịch đầu tiên, giao dịch sẽ hiện ở đây.')" /></td></tr>
            @endforelse
            </tbody></table></div>
        <x-pagination-bar :paginator="$transactions" />
    </div>
@endsection
@push('scripts')
<script>
document.querySelector('[data-copy-webhook]')?.addEventListener('click', async () => {
    const input = document.getElementById('webhookUrl');
    try { await navigator.clipboard.writeText(input.value); toast('success', t('Đã sao chép địa chỉ webhook.')); }
    catch (e) { input.select(); toast('error', t('Không sao chép được, hãy chép thủ công.')); }
});
</script>
@endpush
