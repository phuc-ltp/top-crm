<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\LostReason;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Product;
use App\Services\ActivityLogger;
use App\Services\ExportService;
use App\Services\PipelineService;
use App\Services\UploadService;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OpportunityController extends Controller
{
    public function __construct(private PipelineService $pipeline) {}

    /** Pipeline đang xem (?pipeline=id trong tổ chức, mặc định: pipeline mặc định). */
    private function currentPipeline(Request $request): ?Pipeline
    {
        return ($request->filled('pipeline') ? Pipeline::where('is_active', true)->find($request->integer('pipeline')) : null) ?? Pipeline::default();
    }

    private function filtered(Request $request)
    {
        return Opportunity::visibleTo($request->user())
            ->with(['customer:id,name', 'assignee:id,name,avatar', 'stage', 'pipeline:id,name'])
            ->withMin(['interactions as next_follow_up' => fn ($q) => $q->where('status', 'planned')], 'scheduled_at')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('code', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->filled('stage'), fn ($q) => $q->where('pipeline_stage_id', $request->stage))
            ->when($request->filled('pipeline'), fn ($q) => $q->where('pipeline_id', $request->integer('pipeline')))
            ->when($request->follow_up === 'overdue', fn ($q) => $q->whereHas('interactions', fn ($i) => $i->where('status', 'planned')->where('scheduled_at', '<', now())))
            ->when($request->follow_up === 'none', fn ($q) => $q->open()->whereDoesntHave('interactions', fn ($i) => $i->where('status', 'planned')));
    }

    /** Bảng Kanban của một pipeline */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);
        $pipeline = $this->currentPipeline($request);
        $stages = $pipeline ? $pipeline->stages()->get() : collect();
        $opportunities = $this->filtered($request)->when($pipeline, fn ($q) => $q->where('pipeline_id', $pipeline->id))
            ->orderBy('position')->orderByDesc('id')->limit(500)->get()->groupBy('pipeline_stage_id');

        $open = $opportunities->flatten()->filter(fn ($o) => ! $o->stage->isClosed());

        return view('opportunities.index', [
            'pipeline' => $pipeline, 'pipelines' => Pipeline::where('is_active', true)->ordered()->get(),
            'stages' => $stages, 'opportunities' => $opportunities, 'users' => Lookup::users(),
            'openValue' => $open->sum('value'), 'weighted' => $open->sum(fn ($o) => $o->weighted_value),
            'lostReasons' => LostReason::where('is_active', true)->whereIn('applies_to', ['both', 'opportunity'])->orderBy('position')->pluck('name', 'id'),
        ]);
    }

    /** Dạng danh sách (mọi pipeline) */
    public function list(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);
        $opportunities = $this->applySort($this->filtered($request), $request, ['code', 'title', 'value', 'expected_close_date', 'created_at'])
            ->paginate($this->perPage($request))->withQueryString();

        return view('opportunities.list', ['opportunities' => $opportunities, 'users' => Lookup::users(), 'stages' => Lookup::stages(),
            'pipelines' => Pipeline::ordered()->pluck('name', 'id')]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Opportunity::class);
        $stage = $request->filled('stage') ? PipelineStage::find($request->integer('stage')) : null;
        $pipeline = $stage?->pipeline ?? $this->currentPipeline($request);
        $stage ??= $pipeline?->firstOpenStage();

        return view('opportunities.form', $this->formData($request) + ['opportunity' => new Opportunity([
            'customer_id' => $request->integer('customer_id') ?: null,
            'pipeline_id' => $pipeline?->id,
            'pipeline_stage_id' => $stage?->id,
            'probability' => $stage?->probability ?? 10,
            'assigned_to' => $request->user()->id,
            'expected_close_date' => now()->addDays(30),
        ])]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Opportunity::class);
        [$data, $items, $useItems, $followUpAt] = $this->validated($request);

        $opportunity = DB::transaction(function () use ($data, $items, $useItems, $followUpAt, $request) {
            $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);
            $data['position'] = (int) Opportunity::where('pipeline_stage_id', $stage->id)->max('position') + 1;
            $data['closed_at'] = $stage->isClosed() ? now() : null;
            $opportunity = Opportunity::create($data + ['created_by' => $request->user()->id]);
            $total = $this->pipeline->syncItems($opportunity, $items);
            if ($useItems) {
                $opportunity->forceFill(['value' => $total])->saveQuietly();
            }
            $this->pipeline->recordStageChange($opportunity, null, $stage, $request->user());
            $this->scheduleFollowUp($opportunity, $followUpAt, $request);

            return $opportunity;
        });

        return redirect()->route('opportunities.show', $opportunity)->with('success', __('Đã tạo cơ hội :code.', ['code' => $opportunity->code]));
    }

    public function show(Opportunity $opportunity)
    {
        $this->authorize('view', $opportunity);
        $opportunity->load(['customer', 'contact', 'lead', 'stage', 'pipeline', 'assignee', 'creator', 'lostReasonRef', 'items.product:id,sku',
            'attachments.user:id,name',
            'stageHistories.fromStage', 'stageHistories.toStage', 'stageHistories.user:id,name',
            'quotations' => fn ($q) => $this->onlyVisible($q, 'quotations', request()->user()),
            'tasks' => fn ($q) => $this->onlyVisible($q, 'tasks', request()->user())->with('assignee:id,name')->latest()]);

        return view('opportunities.show', ['opportunity' => $opportunity, 'stages' => $opportunity->pipeline?->stages()->get() ?? collect()]);
    }

    public function edit(Request $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);

        return view('opportunities.form', $this->formData($request) + ['opportunity' => $opportunity->load('items')]);
    }

    public function update(Request $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);
        [$data, $items, $useItems, $followUpAt] = $this->validated($request, $opportunity);

        DB::transaction(function () use ($data, $items, $useItems, $followUpAt, $opportunity, $request) {
            $from = $opportunity->pipeline_stage_id;
            $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);
            if ($from !== $stage->id) {
                $data['closed_at'] = $stage->isClosed() ? now() : null;
            }
            if (! $stage->is_lost) {
                $data['lost_reason_id'] = null;
                $data['lost_reason'] = null;
            }
            $opportunity->update($data);
            $total = $this->pipeline->syncItems($opportunity, $items);
            if ($useItems) {
                $opportunity->forceFill(['value' => $total])->saveQuietly();
            }
            if ($from !== $stage->id) {
                $this->pipeline->recordStageChange($opportunity, $from, $stage, $request->user());
            }
            $this->scheduleFollowUp($opportunity, $followUpAt, $request);
        });

        return redirect()->route('opportunities.show', $opportunity)->with('success', __('Đã cập nhật cơ hội.'));
    }

    public function destroy(Opportunity $opportunity)
    {
        $this->authorize('delete', $opportunity);
        $opportunity->delete();

        return redirect()->route('opportunities.index')->with('success', __('Đã xóa cơ hội.'));
    }

    /** AJAX: kéo thả thẻ Kanban (chỉ trong cùng pipeline; vào cột thất bại phải có lý do) */
    public function move(Request $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate([
            'pipeline_stage_id' => ['required', \App\Support\Tenancy\TenantRule::exists('pipeline_stages')],
            'position' => ['required', 'integer', 'min:0'],
            'lost_reason_id' => ['nullable', 'integer'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);
        $opportunity = $this->pipeline->move($opportunity, $stage, $data['position'], $request->user(), $data['lost_reason_id'] ?? null, $data['lost_reason'] ?? null);

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
        $rows = $this->filtered($request)->with('lostReasonRef:id,name')->orderBy('id')->limit(20000)->get()->map(fn (Opportunity $o) => [
            $o->code, $o->title, $o->customer?->name, $o->pipeline?->label, $o->stage?->label, (float) $o->value, $o->probability.'%',
            $o->weighted_value, vn_date($o->expected_close_date), $o->assignee?->name, $o->lostReasonRef?->name ?? $o->lost_reason, vn_date($o->created_at),
        ]);

        return $exporter->download('co_hoi', [__('Mã'), __('Cơ hội'), __('Khách hàng'), __('Pipeline'), __('Giai đoạn'), __('Giá trị'), __('Xác suất'), __('Giá trị trọng số'),
            __('Dự kiến chốt'), __('Phụ trách'), __('Lý do thất bại'), __('Ngày tạo')], $rows, $request->input('format', 'xlsx'));
    }

    // ---------------- Tệp đính kèm ----------------

    public function addAttachment(Request $request, Opportunity $opportunity, UploadService $uploads)
    {
        $this->authorize('update', $opportunity);
        $request->validate(['file' => ['required', 'file', 'mimes:'.config('crm.attachment_mimes'), 'max:'.config('crm.attachment_max_kb')]], [], ['file' => __('Tệp đính kèm')]);
        $attachment = $uploads->attach($opportunity, $request->file('file'));
        ActivityLogger::log('updated', $opportunity, 'Tải lên tệp đính kèm: '.$attachment->original_name);

        return back()->with('success', __('Đã tải lên tệp đính kèm.'));
    }

    public function downloadAttachment(Opportunity $opportunity, Attachment $attachment)
    {
        $this->authorize('view', $opportunity);
        abort_unless($attachment->attachable_type === 'opportunity' && (int) $attachment->attachable_id === (int) $opportunity->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404, __('Tệp không còn tồn tại.'));

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function deleteAttachment(Opportunity $opportunity, Attachment $attachment)
    {
        $this->authorize('update', $opportunity);
        abort_unless($attachment->attachable_type === 'opportunity' && (int) $attachment->attachable_id === (int) $opportunity->id, 404);
        $attachment->delete();
        ActivityLogger::log('updated', $opportunity, 'Xóa tệp đính kèm: '.$attachment->original_name);

        return back()->with('success', __('Đã xóa tệp đính kèm.'));
    }

    // ---------------- Hỗ trợ ----------------

    private function scheduleFollowUp(Opportunity $opportunity, ?string $followUpAt, Request $request): void
    {
        if ($followUpAt) {
            $opportunity->interactions()->create([
                'type' => 'follow_up', 'status' => 'planned', 'subject' => __('Theo dõi cơ hội :title', ['title' => $opportunity->title]),
                'scheduled_at' => $followUpAt, 'user_id' => $request->user()->id, 'assigned_to' => $opportunity->assigned_to ?? $request->user()->id,
            ]);
        }
    }

    /** @return array{0: array, 1: array, 2: bool, 3: ?string} [dữ liệu cơ hội, dòng sản phẩm, tính giá trị từ sản phẩm, hẹn theo dõi] */
    private function validated(Request $request, ?Opportunity $existing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', \App\Support\Tenancy\TenantRule::exists('customers')],
            'contact_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('contacts')],
            'pipeline_stage_id' => ['required', \App\Support\Tenancy\TenantRule::exists('pipeline_stages')],
            'value' => ['nullable', 'required_unless:value_from_items,1', 'numeric', 'min:0', 'max:999999999999'],
            'value_from_items' => ['nullable', 'boolean'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at'), \App\Support\Tenancy\TenantRule::member()],
            'lost_reason_id' => ['nullable', 'integer'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_at' => ['nullable', 'date', 'after:now'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.product_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('products')],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01', 'max:9999999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [], ['title' => __('Tên cơ hội'), 'follow_up_at' => __('Hẹn theo dõi'), 'lost_reason_id' => __('Lý do thất bại')]);

        abort_unless(Customer::visibleTo($request->user())->whereKey($data['customer_id'])->exists(), 403, __('Bạn không có quyền với khách hàng này.'));
        if (! empty($data['contact_id'])) {
            $contactOk = Contact::whereKey($data['contact_id'])->where('customer_id', $data['customer_id'])->exists();
            abort_unless($contactOk, 422, __('Người liên hệ không thuộc khách hàng đã chọn.'));
        }
        $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);
        // Lý do gửi lên luôn được kiểm tra (thuộc tổ chức, đang dùng, áp dụng cho cơ hội); bắt buộc khi chuyển sang giai đoạn thất bại
        $reasonId = isset($data['lost_reason_id']) ? (int) $data['lost_reason_id'] : null;
        $alreadyLostWithReason = $existing && (int) $existing->pipeline_stage_id === (int) $stage->id && ($existing->lost_reason_id || $existing->lost_reason);
        if ($reasonId !== null || ($stage->is_lost && ! $alreadyLostWithReason)) {
            $this->pipeline->assertLostReason($reasonId);
        }
        if (! $request->user()->can('opportunities.assign')) {
            $data['assigned_to'] = $existing ? $existing->assigned_to : $request->user()->id;
        }

        $items = $data['items'] ?? [];
        $useItems = $request->boolean('value_from_items') && collect($items)->contains(fn ($r) => filled($r['name'] ?? null) || filled($r['product_id'] ?? null));
        $followUpAt = $data['follow_up_at'] ?? null;
        unset($data['items'], $data['value_from_items'], $data['follow_up_at']);
        $data['value'] = (float) ($data['value'] ?? 0);

        return [$data, $items, $useItems, $followUpAt];
    }

    private function formData(Request $request): array
    {
        return [
            'customers' => Lookup::customers($request->user()),
            'contacts' => Contact::visibleTo($request->user())->orderBy('full_name')->get(['id', 'full_name', 'customer_id']),
            'pipelines' => Pipeline::where('is_active', true)->ordered()->with('stages')->get(),
            'users' => Lookup::users(),
            'products' => Product::where('status', 'active')->orderBy('name')->get(['id', 'name', 'sku', 'price']),
            'lostReasons' => LostReason::where('is_active', true)->whereIn('applies_to', ['both', 'opportunity'])->orderBy('position')->pluck('name', 'id'),
        ];
    }
}
