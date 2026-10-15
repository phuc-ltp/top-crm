@extends('layouts.app')
@section('title', $contact->exists ? 'Sửa người liên hệ' : 'Thêm người liên hệ')
@section('content')
    <x-page-header :title="$contact->exists ? 'Sửa: '.$contact->full_name : 'Thêm người liên hệ'" :breadcrumbs="['Người liên hệ' => route('contacts.index'), ($contact->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]" />
    <form method="POST" action="{{ $contact->exists ? route('contacts.update', $contact) : route('contacts.store') }}" class="needs-validation" novalidate>
        @csrf @if ($contact->exists) @method('PUT') @endif
        <div class="card" style="max-width: 860px">
            <div class="card-body">
                <div class="row">
                    <x-input name="full_name" label="Họ tên" :value="$contact->full_name" required maxlength="255" col="col-md-6 mb-3" />
                    <x-input name="position" label="Chức vụ" :value="$contact->position" maxlength="100" col="col-md-6 mb-3" />
                    <x-input name="email" type="email" label="Email" :value="$contact->email" maxlength="255" col="col-md-6 mb-3" />
                    <x-input name="phone" label="Số điện thoại" :value="$contact->phone" maxlength="30" pattern="[0-9+\-\s().]{6,30}" col="col-md-6 mb-3" />
                    <x-input name="company" label="Công ty" :value="$contact->company" maxlength="255" col="col-md-6 mb-3" />
                    <x-select name="customer_id" label="Khách hàng liên kết" :options="$customers" :value="$contact->customer_id" placeholder="— Không liên kết —" col="col-md-6 mb-3" />
                    <x-textarea name="notes" label="Ghi chú" :value="$contact->notes" maxlength="5000" col="col-12 mb-0" />
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2 justify-content-end py-3">
                <a href="{{ $contact->exists ? route('contacts.show', $contact) : route('contacts.index') }}" class="btn btn-light">Hủy</a>
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Lưu</button>
            </div>
        </div>
    </form>
@endsection
