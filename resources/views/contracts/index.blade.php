@extends('layouts.app')
@section('title', __('Hợp đồng'))
@section('content')
    @php($ST = App\Models\Contract::STATES)
    <x-page-header :title="__('Hợp đồng')" :breadcrumbs="['Bán hàng' => route('quotations.index'), 'Hợp đồng' => null]"
                   :subtitle="__('Theo dõi hợp đồng khách hàng, ngày hiệu lực / hết hạn và gia hạn. Người phụ trách được nhắc trước 30 ngày, 7 ngày và đúng ngày hết hạn.')">
        @can('export', App\Models\Contract::class)<a href="{{ route('contracts.export', request()->query()) }}" class="btn btn-light"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('Xuất Excel') }}</a>@endcan
        @can('create', App\Models\Contract::class)<a href="{{ route('contracts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm hợp đồng') }}</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-file-earmark-check" tone="green" :label="__('Đang hiệu lực')" :value="$summary['active']" :href="route('contracts.index', ['state' => 'active'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-cash-coin" :label="__('Giá trị đang hiệu lực')" :value="money($summary['value'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-hourglass-split" tone="orange" :label="__('Sắp hết hạn (30 ngày)')" :value="$summary['expiring']" :href="route('contracts.index', ['state' => 'expiring'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-x-octagon" tone="red" :label="__('Đã hết hạn')" :value="$summary['expired']" :href="route('contracts.index', ['state' => 'expired'])" /></div>
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2" data-auto-submit>
                <div class="col-12 col-md-5"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tìm mã, tên hợp đồng, khách hàng...') }}" aria-label="{{ __('Tìm kiếm') }}"></div>
                <div class="col-6 col-md-3"><select name="state" class="form-select" aria-label="{{ __('Tình trạng') }}"><option value="">{{ __('Mọi tình trạng') }}</option>
                    @foreach ($ST as $k => $s)@if ($k !== 'pending')<option value="{{ $k }}" @selected(request('state') === $k)>{{ __($s[0]) }}</option>@endif @endforeach</select></div>
                <div class="col-6 col-md-3"><select name="type" class="form-select" aria-label="{{ __('Loại') }}"><option value="">{{ __('Mọi loại') }}</option>
                    @foreach (App\Models\Contract::TYPES as $k => $t)<option value="{{ $k }}" @selected(request('type') === $k)>{{ __($t[0]) }}</option>@endforeach</select></div>
            </form>
        </div>
        @if ($contracts->isEmpty())
            <x-empty-state icon="bi-file-earmark-text" :title="__('Chưa có hợp đồng')" />
        @else
            <div class="table-responsive"><table class="table table-hover align-middle mb-0 text-nowrap">
                <thead><tr><th><x-sort-link column="code" :label="__('Mã')" /></th><th>{{ __('Hợp đồng') }}</th><th>{{ __('Khách hàng') }}</th><th class="text-end"><x-sort-link column="value" :label="__('Giá trị')" /></th>
                    <th><x-sort-link column="end_date" :label="__('Hết hạn')" /></th><th>{{ __('Tình trạng') }}</th><th>{{ __('Phụ trách') }}</th></tr></thead>
                <tbody>
                @foreach ($contracts as $c)
                    <tr>
                        <td class="fw-semibold"><a href="{{ route('contracts.show', $c) }}">{{ $c->code }}</a></td>
                        <td style="white-space: normal; min-width: 220px">{{ $c->title }}<div class="small text-muted">{{ label_of(App\Models\Contract::TYPES, $c->type) }}@if ($c->auto_renew) · {{ __('tự gia hạn') }}@endif</div></td>
                        <td>{{ $c->customer?->name }}</td>
                        <td class="text-end">{{ money($c->value) }}</td>
                        <td>{{ $c->end_date ? vn_date($c->end_date) : __('Không thời hạn') }}@if (in_array($c->state, ['expiring', 'active'], true) && $c->days_left !== null && $c->days_left <= 30)<div class="small text-warning-emphasis">{{ __('còn :n ngày', ['n' => $c->days_left]) }}</div>@endif</td>
                        <td>{!! badge($ST, $c->state) !!}</td>
                        <td class="small">{{ $c->assignee?->name ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <x-pagination-bar :paginator="$contracts" />
        @endif
    </div>
@endsection
