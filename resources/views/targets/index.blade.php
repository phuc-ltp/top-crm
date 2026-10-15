@extends('layouts.app')
@section('title', __('Chỉ tiêu doanh số'))
@section('content')
    <x-page-header :title="__('Chỉ tiêu doanh số')" :breadcrumbs="['Chỉ tiêu doanh số' => null]" :subtitle="__('Thực đạt = tiền đã thu của hóa đơn do nhân viên phụ trách trong tháng')">
        <form method="GET" class="d-flex gap-2">
            <select name="month" class="form-select form-select-sm" aria-label="{{ __('Tháng') }}">@for ($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @selected($m === $month)>{{ __('Tháng :n', ['n' => $m]) }}</option>@endfor</select>
            <input type="number" name="year" value="{{ $year }}" class="form-control form-control-sm" style="width:90px" min="2000" max="2100" aria-label="{{ __('Năm') }}">
            <button class="btn btn-primary btn-sm">{{ __('Xem') }}</button>
        </form>
    </x-page-header>

    <div class="row g-4">
        <div class="{{ $canManage ? 'col-lg-8' : 'col-12' }}">
            <div class="card">
                <div class="card-header">{{ __('Tháng :n', ['n' => $month.'/'.$year]) }}</div>
                @if ($targets->isEmpty())
                    <x-empty-state icon="bi-bullseye" :title="__('Chưa đặt chỉ tiêu cho tháng này')" />
                @else
                    <div class="table-responsive"><table class="table">
                        <thead><tr><th>{{ __('Nhân viên') }}</th><th class="text-end">{{ __('Chỉ tiêu') }}</th><th class="text-end">{{ __('Thực đạt') }}</th><th style="min-width:180px">{{ __('Tiến độ') }}</th>@if ($canManage)<th></th>@endif</tr></thead>
                        <tbody>
                        @foreach ($targets as $t)
                            @php($done = (float) ($actual[$t->user_id] ?? 0))
                            @php($pct = $t->target_amount > 0 ? round($done / $t->target_amount * 100, 1) : 0)
                            <tr>
                                <td><div class="d-flex align-items-center gap-2"><x-avatar :user="$t->user" size="sm" />{{ $t->user?->name }}</div></td>
                                <td class="text-end">{{ money($t->target_amount) }}</td>
                                <td class="text-end fw-semibold">{{ money($done) }}</td>
                                <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar {{ $pct >= 100 ? 'bg-success' : '' }}" style="width: {{ min(100, $pct) }}%"></div></div><span class="small fw-semibold">{{ $pct }}%</span></div></td>
                                @if ($canManage)<td class="text-end"><x-delete-button :action="route('targets.destroy', $t)" :message="__('Xóa chỉ tiêu này?')" /></td>@endif
                            </tr>
                        @endforeach
                        </tbody></table></div>
                @endif
            </div>
        </div>
        @if ($canManage)
            <div class="col-lg-4">
                <div class="card"><div class="card-header">{{ __('Đặt / cập nhật chỉ tiêu') }}</div><div class="card-body">
                    <form method="POST" action="{{ route('targets.store') }}" class="needs-validation" novalidate>@csrf
                        <x-select name="user_id" :label="__('Nhân viên')" :options="$users" required :placeholder="__('— Chọn —')" />
                        <div class="row">
                            <x-input name="month" type="number" :label="__('Tháng')" :value="$month" min="1" max="12" required col="col-6 mb-3" />
                            <x-input name="year" type="number" :label="__('Năm')" :value="$year" min="2000" max="2100" required col="col-6 mb-3" />
                        </div>
                        <x-input name="target_amount" type="number" :label="__('Chỉ tiêu (₫)')" min="0" step="1000000" required />
                        <button class="btn btn-primary w-100">{{ __('Lưu chỉ tiêu') }}</button>
                    </form>
                </div></div>
            </div>
        @endif
    </div>
@endsection
