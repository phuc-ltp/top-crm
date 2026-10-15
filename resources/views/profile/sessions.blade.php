@extends('layouts.app')
@section('title', 'Phiên đăng nhập')
@section('content')
    <x-page-header title="Phiên đăng nhập" :breadcrumbs="['Hồ sơ cá nhân' => route('profile.edit'), 'Phiên đăng nhập' => null]" />

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Các thiết bị đang đăng nhập</div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Thiết bị</th><th>Địa chỉ IP</th><th>Hoạt động gần nhất</th></tr></thead>
                        <tbody>
                        @forelse ($sessions as $s)
                            <tr>
                                <td><i class="bi bi-laptop me-2 text-orange"></i>{{ $s->agent }} @if ($s->current)<span class="badge badge-soft-success ms-1">Phiên hiện tại</span>@endif</td>
                                <td>{{ $s->ip }}</td>
                                <td>{{ $s->last_active->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><x-empty-state icon="bi-laptop" title="Không có phiên nào" /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Đăng xuất thiết bị khác</div>
                <div class="card-body">
                    <p class="small text-muted">Nếu nghi ngờ tài khoản bị sử dụng trái phép, hãy đăng xuất tất cả thiết bị khác và đổi mật khẩu.</p>
                    <form method="POST" action="{{ route('profile.sessions.destroy') }}" class="needs-validation" novalidate>
                        @csrf @method('DELETE')
                        <x-input name="password" type="password" label="Xác nhận mật khẩu" required autocomplete="current-password" />
                        <button class="btn btn-outline-primary w-100">Đăng xuất các thiết bị khác</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
