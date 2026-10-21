@extends('layouts.app')
@section('title', __('Chấm công'))
@section('content')
    @php($tz = crm_timezone())
    @php($fmtH = fn ($m) => intdiv($m, 60).'h'.str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT))
    <x-page-header :title="__('Chấm công')" :breadcrumbs="['Chấm công' => null]" :subtitle="now()->timezone($tz)->translatedFormat('l, d/m/Y')" />

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold mb-1" id="clock">{{ now()->timezone($tz)->format('H:i') }}</div>
                    <div class="small text-muted mb-3">{{ __('Giờ làm: :a–:b và :c–:d', ['a' => App\Services\AttendanceService::setting('work_morning_start'), 'b' => App\Services\AttendanceService::setting('work_morning_end'), 'c' => App\Services\AttendanceService::setting('work_afternoon_start'), 'd' => App\Services\AttendanceService::setting('work_afternoon_end')]) }}</div>

                    <div class="d-flex justify-content-center gap-3 mb-3 small">
                        <div><div class="text-muted">{{ __('Vào ca') }}</div><div class="fs-5 fw-semibold">{{ $today?->check_in_at?->timezone($tz)->format('H:i') ?? '—' }}</div></div>
                        <div><div class="text-muted">{{ __('Tan ca') }}</div><div class="fs-5 fw-semibold">{{ $today?->check_out_at?->timezone($tz)->format('H:i') ?? '—' }}</div></div>
                        <div><div class="text-muted">{{ __('Giờ công') }}</div><div class="fs-5 fw-semibold">{{ $fmtH($today?->worked_minutes ?? 0) }}</div></div>
                    </div>

                    @if (! $consented)
                        <form method="POST" action="{{ route('attendance.consent') }}" class="text-start border rounded-3 p-3">@csrf
                            <div class="fw-semibold mb-1"><i class="bi bi-shield-check me-1"></i>{{ __('Đồng ý trước khi chấm công') }}</div>
                            <p class="small text-muted mb-2">{{ __('Khi chấm công, hệ thống chụp ảnh khuôn mặt và lấy vị trí GPS của bạn để xác nhận có mặt tại văn phòng. Ảnh chỉ người quản lý được phân quyền xem, lưu tối đa :days ngày rồi tự xóa; vị trí chỉ dùng để tính khoảng cách tới văn phòng.', ['days' => App\Services\AttendanceService::setting('attendance_selfie_days')]) }}</p>
                            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="agree" value="1" id="agree" required><label class="form-check-label small" for="agree">{{ __('Tôi đồng ý cho hệ thống thu thập ảnh selfie và vị trí khi chấm công.') }}</label></div>
                            <button class="btn btn-primary btn-sm">{{ __('Xác nhận') }}</button>
                        </form>
                    @elseif ($today?->check_out_at)
                        <div class="alert alert-success small mb-0"><i class="bi bi-check2-circle me-1"></i>{{ __('Bạn đã chấm công đủ hôm nay.') }}</div>
                    @else
                        @php($type = $today?->check_in_at ? 'out' : 'in')
                        <div id="punchBox" data-type="{{ $type }}" data-url="{{ route('attendance.punch') }}">
                            <div class="ratio ratio-4x3 bg-body-tertiary rounded-3 mb-2 overflow-hidden mx-auto" style="max-width: 360px">
                                <video id="cam" autoplay playsinline muted class="w-100 h-100" style="object-fit: cover; transform: scaleX(-1)"></video>
                            </div>
                            <canvas id="snap" class="d-none"></canvas>
                            <div class="small mb-2" id="geoStatus"><i class="bi bi-geo-alt me-1"></i>{{ __('Đang lấy vị trí...') }}</div>
                            <button type="button" class="btn btn-lg {{ $type === 'in' ? 'btn-primary' : 'btn-warning' }} w-100" id="punchBtn" disabled style="max-width: 360px">
                                <i class="bi {{ $type === 'in' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right' }} me-1"></i>{{ $type === 'in' ? __('Chấm công vào') : __('Chấm công ra') }}</button>
                            <div class="mt-2"><label class="small text-muted" for="selfieFile">{{ __('Không mở được camera? Chụp ảnh bằng máy:') }}</label>
                                <input type="file" id="selfieFile" accept="image/*" capture="user" class="form-control form-control-sm mx-auto" style="max-width: 360px"></div>
                            @unless ($hasLocations)<div class="small text-warning-emphasis mt-2">{{ __('Công ty chưa khai báo vị trí văn phòng — bản ghi sẽ được đánh dấu để quản lý xem xét.') }}</div>@endunless
                            <div class="small text-muted mt-2">{{ __('Cần mở trang bằng HTTPS và cho phép camera, vị trí trên điện thoại.') }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>{{ __('Công tháng :m/:y', ['m' => $month->month, 'y' => $month->year]) }}</span>
                    <form method="GET" class="d-flex gap-2" data-auto-submit><input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm" aria-label="{{ __('Tháng') }}"></form>
                </div>
                <div class="card-body border-bottom small d-flex flex-wrap gap-4">
                    <div>{{ __('Ngày công') }}: <strong>{{ $totals['days'] }}</strong></div>
                    <div>{{ __('Tổng giờ công') }}: <strong>{{ $fmtH($totals['worked']) }}</strong></div>
                    <div>{{ __('Tổng phút đi trễ') }}: <strong class="{{ $totals['late'] ? 'text-danger' : '' }}">{{ $totals['late'] }}</strong></div>
                </div>
                @if ($records->isEmpty())
                    <x-empty-state icon="bi-fingerprint" :title="__('Chưa có công trong tháng')" />
                @else
                    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>{{ __('Ngày') }}</th><th>{{ __('Vào') }}</th><th>{{ __('Ra') }}</th><th class="text-end">{{ __('Giờ công') }}</th><th>{{ __('Trạng thái') }}</th></tr></thead>
                        <tbody>
                        @foreach ($records as $r)
                            <tr>
                                <td class="small"><a href="{{ route('attendance.records.show', $r) }}">{{ vn_date($r->work_date) }}</a></td>
                                <td class="small">{{ $r->check_in_at?->timezone($tz)->format('H:i') ?? '—' }}@if ($r->check_in_within === false) <i class="bi bi-geo-alt text-warning" title="{{ __('Ngoài khu vực văn phòng') }}"></i>@endif</td>
                                <td class="small">{{ $r->check_out_at?->timezone($tz)->format('H:i') ?? '—' }}@if ($r->check_out_within === false) <i class="bi bi-geo-alt text-warning" title="{{ __('Ngoài khu vực văn phòng') }}"></i>@endif</td>
                                <td class="small text-end">{{ $fmtH($r->worked_minutes) }}</td>
                                <td>{!! badge(App\Models\AttendanceRecord::STATUSES, $r->status) !!}@if ($r->late_minutes) <span class="small text-danger">{{ __('trễ :m phút', ['m' => $r->late_minutes]) }}</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>
    </div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const clock = document.getElementById('clock');
    setInterval(() => { const d = new Date(); clock.textContent = d.toTimeString().slice(0, 5); }, 15000);

    const box = document.getElementById('punchBox');
    if (!box) return;
    const video = document.getElementById('cam'), canvas = document.getElementById('snap'), btn = document.getElementById('punchBtn');
    const geoStatus = document.getElementById('geoStatus'), fileInput = document.getElementById('selfieFile');
    let position = null, stream = null;

    const ready = () => { btn.disabled = !(position && (stream || fileInput.files.length)); };

    // Camera trước (selfie)
    if (navigator.mediaDevices?.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 } }, audio: false })
            .then((s) => { stream = s; video.srcObject = s; ready(); })
            .catch(() => { video.parentElement.classList.add('d-none'); });
    } else {
        video.parentElement.classList.add('d-none');
    }
    fileInput.addEventListener('change', ready);

    // Vị trí GPS (độ chính xác cao)
    if (navigator.geolocation) {
        navigator.geolocation.watchPosition((p) => {
            position = p.coords;
            geoStatus.innerHTML = '<i class="bi bi-geo-alt-fill text-success me-1"></i>' + t('Đã lấy vị trí (sai số ~:m m)').replace(':m', Math.round(p.coords.accuracy));
            ready();
        }, () => { geoStatus.innerHTML = '<i class="bi bi-geo-alt text-danger me-1"></i>' + t('Không lấy được vị trí. Hãy bật định vị và cho phép trình duyệt truy cập.'); },
        { enableHighAccuracy: true, maximumAge: 30000, timeout: 20000 });
    } else {
        geoStatus.textContent = t('Trình duyệt không hỗ trợ định vị.');
    }

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        const form = new FormData();
        form.append('type', box.dataset.type);
        form.append('latitude', position.latitude);
        form.append('longitude', position.longitude);
        form.append('accuracy', Math.round(position.accuracy || 0));
        if (fileInput.files.length) {
            form.append('selfie', fileInput.files[0]);
        } else {
            canvas.width = video.videoWidth || 640; canvas.height = video.videoHeight || 480;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.85));
            form.append('selfie', blob, 'selfie.jpg');
        }
        try {
            const res = await api(box.dataset.url, { method: 'POST', body: form });
            toast('success', res.message);
            if (res.warning) toast('warning', res.warning);
            stream?.getTracks().forEach((tr) => tr.stop());
            setTimeout(() => location.reload(), 1200);
        } catch (e) {
            toast('error', e.message);
            btn.disabled = false;
        }
    });
});
</script>
@endpush
