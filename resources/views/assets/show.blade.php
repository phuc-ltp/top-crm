@extends('layouts.app')
@section('title', $asset->code.' — '.$asset->name)
@section('content')
    @php($manage = auth()->user()->can('assets.manage'))
    @php($T = App\Models\AssetEvent::TYPES)
    @php($actions = collect(App\Services\AssetService::ACTIONS)->filter(fn ($a) => in_array($asset->status, $a[0], true))->keys())
    @php($actionMeta = [
        'assign' => ['Cấp phát', 'bi-person-check', 'primary'], 'recall' => ['Thu hồi', 'bi-arrow-return-left', 'light'],
        'borrow' => ['Cho mượn', 'bi-box-arrow-up-right', 'light'], 'return' => ['Nhận trả', 'bi-box-arrow-in-down-left', 'success'],
        'maintenance' => ['Đưa đi bảo trì', 'bi-tools', 'light'], 'repaired' => ['Bảo trì xong', 'bi-wrench-adjustable', 'success'],
        'status' => ['Đổi tình trạng', 'bi-exclamation-triangle', 'light'], 'dispose' => ['Thanh lý', 'bi-trash3', 'light'],
    ])
    <x-page-header :title="$asset->code.' — '.$asset->name" :breadcrumbs="$canView ? ['Tài sản' => route('assets.index'), $asset->code => null] : ['Tài sản của tôi' => route('assets.mine'), $asset->code => null]">
        <span class="align-self-center">{!! badge(App\Models\Asset::STATUSES, $asset->status) !!}</span>
        @if ($manage)
            @foreach ($actions as $a)
                <button class="btn btn-{{ $actionMeta[$a][2] }}" data-bs-toggle="modal" data-bs-target="#act-{{ $a }}"><i class="bi {{ $actionMeta[$a][1] }} me-1"></i>{{ __($actionMeta[$a][0]) }}</button>
            @endforeach
            <a href="{{ route('assets.edit', $asset) }}" class="btn btn-light" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></a>
            @unless (in_array($asset->status, ['in_use', 'borrowed'], true))<x-delete-button :action="route('assets.destroy', $asset)" class="btn btn-light text-danger" />@endunless
        @endif
    </x-page-header>

    @if ($asset->isOverdue())<div class="alert alert-danger py-2"><i class="bi bi-alarm me-1"></i>{{ __('Quá hạn trả từ :d.', ['d' => vn_date($asset->due_back_at)]) }}</div>@endif

    @if ($manage && $openAudits->isNotEmpty() && ! $asset->isDisposed())
        <div class="card mb-3 border-info"><div class="card-body py-2">
            <form method="POST" action="{{ route('assets.audits.check', $openAudits->first()) }}" class="d-flex flex-wrap align-items-center gap-2">@csrf
                <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                <span class="small"><i class="bi bi-clipboard-check me-1"></i>{{ __('Kiểm kê nhanh — :a', ['a' => $openAudits->first()->name]) }}</span>
                <button name="result" value="found" class="btn btn-sm btn-success">{{ __('Có mặt') }}</button>
                <button name="result" value="damaged" class="btn btn-sm btn-warning">{{ __('Có mặt, hư hỏng') }}</button>
            </form>
        </div></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5">{{ __('Nhóm') }}</dt><dd class="col-7">{{ $asset->category?->name ?? '—' }}</dd>
                    @if ($asset->serial_number)<dt class="col-5">{{ __('Số serial') }}</dt><dd class="col-7">{{ $asset->serial_number }}</dd>@endif
                    @if ($asset->model)<dt class="col-5">{{ __('Model') }}</dt><dd class="col-7">{{ $asset->model }}</dd>@endif
                    <dt class="col-5">{{ __('Người giữ') }}</dt><dd class="col-7">{{ $asset->holder?->name ?? '—' }}@if ($asset->due_back_at)<div class="text-muted">{{ __('Hạn trả :d', ['d' => vn_date($asset->due_back_at)]) }}</div>@endif</dd>
                    <dt class="col-5">{{ __('Vị trí') }}</dt><dd class="col-7">{{ collect([$asset->branch?->name, $asset->location])->filter()->join(' · ') ?: '—' }}</dd>
                    @if ($canView)
                        <dt class="col-5">{{ __('Nhà cung cấp') }}</dt><dd class="col-7">{{ $asset->supplier?->name ?? '—' }}</dd>
                        <dt class="col-5">{{ __('Ngày mua') }}</dt><dd class="col-7">{{ $asset->purchase_date ? vn_date($asset->purchase_date) : '—' }}</dd>
                    @endif
                    <dt class="col-5">{{ __('Bảo hành đến') }}</dt><dd class="col-7 mb-0">{{ $asset->warranty_until ? vn_date($asset->warranty_until) : '—' }}</dd>
                </dl>
                @if ($asset->description)<hr><div class="small" style="white-space: pre-line">{{ $asset->description }}</div>@endif
            </div></div>

            @if ($canView)
                <div class="card mb-3"><div class="card-body">
                    <h6 class="mb-3">{{ __('Giá trị & khấu hao') }}</h6>
                    <dl class="row small mb-2">
                        <dt class="col-6">{{ __('Nguyên giá') }}</dt><dd class="col-6 text-end">{{ money($asset->purchase_cost) }}</dd>
                        <dt class="col-6">{{ __('Khấu hao / tháng') }}</dt><dd class="col-6 text-end">{{ money($dep['monthly']) }}</dd>
                        <dt class="col-6">{{ __('Đã khấu hao') }}</dt><dd class="col-6 text-end">{{ money($dep['accumulated']) }} <span class="text-muted">({{ $dep['months_used'] }}/{{ $asset->depreciation_months }} {{ __('tháng') }})</span></dd>
                        <dt class="col-6">{{ __('Giá trị còn lại') }}</dt><dd class="col-6 text-end fw-semibold">{{ money($dep['book_value']) }}</dd>
                        @if ($asset->isDisposed())<dt class="col-6">{{ __('Giá trị thanh lý') }}</dt><dd class="col-6 text-end mb-0">{{ money($asset->disposal_value) }}</dd>@endif
                    </dl>
                    <div class="progress" style="height: 6px" role="progressbar" aria-label="{{ __('Đã khấu hao') }}" aria-valuenow="{{ $dep['percent'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $dep['percent'] }}%"></div></div>
                </div></div>

                <div class="card"><div class="card-body text-center">
                    <img src="{{ $qr }}" alt="{{ __('Mã QR của :c', ['c' => $asset->code]) }}" width="160" height="160" class="mb-2">
                    <div class="small text-muted mb-2">{{ __('Quét bằng camera điện thoại để mở trang này (cần đăng nhập).') }}</div>
                    <a href="{{ route('assets.qr', $asset) }}" class="btn btn-sm btn-light" download="{{ $asset->code }}.png"><i class="bi bi-download me-1"></i>{{ __('Tải ảnh QR') }}</a>
                    @if ($manage)
                        <form method="POST" action="{{ route('assets.labels') }}" class="d-inline">@csrf<input type="hidden" name="ids[]" value="{{ $asset->id }}"><button class="btn btn-sm btn-light"><i class="bi bi-printer me-1"></i>{{ __('In tem') }}</button></form>
                    @endif
                </div></div>
            @endif
        </div>

        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <h6 class="mb-3">{{ __('Lịch sử luân chuyển') }}</h6>
                <ul class="list-unstyled mb-0">
                    @foreach ($asset->events as $e)
                        <li class="d-flex gap-3 pb-3 mb-3 border-bottom">
                            <span class="badge badge-soft-{{ $T[$e->type][1] ?? 'secondary' }} align-self-start p-2"><i class="bi {{ $T[$e->type][2] ?? 'bi-dot' }}"></i></span>
                            <div class="flex-grow-1 small">
                                <div><span class="fw-semibold">{{ label_of($T, $e->type) }}</span>
                                    @if ($e->targetUser) — {{ $e->targetUser->name }}@endif
                                    @if ($e->to_status && $e->type === 'status') → {!! badge(App\Models\Asset::STATUSES, $e->to_status) !!}@endif
                                    @if ($e->condition) · {!! badge(App\Models\AssetEvent::CONDITIONS, $e->condition) !!}@endif
                                    @if ($e->cost !== null && $canView) · {{ money($e->cost) }}@endif</div>
                                @if ($e->note)<div class="text-body-secondary">{{ $e->note }}</div>@endif
                                <div class="text-muted">{{ vn_date($e->occurred_at, true) }} · {{ $e->user?->name ?? __('Hệ thống') }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div></div>
        </div>
    </div>

    @if ($manage)
        @foreach ($actions as $a)
            <div class="modal fade" id="act-{{ $a }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('assets.act', [$asset, $a]) }}" class="needs-validation" novalidate>@csrf
                    <div class="modal-header"><h5 class="modal-title">{{ __($actionMeta[$a][0]) }} — {{ $asset->code }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
                    <div class="modal-body">
                        @if (in_array($a, ['assign', 'borrow'], true))
                            <x-select name="target_user_id" id="target-{{ $a }}" :label="$a === 'assign' ? __('Cấp cho') : __('Người mượn')" :options="$users" required :translate="false" />
                        @endif
                        @if ($a === 'borrow')
                            <x-input name="due_back_at" id="due-{{ $a }}" type="date" :label="__('Hạn trả')" required :min="now()->toDateString()" :value="now()->addDays(7)->toDateString()" />
                        @endif
                        @if ($a === 'status')
                            <x-select name="status" id="status-{{ $a }}" :label="__('Tình trạng mới')" required :options="collect(App\Models\Asset::STATUSES)->only(array_diff(['available', 'broken', 'lost'], [$asset->status]))->all()" />
                        @endif
                        @if (in_array($a, ['assign', 'recall', 'borrow', 'return'], true))
                            <x-select name="condition" id="cond-{{ $a }}" :label="__('Tình trạng khi bàn giao')" :options="App\Models\AssetEvent::CONDITIONS" value="good" />
                        @endif
                        @if (in_array($a, ['maintenance', 'repaired', 'dispose'], true))
                            <x-input name="cost" id="cost-{{ $a }}" type="number" :label="$a === 'dispose' ? __('Giá trị thanh lý') : __('Chi phí')" min="0" step="1000" append="₫" />
                        @endif
                        <x-textarea name="note" id="note-{{ $a }}" :label="__('Ghi chú')" :required="in_array($a, ['status', 'dispose'], true)" maxlength="1000" rows="2" col="mb-0" />
                        @if ($a === 'dispose')<div class="small text-danger mt-2">{{ __('Thanh lý là thao tác cuối cùng: tài sản dừng khấu hao và không luân chuyển được nữa.') }}</div>@endif
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Xác nhận') }}</button></div>
                </form>
            </div></div></div>
        @endforeach
    @endif
@endsection
