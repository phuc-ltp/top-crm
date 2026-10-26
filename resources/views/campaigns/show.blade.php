@extends('layouts.app')
@section('title', $campaign->name)
@section('content')
    <x-page-header :title="$campaign->name" :breadcrumbs="['Marketing' => route('campaigns.index'), $campaign->name => null]">
        @can('update', $campaign)<a href="{{ route('campaigns.edit', $campaign) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>@endcan
        @can('delete', $campaign)<x-delete-button :action="route('campaigns.destroy', $campaign)" class="btn btn-light text-danger" :label="__('Xóa')" />@endcan
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-wallet2" :label="__('Ngân sách')" :value="money($campaign->budget)" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-funnel" tone="purple" :label="__('Lead thực tế / mục tiêu')" :value="$campaign->actual_leads.' / '.$campaign->target_leads" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-arrow-repeat" tone="green" :label="__('Tỷ lệ chuyển đổi')" :value="$campaign->conversion_rate.'%'" :sub="__(':count lead đã chuyển đổi', ['count' => $campaign->converted_leads])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-coin" tone="blue" :label="__('Chi phí / lead')" :value="money($campaign->cost_per_lead)" /></div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-receipt" tone="orange" :label="__('Chi phí thực tế')" :value="money($perf['cost'])" :sub="$perf['budget_used'] !== null ? __(':p% ngân sách', ['p' => $perf['budget_used']]) : null" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-graph-up-arrow" tone="green" :label="__('Doanh thu (cơ hội đã thắng)')" :value="money($perf['won'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-kanban" tone="purple" :label="__('Đang theo đuổi')" :value="money($perf['open'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-percent" :tone="$perf['roi'] !== null && $perf['roi'] < 0 ? 'red' : 'blue'" :label="__('ROI')" :value="$perf['roi'] === null ? '—' : $perf['roi'].'%'" :sub="$perf['roi'] === null ? __('Ghi nhận chi phí để tính ROI') : __('(doanh thu − chi phí) / chi phí')" /></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body">
                <dl class="detail-list">
                    <dt>{{ __('Loại') }}</dt><dd>{{ __(App\Models\Campaign::TYPES[$campaign->type]) }}</dd>
                    <dt>{{ __('Trạng thái') }}</dt><dd>{!! badge(App\Models\Campaign::STATUSES, $campaign->status) !!}</dd>
                    <dt>{{ __('Thời gian') }}</dt><dd>{{ vn_date($campaign->start_date) }} → {{ vn_date($campaign->end_date) }}</dd>
                    <dt>{{ __('Phụ trách') }}</dt><dd>{{ $campaign->owner?->name ?? '—' }}</dd>
                </dl>
                @if ($campaign->description)<hr><div class="small" style="white-space: pre-line">{{ $campaign->description }}</div>@endif
            </div></div>
            <div class="card mb-4"><div class="card-header">{{ __('Link theo dõi chiến dịch') }}</div><div class="card-body">
                @if ($trackingUrl)
                    <div class="input-group input-group-sm mb-2"><input type="text" class="form-control font-monospace" value="{{ $trackingUrl }}" readonly id="trackingUrl" aria-label="{{ __('Link theo dõi chiến dịch') }}">
                        <button class="btn btn-light" type="button" data-copy="#trackingUrl" title="{{ __('Sao chép') }}" aria-label="{{ __('Sao chép') }}"><i class="bi bi-clipboard"></i></button></div>
                    <div class="small text-muted">{{ __('Dùng link này trong quảng cáo / bài đăng: lead đăng ký qua link được gắn tự động vào chiến dịch kèm nguồn (UTM).') }}</div>
                @else
                    <div class="small text-muted">{{ __('Bật “Form thu lead” trong Cài đặt → Lead để có link theo dõi tự gắn lead vào chiến dịch.') }}</div>
                @endif
            </div></div>
            <div class="card"><div class="card-header">{{ __('Lead theo trạng thái') }}</div><div class="card-body">
                @if ($byStatus->isEmpty())<div class="text-muted small">{{ __('Chưa có lead.') }}</div>@else
                    @php
                        $campaignChart = ['type' => 'doughnut', 'data' => [
                            'labels' => collect($byStatus)->keys()->map(fn ($k) => label_of(App\Models\Lead::statuses(), $k))->values(),
                            'datasets' => [['data' => collect($byStatus)->values(), 'backgroundColor' => ['#0EA5E9', '#3B82F6', '#F59E0B', '#FF6B00', '#16A34A', '#DC2626']]],
                        ], 'options' => ['plugins' => ['legend' => ['position' => 'bottom']]]];
                    @endphp
                    <div class="chart-box sm"><canvas id="campaignChart" data-chart="{{ json_encode($campaignChart) }}"></canvas></div>
                @endif
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><span>{{ __('Chi phí thực tế (:n khoản)', ['n' => $expenses->count()]) }}</span></div>
                @can('update', $campaign)
                    <form method="POST" action="{{ route('campaigns.expenses.store', $campaign) }}" class="p-3 border-bottom row g-2 align-items-end needs-validation" novalidate>@csrf
                        <x-input name="spent_on" type="date" :label="__('Ngày chi')" required :value="now()->toDateString()" col="col-6 col-md-3" />
                        <x-select name="category" :label="__('Hạng mục')" :options="App\Models\CampaignExpense::CATEGORIES" required value="ads" col="col-6 col-md-3" />
                        <x-input name="amount" type="number" :label="__('Số tiền')" required min="1" step="1000" append="₫" col="col-6 col-md-2" />
                        <x-input name="note" :label="__('Ghi chú')" maxlength="500" col="col-6 col-md-3" />
                        <div class="col-12 col-md-1"><button class="btn btn-primary btn-sm w-100" title="{{ __('Ghi nhận khoản chi') }}" aria-label="{{ __('Ghi nhận khoản chi') }}"><i class="bi bi-plus-lg"></i></button></div>
                    </form>
                @endcan
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <tbody>
                    @forelse ($expenses as $x)
                        <tr><td class="text-nowrap small">{{ vn_date($x->spent_on) }}</td><td>{!! badge(App\Models\CampaignExpense::CATEGORIES, $x->category) !!}</td>
                            <td class="small">{{ $x->note }}<div class="text-muted">{{ $x->creator?->name }}</div></td><td class="text-end text-nowrap">{{ money($x->amount) }}</td>
                            <td class="text-end">@can('update', $campaign)<x-delete-button :action="route('campaigns.expenses.destroy', [$campaign, $x])" :message="__('Xóa khoản chi này?')" />@endcan</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted small py-3">{{ __('Chưa ghi nhận khoản chi nào — chi phí / lead đang tính theo ngân sách.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><span>{{ __('Cơ hội từ chiến dịch (:n)', ['n' => $opportunities->count()]) }}</span></div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <tbody>
                    @forelse ($opportunities as $o)
                        <tr><td><a href="{{ route('opportunities.show', $o) }}">{{ $o->title }}</a><div class="small text-muted">{{ $o->customer?->name }}</div></td>
                            <td><span class="badge" style="background: {{ $o->stage?->color ?? '#6B7280' }}">{{ $o->stage?->label }}</span></td>
                            <td class="text-end text-nowrap">{{ money($o->value) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted small py-3">{{ __('Chưa có cơ hội nào gắn chiến dịch. Lead của chiến dịch khi chuyển đổi thành cơ hội sẽ tự gắn; hoặc chọn “Chiến dịch nguồn” trên form cơ hội.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>

            <div class="card">
                <div class="card-header"><span>{{ __('Lead của chiến dịch (:count)', ['count' => $leads->total()]) }}</span></div>
                @can('update', $campaign)
                    @if ($available->isNotEmpty())
                        <form method="POST" action="{{ route('campaigns.leads.attach', $campaign) }}" class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-start">@csrf
                            <select name="lead_ids[]" class="form-select form-select-sm" multiple size="4" style="flex:1;min-width:220px" aria-label="{{ __('Chọn lead') }}">
                                @foreach ($available as $l)<option value="{{ $l->id }}">{{ $l->code }} — {{ $l->name }}</option>@endforeach
                            </select>
                            <button class="btn btn-primary btn-sm"><i class="bi bi-link-45deg"></i> {{ __('Gắn lead đã chọn') }}</button>
                        </form>
                    @endif
                @endcan
                <div class="table-responsive"><table class="table table-hover">
                    <thead><tr><th>Lead</th><th>{{ __('Trạng thái') }}</th><th>{{ __('Điểm') }}</th><th>{{ __('Phụ trách') }}</th><th>{{ __('Ngày gắn') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($leads as $l)
                        <tr><td><a href="{{ route('leads.show', $l) }}" class="row-link">{{ $l->name }}</a><div class="small text-muted">{{ $l->code }}</div></td>
                            <td>{!! badge(App\Models\Lead::statuses(), $l->status) !!}</td><td>{{ $l->score }}</td><td class="small">{{ $l->assignee?->name }}</td>
                            <td class="small">{{ vn_date($l->pivot->created_at) }}</td>
                            <td class="text-end">@can('update', $campaign)<form method="POST" action="{{ route('campaigns.leads.detach', [$campaign, $l]) }}" data-confirm="{{ __('Gỡ lead khỏi chiến dịch?') }}" data-confirm-button="{{ __('Gỡ') }}">@csrf @method('DELETE')<button class="btn btn-light btn-icon btn-sm" title="{{ __('Gỡ') }}"><i class="bi bi-x-lg"></i></button></form>@endcan</td></tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-funnel" :title="__('Chưa có lead nào')" /></td></tr>
                    @endforelse
                    </tbody></table></div>
                <x-pagination-bar :paginator="$leads" />
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
    const input = document.querySelector(btn.dataset.copy);
    if (!input) return;
    const done = () => { const i = btn.querySelector('i'); i.className = 'bi bi-check2'; setTimeout(() => { i.className = 'bi bi-clipboard'; }, 1500); };
    try { await navigator.clipboard.writeText(input.value); done(); } catch (e) { input.focus(); input.select(); }   // trình duyệt chặn clipboard (HTTP): bôi đen để tự sao chép
}));
</script>
@endpush
