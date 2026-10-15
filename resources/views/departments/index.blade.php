@extends('layouts.app')
@section('title', __('Phòng ban'))
@section('content')
    <x-page-header :title="__('Phòng ban')" :breadcrumbs="['Phòng ban' => null]">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deptModal" data-mode="create"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm phòng ban') }}</button>
    </x-page-header>
    <div class="card" style="max-width: 1000px">
        <div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>{{ __('Mã') }}</th><th>{{ __('Tên phòng ban') }}</th><th>{{ __('Mô tả') }}</th><th>{{ __('Nhân viên') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
            <tbody>
            @forelse ($departments as $d)
                <tr>
                    <td class="small text-muted">{{ $d->code }}</td><td class="fw-semibold">{{ $d->name }}</td><td class="small">{{ $d->description }}</td>
                    <td><a href="{{ route('users.index', ['department' => $d->id]) }}">{{ $d->users_count }}</a></td>
                    <td class="table-actions">
                        <button class="btn btn-light btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#deptModal" data-mode="edit"
                                data-action="{{ route('departments.update', $d) }}" data-name="{{ $d->name }}" data-code="{{ $d->code }}" data-description="{{ $d->description }}" title="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></button>
                        <x-delete-button :action="route('departments.destroy', $d)" :message="__('Xóa phòng ban :name?', ['name' => $d->name])" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state icon="bi-diagram-3" :title="__('Chưa có phòng ban')" /></td></tr>
            @endforelse
            </tbody></table></div>
    </div>

    <div class="modal fade" id="deptModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('departments.store') }}" class="needs-validation" novalidate id="deptForm">
            @csrf <input type="hidden" name="_method" value="POST" id="deptMethod">
            <div class="modal-header"><h5 class="modal-title" id="deptTitle">{{ __('Thêm phòng ban') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
            <div class="modal-body">
                <x-input name="code" :label="__('Mã phòng ban')" required maxlength="30" pattern="[A-Za-z0-9_\-]+" />
                <x-input name="name" :label="__('Tên phòng ban')" required maxlength="150" />
                <x-textarea name="description" :label="__('Mô tả')" maxlength="500" rows="2" col="mb-0" />
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Lưu') }}</button></div>
        </form>
    </div></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('deptModal');
    modal.addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget, form = document.getElementById('deptForm');
        const edit = b?.dataset.mode === 'edit';
        form.action = edit ? b.dataset.action : '{{ route('departments.store') }}';
        document.getElementById('deptMethod').value = edit ? 'PUT' : 'POST';
        document.getElementById('deptTitle').textContent = edit ? t('Sửa phòng ban') : t('Thêm phòng ban');
        ['name', 'code', 'description'].forEach(f => form.querySelector(`[name="${f}"]`).value = edit ? (b.dataset[f] || '') : '');
        form.classList.remove('was-validated');
    });
    @if ($errors->any()) bootstrap.Modal.getOrCreateInstance(modal).show(); @endif
});
</script>
@endpush
