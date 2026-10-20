@extends('layouts.app')
@section('title', $document->exists ? __('Sửa tài liệu') : __('Tải lên tài liệu'))
@section('content')
    <x-page-header :title="$document->exists ? __('Sửa: :name', ['name' => $document->title]) : __('Tải lên tài liệu')" :breadcrumbs="['Tài liệu' => route('documents.index'), ($document->exists ? 'Chỉnh sửa' : 'Tải lên') => null]" />
    <form method="POST" action="{{ $document->exists ? route('documents.update', $document) : route('documents.store') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
        @csrf @if ($document->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4"><div class="card-header">{{ __('Thông tin tài liệu') }}</div><div class="card-body">
                    <x-input name="title" :label="__('Tên tài liệu')" :value="$document->title" required maxlength="255" />
                    <x-textarea name="description" :label="__('Mô tả')" :value="$document->description" rows="3" maxlength="2000" />
                    @unless ($document->exists)
                        <x-input name="file" type="file" :label="__('Tệp tài liệu')" required
                                 :help="__('Cho phép: :types — tối đa :size MB. Loại tệp được kiểm tra theo nội dung thật.', ['types' => str_replace(',', ', ', config('crm.document_mimes')), 'size' => config('crm.document_max_kb') / 1024])" />
                    @endunless
                    <div class="row">
                        <x-select name="related_type" :label="__('Liên quan đến')" :options="['customer' => __('labels.customer'), 'opportunity' => __('Cơ hội'), 'project' => __('Dự án')]" :value="$document->related_type" :placeholder="__('— Không —')" :translate="false" col="col-md-4 mb-3" />
                        <div class="col-md-8 mb-3">
                            <label class="form-label" for="related_id">{{ __('Đối tượng') }}</label>
                            <select name="related_id" id="related_id" class="form-select @error('related_id') is-invalid @enderror">
                                <option value="">{{ __('— Chọn —') }}</option>
                                @foreach ($relatedOptions as $type => $items)
                                    @foreach ($items as $id => $label)<option value="{{ $id }}" data-type="{{ $type }}" @selected(old('related_type', $document->related_type) === $type && old('related_id', $document->related_id) == $id)>{{ $label }}</option>@endforeach
                                @endforeach
                            </select>
                            @error('related_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-header">{{ __('Phân loại & quyền xem') }}</div><div class="card-body">
                    <x-select name="category" :label="__('Danh mục')" :options="App\Models\Document::CATEGORIES" :value="$document->category" required />
                    <x-select name="visibility" :label="__('Phạm vi xem')" :options="App\Models\Document::VISIBILITIES" :value="$document->visibility" required />
                    <x-select name="team_id" :label="__('Nhóm được xem')" :options="$teams" :value="$document->team_id" :placeholder="__('— Chọn nhóm —')" :translate="false"
                              :help="__('Chỉ dùng khi phạm vi là Theo nhóm. Bạn chỉ chia sẻ được cho nhóm mình tham gia.')" />
                    <div class="small text-muted">{{ __('Riêng tư: chỉ bạn (và quản trị tài liệu). Toàn công ty: mọi người có quyền xem tài liệu.') }}</div>
                </div></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>{{ $document->exists ? __('Lưu') : __('Tải lên') }}</button>
                    <a href="{{ $document->exists ? route('documents.show', $document) : route('documents.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                </div>
            </div>
        </div>
    </form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const type = document.getElementById('related_type'), target = document.getElementById('related_id');
    const visibility = document.getElementById('visibility'), team = document.getElementById('team_id');
    const sync = () => {
        Array.from(target.options).forEach(o => { if (o.value) o.hidden = o.dataset.type !== type.value; });
        if (target.selectedOptions[0]?.hidden) target.value = '';
        target.disabled = !type.value; target.required = !!type.value;
        team.disabled = visibility.value !== 'team'; team.required = visibility.value === 'team';
    };
    type.addEventListener('change', sync); visibility.addEventListener('change', sync); sync();
});
</script>
@endpush
