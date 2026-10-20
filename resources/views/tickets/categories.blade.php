@extends('layouts.app')
@section('title', __('Danh mục ticket'))
@section('content')
    <x-page-header :title="__('Danh mục ticket')" :breadcrumbs="['Hỗ trợ' => route('tickets.index'), 'Danh mục' => null]">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" data-mode="create"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm danh mục') }}</button>
    </x-page-header>
    <div class="card" style="max-width: 1000px">
        <div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>{{ __('Tên danh mục') }}</th><th>{{ __('Mô tả') }}</th><th>{{ __('Số ticket') }}</th><th>{{ __('Trạng thái') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
            <tbody>
            @forelse ($categories as $c)
                <tr>
                    <td class="fw-semibold">{{ $c->name }}</td><td class="small">{{ $c->description }}</td>
                    <td><a href="{{ route('tickets.index', ['category_id' => $c->id]) }}">{{ $c->tickets_count }}</a></td>
                    <td>@if ($c->is_active)<span class="badge badge-soft-success">{{ __('Đang dùng') }}</span>@else<span class="badge badge-soft-secondary">{{ __('Ngừng dùng') }}</span>@endif</td>
                    <td class="table-actions">
                        <button class="btn btn-light btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#catModal" data-mode="edit" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"
                                data-action="{{ route('ticket-categories.update', $c) }}" data-name="{{ $c->name }}" data-description="{{ $c->description }}" data-active="{{ $c->is_active ? '1' : '0' }}"><i class="bi bi-pencil"></i></button>
                        <x-delete-button :action="route('ticket-categories.destroy', $c)" :message="__('Xóa danh mục :name? Ticket thuộc danh mục sẽ chuyển về Chưa phân loại.', ['name' => $c->name])" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state icon="bi-tags" :title="__('Chưa có danh mục')" /></td></tr>
            @endforelse
            </tbody></table></div>
    </div>

    <div class="modal fade" id="catModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('ticket-categories.store') }}" class="needs-validation" novalidate id="catForm">
            @csrf <input type="hidden" name="_method" value="POST" id="catMethod">
            <div class="modal-header"><h5 class="modal-title" id="catTitle">{{ __('Thêm danh mục') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
            <div class="modal-body">
                <x-input name="name" :label="__('Tên danh mục')" required maxlength="150" />
                <x-textarea name="description" :label="__('Mô tả')" maxlength="500" rows="2" />
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked><label class="form-check-label" for="is_active">{{ __('Đang dùng') }}</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Lưu') }}</button></div>
        </form>
    </div></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('catModal');
    modal.addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget, form = document.getElementById('catForm');
        const edit = b?.dataset.mode === 'edit';
        form.action = edit ? b.dataset.action : '{{ route('ticket-categories.store') }}';
        document.getElementById('catMethod').value = edit ? 'PUT' : 'POST';
        document.getElementById('catTitle').textContent = edit ? t('Sửa danh mục') : t('Thêm danh mục');
        ['name', 'description'].forEach(f => form.querySelector(`[name="${f}"]`).value = edit ? (b.dataset[f] || '') : '');
        form.querySelector('[name="is_active"]').checked = edit ? b.dataset.active === '1' : true;
        form.classList.remove('was-validated');
    });
    @if ($errors->any()) bootstrap.Modal.getOrCreateInstance(modal).show(); @endif
});
</script>
@endpush
