@extends('layouts.app')
@section('title', __('Bản quyền phần mềm'))
@section('content')
    @php($ST = App\Models\SoftwareLicense::STATES)
    <x-page-header :title="__('Bản quyền phần mềm')" :breadcrumbs="['Tài sản' => route('assets.overview'), 'Bản quyền phần mềm' => null]"
                   :subtitle="__('Theo dõi giấy phép, số chỗ đã dùng và hạn gia hạn. Hệ thống nhắc người phụ trách trước 30 ngày, 7 ngày và đúng ngày hết hạn.')">
        @can('licenses.manage')<a href="{{ route('licenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm giấy phép') }}</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-key" tone="blue" :label="__('Giấy phép')" :value="$summary['total']" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-people" :label="__('Chỗ đã dùng / tổng')" :value="$summary['used'].' / '.$summary['seats']" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-hourglass-split" tone="red" :label="__('Sắp hết hạn / đã hết hạn')" :value="$summary['expiring'].' / '.$summary['expired']" :href="route('licenses.index', ['state' => 'expiring'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-cash-coin" tone="green" :label="__('Chi phí ước tính / năm')" :value="money($summary['yearly'])" /></div>
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2" data-auto-submit>
                <div class="col-12 col-md-6"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tìm tên phần mềm, nhà phát hành...') }}" aria-label="{{ __('Tìm kiếm') }}"></div>
                <div class="col-12 col-md-3"><select name="state" class="form-select" aria-label="{{ __('Tình trạng hạn') }}"><option value="">{{ __('Mọi tình trạng') }}</option>
                    @foreach ($ST as $k => $s)<option value="{{ $k }}" @selected(request('state') === $k)>{{ __($s[0]) }}</option>@endforeach</select></div>
            </form>
        </div>
        @if ($licenses->isEmpty())
            <x-empty-state icon="bi-key" :title="__('Chưa có giấy phép nào')" />
        @else
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Phần mềm') }}</th><th>{{ __('Loại') }}</th><th class="text-end">{{ __('Chỗ đã dùng') }}</th><th>{{ __('Hết hạn') }}</th><th>{{ __('Người phụ trách') }}</th><th class="text-end">{{ __('Chi phí') }}</th></tr></thead>
                <tbody>
                @foreach ($licenses as $l)
                    <tr>
                        <td class="fw-semibold"><a href="{{ route('licenses.show', $l) }}">{{ $l->name }}</a>
                            @if ($l->vendor || $l->edition)<div class="small text-muted fw-normal">{{ collect([$l->vendor, $l->edition])->filter()->join(' · ') }}</div>@endif</td>
                        <td>{!! badge(App\Models\SoftwareLicense::TYPES, $l->license_type) !!}</td>
                        <td class="text-end {{ $l->active_assignments_count >= $l->seats ? 'text-warning-emphasis fw-semibold' : '' }}">{{ $l->active_assignments_count }} / {{ $l->seats }}</td>
                        <td class="text-nowrap">@if ($l->expires_at){{ vn_date($l->expires_at) }} {!! badge($ST, $l->state) !!}@else<span class="text-muted">{{ __('Không thời hạn') }}</span>@endif</td>
                        <td class="small">{{ $l->owner?->name ?? '—' }}</td>
                        <td class="text-end text-nowrap">{{ money($l->cost) }}<div class="small text-muted">{{ label_of(App\Models\SoftwareLicense::CYCLES, $l->billing_cycle) }}</div></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
@endsection
