@extends('layouts.app')
@section('title', $contact->full_name)
@section('content')
    <x-page-header :title="$contact->full_name" :breadcrumbs="['Người liên hệ' => route('contacts.index'), $contact->full_name => null]">
        @can('update', $contact)<a href="{{ route('contacts.edit', $contact) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>@endcan
        @can('delete', $contact)<x-delete-button :action="route('contacts.destroy', $contact)" class="btn btn-light text-danger" :label="__('Xóa')" />@endcan
    </x-page-header>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <dl class="detail-list">
                    <dt>{{ __('Chức vụ') }}</dt><dd>{{ $contact->position ?? '—' }}</dd>
                    <dt>Email</dt><dd>{{ $contact->email ?? '—' }}</dd>
                    <dt>{{ __('Điện thoại') }}</dt><dd>{{ $contact->phone ?? '—' }}<x-contact-qr type="contact" :id="$contact->id" :phone="$contact->phone" /></dd>
                    <dt>{{ __('Công ty') }}</dt><dd>{{ $contact->company ?? '—' }}</dd>
                    <dt>{{ __('labels.customer') }}</dt><dd>@if ($contact->customer)<a href="{{ route('customers.show', $contact->customer) }}">{{ $contact->customer->name }}</a>@else — @endif</dd>
                    <dt>{{ __('Người tạo') }}</dt><dd>{{ $contact->creator?->name ?? '—' }}</dd>
                    <dt>{{ __('Ngày tạo') }}</dt><dd>{{ vn_date($contact->created_at, true) }}</dd>
                </dl>
                @if ($contact->notes)<hr><div class="small" style="white-space: pre-line">{{ $contact->notes }}</div>@endif
            </div></div>
        </div>
        <div class="col-lg-8">
            @include('partials.interactions', ['subject' => $contact, 'type' => 'contact', 'canAdd' => auth()->user()->can('update', $contact)])
        </div>
    </div>
@endsection
