@extends('layouts.app')
@section('title', __('Danh mục tài sản'))
@section('content')
    @php($manage = auth()->user()->can('assets.manage'))
    <x-page-header :title="__('Danh mục tài sản')" :breadcrumbs="['Tài sản' => route('assets.overview'), 'Danh mục' => null]">
        @if ($manage)
            <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-light"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('Xuất Excel') }}</a>
            <button type="submit" form="labelsForm" class="btn btn-light"><i class="bi bi-qr-code me-1"></i>{{ __('In tem QR') }}</button>
            <a href="{{ route('assets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm tài sản') }}</a>
        @endif
    </x-page-header>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2" data-auto-submit>
                <div class="col-12 col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tìm mã, tên, số serial...') }}" aria-label="{{ __('Tìm kiếm') }}"></div>
                <div class="col-6 col-md-3"><select name="status" class="form-select" aria-label="{{ __('Trạng thái') }}"><option value="">{{ __('Đang quản lý (trừ đã thanh lý)') }}</option>
                    @foreach (App\Models\Asset::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($s[0]) }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><select name="category" class="form-select" aria-label="{{ __('Nhóm') }}"><option value="">{{ __('Mọi nhóm') }}</option>
                    @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected((int) request('category') === $id)>{{ $name }}</option>@endforeach</select></div>
                <div class="col-12 col-md-3"><select name="holder" class="form-select" aria-label="{{ __('Người giữ') }}"><option value="">{{ __('Mọi người giữ') }}</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected((int) request('holder') === (int) $id)>{{ $name }}</option>@endforeach</select></div>
            </form>
        </div>
        @if ($assets->isEmpty())
            <x-empty-state icon="bi-pc-display" :title="__('Chưa có tài sản')" :message="$manage ? __('Bấm “Thêm tài sản” để bắt đầu quản lý thiết bị, nội thất, phương tiện...') : null" />
        @else
            <form method="POST" action="{{ route('assets.labels') }}" id="labelsForm">@csrf
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr>
                    @if ($manage)<th style="width: 36px"><input type="checkbox" class="form-check-input" data-check-all aria-label="{{ __('Chọn tất cả') }}"></th>@endif
                    <th>{{ __('Mã') }}</th><th>{{ __('Tên tài sản') }}</th><th>{{ __('Nhóm') }}</th><th>{{ __('Trạng thái') }}</th><th>{{ __('Người giữ / vị trí') }}</th><th class="text-end">{{ __('Nguyên giá') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($assets as $a)
                    <tr>
                        @if ($manage)<td><input type="checkbox" class="form-check-input" data-check-item name="ids[]" value="{{ $a->id }}" aria-label="{{ __('Chọn :c', ['c' => $a->code]) }}"></td>@endif
                        <td class="text-nowrap fw-semibold"><a href="{{ route('assets.show', $a) }}">{{ $a->code }}</a></td>
                        <td>{{ $a->name }}@if ($a->serial_number)<div class="small text-muted">S/N {{ $a->serial_number }}</div>@endif</td>
                        <td class="small">{{ $a->category?->name ?? '—' }}</td>
                        <td>{!! badge(App\Models\Asset::STATUSES, $a->status) !!}@if ($a->isOverdue())<div class="small text-danger">{{ __('Quá hạn trả :d', ['d' => vn_date($a->due_back_at)]) }}</div>@endif</td>
                        <td class="small">@if ($a->holder)<i class="bi bi-person me-1"></i>{{ $a->holder->name }}@endif
                            @if ($a->location || $a->branch)<div class="text-muted"><i class="bi bi-geo-alt me-1"></i>{{ collect([$a->branch?->name, $a->location])->filter()->join(' · ') }}</div>@endif</td>
                        <td class="text-end text-nowrap">{{ money($a->purchase_cost) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            </form>
            @if ($manage)<div class="card-body border-top small text-muted py-2">{{ __('Chọn tài sản rồi bấm “In tem QR”; không chọn thì in tem cho toàn bộ tài sản đang quản lý (tối đa 500).') }}</div>@endif
            <x-pagination-bar :paginator="$assets" />
        @endif
    </div>
@endsection

@push('scripts')
<script>
document.querySelector('[data-check-all]')?.addEventListener('change', (e) => document.querySelectorAll('[data-check-item]').forEach((c) => { c.checked = e.target.checked; }));
</script>
@endpush
