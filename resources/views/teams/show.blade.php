@extends('layouts.app')
@section('title', $team->name)
@section('content')
    <x-page-header :title="$team->name" :breadcrumbs="['Nhóm' => route('teams.index'), 'Chi tiết' => null]" :subtitle="$team->description">
        @can('teams.manage')
            <a href="{{ route('teams.edit', $team) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>
            <x-delete-button :action="route('teams.destroy', $team)" class="btn btn-light text-danger" :label="__('Xóa')" :message="__('Xóa nhóm :name? Thành viên không bị ảnh hưởng.', ['name' => $team->name])" />
        @endcan
    </x-page-header>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="h6 mb-0">{{ __('KPI nhóm') }} <span class="text-muted fw-normal small">{{ vn_date($from) }} → {{ vn_date($to) }}</span></h2>
        <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Kỳ') }}">
            @foreach (['month' => 'Tháng này', 'quarter' => 'Quý này', 'year' => 'Năm nay'] as $k => $l)
                <a href="{{ route('teams.show', [$team, 'period' => $k]) }}" class="btn {{ $period === $k ? 'btn-primary' : 'btn-light' }}">{{ __($l) }}</a>
            @endforeach
        </div>
    </div>
    @include('employees._kpi', ['k' => $total])

    <div class="card mt-4">
        <div class="card-header"><span>{{ __('Thành viên (:count)', ['count' => $team->members->count()]) }}</span>
            <span class="small text-muted fw-normal">{{ $team->department?->name ?? __('Không thuộc phòng ban') }}</span></div>
        @if ($team->members->isEmpty())
            <x-empty-state icon="bi-people" :title="__('Chưa có thành viên.')" />
        @else
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>{{ __('Nhân viên') }}</th><th class="text-end">{{ __('Doanh thu đã thu') }}</th><th class="text-end">{{ __('% chỉ tiêu') }}</th>
                    <th class="text-end">{{ __('Cơ hội thắng') }}</th><th class="text-end">{{ __('Việc hoàn thành') }}</th><th class="text-end">{{ __('Đúng hạn') }}</th><th class="text-end">{{ __('Ticket đã giải quyết') }}</th></tr></thead>
                <tbody>
                @foreach ($team->members as $m)
                    @php($r = $kpi[$m->id])
                    <tr>
                        <td><div class="d-flex align-items-center gap-2"><x-avatar :user="$m" size="sm" />
                            <a href="{{ route('employees.show', $m->id) }}" class="row-link text-nowrap">{{ $m->name }}</a>
                            @if ($m->id === $team->leader_id)<span class="badge badge-soft-orange">{{ __('Trưởng nhóm') }}</span>@endif</div></td>
                        <td class="text-end small">{{ money($r['revenue']) }}</td>
                        <td class="text-end small">{{ $r['achievement'] !== null ? $r['achievement'].'%' : '—' }}</td>
                        <td class="text-end small">{{ $r['deals_won'] }}</td>
                        <td class="text-end small">{{ $r['tasks_done'] }}</td>
                        <td class="text-end small">{{ $r['on_time_rate'] !== null ? $r['on_time_rate'].'%' : '—' }}</td>
                        <td class="text-end small">{{ $r['tickets_resolved'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body small text-muted border-top">{{ __('Doanh thu đã thu = tiền thu được của các hóa đơn do người đó phụ trách trong kỳ (giống trang Chỉ tiêu doanh số). Đúng hạn = việc hoàn thành không trễ hạn chót.') }}</div>
        @endif
    </div>
@endsection
