@extends('layouts.app')
@section('title', __('Chi tiết công'))
@section('content')
    @php($tz = crm_timezone())
    <x-page-header :title="__('Công ngày :d — :name', ['d' => vn_date($record->work_date), 'name' => $record->user?->name])" :breadcrumbs="['Chấm công' => route('attendance.index'), 'Chi tiết' => null]" />
    <div class="row g-4">
        @foreach (['in' => 'Vào ca', 'out' => 'Tan ca'] as $t => $label)
            @php($p = $t === 'in' ? 'check_in' : 'check_out')
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><span>{{ __($label) }}</span>
                        @if ($record->{$p.'_within'} === false)<span class="badge badge-soft-warning">{{ __('Ngoài khu vực văn phòng') }}</span>@elseif ($record->{$p.'_within'})<span class="badge badge-soft-success">{{ __('Trong khu vực') }}</span>@endif</div>
                    <div class="card-body">
                        @if ($record->{$p.'_at'})
                            <div class="fs-4 fw-bold mb-2">{{ $record->{$p.'_at'}->timezone($tz)->format('H:i') }}</div>
                            @if ($record->{$p.'_selfie'})<img src="{{ route('attendance.records.selfie', [$record, $t]) }}" alt="{{ __('Ảnh selfie') }}" class="img-fluid rounded-3 mb-2" style="max-height: 260px">@endif
                            <dl class="detail-list small mb-0">
                                <dt>{{ __('Văn phòng gần nhất') }}</dt><dd>{{ ($t === 'in' ? $record->checkInLocation : $record->checkOutLocation)?->name ?? '—' }}</dd>
                                <dt>{{ __('Khoảng cách') }}</dt><dd>{{ $record->{$p.'_distance'} !== null ? number_format($record->{$p.'_distance'}).' m' : '—' }}@if ($record->{$p.'_accuracy'}) <span class="text-muted">({{ __('sai số GPS ~:m m', ['m' => $record->{$p.'_accuracy'}]) }})</span>@endif</dd>
                                @can('payroll.view_all')
                                    @if ($record->{$p.'_lat'} !== null)<dt>{{ __('Vị trí') }}</dt><dd><a href="https://www.openstreetmap.org/?mlat={{ $record->{$p.'_lat'} }}&mlon={{ $record->{$p.'_lng'} }}#map=18/{{ $record->{$p.'_lat'} }}/{{ $record->{$p.'_lng'} }}" target="_blank" rel="noopener noreferrer">{{ __('Xem trên bản đồ') }}</a></dd>@endif
                                @endcan
                            </dl>
                        @else
                            <div class="text-muted small">{{ __('Chưa chấm.') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="row g-4 mt-1">
        <div class="col-md-6">
            <div class="card"><div class="card-body">
                <dl class="detail-list mb-0">
                    <dt>{{ __('Trạng thái') }}</dt><dd>{!! badge(App\Models\AttendanceRecord::STATUSES, $record->status) !!}</dd>
                    <dt>{{ __('Giờ công') }}</dt><dd>{{ intdiv($record->worked_minutes, 60) }}h{{ str_pad((string) ($record->worked_minutes % 60), 2, '0', STR_PAD_LEFT) }}</dd>
                    <dt>{{ __('Đi trễ') }}</dt><dd>{{ __(':m phút', ['m' => $record->late_minutes]) }}</dd>
                    <dt>{{ __('Về sớm') }}</dt><dd>{{ __(':m phút', ['m' => $record->early_minutes]) }}</dd>
                    @if ($record->adjusted_at)<dt>{{ __('Đã điều chỉnh') }}</dt><dd class="small">{{ $record->adjuster?->name }} · {{ vn_date($record->adjusted_at, true) }}<div class="text-muted">{{ $record->adjusted_reason }}</div></dd>@endif
                </dl>
            </div></div>
        </div>
        @can('payroll.manage')
            <div class="col-md-6">
                <div class="card"><div class="card-header">{{ __('Điều chỉnh công') }}</div><div class="card-body">
                    <form method="POST" action="{{ route('attendance.records.adjust', $record) }}" class="needs-validation" novalidate>@csrf @method('PUT')
                        <div class="row">
                            <x-input name="check_in_time" type="time" :label="__('Giờ vào')" :value="$record->check_in_at?->timezone($tz)->format('H:i')" col="col-6 mb-3" />
                            <x-input name="check_out_time" type="time" :label="__('Giờ ra')" :value="$record->check_out_at?->timezone($tz)->format('H:i')" col="col-6 mb-3" />
                        </div>
                        <x-input name="reason" :label="__('Lý do điều chỉnh')" required maxlength="500" />
                        <button class="btn btn-primary btn-sm">{{ __('Lưu điều chỉnh') }}</button>
                    </form>
                </div></div>
            </div>
        @endcan
    </div>
@endsection
