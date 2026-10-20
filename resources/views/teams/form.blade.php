@extends('layouts.app')
@section('title', $team->exists ? __('Sửa nhóm') : __('Tạo nhóm'))
@section('content')
    <x-page-header :title="$team->exists ? __('Sửa: :name', ['name' => $team->name]) : __('Tạo nhóm')" :breadcrumbs="['Nhóm' => route('teams.index'), ($team->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]" />
    @php($selected = array_map('intval', old('members', $team->exists ? $team->members->pluck('id')->all() : [])))
    <form method="POST" action="{{ $team->exists ? route('teams.update', $team) : route('teams.store') }}" class="needs-validation" novalidate>
        @csrf @if ($team->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card mb-4"><div class="card-header">{{ __('Thông tin nhóm') }}</div><div class="card-body">
                    <x-input name="name" :label="__('Tên nhóm')" :value="$team->name" required maxlength="150" />
                    <x-textarea name="description" :label="__('Mô tả')" :value="$team->description" rows="3" maxlength="1000" />
                    <x-select name="leader_id" :label="__('Trưởng nhóm')" :options="$users" :value="$team->leader_id" :placeholder="__('— Chưa chọn —')" :translate="false" />
                    <x-select name="department_id" :label="__('Phòng ban')" :options="$departments" :value="$team->department_id" :placeholder="__('— Không thuộc phòng ban —')" :translate="false" col="mb-0" />
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">{{ __('Thành viên') }} <span class="small text-muted fw-normal">({{ __('trưởng nhóm tự được thêm') }})</span></div>
                    <div class="card-body">
                        <input type="search" class="form-control form-control-sm mb-2" placeholder="{{ __('Tìm người...') }}" data-filter-list="#memberList" aria-label="{{ __('Tìm người') }}">
                        <div id="memberList" class="border rounded-3 p-2" style="max-height: 300px; overflow-y: auto">
                            @foreach ($users as $id => $name)
                                <div class="form-check" data-filter-item="{{ mb_strtolower($name) }}">
                                    <input class="form-check-input" type="checkbox" name="members[]" value="{{ $id }}" id="m{{ $id }}" @checked(in_array($id, $selected, true))>
                                    <label class="form-check-label small" for="m{{ $id }}">{{ $name }}</label>
                                </div>
                            @endforeach
                        </div>
                        @error('members.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Lưu nhóm') }}</button>
            <a href="{{ $team->exists ? route('teams.show', $team) : route('teams.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
        </div>
    </form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-filter-list]').forEach((input) => {
        const items = document.querySelectorAll(input.dataset.filterList + ' [data-filter-item]');
        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            items.forEach((el) => { el.hidden = term !== '' && !el.dataset.filterItem.includes(term); });
        });
    });
});
</script>
@endpush
