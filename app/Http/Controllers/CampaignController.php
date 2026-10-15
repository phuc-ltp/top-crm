<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Lead;
use App\Services\ExportService;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    private function filtered(Request $request)
    {
        return Campaign::visibleTo($request->user())->with('owner:id,name')
            ->withCount(['leads', 'leads as converted_leads_count' => fn ($q) => $q->where('status', 'converted')])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', $this->like($request->q)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);
        $campaigns = $this->applySort($this->filtered($request), $request, ['name', 'start_date', 'budget', 'created_at'], 'start_date')
            ->paginate($this->perPage($request))->withQueryString();

        $all = $this->filtered($request)->get();
        $summary = [
            'budget' => $all->sum('budget'),
            'leads' => $all->sum('leads_count'),
            'converted' => $all->sum('converted_leads_count'),
            'active' => $all->where('status', 'active')->count(),
        ];

        return view('campaigns.index', compact('campaigns', 'summary'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Campaign::class);

        return view('campaigns.form', ['campaign' => new Campaign(['status' => 'planned', 'type' => 'social', 'owner_id' => $request->user()->id,
            'start_date' => today(), 'end_date' => today()->addMonth()]), 'users' => Lookup::users()]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Campaign::class);
        $campaign = Campaign::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Đã tạo chiến dịch '.$campaign->name.'.');
    }

    public function show(Request $request, Campaign $campaign)
    {
        $this->authorize('view', $campaign);
        $campaign->loadCount(['leads', 'leads as converted_leads_count' => fn ($q) => $q->where('status', 'converted')]);
        $leads = $campaign->leads()->with('assignee:id,name')->latest('campaign_leads.created_at')->paginate(15);
        $byStatus = $campaign->leads()->selectRaw('leads.status, COUNT(*) as cnt')->groupBy('leads.status')->pluck('cnt', 'status');
        $available = Lead::visibleTo($request->user())->whereDoesntHave('campaigns', fn ($q) => $q->where('campaigns.id', $campaign->id))
            ->orderByDesc('id')->limit(300)->get(['id', 'code', 'name']);

        return view('campaigns.show', compact('campaign', 'leads', 'byStatus', 'available'));
    }

    public function edit(Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        return view('campaigns.form', ['campaign' => $campaign, 'users' => Lookup::users()]);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $campaign->update($this->validated($request));

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Đã cập nhật chiến dịch.');
    }

    public function destroy(Campaign $campaign)
    {
        $this->authorize('delete', $campaign);
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Đã xóa chiến dịch.');
    }

    public function attachLeads(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $data = $request->validate(['lead_ids' => ['required', 'array', 'max:300'], 'lead_ids.*' => ['integer']], [], ['lead_ids' => 'Lead']);
        $ids = Lead::visibleTo($request->user())->whereIn('id', $data['lead_ids'])->pluck('id');
        $campaign->leads()->syncWithoutDetaching($ids->mapWithKeys(fn ($id) => [$id => ['created_at' => now()]])->all());

        return back()->with('success', 'Đã gắn '.$ids->count().' lead vào chiến dịch.');
    }

    public function detachLead(Campaign $campaign, Lead $lead)
    {
        $this->authorize('update', $campaign);
        $campaign->leads()->detach($lead->id);

        return back()->with('success', 'Đã gỡ lead khỏi chiến dịch.');
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', Campaign::class);
        $rows = $this->filtered($request)->orderBy('start_date')->get()->map(fn (Campaign $c) => [
            $c->name, Campaign::TYPES[$c->type] ?? $c->type, vn_date($c->start_date), vn_date($c->end_date), (float) $c->budget,
            $c->target_leads, $c->actual_leads, $c->converted_leads, $c->conversion_rate.'%', $c->cost_per_lead, label_of(Campaign::STATUSES, $c->status),
        ]);

        return $exporter->download('chien_dich', ['Chiến dịch', 'Loại', 'Bắt đầu', 'Kết thúc', 'Ngân sách', 'Lead mục tiêu', 'Lead thực tế',
            'Đã chuyển đổi', 'Tỷ lệ chuyển đổi', 'Chi phí/lead', 'Trạng thái'], $rows, $request->input('format', 'xlsx'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Campaign::TYPES))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'target_leads' => ['required', 'integer', 'min:0', 'max:1000000'],
            'status' => ['required', Rule::in(array_keys(Campaign::STATUSES))],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ], [], ['name' => 'Tên chiến dịch', 'owner_id' => 'Người phụ trách']);
    }
}
