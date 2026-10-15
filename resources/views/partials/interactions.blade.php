{{-- Hoạt động & dòng thời gian dùng chung. Biến: $subject (model), $type (customer|lead|contact|opportunity), $canAdd --}}
@php
    $me = auth()->user();
    $planned = $subject->interactions()->planned()->with(['assignee:id,name', 'user:id,name'])->reorder('scheduled_at')->get();
    $timeline = app(\App\Services\TimelineService::class)->for($subject, $me);
    $memberOptions = \App\Support\Lookup::users();
    $uid = $type.'-'.$subject->id;
@endphp
<div class="card activity-panel">
    <div class="card-header"><span><i class="bi bi-activity me-2 text-orange"></i>{{ __('Hoạt động & dòng thời gian') }}</span>
        <a href="{{ route('activities.index') }}" class="small">{{ __('Tất cả hoạt động') }}</a>
    </div>
    <div class="card-body">
        @if ($canAdd ?? false)
            <ul class="nav nav-pills nav-pills-sm mb-2" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link {{ old('mode') === 'plan' ? '' : 'active' }}" data-bs-toggle="pill" data-bs-target="#act-log-{{ $uid }}" type="button" role="tab"><i class="bi bi-journal-check me-1"></i>{{ __('Ghi nhận') }}</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link {{ old('mode') === 'plan' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#act-plan-{{ $uid }}" type="button" role="tab"><i class="bi bi-calendar-plus me-1"></i>{{ __('Lên lịch') }}</button></li>
            </ul>
            <div class="tab-content mb-4">
                <div class="tab-pane fade {{ old('mode') === 'plan' ? '' : 'show active' }}" id="act-log-{{ $uid }}" role="tabpanel">
                    <form method="POST" action="{{ route('interactions.store', [$type, $subject->id]) }}" class="needs-validation p-3 rounded-3 bg-body-tertiary" novalidate>
                        @csrf <input type="hidden" name="mode" value="log">
                        <div class="row g-2">
                            <div class="col-md-4"><select name="type" class="form-select form-select-sm" required aria-label="{{ __('Loại hoạt động') }}">
                                @foreach (App\Models\Interaction::TYPES as $k => $t)<option value="{{ $k }}">{{ __($t[0]) }}</option>@endforeach</select></div>
                            <div class="col-md-4"><input type="text" name="subject" class="form-control form-control-sm" placeholder="{{ __('Tiêu đề (không bắt buộc)') }}" maxlength="255"></div>
                            <div class="col-md-4"><input type="datetime-local" name="occurred_at" class="form-control form-control-sm" value="{{ now()->timezone(crm_timezone())->format('Y-m-d\TH:i') }}" aria-label="{{ __('Thời điểm') }}"></div>
                            <div class="col-12"><textarea name="content" class="form-control form-control-sm" rows="2" placeholder="{{ __('Nội dung trao đổi, ghi chú...') }}" required maxlength="5000"></textarea><div class="invalid-feedback">{{ __('Vui lòng nhập nội dung.') }}</div></div>
                            <div class="col-12 text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Ghi nhận') }}</button></div>
                        </div>
                    </form>
                </div>
                <div class="tab-pane fade {{ old('mode') === 'plan' ? 'show active' : '' }}" id="act-plan-{{ $uid }}" role="tabpanel">
                    <form method="POST" action="{{ route('interactions.store', [$type, $subject->id]) }}" class="needs-validation p-3 rounded-3 bg-body-tertiary" novalidate>
                        @csrf <input type="hidden" name="mode" value="plan">
                        <div class="row g-2">
                            <div class="col-md-4"><select name="type" class="form-select form-select-sm" required aria-label="{{ __('Loại hoạt động') }}">
                                @foreach (App\Models\Interaction::TYPES as $k => $t)<option value="{{ $k }}" @selected($k === 'call')>{{ __($t[0]) }}</option>@endforeach</select></div>
                            <div class="col-md-4"><input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required value="{{ now()->timezone(crm_timezone())->addDay()->setTime(9, 0)->format('Y-m-d\TH:i') }}" aria-label="{{ __('Thời điểm hẹn') }}"><div class="invalid-feedback">{{ __('Vui lòng chọn thời điểm hẹn.') }}</div></div>
                            <div class="col-md-4"><select name="assigned_to" class="form-select form-select-sm" aria-label="{{ __('Người phụ trách') }}">
                                @foreach ($memberOptions as $id => $name)<option value="{{ $id }}" @selected($id === $me->id)>{{ $name }}</option>@endforeach</select></div>
                            <div class="col-12"><input type="text" name="subject" class="form-control form-control-sm" placeholder="{{ __('Việc cần làm, VD: Gọi lại báo giá') }}" maxlength="255"></div>
                            <div class="col-12"><textarea name="content" class="form-control form-control-sm" rows="2" placeholder="{{ __('Ghi chú chuẩn bị (không bắt buộc)') }}" maxlength="5000"></textarea></div>
                            <div class="col-12 text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-calendar-plus me-1"></i>{{ __('Lên lịch') }}</button></div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Sắp tới (đã lên lịch) --}}
        @if ($planned->isNotEmpty())
            <div class="small fw-semibold text-uppercase text-muted mb-2" style="letter-spacing:.06em">{{ __('Sắp tới (:count)', ['count' => $planned->count()]) }}</div>
            <ul class="list-unstyled mb-4">
                @foreach ($planned as $a)
                    <li class="activity-planned {{ $a->isOverdue() ? 'is-overdue' : '' }}">
                        <div class="d-flex gap-2 align-items-start">
                            <span class="tl-icon position-static flex-shrink-0"><i class="bi {{ App\Models\Interaction::TYPES[$a->type][1] ?? 'bi-dot' }}"></i></span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold small">{{ label_of(App\Models\Interaction::TYPES, $a->type) }}@if ($a->subject) · {{ $a->subject }}@endif</div>
                                <div class="small {{ $a->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">
                                    <i class="bi bi-clock me-1"></i>{{ vn_date($a->scheduled_at, true) }}@if ($a->isOverdue()) · {{ __('Quá hạn :time', ['time' => $a->scheduled_at->diffForHumans(null, true)]) }}@endif
                                    · {{ $a->assignee?->name ?? '—' }}
                                </div>
                                @if ($a->content)<div class="small mt-1" style="white-space: pre-line">{{ $a->content }}</div>@endif
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                @if ($a->completableBy($me))
                                    <button class="btn btn-light btn-icon btn-sm text-success" type="button" data-bs-toggle="collapse" data-bs-target="#done-{{ $a->id }}" title="{{ __('Đánh dấu hoàn thành') }}" aria-label="{{ __('Đánh dấu hoàn thành') }}"><i class="bi bi-check2"></i></button>
                                @endif
                                @if ($a->editableBy($me))
                                    <button class="btn btn-light btn-icon btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#edit-{{ $a->id }}" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" action="{{ route('interactions.destroy', $a) }}" data-confirm="{{ __('Xóa hoạt động này?') }}" data-confirm-button="{{ __('Xóa') }}">@csrf @method('DELETE')
                                        <button class="btn btn-light btn-icon btn-sm text-muted" title="{{ __('Xóa') }}" aria-label="{{ __('Xóa') }}"><i class="bi bi-x-lg"></i></button></form>
                                @endif
                            </div>
                        </div>
                        @if ($a->completableBy($me))
                            <form method="POST" action="{{ route('interactions.complete', $a) }}" class="collapse mt-2" id="done-{{ $a->id }}">@csrf @method('PATCH')
                                <div class="input-group input-group-sm">
                                    <input type="text" name="outcome" class="form-control" maxlength="1000" placeholder="{{ __('Kết quả (không bắt buộc), VD: Khách đồng ý hẹn demo') }}" aria-label="{{ __('Kết quả') }}">
                                    <button class="btn btn-success"><i class="bi bi-check2 me-1"></i>{{ __('Hoàn thành') }}</button>
                                </div>
                            </form>
                        @endif
                        @if ($a->editableBy($me))
                            <form method="POST" action="{{ route('interactions.update', $a) }}" class="collapse mt-2 needs-validation" id="edit-{{ $a->id }}" novalidate>@csrf @method('PUT')
                                <div class="row g-2">
                                    <div class="col-md-4"><select name="type" class="form-select form-select-sm" required>@foreach (App\Models\Interaction::TYPES as $k => $t)<option value="{{ $k }}" @selected($a->type === $k)>{{ __($t[0]) }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required value="{{ $a->scheduled_at?->copy()->timezone(crm_timezone())->format('Y-m-d\TH:i') }}" aria-label="{{ __('Thời điểm hẹn') }}"></div>
                                    <div class="col-md-4"><select name="assigned_to" class="form-select form-select-sm" aria-label="{{ __('Người phụ trách') }}">@foreach ($memberOptions as $id => $name)<option value="{{ $id }}" @selected($a->assigned_to === $id)>{{ $name }}</option>@endforeach</select></div>
                                    <div class="col-12"><input type="text" name="subject" class="form-control form-control-sm" value="{{ $a->subject }}" maxlength="255" aria-label="{{ __('Tiêu đề') }}"></div>
                                    <div class="col-12"><textarea name="content" class="form-control form-control-sm" rows="2" maxlength="5000" aria-label="{{ __('Nội dung') }}">{{ $a->content }}</textarea></div>
                                    <div class="col-12 text-end"><button class="btn btn-primary btn-sm">{{ __('Lưu') }}</button></div>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Dòng thời gian --}}
        <div class="small fw-semibold text-uppercase text-muted mb-2" style="letter-spacing:.06em">{{ __('Dòng thời gian') }}</div>
        @if ($timeline->isEmpty())
            <x-empty-state icon="bi-clock-history" :title="__('Chưa có hoạt động nào')" />
        @else
            <ul class="timeline">
                @foreach ($timeline as $item)
                    <li class="tl-{{ $item['kind'] }}">
                        <span class="tl-icon tone-{{ $item['tone'] }}"><i class="bi {{ $item['icon'] }}"></i></span>
                        <div class="d-flex justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="fw-semibold small">@if ($item['url'])<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif</div>
                                <div class="small text-muted">{{ vn_date($item['at'], true) }}@if ($item['meta']) · {{ $item['meta'] }}@endif</div>
                            </div>
                            @if ($item['activity'] && $item['activity']->editableBy($me))
                                <form method="POST" action="{{ route('interactions.destroy', $item['activity']) }}" data-confirm="{{ __('Xóa hoạt động này?') }}" data-confirm-button="{{ __('Xóa') }}">@csrf @method('DELETE')
                                    <button class="btn btn-link btn-sm text-muted p-0" title="{{ __('Xóa') }}" aria-label="{{ __('Xóa') }}"><i class="bi bi-x-lg"></i></button>
                                </form>
                            @endif
                        </div>
                        @if ($item['body'])<div class="mt-1 small" style="white-space: pre-line">{{ $item['body'] }}</div>@endif
                        @if ($item['activity']?->outcome)<div class="mt-1 small"><span class="text-muted">{{ __('Kết quả') }}:</span> {{ $item['activity']->outcome }}</div>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
