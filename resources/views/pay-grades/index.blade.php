@extends('layouts.app')
@section('title', __('Bậc lương'))
@section('content')
    <x-page-header :title="__('Bậc lương')" :breadcrumbs="['Nhân sự' => route('employees.index'), 'Bậc lương' => null]"
                   :subtitle="__('Đơn giá lương theo giờ từ :min đến :max. Mỗi vai trò có một bậc mặc định; chỉnh riêng cho từng người trong hồ sơ nhân viên.', ['min' => money(config('crm.pay_rate_min')), 'max' => money(config('crm.pay_rate_max'))])">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gradeModal" data-mode="create"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm bậc lương') }}</button>
    </x-page-header>

    <div class="card" style="max-width: 1100px">
        <div class="table-responsive"><table class="table table-hover align-middle">
            <thead><tr><th>{{ __('Tên bậc') }}</th><th class="text-end">{{ __('Đơn giá / giờ') }}</th><th class="text-end">{{ __('Một ngày (8 giờ)') }}</th><th>{{ __('Mặc định cho vai trò') }}</th><th class="text-end">{{ __('Nhân viên') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
            <tbody>
            @foreach ($grades as $g)
                <tr>
                    <td class="fw-semibold">{{ $g->name }}@if ($g->description)<div class="small text-muted fw-normal">{{ $g->description }}</div>@endif</td>
                    <td class="text-end">{{ money($g->hourly_rate) }}</td>
                    <td class="text-end small text-muted">{{ money(bcmul((string) $g->hourly_rate, '8', 2)) }}</td>
                    <td class="small">{{ collect($g->roles ?? [])->map(fn ($r) => __($r))->implode(', ') ?: '—' }}</td>
                    <td class="text-end small">{{ $g->profiles_count }}</td>
                    <td class="table-actions">
                        <button class="btn btn-light btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#gradeModal" data-mode="edit" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"
                                data-action="{{ route('pay-grades.update', $g) }}" data-name="{{ $g->name }}" data-rate="{{ (float) $g->hourly_rate }}" data-description="{{ $g->description }}" data-roles="{{ json_encode($g->roles ?? []) }}"><i class="bi bi-pencil"></i></button>
                        <x-delete-button :action="route('pay-grades.destroy', $g)" :message="__('Xóa bậc lương :name?', ['name' => $g->name])" />
                    </td>
                </tr>
            @endforeach
            </tbody></table></div>
        <div class="card-body small text-muted border-top">{{ __('Tăng ca 18:00–21:00 trả theo mức độ công việc (cao 100.000 / vừa 70.000 / thấp 40.000 đ/giờ) nhưng không thấp hơn 150% lương giờ của người đó (Điều 98 Bộ luật Lao động 2019).') }}</div>
    </div>

    <div class="modal fade" id="gradeModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('pay-grades.store') }}" class="needs-validation" novalidate id="gradeForm">
            @csrf <input type="hidden" name="_method" value="POST" id="gradeMethod">
            <div class="modal-header"><h5 class="modal-title" id="gradeTitle">{{ __('Thêm bậc lương') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
            <div class="modal-body">
                <x-input name="name" :label="__('Tên bậc')" required maxlength="100" />
                <x-input name="hourly_rate" type="number" :label="__('Đơn giá / giờ')" required :min="config('crm.pay_rate_min')" :max="config('crm.pay_rate_max')" step="1000" append="₫" />
                <label class="form-label">{{ __('Mặc định cho vai trò') }}</label>
                <div class="border rounded-3 p-2 mb-3" style="max-height: 200px; overflow-y: auto">
                    @foreach ($roles as $r)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $r }}" id="role-{{ $loop->index }}"><label class="form-check-label small" for="role-{{ $loop->index }}">{{ __($r) }}</label></div>
                    @endforeach
                </div>
                <x-input name="description" :label="__('Mô tả')" maxlength="255" col="mb-0" />
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Lưu') }}</button></div>
        </form>
    </div></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('gradeModal');
    modal.addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget, form = document.getElementById('gradeForm');
        const edit = b?.dataset.mode === 'edit';
        form.action = edit ? b.dataset.action : '{{ route('pay-grades.store') }}';
        document.getElementById('gradeMethod').value = edit ? 'PUT' : 'POST';
        document.getElementById('gradeTitle').textContent = edit ? t('Sửa bậc lương') : t('Thêm bậc lương');
        form.querySelector('[name="name"]').value = edit ? b.dataset.name : '';
        form.querySelector('[name="hourly_rate"]').value = edit ? b.dataset.rate : '';
        form.querySelector('[name="description"]').value = edit ? (b.dataset.description || '') : '';
        const roles = edit ? JSON.parse(b.dataset.roles || '[]') : [];
        form.querySelectorAll('[name="roles[]"]').forEach(c => { c.checked = roles.includes(c.value); });
        form.classList.remove('was-validated');
    });
    @if ($errors->any()) bootstrap.Modal.getOrCreateInstance(modal).show(); @endif
});
</script>
@endpush
