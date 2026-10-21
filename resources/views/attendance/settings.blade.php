@extends('layouts.app')
@section('title', __('Cài đặt chấm công'))
@section('content')
    <x-page-header :title="__('Cài đặt chấm công')" :breadcrumbs="['Chấm công' => route('attendance.index'), 'Cài đặt' => null]" />
    <div class="row g-4">
        <div class="col-lg-5">
            <form method="POST" action="{{ route('attendance.settings.update') }}" class="card needs-validation" novalidate>@csrf @method('PUT')
                <div class="card-header">{{ __('Giờ làm việc') }}</div>
                <div class="card-body">
                    <div class="row">
                        <x-input name="work_morning_start" type="time" :label="__('Vào ca sáng')" :value="$s['work_morning_start']" required col="col-6 mb-3" />
                        <x-input name="work_morning_end" type="time" :label="__('Hết ca sáng')" :value="$s['work_morning_end']" required col="col-6 mb-3" />
                        <x-input name="work_afternoon_start" type="time" :label="__('Vào ca chiều')" :value="$s['work_afternoon_start']" required col="col-6 mb-3" />
                        <x-input name="work_afternoon_end" type="time" :label="__('Hết ca chiều')" :value="$s['work_afternoon_end']" required col="col-6 mb-3" />
                        <x-input name="work_ot_start" type="time" :label="__('Bắt đầu tăng ca')" :value="$s['work_ot_start']" required col="col-6 mb-3" />
                        <x-input name="work_ot_end" type="time" :label="__('Kết thúc tăng ca')" :value="$s['work_ot_end']" required col="col-6 mb-3" />
                    </div>
                    <label class="form-label">{{ __('Ngày làm việc') }}</label>
                    @php($days = array_map('trim', explode(',', $s['work_days'])))
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach ([1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'] as $n => $l)
                            <input type="checkbox" class="btn-check" name="work_days[]" value="{{ $n }}" id="wd{{ $n }}" @checked(in_array((string) $n, $days, true))><label class="btn btn-outline-primary btn-sm" for="wd{{ $n }}">{{ __($l) }}</label>
                        @endforeach
                    </div>
                    <div class="row">
                        <x-input name="attendance_late_grace" type="number" :label="__('Cho phép trễ (phút)')" :value="$s['attendance_late_grace']" min="0" max="60" required col="col-6 mb-3" />
                        <x-input name="attendance_selfie_days" type="number" :label="__('Lưu ảnh selfie (ngày)')" :value="$s['attendance_selfie_days']" min="7" max="365" required col="col-6 mb-3" />
                    </div>
                    <button class="btn btn-primary">{{ __('Lưu cài đặt') }}</button>
                </div>
            </form>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center"><span>{{ __('Văn phòng / địa điểm chấm công') }}</span>
                    <button class="btn btn-soft-primary btn-sm" data-bs-toggle="modal" data-bs-target="#locModal" data-mode="create"><i class="bi bi-plus-lg"></i> {{ __('Thêm') }}</button></div>
                @if ($locations->isEmpty())
                    <x-empty-state icon="bi-geo-alt" :title="__('Chưa có văn phòng — nhân viên chấm công sẽ bị đánh dấu ngoài khu vực')" />
                @else
                    <div class="table-responsive"><table class="table mb-0 align-middle">
                        <thead><tr><th>{{ __('Tên') }}</th><th>{{ __('Tọa độ') }}</th><th class="text-end">{{ __('Bán kính') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                        <tbody>
                        @foreach ($locations as $l)
                            <tr>
                                <td class="small fw-semibold">{{ $l->name }}@unless ($l->is_active) <span class="badge badge-soft-secondary">{{ __('Ngừng dùng') }}</span>@endunless<div class="text-muted fw-normal">{{ $l->address }}</div></td>
                                <td class="small"><a href="https://www.openstreetmap.org/?mlat={{ $l->latitude }}&mlon={{ $l->longitude }}#map=17/{{ $l->latitude }}/{{ $l->longitude }}" target="_blank" rel="noopener noreferrer">{{ $l->latitude }}, {{ $l->longitude }}</a></td>
                                <td class="small text-end">{{ $l->radius_m }} m</td>
                                <td class="table-actions">
                                    <button class="btn btn-light btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#locModal" data-mode="edit" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"
                                            data-action="{{ route('attendance.locations.update', $l) }}" data-name="{{ $l->name }}" data-address="{{ $l->address }}" data-latitude="{{ $l->latitude }}" data-longitude="{{ $l->longitude }}" data-radius="{{ $l->radius_m }}" data-active="{{ $l->is_active ? '1' : '0' }}"><i class="bi bi-pencil"></i></button>
                                    <x-delete-button :action="route('attendance.locations.destroy', $l)" :message="__('Xóa văn phòng :name?', ['name' => $l->name])" />
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                <div class="card-body small text-muted border-top">{{ __('Mẹo: mở Google Maps, nhấn giữ đúng vị trí văn phòng để lấy tọa độ (vĩ độ, kinh độ). Bán kính 100–200 m phù hợp đa số tòa nhà.') }}</div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="locModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('attendance.locations.store') }}" class="needs-validation" novalidate id="locForm">
            @csrf <input type="hidden" name="_method" value="POST" id="locMethod">
            <div class="modal-header"><h5 class="modal-title" id="locTitle">{{ __('Thêm văn phòng') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
            <div class="modal-body">
                <x-input name="name" :label="__('Tên văn phòng')" required maxlength="150" />
                <x-input name="address" :label="__('Địa chỉ')" maxlength="500" />
                <div class="row"><x-input name="latitude" type="number" step="0.0000001" :label="__('Vĩ độ')" required col="col-6 mb-3" /><x-input name="longitude" type="number" step="0.0000001" :label="__('Kinh độ')" required col="col-6 mb-3" /></div>
                <x-input name="radius_m" type="number" :label="__('Bán kính (m)')" value="150" min="30" max="5000" required />
                <div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="loc_active" checked><label class="form-check-label" for="loc_active">{{ __('Đang dùng') }}</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Lưu') }}</button></div>
        </form>
    </div></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('locModal');
    modal.addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget, form = document.getElementById('locForm');
        const edit = b?.dataset.mode === 'edit';
        form.action = edit ? b.dataset.action : '{{ route('attendance.locations.store') }}';
        document.getElementById('locMethod').value = edit ? 'PUT' : 'POST';
        document.getElementById('locTitle').textContent = edit ? t('Sửa văn phòng') : t('Thêm văn phòng');
        ['name', 'address', 'latitude', 'longitude'].forEach(f => form.querySelector(`[name="${f}"]`).value = edit ? (b.dataset[f] || '') : '');
        form.querySelector('[name="radius_m"]').value = edit ? b.dataset.radius : 150;
        form.querySelector('#loc_active').checked = edit ? b.dataset.active === '1' : true;
        form.classList.remove('was-validated');
    });
    @if ($errors->hasAny(['name', 'latitude', 'longitude', 'radius_m'])) bootstrap.Modal.getOrCreateInstance(modal).show(); @endif
});
</script>
@endpush
