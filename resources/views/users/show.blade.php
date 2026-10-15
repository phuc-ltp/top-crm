@extends('layouts.app')
@section('title', $user->name)
@section('content')
    <x-page-header :title="$user->name" :breadcrumbs="['Người dùng' => route('users.index'), $user->name => null]">
        @can('update', $user)<a href="{{ route('users.edit', $user) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>@endcan
    </x-page-header>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-people" tone="blue" :label="__('Khách hàng phụ trách')" :value="$stats['customers']" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-funnel" tone="purple" :label="__('Lead được giao')" :value="$stats['leads']" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-kanban" :label="__('Cơ hội đang mở')" :value="$stats['opportunities']" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-check2-square" tone="gray" :label="__('Việc chưa xong')" :value="$stats['tasks']" /></div>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body text-center">
                <x-avatar :user="$user" size="lg" class="mb-2" />
                <div class="fw-bold">{{ $user->name }}</div><div class="small text-muted mb-2">{{ $user->email }}</div>
                @foreach ($user->roles as $r)<span class="badge badge-soft-orange">{{ __($r->name) }}</span>@endforeach @if ($user->isPlatformAdmin())<span class="badge badge-soft-danger">{{ __('Platform Super Administrator') }}</span>@endif {!! badge(App\Models\User::STATUSES, $user->status) !!}
                <hr>
                <dl class="detail-list text-start small">
                    <dt>{{ __('Phòng ban') }}</dt><dd>{{ $user->department?->name ?? '—' }}</dd>
                    <dt>{{ __('Chức vụ') }}</dt><dd>{{ $user->position ?? '—' }}</dd>
                    <dt>{{ __('Điện thoại') }}</dt><dd>{{ $user->phone ?? '—' }}</dd>
                    <dt>{{ __('Đăng nhập gần nhất') }}</dt><dd>{{ vn_date($user->last_login_at, true) }}</dd>
                    <dt>{{ __('Ngày tạo') }}</dt><dd>{{ vn_date($user->created_at) }}</dd>
                </dl>
            </div></div>
            @can('update', $user)
                @if (! $managesAccount)
                    <div class="alert alert-info small"><i class="bi bi-people me-1"></i>{{ __('Tài khoản này thuộc nhiều tổ chức. Bạn quản lý vai trò và tư cách thành viên trong tổ chức của mình; thông tin đăng nhập do chủ tài khoản hoặc quản trị nền tảng thay đổi.') }}</div>
                @else
                <div class="card"><div class="card-header">{{ __('Đặt lại mật khẩu') }}</div><div class="card-body">
                    <form method="POST" action="{{ route('users.send-reset', $user) }}" class="mb-3">@csrf
                        <button class="btn btn-light w-100"><i class="bi bi-envelope me-1"></i>{{ __('Gửi liên kết đặt lại qua email') }}</button></form>
                    <div class="small text-muted mb-2">{{ __('Hoặc đặt mật khẩu tạm thời (khi chưa cấu hình email):') }}</div>
                    <form method="POST" action="{{ route('users.password', $user) }}" class="needs-validation" novalidate>@csrf @method('PUT')
                        <x-input name="password" type="password" :label="__('Mật khẩu mới')" required minlength="8" autocomplete="new-password" />
                        <x-input name="password_confirmation" type="password" :label="__('Nhập lại')" required minlength="8" autocomplete="new-password" />
                        <button class="btn btn-outline-primary w-100">{{ __('Đặt mật khẩu') }}</button>
                    </form>
                </div></div>
                @endif
            @endcan
        </div>
        <div class="col-lg-8">
            <div class="card"><div class="card-header">{{ __('Hoạt động gần đây') }}</div><div class="card-body">
                <ul class="timeline">
                    @forelse ($logs as $l)
                        <li><span class="tl-icon"><i class="bi bi-activity"></i></span><div class="small fw-semibold">{{ $l->description }}</div>
                            <div class="small-xs text-muted">{{ vn_date($l->created_at, true) }} · IP {{ $l->ip_address ?? '—' }}</div></li>
                    @empty
                        <li class="ps-0 small text-muted">{{ __('Chưa có hoạt động.') }}</li>
                    @endforelse
                </ul>
            </div></div>
        </div>
    </div>
@endsection
