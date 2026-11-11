@extends('layouts.app')
@section('title', __('Yêu cầu tham gia'))
@section('content')
    <x-page-header :title="__('Yêu cầu tham gia')" :breadcrumbs="['Người dùng' => route('users.index'), 'Yêu cầu tham gia' => null]"
                   :subtitle="__('Người tự tạo tài khoản ở trang đăng nhập (đã xác thực email). Duyệt để tạo tài khoản với vai trò bạn chọn.')" />

    @unless ($signupOpen)
        <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>{{ __('Công ty này chưa nhận đăng ký trực tuyến. Quản trị nền tảng bật ở Cài đặt tổ chức → Bảo mật đăng nhập → “Công ty nhận yêu cầu tạo tài khoản” (cần Email gửi đi hoạt động).') }}</div>
    @endunless
    @error('role')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (\App\Models\JoinRequest::statuses() as $k => [$label, $color])
            <a href="{{ route('join-requests.index', ['status' => $k]) }}" class="btn btn-sm {{ $status === $k ? 'btn-dark' : 'btn-light' }}">{{ __($label) }} ({{ $counts[$k] ?? 0 }})</a>
        @endforeach
    </div>

    <div class="row g-3">
        @forelse ($requests as $r)
            <div class="col-xl-6"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <div class="fw-bold">{{ $r->name }}</div>
                        <div class="small"><i class="bi bi-envelope me-1 text-muted"></i>{{ $r->email }} <span class="badge text-bg-success-subtle text-success-emphasis ms-1"><i class="bi bi-patch-check"></i> {{ __('Email đã xác thực') }}</span></div>
                        @if ($r->phone)<div class="small"><i class="bi bi-telephone me-1 text-muted"></i><a href="tel:{{ preg_replace('/[^0-9+]/', '', $r->phone) }}">{{ $r->phone }}</a></div>@endif
                    </div>
                    <div class="small text-muted text-end text-nowrap">{{ vn_date($r->created_at, true) }}</div>
                </div>
                @if ($r->message)<div class="small bg-light rounded-3 p-2 mt-2"><i class="bi bi-chat-left-text me-1 text-muted"></i>{{ $r->message }}</div>@endif

                @if ($r->status === 'pending')
                    <form method="POST" action="{{ route('join-requests.approve', $r) }}" class="row g-2 mt-2 align-items-end">@csrf
                        <div class="col-sm-7">
                            <label class="form-label small mb-1" for="role_{{ $r->id }}">{{ __('Vai trò khi duyệt') }}</label>
                            <select name="role" id="role_{{ $r->id }}" class="form-select form-select-sm" required>
                                @foreach ($roles as $value => $label)<option value="{{ $value }}" @selected($value === 'Sales Employee')>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-sm-5"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-check2-circle me-1"></i>{{ __('Duyệt & tạo tài khoản') }}</button></div>
                    </form>
                    <details class="mt-2">
                        <summary class="small text-danger" style="cursor:pointer">{{ __('Từ chối') }}</summary>
                        <form method="POST" action="{{ route('join-requests.reject', $r) }}" class="d-flex gap-2 mt-2" data-confirm="{{ __('Từ chối yêu cầu của :email?', ['email' => $r->email]) }}" data-confirm-button="{{ __('Từ chối') }}">@csrf
                            <input type="text" name="reject_reason" class="form-control form-control-sm" maxlength="255" placeholder="{{ __('Lý do (gửi kèm email, không bắt buộc)') }}" aria-label="{{ __('Lý do') }}">
                            <button class="btn btn-light btn-sm text-danger text-nowrap">{{ __('Từ chối') }}</button>
                        </form>
                    </details>
                @else
                    <div class="small text-muted mt-2 border-top pt-2">
                        @php([$label, $color] = \App\Models\JoinRequest::statuses()[$r->status])
                        <span class="badge text-bg-{{ $color }}">{{ __($label) }}</span>
                        {{ $r->reviewer?->name }} · {{ $r->reviewed_at ? vn_date($r->reviewed_at, true) : '' }}
                        @if ($r->role) · {{ __($r->role) }} @endif
                        @if ($r->reject_reason)<div class="fst-italic">{{ $r->reject_reason }}</div>@endif
                    </div>
                @endif
            </div></div></div>
        @empty
            <div class="col-12"><div class="card"><x-empty-state icon="bi-person-plus" :title="__('Không có yêu cầu nào')" :message="__('Người mới bấm “Tạo tài khoản” ở trang đăng nhập — yêu cầu sẽ hiện ở đây để bạn duyệt.')" /></div></div>
        @endforelse
    </div>
    @if ($requests->total() > 0)<div class="card mt-3"><x-pagination-bar :paginator="$requests" /></div>@endif
@endsection
