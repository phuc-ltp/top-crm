@extends('layouts.app')
@section('title', __('Chi nhánh'))
@section('content')
    <x-page-header :title="__('Chi nhánh')" :breadcrumbs="['Chi nhánh' => null]">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#branchModal" data-mode="create"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm chi nhánh') }}</button>
    </x-page-header>
    <div class="card" style="max-width: 1100px">
        <div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>{{ __('Mã') }}</th><th>{{ __('Tên chi nhánh') }}</th><th>{{ __('Địa chỉ') }}</th><th>{{ __('Điện thoại') }}</th><th>{{ __('Nhân viên') }}</th><th>{{ __('Trạng thái') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
            <tbody>
            @forelse ($branches as $b)
                <tr>
                    <td class="small text-muted">{{ $b->code ?? '—' }}</td><td class="fw-semibold">{{ $b->name }}</td><td class="small">{{ $b->address }}</td><td class="small">{{ $b->phone }}</td>
                    <td><a href="{{ route('employees.index', ['branch_id' => $b->id]) }}">{{ $b->profiles_count }}</a></td>
                    <td>@if ($b->is_active)<span class="badge badge-soft-success">{{ __('Đang hoạt động') }}</span>@else<span class="badge badge-soft-secondary">{{ __('Ngừng hoạt động') }}</span>@endif</td>
                    <td class="table-actions">
                        <button class="btn btn-light btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#branchModal" data-mode="edit" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"
                                data-action="{{ route('branches.update', $b) }}" data-name="{{ $b->name }}" data-code="{{ $b->code }}" data-address="{{ $b->address }}" data-phone="{{ $b->phone }}" data-active="{{ $b->is_active ? '1' : '0' }}"><i class="bi bi-pencil"></i></button>
                        <x-delete-button :action="route('branches.destroy', $b)" :message="__('Xóa chi nhánh :name?', ['name' => $b->name])" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state icon="bi-building" :title="__('Chưa có chi nhánh')" /></td></tr>
            @endforelse
            </tbody></table></div>
    </div>

    <div class="modal fade" id="branchModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('branches.store') }}" class="needs-validation" novalidate id="branchForm">
            @csrf <input type="hidden" name="_method" value="POST" id="branchMethod">
            <div class="modal-header"><h5 class="modal-title" id="branchTitle">{{ __('Thêm chi nhánh') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
            <div class="modal-body">
                <x-input name="name" :label="__('Tên chi nhánh')" required maxlength="150" />
                <x-input name="code" :label="__('Mã')" maxlength="30" pattern="[A-Za-z0-9_\-]+" />
                <x-input name="address" :label="__('Địa chỉ')" maxlength="500" />
                <x-input name="phone" :label="__('Điện thoại')" maxlength="30" />
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked><label class="form-check-label" for="is_active">{{ __('Đang hoạt động') }}</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Lưu') }}</button></div>
        </form>
    </div></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('branchModal');
    modal.addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget, form = document.getElementById('branchForm');
        const edit = b?.dataset.mode === 'edit';
        form.action = edit ? b.dataset.action : '{{ route('branches.store') }}';
        document.getElementById('branchMethod').value = edit ? 'PUT' : 'POST';
        document.getElementById('branchTitle').textContent = edit ? t('Sửa chi nhánh') : t('Thêm chi nhánh');
        ['name', 'code', 'address', 'phone'].forEach(f => form.querySelector(`[name="${f}"]`).value = edit ? (b.dataset[f] || '') : '');
        form.querySelector('[name="is_active"]').checked = edit ? b.dataset.active === '1' : true;
        form.classList.remove('was-validated');
    });
    @if ($errors->any()) bootstrap.Modal.getOrCreateInstance(modal).show(); @endif
});
</script>
@endpush
