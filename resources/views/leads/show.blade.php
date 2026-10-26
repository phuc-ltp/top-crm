@extends('layouts.app')
@section('title', $lead->name)
@section('content')
    <x-page-header :title="$lead->name" :breadcrumbs="['Leads' => route('leads.index'), $lead->code => null]">
        @if (! $lead->isConverted())
            @can('update', $lead)<a href="{{ route('leads.convert', $lead) }}" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Chuyển đổi') }}</a>@endcan
        @endif
        @can('update', $lead)<a href="{{ route('leads.edit', $lead) }}" class="btn btn-light"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>@endcan
        @can('delete', $lead)<x-delete-button :action="route('leads.destroy', $lead)" class="btn btn-light text-danger" :label="__('Xóa')" />@endcan
    </x-page-header>

    @if ($lead->isConverted())
        <div class="alert alert-success d-flex flex-wrap gap-2 align-items-center">
            <i class="bi bi-check-circle-fill"></i> {{ __('Lead đã chuyển đổi ngày :date', ['date' => vn_date($lead->converted_at, true)]) }}
            @if ($lead->convertedCustomer) → {{ __('labels.customer') }} <a href="{{ route('customers.show', $lead->convertedCustomer) }}" class="fw-semibold">{{ $lead->convertedCustomer->name }}</a>@endif
            @if ($lead->convertedOpportunity) · {{ __('labels.opportunity') }} <a href="{{ route('opportunities.show', $lead->convertedOpportunity) }}" class="fw-semibold">{{ $lead->convertedOpportunity->title }}</a>@endif
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="position-relative d-inline-block">
                            <div class="fw-bold" style="font-size:2.4rem;color:var(--crm-primary)">{{ $lead->score }}</div>
                            <div class="small text-muted">{{ __('Điểm lead ·') }} <span class="badge badge-soft-{{ $lead->score_level[1] }}">{{ $lead->score_level[0] }}</span></div>
                        </div>
                    </div>
                    <dl class="detail-list">
                        <dt>{{ __('Mã') }}</dt><dd>{{ $lead->code }}</dd>
                        <dt>{{ __('Giai đoạn') }}</dt><dd>{!! badge(App\Models\Lead::statuses(), $lead->status) !!}
                            @if ($lead->status === 'lost' && $lead->lostReason)<div class="small-xs text-danger">{{ $lead->lostReason->name }}@if ($lead->lost_note) — {{ $lead->lost_note }}@endif</div>@endif</dd>
                        @if ($lead->qualified_at)<dt>{{ __('Đủ điều kiện từ') }}</dt><dd>{{ vn_date($lead->qualified_at, true) }}</dd>@endif
                        <dt>{{ __('Marketing') }}</dt><dd>@if ($lead->marketing_consent)<span class="badge badge-soft-success">{{ __('Đã đồng ý') }}</span><div class="small-xs text-muted">{{ $lead->marketing_consent_source }}</div>@else<span class="badge badge-soft-secondary">{{ __('Chưa đồng ý') }}</span>@endif</dd>
                        <dt>{{ __('Công ty') }}</dt><dd>{{ $lead->company ?? '—' }}</dd>
                        <dt>Email</dt><dd>{{ $lead->email ?? '—' }}</dd>
                        <dt>{{ __('Điện thoại') }}</dt><dd>{{ $lead->phone ?? '—' }}<x-contact-qr type="lead" :id="$lead->id" :phone="$lead->phone" /></dd>
                        <dt>{{ __('Nguồn') }}</dt><dd>{{ $lead->source?->label ?? "—" }}</dd>
                        <dt>{{ __('Giá trị ước tính') }}</dt><dd>{{ money($lead->estimated_value) }}</dd>
                        <dt>{{ __('Chiến dịch') }}</dt><dd>@forelse ($lead->campaigns as $c)<a href="{{ route('campaigns.show', $c) }}" class="d-block">{{ $c->name }}</a>@empty — @endforelse</dd>
                        <dt>{{ __('Phụ trách') }}</dt><dd>{{ $lead->assignee?->name ?? __('Chưa phân công') }}</dd>
                        <dt>{{ __('Người tạo') }}</dt><dd>{{ $lead->creator?->name ?? '—' }}</dd>
                        <dt>{{ __('Ngày tạo') }}</dt><dd>{{ vn_date($lead->created_at, true) }}</dd>
                    </dl>
                    @if ($lead->notes)<hr><div class="small" style="white-space: pre-line">{{ $lead->notes }}</div>@endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            @include('partials.interactions', ['subject' => $lead, 'type' => 'lead', 'canAdd' => auth()->user()->can('update', $lead)])
        </div>
    </div>
@endsection
