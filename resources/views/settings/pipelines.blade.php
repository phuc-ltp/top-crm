@extends('layouts.app')
@section('title', __('Cấu hình pipeline'))
@section('content')
    <x-page-header :title="__('Cấu hình pipeline')" :breadcrumbs="['Cài đặt tổ chức' => route('settings.edit'), 'Cấu hình pipeline' => null]"
                   :subtitle="__('Pipeline bán hàng và giai đoạn của :org. Mỗi pipeline có giai đoạn riêng.', ['org' => $organization?->name])" />
    @include('settings._tabs')

    <div class="row g-4">
        <div class="col-xl-8">
            @foreach ($pipelines as $p)
                <div class="card mb-4">
                    <div class="card-header">
                        <span><i class="bi bi-kanban me-2 text-orange"></i>{{ $p->label }}
                            @if ($p->is_default)<span class="badge badge-soft-orange ms-1">{{ __('Mặc định') }}</span>@endif
                            @unless ($p->is_active)<span class="badge badge-soft-secondary ms-1">{{ __('Đang ẩn') }}</span>@endunless
                            <span class="small text-muted ms-1">· {{ __(':count cơ hội', ['count' => $p->opportunities_count]) }}</span></span>
                        <div class="d-flex gap-1">
                            <button class="btn btn-light btn-icon btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#pl-{{ $p->id }}" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></button>
                            @if (! $p->is_default && $p->opportunities_count === 0)
                                <x-delete-button :action="route('settings.pipelines.destroy', $p)" :message="__('Xóa pipeline :name?', ['name' => $p->name])" />
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('settings.pipelines.update', $p) }}" class="collapse card-body border-bottom needs-validation" id="pl-{{ $p->id }}" novalidate>
                        @csrf @method('PUT')
                        <div class="row g-2 align-items-end">
                            <x-input name="name" :id="'pl-name-'.$p->id" :label="__('Tên pipeline')" :value="$p->name" required maxlength="100" col="col-md-6" />
                            @unless ($p->is_default)
                                <div class="col-md-3 pb-2"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="pl-act-{{ $p->id }}" @checked($p->is_active)><label class="form-check-label small" for="pl-act-{{ $p->id }}">{{ __('Đang dùng') }}</label></div></div>
                                <div class="col-md-3 pb-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="pl-def-{{ $p->id }}"><label class="form-check-label small" for="pl-def-{{ $p->id }}">{{ __('Đặt làm mặc định') }}</label></div></div>
                            @endunless
                            <div class="col-12 text-end"><button class="btn btn-primary btn-sm">{{ __('Lưu') }}</button></div>
                        </div>
                    </form>
                    <div class="list-group list-group-flush">
                        @foreach ($p->stages as $st)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="min-w-0">
                                        <span class="badge" style="background: {{ $st->color }}">{{ $st->label }}</span>
                                        <span class="small text-muted ms-1">{{ $st->probability }}%</span>
                                        @if ($st->is_won)<span class="small text-success ms-1"><i class="bi bi-trophy"></i> {{ __('thắng') }}</span>@endif
                                        @if ($st->is_lost)<span class="small text-danger ms-1"><i class="bi bi-x-octagon"></i> {{ __('thất bại') }}</span>@endif
                                        <span class="small text-muted ms-1">· {{ __(':count cơ hội', ['count' => $st->opportunities_count]) }}</span>
                                    </div>
                                    <div class="d-flex gap-1 flex-shrink-0">
                                        <button class="btn btn-light btn-icon btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#ps-{{ $st->id }}" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-light btn-icon btn-sm text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#psd-{{ $st->id }}" title="{{ __('Xóa') }}" aria-label="{{ __('Xóa') }}"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('settings.pipelines.stages.update', $st) }}" class="collapse mt-3 needs-validation" id="ps-{{ $st->id }}" novalidate>
                                    @csrf @method('PUT')
                                    <div class="row g-2 align-items-end">
                                        <x-input name="name" :id="'ps-name-'.$st->id" :label="__('Tên giai đoạn')" :value="$st->name" required maxlength="100" col="col-md-4" />
                                        <x-input name="probability" :id="'ps-prob-'.$st->id" type="number" :label="__('Xác suất (%)')" :value="$st->probability" required min="0" max="100" col="col-md-2" />
                                        <div class="col-md-2"><label class="form-label" for="ps-color-{{ $st->id }}">{{ __('Màu') }}</label><input type="color" name="color" id="ps-color-{{ $st->id }}" value="{{ $st->color }}" class="form-control form-control-color w-100"></div>
                                        @unless ($st->isClosed())
                                            <x-input name="position" :id="'ps-pos-'.$st->id" type="number" :label="__('Thứ tự')" :value="$st->position" min="1" max="99" col="col-md-2" />
                                        @endunless
                                        <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">{{ __('Lưu') }}</button></div>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('settings.pipelines.stages.destroy', $st) }}" class="collapse mt-3" id="psd-{{ $st->id }}"
                                      data-confirm="{{ __('Xóa giai đoạn :name?', ['name' => $st->name]) }}" data-confirm-button="{{ __('Xóa') }}">
                                    @csrf @method('DELETE')
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">{{ __('Chuyển :count cơ hội sang', ['count' => $st->opportunities_count]) }}</span>
                                        <select name="move_to" class="form-select" required aria-label="{{ __('Chuyển cơ hội sang') }}">
                                            @foreach ($p->stages->where('id', '!=', $st->id) as $o)<option value="{{ $o->id }}">{{ $o->label }}</option>@endforeach
                                        </select>
                                        <button class="btn btn-danger">{{ __('Xóa giai đoạn') }}</button>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('settings.pipelines.stages.store', $p) }}" class="row g-2 align-items-end needs-validation" novalidate>
                            @csrf
                            <x-input name="name" :id="'new-st-'.$p->id" :label="__('Thêm giai đoạn')" required maxlength="100" :placeholder="__('VD: Khảo sát')" col="col-md-5" />
                            <x-input name="probability" :id="'new-st-prob-'.$p->id" type="number" :label="__('Xác suất (%)')" value="30" required min="0" max="100" col="col-md-2" />
                            <div class="col-md-2"><label class="form-label" for="new-st-color-{{ $p->id }}">{{ __('Màu') }}</label><input type="color" name="color" id="new-st-color-{{ $p->id }}" value="#8B5CF6" class="form-control form-control-color w-100"></div>
                            <div class="col-md-3"><button class="btn btn-soft-primary w-100"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm') }}</button></div>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header"><span><i class="bi bi-plus-circle me-2 text-orange"></i>{{ __('Tạo pipeline mới') }}</span></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.pipelines.store') }}" class="needs-validation" novalidate>
                        @csrf
                        <x-input name="name" id="new-pipeline" :label="__('Tên pipeline')" required maxlength="100" :placeholder="__('VD: Dự án, Bán lẻ, Gia hạn hợp đồng')" />
                        <button class="btn btn-primary w-100">{{ __('Tạo pipeline') }}</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0">{{ __('Pipeline mới có sẵn 4 giai đoạn mẫu (Cơ hội mới, Đang xử lý, Thắng, Thất bại) để bạn chỉnh sửa.') }}</p>
                </div>
            </div>
            <div class="card"><div class="card-header"><span><i class="bi bi-info-circle me-2 text-orange"></i>{{ __('Lưu ý') }}</span></div><div class="card-body small text-muted">
                <ul class="ps-3 mb-0">
                    <li>{{ __('Mỗi pipeline luôn cần ít nhất một giai đoạn đang mở, một giai đoạn thắng và một giai đoạn thất bại.') }}</li>
                    <li>{{ __('Giai đoạn mới được đặt trước các giai đoạn thắng / thất bại.') }}</li>
                    <li>{{ __('Xóa giai đoạn: các cơ hội đang ở đó được chuyển sang giai đoạn bạn chọn.') }}</li>
                    <li>{{ __('Pipeline còn cơ hội không xóa được — hãy ẩn để không dùng nữa.') }}</li>
                    <li>{{ __('Phễu trên bảng điều khiển hiển thị pipeline mặc định.') }}</li>
                </ul>
            </div></div>
        </div>
    </div>
@endsection
