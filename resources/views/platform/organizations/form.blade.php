@extends('layouts.app')
@section('title', $organization->exists ? __('Sửa tổ chức') : __('Tạo tổ chức'))
@section('content')
    <x-page-header :title="$organization->exists ? __('Sửa: :name', ['name' => $organization->name]) : __('Tạo tổ chức')"
                   :breadcrumbs="['Quản lý tổ chức' => route('platform.organizations.index'), ($organization->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]" />
    <form method="POST" action="{{ $organization->exists ? route('platform.organizations.update', $organization) : route('platform.organizations.store') }}" class="needs-validation" novalidate>
        @csrf @if ($organization->exists) @method('PUT') @endif
        <div class="card" style="max-width: 900px">
            <div class="card-header"><span class="card-title">{{ __('Thông tin tổ chức') }}</span></div>
            <div class="card-body"><div class="row">
                <x-input name="name" :label="__('Tên tổ chức')" :value="$organization->name" required maxlength="255" col="col-md-6 mb-3" />
                <x-input name="slug" :label="__('Mã tổ chức (slug)')" :value="$organization->slug" :required="$organization->exists" maxlength="80" pattern="[A-Za-z0-9_\-]+" col="col-md-6 mb-3"
                         :help="$organization->exists ? __('Chữ không dấu, số, gạch ngang.') : __('Để trống để tự tạo từ tên tổ chức.')" />
                @if ($organization->exists)
                    <x-select name="status" :label="__('Trạng thái')" :options="App\Models\Organization::STATUSES" :value="$organization->status" required col="col-md-6 mb-3"
                              :help="__('Tạm ngưng: thành viên không thể làm việc trong tổ chức này cho tới khi kích hoạt lại.')" />
                @endif
            </div></div>

            @unless ($organization->exists)
                <div class="card-header border-top"><span class="card-title">{{ __('Chủ sở hữu tổ chức') }}</span></div>
                <div class="card-body"><div class="row">
                    <div class="col-12 small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>{{ __('Nếu email đã có tài khoản, người đó được thêm làm chủ sở hữu (không cần mật khẩu). Nếu chưa có, hệ thống tạo tài khoản mới.') }}</div>
                    <x-input name="owner_email" type="email" :label="__('Email chủ sở hữu')" required maxlength="255" col="col-md-6 mb-3" autocomplete="off" />
                    <x-input name="owner_name" :label="__('Họ tên chủ sở hữu')" maxlength="255" col="col-md-6 mb-3" />
                    <x-input name="owner_password" type="password" :label="__('Mật khẩu (tài khoản mới)')" minlength="10" col="col-md-6 mb-3" autocomplete="new-password"
                             :help="__('Mật khẩu tối thiểu 10 ký tự, có chữ hoa, chữ thường và chữ số.')" />
                    <x-input name="owner_password_confirmation" type="password" :label="__('Nhập lại mật khẩu')" minlength="10" col="col-md-6 mb-3" autocomplete="new-password" />
                </div></div>
            @endunless

            <div class="card-footer bg-transparent d-flex justify-content-end gap-2 py-3">
                <a href="{{ route('platform.organizations.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Lưu') }}</button>
            </div>
        </div>
    </form>
@endsection
