@extends('layouts.app')
@section('title', __('Trường tùy biến'))
@section('content')
    <x-page-header :title="__('Trường tùy biến')" :breadcrumbs="['Cài đặt tổ chức' => route('settings.edit'), 'Trường tùy biến' => null]"
                   :subtitle="__('Thêm trường thông tin riêng cho hồ sơ khách hàng của :org.', ['org' => $organization?->name])" />
    @include('settings._tabs')

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><span><i class="bi bi-ui-checks me-2 text-orange"></i>{{ __('Trường của khách hàng') }}</span><span class="small text-muted">{{ $fields->count() }} / {{ App\Models\CustomFieldDefinition::MAX_PER_ENTITY }}</span></div>
                @if ($fields->isEmpty())
                    <x-empty-state icon="bi-ui-checks" :title="__('Chưa có trường tùy biến')" :message="__('Ví dụ: Mã số thuế, Ngày sinh, Quy mô nhân sự, Ngành nghề...')" />
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($fields as $f)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="min-w-0">
                                        <div class="fw-semibold">{{ $f->label }}
                                            @if ($f->is_required)<span class="badge badge-soft-danger ms-1">{{ __('Bắt buộc') }}</span>@endif
                                            @unless ($f->is_active)<span class="badge badge-soft-secondary ms-1">{{ __('Đang ẩn') }}</span>@endunless
                                        </div>
                                        <div class="small text-muted">{{ __(App\Models\CustomFieldDefinition::TYPES[$f->type] ?? $f->type) }} · <code>{{ $f->key }}</code>
                                            @if ($f->type === 'select') · {{ implode(', ', $f->options ?? []) }}@endif</div>
                                    </div>
                                    <div class="d-flex gap-1 flex-shrink-0">
                                        <button class="btn btn-light btn-icon btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#cf-{{ $f->id }}" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></button>
                                        <x-delete-button :action="route('settings.custom-fields.destroy', $f)" :message="__('Xóa trường :label? Dữ liệu đã nhập vẫn được giữ trong hồ sơ nhưng không hiển thị nữa.', ['label' => $f->label])" />
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('settings.custom-fields.update', $f) }}" class="collapse mt-3 needs-validation" id="cf-{{ $f->id }}" novalidate>
                                    @csrf @method('PUT')
                                    <div class="row g-2">
                                        <x-input name="label" :id="'label-'.$f->id" :label="__('Tên trường')" :value="$f->label" required maxlength="100" col="col-md-6" />
                                        <x-input name="position" :id="'position-'.$f->id" type="number" :label="__('Thứ tự')" :value="$f->position" min="0" max="999" col="col-md-2" />
                                        <div class="col-md-4 d-flex flex-column justify-content-end gap-1 pb-1">
                                            <div class="form-check form-switch"><input type="hidden" name="is_required" value="0"><input class="form-check-input" type="checkbox" name="is_required" value="1" id="req-{{ $f->id }}" @checked($f->is_required)><label class="form-check-label small" for="req-{{ $f->id }}">{{ __('Bắt buộc nhập') }}</label></div>
                                            <div class="form-check form-switch"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act-{{ $f->id }}" @checked($f->is_active)><label class="form-check-label small" for="act-{{ $f->id }}">{{ __('Hiển thị') }}</label></div>
                                        </div>
                                        @if ($f->type === 'select')
                                            <x-textarea name="options" :id="'options-'.$f->id" :label="__('Các lựa chọn (mỗi dòng một giá trị)')" :value="implode(PHP_EOL, $f->options ?? [])" rows="3" col="col-12" required />
                                        @endif
                                        <div class="col-12 text-end"><button class="btn btn-primary btn-sm">{{ __('Lưu') }}</button></div>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><span><i class="bi bi-plus-circle me-2 text-orange"></i>{{ __('Thêm trường') }}</span></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.custom-fields.store') }}" class="needs-validation" novalidate>
                        @csrf
                        <x-input name="label" :label="__('Tên trường')" required maxlength="100" :placeholder="__('VD: Mã số thuế')" />
                        <x-select name="type" :label="__('Kiểu dữ liệu')" :options="App\Models\CustomFieldDefinition::TYPES" :value="old('type', 'text')" required />
                        <x-textarea name="options" :label="__('Các lựa chọn (chỉ cho Danh sách chọn, mỗi dòng một giá trị)')" rows="3" />
                        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_required" value="1" id="is_required"><label class="form-check-label" for="is_required">{{ __('Bắt buộc nhập') }}</label></div>
                        <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm trường') }}</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0">{{ __('Kiểu dữ liệu không đổi được sau khi tạo. Ẩn trường thay vì xóa nếu muốn giữ dữ liệu hiển thị về sau.') }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
