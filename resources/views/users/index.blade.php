@extends('layouts.app')
@section('title', 'Người dùng')
@section('content')
    <x-page-header title="Người dùng" :breadcrumbs="['Người dùng' => null]" subtitle="Tài khoản nhân viên, vai trò và trạng thái">
        @can('create', App\Models\User::class)<a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Tạo tài khoản</a>@endcan
    </x-page-header>
    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên, email, điện thoại..."></div>
            <select name="role" class="form-select"><option value="">Mọi vai trò</option>@foreach ($roles as $r)<option value="{{ $r }}" @selected(request('role') === $r)>{{ $r }}</option>@endforeach</select>
            <select name="department" class="form-select"><option value="">Mọi phòng ban</option>@foreach ($departments as $id => $name)<option value="{{ $id }}" @selected(request('department') == $id)>{{ $name }}</option>@endforeach</select>
            <select name="status" class="form-select"><option value="">Mọi trạng thái</option>@foreach (App\Models\User::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $s[0] }}</option>@endforeach</select>
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>Lọc</button>
        </form>
        <div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>Nhân viên</th><th>Vai trò</th><th>Phòng ban</th><th>Trạng thái</th><th>Đăng nhập gần nhất</th><th class="text-end">Thao tác</th></tr></thead>
            <tbody>
            @forelse ($users as $u)
                <tr>
                    <td><div class="d-flex align-items-center gap-2"><x-avatar :user="$u" />
                        <div><a href="{{ route('users.show', $u) }}" class="row-link">{{ $u->name }}</a> @if ($u->is_demo)<span class="badge badge-soft-warning">Demo</span>@endif<div class="small text-muted">{{ $u->email }}</div></div></div></td>
                    <td>@foreach ($u->roles as $r)<span class="badge badge-soft-orange">{{ $r->name }}</span>@endforeach</td>
                    <td class="small">{{ $u->department?->name ?? '—' }}<div class="text-muted">{{ $u->position }}</div></td>
                    <td>{!! badge(App\Models\User::STATUSES, $u->status) !!}</td>
                    <td class="small text-muted">{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Chưa đăng nhập' }}</td>
                    <td class="table-actions">
                        @can('update', $u)
                            @if ($u->id !== auth()->id())
                                <form method="POST" action="{{ route('users.toggle-status', $u) }}" class="d-inline" data-confirm="{{ $u->status === 'active' ? 'Khóa' : 'Mở khóa' }} tài khoản {{ $u->name }}?" data-confirm-button="Xác nhận">@csrf
                                    <button class="btn btn-light btn-icon btn-sm" title="{{ $u->status === 'active' ? 'Khóa' : 'Mở khóa' }}"><i class="bi {{ $u->status === 'active' ? 'bi-lock' : 'bi-unlock' }}"></i></button></form>
                            @endif
                            <a href="{{ route('users.edit', $u) }}" class="btn btn-light btn-icon btn-sm" title="Sửa"><i class="bi bi-pencil"></i></a>
                        @endcan
                        @can('delete', $u)<x-delete-button :action="route('users.destroy', $u)" message="Xóa tài khoản {{ $u->name }}? Dữ liệu do người này tạo vẫn được giữ." />@endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="bi-people" title="Không có người dùng" /></td></tr>
            @endforelse
            </tbody></table></div>
        <x-pagination-bar :paginator="$users" />
    </div>
@endsection
