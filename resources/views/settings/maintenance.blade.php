@extends('layouts.app')
@section('title', __('Tác vụ & sao lưu'))
@section('content')
    <x-page-header :title="__('Tác vụ & sao lưu')" :breadcrumbs="['Hệ thống' => route('settings.edit'), 'Tác vụ & sao lưu' => null]"
                   :subtitle="__('Tác vụ tự động (nhắc việc, SLA, nhắc hợp đồng / tài sản, sao lưu hằng ngày) chạy khi có lịch gọi mỗi phút. Chỉ quản trị nền tảng xem được trang này.')" />

    <div class="row g-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-body">
            <h6 class="fw-bold mb-2"><i class="bi bi-clock-history me-1 text-orange"></i>{{ __('Tác vụ tự động') }}</h6>
            <div class="alert {{ $cronHealthy ? 'alert-success' : 'alert-warning' }} py-2 small">
                @if ($cronHealthy)
                    <i class="bi bi-check-circle me-1"></i>{{ __('Đang chạy — lần gần nhất :time.', ['time' => vn_date($lastRun, true)]) }}
                @elseif ($lastRun)
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ __('Đã ngừng — lần gần nhất :time. Kiểm tra dịch vụ cron.', ['time' => vn_date($lastRun, true)]) }}
                @else
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ __('Chưa từng chạy — làm theo hướng dẫn bên dưới.') }}
                @endif
            </div>
            <label class="form-label small fw-semibold" for="cronUrl">{{ __('Đường dẫn cron (bí mật)') }}</label>
            <div class="input-group mb-2">
                <input type="text" class="form-control font-monospace small" value="{{ $cronUrl }}" readonly id="cronUrl">
                <button class="btn btn-dark" type="button" data-copy-cron><i class="bi bi-clipboard me-1"></i>{{ __('Sao chép') }}</button>
            </div>
            <form method="POST" action="{{ route('settings.maintenance.regenerate') }}" class="mb-3" data-confirm="{{ __('Tạo đường dẫn cron mới? Đường dẫn cũ ngừng hoạt động ngay.') }}" data-confirm-button="{{ __('Tạo mới') }}">@csrf
                <button class="btn btn-light btn-sm"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Tạo đường dẫn mới') }}</button>
            </form>
            <ol class="small mb-0 ps-3">
                <li>{{ __('Hosting có mục Cron Jobs: thêm lệnh chạy mỗi phút: php artisan schedule:run (trong thư mục dự án). Không có: dùng cách dưới.') }}</li>
                <li>{{ __('Đăng ký miễn phí tại cron-job.org → Create cronjob → URL = đường dẫn ở trên → Every 1 minute → Save.') }}</li>
                <li>{{ __('Vài phút sau tải lại trang này: khung trên chuyển xanh "Đang chạy".') }}</li>
            </ol>
        </div></div></div>

        <div class="col-lg-6"><div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-database-check me-1 text-orange"></i>{{ __('Sao lưu database') }}</h6>
                <form method="POST" action="{{ route('settings.maintenance.backup') }}">@csrf
                    <button class="btn btn-primary btn-sm" data-loading-text="{{ __('Đang sao lưu...') }}"><i class="bi bi-cloud-arrow-down me-1"></i>{{ __('Sao lưu ngay') }}</button>
                </form>
            </div>
            <p class="small text-muted">{{ __('Tự động mỗi ngày lúc 02:00 (khi tác vụ tự động đang chạy), giữ 14 bản gần nhất. Nên tải 1 bản về máy mỗi tuần.') }}</p>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Tệp') }}</th><th class="text-end">{{ __('Dung lượng') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($backups as $b)
                    <tr>
                        <td class="small">{{ vn_date(\Illuminate\Support\Carbon::createFromTimestamp($b['time']), true) }}<div class="text-muted font-monospace" style="font-size:.75rem">{{ $b['name'] }}</div></td>
                        <td class="text-end small text-nowrap">{{ number_format($b['bytes'] / 1024, 0, ',', '.') }} KB</td>
                        <td class="text-end"><a href="{{ route('settings.maintenance.download', $b['name']) }}" class="btn btn-light btn-sm"><i class="bi bi-download"></i> {{ __('Tải') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="small text-muted">{{ __('Chưa có bản sao lưu nào — bấm "Sao lưu ngay".') }}</td></tr>
                @endforelse
                </tbody></table></div>
        </div></div></div>
    </div>
@endsection
@push('scripts')
<script>
document.querySelector('[data-copy-cron]')?.addEventListener('click', async () => {
    const input = document.getElementById('cronUrl');
    try { await navigator.clipboard.writeText(input.value); toast('success', t('Đã sao chép đường dẫn cron.')); }
    catch (e) { input.select(); toast('error', t('Không sao chép được, hãy chép thủ công.')); }
});
</script>
@endpush
