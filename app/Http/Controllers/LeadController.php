<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\ExportService;
use App\Services\LeadService;
use App\Support\Lookup;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(private LeadService $service) {}

    private function filtered(Request $request)
    {
        return Lead::visibleTo($request->user())
            ->with(['assignee:id,name,avatar', 'source:id,name'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term)
                    ->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('company', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('source'), fn ($q) => $q->where('lead_source_id', $request->source))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->filled('campaign'), fn ($q) => $q->whereHas('campaigns', fn ($c) => $c->where('campaigns.id', $request->campaign)))
            ->when($request->filled('score'), fn ($q) => match ($request->score) {
                'hot' => $q->where('score', '>=', 70), 'warm' => $q->whereBetween('score', [40, 69]), 'cold' => $q->where('score', '<', 40), default => $q,
            });
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);

        $base = Lead::visibleTo($request->user());
        $summary = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $leads = $this->applySort($this->filtered($request), $request, ['code', 'name', 'score', 'estimated_value', 'created_at', 'status'])
            ->paginate($this->perPage($request))->withQueryString();

        return view('leads.index', [
            'leads' => $leads, 'summary' => $summary, 'users' => Lookup::users(), 'sources' => Lookup::sources(),
            'campaigns' => Lookup::campaigns(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Lead::class);

        return view('leads.form', $this->formData() + ['lead' => new Lead(['status' => 'new', 'assigned_to' => $request->user()->id])]);
    }

    public function store(LeadRequest $request)
    {
        $this->authorize('create', Lead::class);
        $data = $this->guardAssignment($request, $request->validated());
        $lead = $this->service->save(new Lead, $data, $request->user(), $request->boolean('auto_score', true));

        return redirect()->route('leads.show', $lead)->with('success', __('Đã tạo lead :name (điểm: :score).', ['name' => $lead->name, 'score' => $lead->score]));
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);
        $lead->load(['assignee', 'creator', 'source', 'campaigns', 'convertedCustomer', 'convertedOpportunity', 'interactions.user:id,name']);

        return view('leads.show', compact('lead'));
    }

    public function edit(Lead $lead)
    {
        $this->authorize('update', $lead);

        return view('leads.form', $this->formData() + ['lead' => $lead->load('campaigns')]);
    }

    public function update(LeadRequest $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $data = $this->guardAssignment($request, $request->validated(), $lead);
        $data['campaign_ids'] = $request->input('campaign_ids', []);
        $this->service->save($lead, $data, $request->user(), $request->boolean('auto_score'));

        return redirect()->route('leads.show', $lead)->with('success', __('Đã cập nhật lead.'));
    }

    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);
        $lead->delete();

        return redirect()->route('leads.index')->with('success', __('Đã xóa lead :name.', ['name' => $lead->name]));
    }

    public function convertForm(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        abort_if($lead->isConverted(), 409, __('Lead đã được chuyển đổi.'));

        $matches = Customer::visibleTo($request->user())
            ->where(fn ($q) => $q->when($lead->email, fn ($w) => $w->orWhere('email', $lead->email))
                ->when($lead->phone, fn ($w) => $w->orWhere('phone', $lead->phone)))
            ->when(! $lead->email && ! $lead->phone, fn ($q) => $q->whereRaw('1 = 0'))
            ->limit(5)->get();

        return view('leads.convert', ['lead' => $lead, 'matches' => $matches, 'customers' => Lookup::customers($request->user())]);
    }

    public function convert(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $this->authorize('create', Customer::class);

        $data = $request->validate([
            'mode' => ['required', 'in:new,existing'],
            'customer_id' => ['required_if:mode,existing', 'nullable', \App\Support\Tenancy\TenantRule::exists('customers')],
            'customer_name' => ['required_if:mode,new', 'nullable', 'string', 'max:255'],
            'customer_type' => ['nullable', 'in:individual,business'],
            'create_opportunity' => ['nullable', 'boolean'],
            'opportunity_title' => ['required_if:create_opportunity,1', 'nullable', 'string', 'max:255'],
            'opportunity_value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
        ], [], ['customer_id' => __('Khách hàng'), 'customer_name' => __('Tên khách hàng'), 'opportunity_title' => __('Tên cơ hội')]);

        if ($data['mode'] === 'new') {
            $data['customer_id'] = null;
        } else {
            abort_unless(Customer::visibleTo($request->user())->whereKey($data['customer_id'])->exists(), 403);
        }

        $result = $this->service->convert($lead, $data, $request->user());

        $redirect = $result['opportunity'] ? route('opportunities.show', $result['opportunity']) : route('customers.show', $result['customer']);

        return redirect($redirect)->with('success', __('Chuyển đổi lead thành công!'));
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', Lead::class);
        $rows = $this->filtered($request)->orderBy('id')->limit(20000)->get()->map(fn (Lead $l) => [
            $l->code, $l->name, $l->company, $l->email, $l->phone, $l->source?->label, label_of(Lead::STATUSES, $l->status),
            $l->score, (float) $l->estimated_value, $l->assignee?->name, vn_date($l->created_at), vn_date($l->converted_at),
        ]);

        return $exporter->download('leads', [__('Mã'), __('Tên'), __('Công ty'), 'Email', __('Điện thoại'), __('Nguồn'), __('Trạng thái'), __('Điểm'),
            __('Giá trị ước tính'), __('Phụ trách'), __('Ngày tạo'), __('Ngày chuyển đổi')], $rows, $request->input('format', 'xlsx'));
    }

    private function formData(): array
    {
        return ['users' => Lookup::users(), 'sources' => Lookup::sources(), 'campaigns' => Lookup::campaigns()];
    }

    private function guardAssignment(Request $request, array $data, ?Lead $existing = null): array
    {
        if (! $request->user()->can('leads.assign')) {
            $data['assigned_to'] = $existing ? $existing->assigned_to : $request->user()->id;
        }

        return $data;
    }
}
