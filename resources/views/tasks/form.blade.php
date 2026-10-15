@extends('layouts.app')
@section('title', $task->exists ? 'Sửa công việc' : 'Tạo công việc')
@section('content')
    <x-page-header :title="$task->exists ? 'Sửa: '.$task->title : 'Tạo công việc'" :breadcrumbs="['Công việc' => route('tasks.index'), ($task->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]" />
    <form method="POST" action="{{ $task->exists ? route('tasks.update', $task) : route('tasks.store') }}" class="needs-validation" novalidate>
        @csrf @if ($task->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4"><div class="card-header">Nội dung công việc</div><div class="card-body">
                    <x-input name="title" label="Tiêu đề" :value="$task->title" required maxlength="255" />
                    <x-textarea name="description" label="Mô tả chi tiết" :value="$task->description" rows="5" maxlength="10000" />
                    <div class="row">
                        <x-select name="related_type" label="Liên quan đến" :options="App\Models\Task::RELATED_TYPES" :value="$task->related_type" placeholder="— Không —" col="col-md-4 mb-3" />
                        <div class="col-md-8 mb-3">
                            <label class="form-label" for="related_id">Đối tượng</label>
                            <select name="related_id" id="related_id" class="form-select @error('related_id') is-invalid @enderror">
                                <option value="">— Chọn —</option>
                                @foreach ($relatedOptions as $type => $items)
                                    @foreach ($items as $id => $label)
                                        <option value="{{ $id }}" data-type="{{ $type }}" @selected(old('related_type', $task->related_type) === $type && old('related_id', $task->related_id) == $id)>{{ $label }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            @error('related_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div></div>
                @unless ($task->exists)
                    <div class="card"><div class="card-header">Checklist <span class="small text-muted fw-normal">(mỗi dòng một mục)</span></div><div class="card-body" id="checklistBox">
                        @foreach (old('checklist', ['']) as $item)
                            <input type="text" name="checklist[]" class="form-control form-control-sm mb-2" value="{{ $item }}" placeholder="Nội dung mục checklist" maxlength="255">
                        @endforeach
                        <button type="button" class="btn btn-soft-primary btn-sm" id="addChecklist"><i class="bi bi-plus"></i> Thêm mục</button>
                    </div></div>
                @endunless
            </div>
            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-header">Thiết lập</div><div class="card-body">
                    <x-select name="status" label="Trạng thái" :options="App\Models\Task::STATUSES" :value="$task->status" required />
                    <x-select name="priority" label="Mức ưu tiên" :options="App\Models\Task::PRIORITIES" :value="$task->priority" required />
                    <x-input name="due_date" type="datetime-local" label="Hạn chót" :value="optional($task->due_date)->format('Y-m-d\TH:i')" />
                    @can('tasks.assign')
                        <x-select name="assigned_to" label="Giao cho" :options="$users" :value="$task->assigned_to" placeholder="— Chưa giao —" />
                    @endcan
                </div></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>Lưu công việc</button>
                    <a href="{{ $task->exists ? route('tasks.show', $task) : route('tasks.index') }}" class="btn btn-light">Hủy</a>
                </div>
            </div>
        </div>
    </form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const type = document.getElementById('related_type');
    const target = document.getElementById('related_id');
    const sync = () => {
        Array.from(target.options).forEach(o => { if (o.value) o.hidden = o.dataset.type !== type.value; });
        if (target.selectedOptions[0]?.hidden) target.value = '';
        target.disabled = !type.value;
        target.required = !!type.value;
    };
    type.addEventListener('change', sync); sync();
    document.getElementById('addChecklist')?.addEventListener('click', (e) => {
        const input = document.createElement('input');
        Object.assign(input, { type: 'text', name: 'checklist[]', className: 'form-control form-control-sm mb-2', placeholder: 'Nội dung mục checklist', maxLength: 255 });
        e.currentTarget.before(input); input.focus();
    });
});
</script>
@endpush
