@extends('layouts.app')
@section('title', $project->exists ? __('Sửa dự án') : __('Tạo dự án'))
@section('content')
    <x-page-header :title="$project->exists ? __('Sửa: :name', ['name' => $project->name]) : __('Tạo dự án')" :breadcrumbs="['Dự án' => route('projects.index'), ($project->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]" />
    @php($selectedMembers = array_map('intval', old('members', $project->exists ? $project->members->pluck('id')->all() : [])))
    <form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="needs-validation" novalidate>
        @csrf @if ($project->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4"><div class="card-header">{{ __('Thông tin dự án') }}</div><div class="card-body">
                    <x-input name="name" :label="__('Tên dự án')" :value="$project->name" required maxlength="255" />
                    <x-textarea name="description" :label="__('Mô tả / mục tiêu')" :value="$project->description" rows="5" maxlength="10000" />
                    <x-select name="customer_id" :label="__('Khách hàng')" :options="$customers" :value="$project->customer_id" :placeholder="__('— Dự án nội bộ —')" :translate="false" />
                </div></div>
                <div class="card">
                    <div class="card-header">{{ __('Thành viên') }} <span class="small text-muted fw-normal">({{ __('thành viên xem được mọi công việc của dự án') }})</span></div>
                    <div class="card-body">
                        <input type="search" class="form-control form-control-sm mb-2" placeholder="{{ __('Tìm người...') }}" data-filter-list="#memberList" aria-label="{{ __('Tìm người') }}">
                        <div id="memberList" class="border rounded-3 p-2" style="max-height: 260px; overflow-y: auto">
                            @foreach ($users as $id => $name)
                                <div class="form-check" data-filter-item="{{ mb_strtolower($name) }}">
                                    <input class="form-check-input" type="checkbox" name="members[]" value="{{ $id }}" id="m{{ $id }}" @checked(in_array($id, $selectedMembers, true))>
                                    <label class="form-check-label small" for="m{{ $id }}">{{ $name }}</label>
                                </div>
                            @endforeach
                        </div>
                        @error('members.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-header">{{ __('Thiết lập') }}</div><div class="card-body">
                    <x-select name="manager_id" :label="__('Người quản lý')" :options="$users" :value="$project->manager_id" required :translate="false" />
                    <x-select name="status" :label="__('Trạng thái')" :options="App\Models\Project::STATUSES" :value="$project->status" required />
                    <x-select name="priority" :label="__('Mức ưu tiên')" :options="App\Models\Task::PRIORITIES" :value="$project->priority" required />
                    <div class="row">
                        <x-input name="start_date" type="date" :label="__('Ngày bắt đầu')" :value="optional($project->start_date)->format('Y-m-d')" col="col-6 mb-3" />
                        <x-input name="end_date" type="date" :label="__('Ngày kết thúc')" :value="optional($project->end_date)->format('Y-m-d')" col="col-6 mb-3" />
                    </div>
                    <x-input name="budget" type="number" :label="__('Ngân sách tham khảo')" :value="$project->budget !== null ? (float) $project->budget : null" min="0" step="1000" append="₫" />
                </div></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>{{ __('Lưu dự án') }}</button>
                    <a href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                </div>
            </div>
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
