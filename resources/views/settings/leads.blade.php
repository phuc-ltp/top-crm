@extends('layouts.app')
@section('title', __('Cấu hình lead'))
@section('content')
    <x-page-header :title="__('Cấu hình lead')" :breadcrumbs="['Cài đặt tổ chức' => route('settings.edit'), 'Cấu hình lead' => null]"
                   :subtitle="__('Giai đoạn lead, lý do thất bại và form thu lead từ website của :org.', ['org' => $organization?->name])" />
    @include('settings._tabs')

    <div class="row g-4">
        <div class="col-xl-7">
            {{-- Giai đoạn lead --}}
            <div class="card mb-4">
                <div class="card-header"><span><i class="bi bi-signpost-split me-2 text-orange"></i>{{ __('Giai đoạn lead') }}</span><span class="small text-muted">{{ $stages->count() }} / 15</span></div>
                <div class="list-group list-group-flush">
                    @foreach ($stages as $st)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="min-w-0">
                                    <span class="badge badge-soft-{{ $st->color }}">{{ __($st->name) }}</span>
                                    @if ($st->is_system)<span class="small text-muted ms-1"><i class="bi bi-lock"></i> {{ __('hệ thống') }}</span>@endif
                                    @if ($st->is_qualified)<span class="small text-muted ms-1"><i class="bi bi-patch-check"></i> {{ __('đủ điều kiện') }}</span>@endif
                                    <span class="small text-muted ms-1">· {{ __(':count lead', ['count' => $st->leads_count]) }}</span>
                                </div>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <button class="btn btn-light btn-icon btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#st-{{ $st->id }}" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></button>
                                    @unless ($st->is_system)
                                        <button class="btn btn-light btn-icon btn-sm text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#del-{{ $st->id }}" title="{{ __('Xóa') }}" aria-label="{{ __('Xóa') }}"><i class="bi bi-trash"></i></button>
                                    @endunless
                                </div>
                            </div>
                            <form method="POST" action="{{ route('settings.leads.stages.update', $st) }}" class="collapse mt-3 needs-validation" id="st-{{ $st->id }}" novalidate>
                                @csrf @method('PUT')
                                <div class="row g-2 align-items-end">
                                    <x-input name="name" :id="'st-name-'.$st->id" :label="__('Tên giai đoạn')" :value="$st->name" required maxlength="100" col="col-md-5" />
                                    <x-select name="color" :id="'st-color-'.$st->id" :label="__('Màu')" :options="App\Models\LeadStage::COLORS" :value="$st->color" required col="col-md-3" />
                                    @unless ($st->is_system)
                                        <x-input name="position" :id="'st-pos-'.$st->id" type="number" :label="__('Thứ tự')" :value="$st->position" min="1" max="99" col="col-md-2" />
                                        <div class="col-md-2 pb-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_qualified" value="1" id="st-q-{{ $st->id }}" @checked($st->is_qualified)><label class="form-check-label small" for="st-q-{{ $st->id }}">{{ __('Đủ điều kiện') }}</label></div></div>
                                    @endunless
                                    <div class="col-12 text-end"><button class="btn btn-primary btn-sm">{{ __('Lưu') }}</button></div>
                                </div>
                            </form>
                            @unless ($st->is_system)
                                <form method="POST" action="{{ route('settings.leads.stages.destroy', $st) }}" class="collapse mt-3" id="del-{{ $st->id }}"
                                      data-confirm="{{ __('Xóa giai đoạn :name?', ['name' => $st->name]) }}" data-confirm-button="{{ __('Xóa') }}">
                                    @csrf @method('DELETE')
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">{{ __('Chuyển :count lead sang', ['count' => $st->leads_count]) }}</span>
                                        <select name="move_to" class="form-select" required aria-label="{{ __('Chuyển lead sang') }}">
                                            @foreach ($stages->reject(fn ($o) => $o->id === $st->id || in_array($o->key, ['converted', 'lost'], true)) as $o)<option value="{{ $o->key }}">{{ __($o->name) }}</option>@endforeach
                                        </select>
                                        <button class="btn btn-danger">{{ __('Xóa giai đoạn') }}</button>
                                    </div>
                                </form>
                            @endunless
                        </div>
                    @endforeach
                </div>
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('settings.leads.stages.store') }}" class="row g-2 align-items-end needs-validation" novalidate>
                        @csrf
                        <x-input name="name" id="new-stage-name" :label="__('Thêm giai đoạn')" required maxlength="100" :placeholder="__('VD: Đã hẹn gặp')" col="col-md-6" />
                        <x-select name="color" id="new-stage-color" :label="__('Màu')" :options="App\Models\LeadStage::COLORS" value="purple" required col="col-md-3" />
                        <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm') }}</button></div>
                    </form>
                    <div class="form-text">{{ __('Giai đoạn mới được đặt trước "Đã chuyển đổi". "Đã chuyển đổi" chỉ đạt được qua chức năng chuyển đổi; "Thất bại" cần chọn lý do.') }}</div>
                </div>
            </div>

            {{-- Lý do thất bại --}}
            <div class="card">
                <div class="card-header"><span><i class="bi bi-x-octagon me-2 text-orange"></i>{{ __('Lý do thất bại') }}</span></div>
                <div class="list-group list-group-flush">
                    @foreach ($reasons as $r)
                        <div class="list-group-item">
                            <form method="POST" action="{{ route('settings.leads.reasons.update', $r) }}" class="row g-2 align-items-center">
                                @csrf @method('PUT')
                                <div class="col-md-5"><input type="text" name="name" value="{{ $r->name }}" class="form-control form-control-sm" maxlength="150" required aria-label="{{ __('Lý do') }}"></div>
                                <div class="col-md-3"><select name="applies_to" class="form-select form-select-sm" aria-label="{{ __('Áp dụng cho') }}">@foreach (App\Models\LostReason::APPLIES as $k => $label)<option value="{{ $k }}" @selected($r->applies_to === $k)>{{ __($label) }}</option>@endforeach</select></div>
                                <div class="col-md-2"><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="r-act-{{ $r->id }}" @checked($r->is_active)><label class="form-check-label small" for="r-act-{{ $r->id }}">{{ __('Dùng') }}</label></div></div>
                                <div class="col-md-2 d-flex gap-1 justify-content-end"><button class="btn btn-light btn-sm" title="{{ __('Lưu') }}" aria-label="{{ __('Lưu') }}"><i class="bi bi-check2"></i></button></div>
                            </form>
                            <div class="text-end mt-1"><x-delete-button :action="route('settings.leads.reasons.destroy', $r)" :message="__('Xóa lý do :name?', ['name' => $r->name])" /></div>
                        </div>
                    @endforeach
                </div>
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('settings.leads.reasons.store') }}" class="row g-2 align-items-end needs-validation" novalidate>
                        @csrf
                        <x-input name="name" id="new-reason" :label="__('Thêm lý do')" required maxlength="150" col="col-md-6" />
                        <x-select name="applies_to" id="new-reason-applies" :label="__('Áp dụng cho')" :options="App\Models\LostReason::APPLIES" value="both" required col="col-md-3" />
                        <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm') }}</button></div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Form website --}}
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><span><i class="bi bi-globe2 me-2 text-orange"></i>{{ __('Form thu lead từ website') }}</span>
                    <span class="badge badge-soft-{{ $form['enabled'] ? 'success' : 'secondary' }}">{{ $form['enabled'] ? __('Đang bật') : __('Đang tắt') }}</span></div>
                <div class="card-body">
                    <p class="small text-muted">{{ __('Trang đăng ký công khai: khách tự điền thông tin, hệ thống tạo lead và báo cho người nhận. Có chống spam (trường ẩn, kiểm tra thời gian điền, giới hạn số lần gửi).') }}</p>
                    @if ($form['enabled'] && $form['token'])
                        @php($url = route('lead-form.show', $form['token']))
                        <label class="form-label small" for="leadFormUrl">{{ __('Đường dẫn form') }}</label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control form-control-sm font-monospace" id="leadFormUrl" value="{{ $url }}" readonly>
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-light btn-sm" title="{{ __('Mở') }}"><i class="bi bi-box-arrow-up-right"></i></a>
                        </div>
                        <form method="POST" action="{{ route('settings.leads.form.token') }}" class="mb-3" data-confirm="{{ __('Tạo đường dẫn mới? Đường dẫn cũ sẽ ngừng hoạt động ngay.') }}">@csrf
                            <button class="btn btn-link btn-sm p-0"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Tạo đường dẫn mới (khi bị spam)') }}</button></form>
                        <div class="alert alert-info small py-2">{{ __('Hệ thống đang chạy trên máy của bạn, nên chỉ mở được từ máy này cho tới khi đưa lên Internet (Phase 7).') }}</div>
                    @endif
                    <form method="POST" action="{{ route('settings.leads.form') }}">
                        @csrf @method('PUT')
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" name="lead_form_enabled" value="1" id="lead_form_enabled" @checked($form['enabled'])>
                            <label class="form-check-label fw-semibold" for="lead_form_enabled">{{ __('Bật form thu lead') }}</label>
                        </div>
                        <x-select name="lead_form_assignee" :label="__('Người nhận lead')" :options="$users" :value="$form['assignee']" :placeholder="__('— Chủ sở hữu tổ chức —')" />
                        <x-select name="lead_form_source" :label="__('Nguồn lead')" :options="$sources" :value="$form['source']" :placeholder="__('— Website —')" />
                        <x-textarea name="lead_form_thanks" :label="__('Lời cảm ơn sau khi gửi')" :value="$form['thanks']" rows="2" maxlength="300" :help="__('Để trống để dùng lời cảm ơn mặc định.')" />
                        <button class="btn btn-primary w-100">{{ __('Lưu cấu hình form') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
