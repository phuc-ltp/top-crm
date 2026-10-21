@extends('layouts.app')
@section('title', $license->name)
@section('content')
    @php($active = $assignments->whereNull('released_at'))
    <x-page-header :title="$license->name" :breadcrumbs="['Bản quyền phần mềm' => route('licenses.index'), $license->name => null]"
                   :subtitle="collect([$license->vendor, $license->edition])->filter()->join(' · ') ?: null">
        <span class="align-self-center">{!! badge(App\Models\SoftwareLicense::STATES, $license->state) !!}</span>
        @if ($manage)
            <a href="{{ route('licenses.edit', $license) }}" class="btn btn-light"><i class="bi bi-pencil me-1"></i>{{ __('Sửa / gia hạn') }}</a>
            @if ($active->isEmpty())<x-delete-button :action="route('licenses.destroy', $license)" class="btn btn-light text-danger" />@endif
        @endif
    </x-page-header>

    @if ($license->state !== 'active')
        <div class="alert alert-{{ $license->state === 'expired' ? 'danger' : 'warning' }} py-2"><i class="bi bi-hourglass-split me-1"></i>
            {{ $license->state === 'expired' ? __('Giấy phép đã hết hạn ngày :d.', ['d' => vn_date($license->expires_at)]) : __('Còn :n ngày đến hạn :d.', ['n' => $license->days_left, 'd' => vn_date($license->expires_at)]) }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5">{{ __('Loại') }}</dt><dd class="col-7">{!! badge(App\Models\SoftwareLicense::TYPES, $license->license_type) !!}</dd>
                    <dt class="col-5">{{ __('Chỗ đã dùng') }}</dt><dd class="col-7">{{ $active->count() }} / {{ $license->seats }}</dd>
                    <dt class="col-5">{{ __('Ngày mua') }}</dt><dd class="col-7">{{ $license->purchase_date ? vn_date($license->purchase_date) : '—' }}</dd>
                    <dt class="col-5">{{ __('Hết hạn') }}</dt><dd class="col-7">{{ $license->expires_at ? vn_date($license->expires_at) : __('Không thời hạn') }}</dd>
                    <dt class="col-5">{{ __('Chi phí') }}</dt><dd class="col-7">{{ money($license->cost) }} · {{ label_of(App\Models\SoftwareLicense::CYCLES, $license->billing_cycle) }}</dd>
                    <dt class="col-5">{{ __('Nhà cung cấp') }}</dt><dd class="col-7">{{ $license->supplier?->name ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Người phụ trách') }}</dt><dd class="col-7 mb-0">{{ $license->owner?->name ?? '—' }}</dd>
                </dl>
                @if ($license->notes)<hr><div class="small" style="white-space: pre-line">{{ $license->notes }}</div>@endif
            </div></div>

            @if ($manage)
                <div class="card"><div class="card-body">
                    <h6 class="mb-2">{{ __('Khóa bản quyền') }}</h6>
                    @if (session('license_key'))
                        <textarea class="form-control font-monospace small mb-2" rows="3" readonly aria-label="{{ __('Khóa bản quyền') }}">{{ session('license_key') }}</textarea>
                        <div class="small text-muted">{{ __('Khóa chỉ hiện một lần; tải lại trang sẽ ẩn đi.') }}</div>
                    @elseif (filled($license->license_key))
                        <div class="font-monospace text-muted mb-2">•••••-•••••-•••••</div>
                        <form method="POST" action="{{ route('licenses.reveal', $license) }}">@csrf<button class="btn btn-sm btn-light"><i class="bi bi-eye me-1"></i>{{ __('Hiện khóa') }}</button></form>
                    @else
                        <div class="small text-muted">{{ __('Chưa lưu khóa.') }}</div>
                    @endif
                </div></div>
            @endif
        </div>

        <div class="col-lg-8">
            @if ($manage && $license->state !== 'expired' && $active->count() < $license->seats)
                <div class="card mb-3"><div class="card-body">
                    <h6 class="mb-3">{{ __('Gán giấy phép') }}</h6>
                    <form method="POST" action="{{ route('licenses.assign', $license) }}" class="row g-2 align-items-end">@csrf
                        <x-select name="user_id" :label="__('Cho người dùng')" :options="$users" :placeholder="__('— Chọn người —')" :translate="false" col="col-md-4" />
                        <x-select name="asset_id" :label="__('hoặc cho thiết bị')" :options="$assets" :placeholder="__('— Chọn thiết bị —')" :translate="false" col="col-md-4" />
                        <div class="col-md-4"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>{{ __('Gán (còn :n chỗ)', ['n' => $license->seats - $active->count()]) }}</button></div>
                    </form>
                    @error('user_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div></div>
            @endif

            <div class="card">
                <div class="card-body pb-0"><h6>{{ __('Đang sử dụng & lịch sử gán') }}</h6></div>
                @if ($assignments->isEmpty())
                    <x-empty-state icon="bi-person-plus" :title="__('Chưa gán cho ai')" />
                @else
                    <div class="table-responsive"><table class="table align-middle mb-0 small">
                        <thead><tr><th>{{ __('Người / thiết bị') }}</th><th>{{ __('Gán lúc') }}</th><th>{{ __('Thu hồi') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($assignments as $a)
                            <tr class="{{ $a->released_at ? 'text-muted' : '' }}">
                                <td>@if ($a->user)<i class="bi bi-person me-1"></i>{{ $a->user->name }}@elseif ($a->asset)<i class="bi bi-pc-display me-1"></i>{{ $a->asset->code }} — {{ $a->asset->name }}@else — @endif
                                    @if ($a->note)<div class="text-muted">{{ $a->note }}</div>@endif</td>
                                <td class="text-nowrap">{{ vn_date($a->assigned_at, true) }}<div class="text-muted">{{ $a->assigner?->name }}</div></td>
                                <td class="text-nowrap">@if ($a->released_at){{ vn_date($a->released_at, true) }}<div class="text-muted">{{ $a->releaser?->name }}</div>@else<span class="badge badge-soft-success">{{ __('Đang dùng') }}</span>@endif</td>
                                <td class="text-end">
                                    @if ($manage && ! $a->released_at)
                                        <form method="POST" action="{{ route('licenses.release', [$license, $a]) }}" data-confirm="{{ __('Thu hồi giấy phép này?') }}" data-confirm-button="{{ __('Thu hồi') }}">@csrf<button class="btn btn-sm btn-light">{{ __('Thu hồi') }}</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>
    </div>
@endsection
