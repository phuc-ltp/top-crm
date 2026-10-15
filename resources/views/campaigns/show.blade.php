@extends('layouts.app')
@section('title', $campaign->name)
@section('content')
    <x-page-header :title="$campaign->name" :breadcrumbs="['Marketing' => route('campaigns.index'), $campaign->name => null]">
        @can('update', $campaign)<a href="{{ route('campaigns.edit', $campaign) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Chỉnh sửa</a>@endcan
        @can('delete', $campaign)<x-delete-button :action="route('campaigns.destroy', $campaign)" class="btn btn-light text-danger" label="Xóa" />@endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-wallet2" label="Ngân sách" :value="money($campaign->budget)" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-funnel" tone="purple" label="Lead thực tế / mục tiêu" :value="$campaign->actual_leads.' / '.$campaign->target_leads" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-arrow-repeat" tone="green" label="Tỷ lệ chuyển đổi" :value="$campaign->conversion_rate.'%'" :sub="$campaign->converted_leads.' lead đã chuyển đổi'" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-coin" tone="blue" label="Chi phí / lead" :value="money($campaign->cost_per_lead)" /></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body">
                <dl class="detail-list">
                    <dt>Loại</dt><dd>{{ App\Models\Campaign::TYPES[$campaign->type] }}</dd>
                    <dt>Trạng thái</dt><dd>{!! badge(App\Models\Campaign::STATUSES, $campaign->status) !!}</dd>
                    <dt>Thời gian</dt><dd>{{ vn_date($campaign->start_date) }} → {{ vn_date($campaign->end_date) }}</dd>
                    <dt>Phụ trách</dt><dd>{{ $campaign->owner?->name ?? '—' }}</dd>
                </dl>
                @if ($campaign->description)<hr><div class="small" style="white-space: pre-line">{{ $campaign->description }}</div>@endif
            </div></div>
            <div class="card"><div class="card-header">Lead theo trạng thái</div><div class="card-body">
                @if ($byStatus->isEmpty())<div class="text-muted small">Chưa có lead.</div>@else
                    @php
                        $campaignChart = ['type' => 'doughnut', 'data' => [
                            'labels' => collect($byStatus)->keys()->map(fn ($k) => label_of(App\Models\Lead::STATUSES, $k))->values(),
                            'datasets' => [['data' => collect($byStatus)->values(), 'backgroundColor' => ['#0EA5E9', '#3B82F6', '#F59E0B', '#FF6B00', '#16A34A', '#DC2626']]],
                        ], 'options' => ['plugins' => ['legend' => ['position' => 'bottom']]]];
                    @endphp
                    <div class="chart-box sm"><canvas id="campaignChart" data-chart="{{ json_encode($campaignChart) }}"></canvas></div>
                @endif
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><span>Lead của chiến dịch ({{ $leads->total() }})</span></div>
                @can('update', $campaign)
                    @if ($available->isNotEmpty())
                        <form method="POST" action="{{ route('campaigns.leads.attach', $campaign) }}" class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-start">@csrf
                            <select name="lead_ids[]" class="form-select form-select-sm" multiple size="4" style="flex:1;min-width:220px" aria-label="Chọn lead">
                                @foreach ($available as $l)<option value="{{ $l->id }}">{{ $l->code }} — {{ $l->name }}</option>@endforeach
                            </select>
                            <button class="btn btn-primary btn-sm"><i class="bi bi-link-45deg"></i> Gắn lead đã chọn</button>
                        </form>
                    @endif
                @endcan
                <div class="table-responsive"><table class="table table-hover">
                    <thead><tr><th>Lead</th><th>Trạng thái</th><th>Điểm</th><th>Phụ trách</th><th>Ngày gắn</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($leads as $l)
                        <tr><td><a href="{{ route('leads.show', $l) }}" class="row-link">{{ $l->name }}</a><div class="small text-muted">{{ $l->code }}</div></td>
                            <td>{!! badge(App\Models\Lead::STATUSES, $l->status) !!}</td><td>{{ $l->score }}</td><td class="small">{{ $l->assignee?->name }}</td>
                            <td class="small">{{ vn_date($l->pivot->created_at) }}</td>
                            <td class="text-end">@can('update', $campaign)<form method="POST" action="{{ route('campaigns.leads.detach', [$campaign, $l]) }}" data-confirm="Gỡ lead khỏi chiến dịch?" data-confirm-button="Gỡ">@csrf @method('DELETE')<button class="btn btn-light btn-icon btn-sm" title="Gỡ"><i class="bi bi-x-lg"></i></button></form>@endcan</td></tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-funnel" title="Chưa có lead nào" /></td></tr>
                    @endforelse
                    </tbody></table></div>
                <x-pagination-bar :paginator="$leads" />
            </div>
        </div>
    </div>
@endsection
