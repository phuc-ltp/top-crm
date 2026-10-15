@extends('layouts.app')
@section('title', $ticket->exists ? __('Sửa ticket') : __('Tạo ticket'))
@section('content')
    <x-page-header :title="$ticket->exists ? __('Sửa ticket :code', ['code' => $ticket->code]) : __('Tạo ticket hỗ trợ')" :breadcrumbs="['Hỗ trợ' => route('tickets.index'), ($ticket->exists ? $ticket->code : 'Tạo mới') => null]" />
    <form method="POST" action="{{ $ticket->exists ? route('tickets.update', $ticket) : route('tickets.store') }}" class="needs-validation" novalidate>
        @csrf @if ($ticket->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8"><div class="card"><div class="card-body"><div class="row">
                <x-select name="customer_id" :label="__('labels.customer')" :options="$customers" :value="$ticket->customer_id" required :placeholder="__('— Chọn khách hàng —')" col="col-md-6 mb-3" />
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="contact_id">{{ __('Người liên hệ') }}</label>
                    <select name="contact_id" id="contact_id" class="form-select"><option value="">{{ __('— Không chọn —') }}</option>
                        @foreach ($contacts as $ct)<option value="{{ $ct->id }}" data-customer="{{ $ct->customer_id }}" @selected(old('contact_id', $ticket->contact_id) == $ct->id)>{{ $ct->full_name }}</option>@endforeach
                    </select>
                </div>
                <x-input name="subject" :label="__('Chủ đề')" :value="$ticket->subject" required maxlength="255" col="col-12 mb-3" />
                <x-textarea name="description" :label="__('Mô tả chi tiết')" :value="$ticket->description" required rows="6" maxlength="10000" col="col-12 mb-0" />
            </div></div></div></div>
            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-body">
                    <x-select name="priority" :label="__('Mức ưu tiên')" :options="App\Models\SupportTicket::PRIORITIES" :value="$ticket->priority" required />
                    <x-select name="status" :label="__('Trạng thái')" :options="App\Models\SupportTicket::STATUSES" :value="$ticket->status" required />
                    @can('tickets.assign')<x-select name="assigned_to" :label="__('Nhân viên xử lý')" :options="$users" :value="$ticket->assigned_to" :placeholder="__('— Chưa giao —')" />@endcan
                </div></div>
                <div class="d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>{{ __('Lưu ticket') }}</button>
                    <a href="{{ $ticket->exists ? route('tickets.show', $ticket) : route('tickets.index') }}" class="btn btn-light">{{ __('Hủy') }}</a></div>
            </div>
        </div>
    </form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const customer = document.getElementById('customer_id'), contact = document.getElementById('contact_id');
    const sync = () => { Array.from(contact.options).forEach(o => { if (o.value) o.hidden = o.dataset.customer !== customer.value; }); if (contact.selectedOptions[0]?.hidden) contact.value = ''; };
    customer.addEventListener('change', sync); sync();
});
</script>
@endpush
