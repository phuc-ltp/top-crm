@extends('layouts.app')
@section('title', $contract->code.' — '.$contract->title)
@section('content')
    <x-page-header :title="$contract->code.' — '.$contract->title" :breadcrumbs="['Hợp đồng' => route('contracts.index'), $contract->code => null]">
        <span class="align-self-center">{!! badge(App\Models\Contract::STATES, $contract->state) !!}</span>
        @can('update', $contract)
            @if ($contract->status !== 'terminated')
                <a href="{{ route('contracts.edit', $contract) }}" class="btn btn-light"><i class="bi bi-pencil me-1"></i>{{ __('Sửa') }}</a>
                @if ($contract->end_date && $contract->renewals->isEmpty())
                    @can('create', App\Models\Contract::class)
                        <form method="POST" action="{{ route('contracts.renew', $contract) }}" class="d-inline">@csrf<button class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Gia hạn') }}</button></form>
                    @endcan
                @endif
                @if ($contract->status === 'signed')
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#terminateModal">{{ __('Chấm dứt') }}</button>
                @endif
            @endif
        @endcan
        @can('delete', $contract)<x-delete-button :action="route('contracts.destroy', $contract)" class="btn btn-light text-danger" />@endcan
    </x-page-header>

    @if ($contract->state === 'expiring')<div class="alert alert-warning py-2"><i class="bi bi-hourglass-split me-1"></i>{{ __('Còn :n ngày đến ngày hết hạn :d.', ['n' => $contract->days_left, 'd' => vn_date($contract->end_date)]) }}@if ($contract->renewals->isEmpty()) {{ __('Bấm “Gia hạn” để tạo hợp đồng nối tiếp.') }}@endif</div>@endif
    @if ($contract->state === 'expired')<div class="alert alert-danger py-2">{{ __('Hợp đồng đã hết hạn ngày :d.', ['d' => vn_date($contract->end_date)]) }}</div>@endif
    @if ($contract->status === 'terminated')<div class="alert alert-secondary py-2">{{ __('Đã chấm dứt ngày :d: :r', ['d' => vn_date($contract->terminated_on), 'r' => $contract->termination_reason]) }}</div>@endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5">{{ __('Khách hàng') }}</dt><dd class="col-7">@if ($contract->customer)<a href="{{ route('customers.show', $contract->customer) }}">{{ $contract->customer->name }}</a>@endif</dd>
                    <dt class="col-5">{{ __('Người đại diện') }}</dt><dd class="col-7">{{ $contract->contact?->full_name ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Loại') }}</dt><dd class="col-7">{{ label_of(App\Models\Contract::TYPES, $contract->type) }}</dd>
                    <dt class="col-5">{{ __('Giá trị') }}</dt><dd class="col-7 fw-semibold">{{ money($contract->value) }}</dd>
                    <dt class="col-5">{{ __('Ngày ký') }}</dt><dd class="col-7">{{ $contract->signed_date ? vn_date($contract->signed_date) : '—' }}</dd>
                    <dt class="col-5">{{ __('Hiệu lực') }}</dt><dd class="col-7">{{ $contract->start_date ? vn_date($contract->start_date) : '—' }} → {{ $contract->end_date ? vn_date($contract->end_date) : __('Không thời hạn') }}</dd>
                    <dt class="col-5">{{ __('Tự gia hạn') }}</dt><dd class="col-7">{{ $contract->auto_renew ? __('Có') : __('Không') }}</dd>
                    <dt class="col-5">{{ __('Cơ hội') }}</dt><dd class="col-7">@if ($contract->opportunity)<a href="{{ route('opportunities.show', $contract->opportunity) }}">{{ $contract->opportunity->title }}</a>@else — @endif</dd>
                    <dt class="col-5">{{ __('Phụ trách') }}</dt><dd class="col-7 mb-0">{{ $contract->assignee?->name ?? '—' }}</dd>
                </dl>
            </div></div>
            @if ($contract->renewedFrom || $contract->renewals->isNotEmpty())
                <div class="card"><div class="card-body small">
                    <h6 class="mb-2">{{ __('Chuỗi gia hạn') }}</h6>
                    @if ($contract->renewedFrom)<div>{{ __('Gia hạn từ') }}: <a href="{{ route('contracts.show', $contract->renewedFrom) }}">{{ $contract->renewedFrom->code }}</a></div>@endif
                    @foreach ($contract->renewals as $r)<div>{{ __('Bản gia hạn') }}: <a href="{{ route('contracts.show', $r) }}">{{ $r->code }}</a> {!! badge(App\Models\Contract::STATES, $r->state) !!}</div>@endforeach
                </div></div>
            @endif
        </div>

        <div class="col-lg-8">
            @if ($contract->terms)
                <div class="card mb-3"><div class="card-body"><h6 class="mb-2">{{ __('Điều khoản chính / ghi chú') }}</h6><div class="small" style="white-space: pre-line">{{ $contract->terms }}</div></div></div>
            @endif
            <div class="card">
                <div class="card-body pb-0 d-flex justify-content-between align-items-center"><h6 class="mb-0">{{ __('Tệp hợp đồng') }}</h6>
                    @can('documents.create')<a href="{{ route('documents.create', ['related_type' => 'contract', 'related_id' => $contract->id]) }}" class="btn btn-sm btn-light"><i class="bi bi-upload me-1"></i>{{ __('Tải lên bản hợp đồng') }}</a>@endcan</div>
                @if ($documents->isEmpty())
                    <x-empty-state icon="bi-file-earmark-arrow-up" :title="__('Chưa có tệp')" :message="__('Đính kèm bản hợp đồng đã ký (PDF / ảnh scan), phụ lục, biên bản thanh lý...')" />
                @else
                    <div class="table-responsive"><table class="table align-middle mb-0 small mt-2">
                        <tbody>@foreach ($documents as $d)<tr><td><i class="bi bi-file-earmark me-1"></i><a href="{{ route('documents.show', $d) }}">{{ $d->title }}</a></td>
                            <td class="text-muted">{{ $d->latestVersion ? 'v'.$d->latestVersion->version : '' }}</td><td class="text-end text-muted">{{ vn_date($d->created_at) }}</td></tr>@endforeach</tbody>
                    </table></div>
                @endif
            </div>
        </div>
    </div>

    @can('update', $contract)
        @if ($contract->status === 'signed')
            <div class="modal fade" id="terminateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('contracts.terminate', $contract) }}" class="needs-validation" novalidate>@csrf
                    <div class="modal-header"><h5 class="modal-title">{{ __('Chấm dứt hợp đồng :c', ['c' => $contract->code]) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
                    <div class="modal-body">
                        <x-input name="terminated_on" type="date" :label="__('Ngày chấm dứt')" required :value="now()->toDateString()" />
                        <x-textarea name="termination_reason" :label="__('Lý do chấm dứt')" required maxlength="500" rows="2" col="mb-0" />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-danger">{{ __('Chấm dứt hợp đồng') }}</button></div>
                </form>
            </div></div></div>
        @endif
    @endcan
@endsection
