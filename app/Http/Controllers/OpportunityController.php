<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Services\ExportService;
use App\Services\PipelineService;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OpportunityController extends Controller
{
    public function __construct(private PipelineService $pipeline) {}

    private function filtered(Request $request)
    {
        return Opportunity::visibleTo($request->user())
            ->with(['customer:id,name', 'assignee:id,name,avatar', 'stage'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('code', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->filled('stage'), fn ($q) => $q->where('pipeline_stage_id', $request->stage));
    }

    /** Bảng Kanban */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        $stages = PipelineStage::ordered()->get();
        $opportunities = $this->filtered($request)->orderBy('position')->orderByDesc('id')->limit(500)->get()->groupBy('pipeline_stage_id');

        $openValue = $opportunities->flatten()->filter(fn ($o) => ! $o->stage->isClosed())->sum('value');
        $weighted = $opportunities->flatten()->filter(fn ($o) => ! $o->stage->isClosed())->sum(fn ($o) => $o->weighted_value);

        return view('opportunities.index', [
            'stages' => $stages, 'opportunities' => $opportunities, 'users' => Lookup::users(),
            'openValue' => $openValue, 'weighted' => $weighted,
        ]);
    }

    /** Dạng danh sách */
    public function list(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);
        $opportunities = $this->applySort($this->filtered($request), $request, ['code', 'title', 'value', 'expected_close_date', 'created_at'])
            ->paginate($this->perPage($request))->withQueryString();

        return view('opportunities.list', ['opportunities' => $opportunities, 'users' => Lookup::users(), 'stages' => Lookup::stages()]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Opportunity::class);
        $stage = PipelineStage::ordered()->first();

        return view('opportunities.form', $this->formData($request) + ['opportunity' => new Opportunity([
            'customer_id' => $request->integer('customer_id') ?: null,
            'pipeline_stage_id' => $request->integer('stage') ?: $stage?->id,
            'probability' => $stage?->probability ?? 10,
            'assigned_to' => $request->user()->id,
            'expected_close_date' => now()->addDays(30),
        ])]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Opportunity::class);
        $data = $this->validated($request);

        $opportunity = DB::transaction(function () use ($data, $request) {
            $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);
            $data['position'] = (int) Opportunity::where('pipeline_stage_id', $stage->id)->max('position') + 1;
            $data['closed_at'] = $stage->isClosed() ? now() : null;
            $opportunity = Opportunity::create($data + ['created_by' => $request->user()->id]);
            $this->pipeline->recordStageChange($opportunity, null, $stage, $request->user());

            return $opportunity;
        });

        return redirect()->route('opportunities.show', $opportunity)->with('success', __('Đã tạo cơ hội :code.', ['code' => $opportunity->code]));
    }

    public function show(Opportunity $opportunity)
    {
        $this->authorize('view', $opportunity);
        $opportunity->load(['customer', 'contact', 'lead', 'stage', 'assignee', 'creator', 'interactions.user:id,name',
            'stageHistories.fromStage', 'stageHistories.toStage', 'stageHistories.user:id,name', 'quotations',
            'tasks' => fn ($q) => $q->with('assignee:id,name')->latest()]);

        return view('opportunities.show', ['opportunity' => $opportunity, 'stages' => PipelineStage::ordered()->get()]);
    }

    public function edit(Request $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);

        return view('opportunities.form', $this->formData($request) + ['opportunity' => $opportunity]);
    }

    public function update(Request $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);
        $data = $this->validated($request, $opportunity);

        DB::transaction(function () use ($data, $opportunity, $request) {
            $from = $opportunity->pipeline_stage_id;
            $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);
            if ($from !== $stage->id) {
                $data['closed_at'] = $stage->isClosed() ? now() : null;
            }
            $opportunity->update($data);
            if ($from !== $stage->id) {
                $this->pipeline->recordStageChange($opportunity, $from, $stage, $request->user());
            }
        });

        return redirect()->route('opportunities.show', $opportunity)->with('success', __('Đã cập nhật cơ hội.'));
    }

    public function destroy(Opportunity $opportunity)
    {
        $this->authorize('delete', $opportunity);
        $opportunity->delete();

        return redirect()->route('opportunities.index')->with('success', __('Đã xóa cơ hội.'));
    }

    /** AJAX: kéo thả thẻ Kanban */
    public function move(Request $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate([
            'pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'position' => ['required', 'integer', 'min:0'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);
        $opportunity = $this->pipeline->move($opportunity, $stage, $data['position'], $request->user());

        return response()->json([
            'success' => true,
            'message' => __('Đã chuyển ":title" sang ":stage".', ['title' => $opportunity->title, 'stage' => __($stage->name)]),
            'probability' => $opportunity->probability,
            'data' => ['id' => $opportunity->id, 'stage_id' => $stage->id, 'position' => $opportunity->position],
        ]);
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', Opportunity::class);
        $rows = $this->filtered($request)->orderBy('id')->limit(20000)->get()->map(fn (Opportunity $o) => [
            $o->code, $o->title, $o->customer?->name, $o->stage?->label, (float) $o->value, $o->probability.'%',
            $o->weighted_value, vn_date($o->expected_close_date), $o->assignee?->name, vn_date($o->created_at),
        ]);

        return $exporter->download('co_hoi', [__('Mã'), __('Cơ hội'), __('Khách hàng'), __('Giai đoạn'), __('Giá trị'), __('Xác suất'), __('Giá trị trọng số'),
            __('Dự kiến chốt'), __('Phụ trách'), __('Ngày tạo')], $rows, $request->input('format', 'xlsx'));
    }

    private function validated(Request $request, ?Opportunity $existing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'exists:customers,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'lost_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], ['title' => __('Tên cơ hội')]);

        abort_unless(Customer::visibleTo($request->user())->whereKey($data['customer_id'])->exists(), 403, __('Bạn không có quyền với khách hàng này.'));
        if (! empty($data['contact_id'])) {
            $contactOk = Contact::whereKey($data['contact_id'])->where('customer_id', $data['customer_id'])->exists();
            abort_unless($contactOk, 422, __('Người liên hệ không thuộc khách hàng đã chọn.'));
        }
        if (! $request->user()->can('opportunities.assign')) {
            $data['assigned_to'] = $existing ? $existing->assigned_to : $request->user()->id;
        }

        return $data;
    }

    private function formData(Request $request): array
    {
        return [
            'customers' => Lookup::customers($request->user()),
            'contacts' => Contact::visibleTo($request->user())->orderBy('full_name')->get(['id', 'full_name', 'customer_id']),
            'stages' => PipelineStage::ordered()->get(),
            'users' => Lookup::users(),
        ];
    }
}
