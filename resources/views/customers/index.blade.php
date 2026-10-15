@extends('layouts.app')
@section('title', 'Khách hàng')
@section('content')
    <x-page-header title="Khách hàng" :breadcrumbs="['Khách hàng' => null]" subtitle="Quản lý thông tin, phân loại và người phụ trách khách hàng">
        @can('export', App\Models\Customer::class)
            <div class="dropdown">
                <button class="btn btn-light" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Xuất</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('customers.export', request()->query() + ['format' => 'xlsx']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel (.xlsx)</a></li>
                    <li><a class="dropdown-item" href="{{ route('customers.export', request()->query() + ['format' => 'csv']) }}"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                </ul>
            </div>
        @endcan
        @can('create', App\Models\Customer::class)
            <a href="{{ route('customers.import') }}" class="btn btn-light"><i class="bi bi-upload me-1"></i>Nhập CSV</a>
            <a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Thêm khách hàng</a>
        @endcan
    </x-page-header>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên, mã, email, điện thoại...">
            </div>
            <select name="status" class="form-select"><option value="">Mọi trạng thái</option>
                @foreach (App\Models\Customer::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $s[0] }}</option>@endforeach
            </select>
            <select name="type" class="form-select"><option value="">Mọi loại</option>
                @foreach (App\Models\Customer::TYPES as $k => $t)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $t }}</option>@endforeach
            </select>
            <select name="source" class="form-select"><option value="">Mọi nguồn</option>
                @foreach ($sources as $id => $name)<option value="{{ $id }}" @selected(request('source') == $id)>{{ $name }}</option>@endforeach
            </select>
            @can('customers.view_all')
                <select name="assigned_to" class="form-select"><option value="">Mọi nhân viên</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('assigned_to') == $id)>{{ $name }}</option>@endforeach
                </select>
            @endcan
            <select name="tag" class="form-select"><option value="">Mọi thẻ</option>
                @foreach ($tags as $id => $name)<option value="{{ $id }}" @selected(request('tag') == $id)>{{ $name }}</option>@endforeach
            </select>
            <select name="archived" class="form-select"><option value="">Đang hoạt động</option><option value="1" @selected(request('archived') === '1')>Đã lưu trữ</option></select>
            <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>Lọc</button>
            @if (request()->hasAny(['q', 'status', 'type', 'source', 'assigned_to', 'tag', 'archived']))
                <a href="{{ route('customers.index') }}" class="btn btn-link text-muted">Xóa lọc</a>
            @endif
        </form>

        @can('assign', App\Models\Customer::class)
            <form method="POST" action="{{ route('customers.bulk-assign') }}" id="bulkForm" class="d-none px-3 py-2 bg-orange-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                @csrf
                <span class="small fw-semibold"><span id="bulkCount">0</span> khách hàng được chọn</span>
                <select name="assigned_to" class="form-select form-select-sm w-auto" required>
                    <option value="">Phân công cho...</option>
                    @foreach ($users as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
                <button class="btn btn-primary btn-sm">Phân công</button>
            </form>
        @endcan

        @if ($customers->isEmpty())
            <x-empty-state icon="bi-people" title="Không có khách hàng nào" message="Thử thay đổi bộ lọc hoặc thêm khách hàng mới.">
                @can('create', App\Models\Customer::class)<a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">Thêm khách hàng</a>@endcan
            </x-empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        @can('assign', App\Models\Customer::class)<th style="width:36px"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="Chọn tất cả"></th>@endcan
                        <th><x-sort-link column="code" label="Mã" /></th>
                        <th><x-sort-link column="name" label="Khách hàng" /></th>
                        <th>Liên hệ</th>
                        <th>Loại / Nguồn</th>
                        <th><x-sort-link column="status" label="Trạng thái" /></th>
                        <th>Phụ trách</th>
                        <th><x-sort-link column="created_at" label="Ngày tạo" /></th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($customers as $c)
                        <tr>
                            @can('assign', App\Models\Customer::class)<td><input type="checkbox" class="form-check-input row-check" value="{{ $c->id }}" aria-label="Chọn"></td>@endcan
                            <td class="text-muted small">{{ $c->code }}</td>
                            <td>
                                <a href="{{ route('customers.show', $c) }}" class="row-link">{{ $c->name }}</a>
                                @if ($c->company)<div class="small text-muted">{{ $c->company }}</div>@endif
                                <div class="mt-1">@foreach ($c->tags as $tag)<span class="tag-chip me-1" style="background: {{ $tag->color }}">{{ $tag->name }}</span>@endforeach</div>
                            </td>
                            <td class="small">
                                @if ($c->phone)<div><i class="bi bi-telephone text-muted me-1"></i>{{ $c->phone }}</div>@endif
                                @if ($c->email)<div class="text-truncate" style="max-width:200px"><i class="bi bi-envelope text-muted me-1"></i>{{ $c->email }}</div>@endif
                            </td>
                            <td class="small">{{ App\Models\Customer::TYPES[$c->type] ?? '' }}<div class="text-muted">{{ $c->source?->name }}</div></td>
                            <td>{!! badge(App\Models\Customer::STATUSES, $c->status) !!}</td>
                            <td>@if ($c->assignee)<div class="d-flex align-items-center gap-2"><x-avatar :user="$c->assignee" size="sm" /><span class="small">{{ $c->assignee->name }}</span></div>@else<span class="text-muted small">Chưa phân công</span>@endif</td>
                            <td class="small text-muted">{{ vn_date($c->created_at) }}</td>
                            <td class="table-actions">
                                @if ($c->trashed())
                                    @can('delete', $c)
                                        <form method="POST" action="{{ route('customers.destroy', $c->id) }}" class="d-inline">@csrf @method('DELETE')
                                            <button class="btn btn-sm btn-soft-primary"><i class="bi bi-arrow-counterclockwise me-1"></i>Khôi phục</button>
                                        </form>
                                    @endcan
                                @else
                                    <a href="{{ route('customers.show', $c) }}" class="btn btn-light btn-icon btn-sm" title="Xem"><i class="bi bi-eye"></i></a>
                                    @can('update', $c)<a href="{{ route('customers.edit', $c) }}" class="btn btn-light btn-icon btn-sm" title="Sửa"><i class="bi bi-pencil"></i></a>@endcan
                                    @can('delete', $c)<x-delete-button :action="route('customers.destroy', $c)" message="Lưu trữ khách hàng {{ $c->name }}? Bạn có thể khôi phục sau." />@endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination-bar :paginator="$customers" />
        @endif
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('bulkForm');
    if (!form) return;
    const checks = () => Array.from(document.querySelectorAll('.row-check'));
    const sync = () => {
        const selected = checks().filter(c => c.checked);
        form.classList.toggle('d-none', selected.length === 0);
        document.getElementById('bulkCount').textContent = selected.length;
        form.querySelectorAll('input[name="ids[]"]').forEach(i => i.remove());
        selected.forEach(c => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = c.value; form.appendChild(i); });
    };
    document.getElementById('checkAll')?.addEventListener('change', e => { checks().forEach(c => c.checked = e.target.checked); sync(); });
    checks().forEach(c => c.addEventListener('change', sync));
});
</script>
@endpush
