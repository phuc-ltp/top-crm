<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Services\ContractService;
use App\Services\ExportService;
use App\Support\Lookup;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Quản lý hợp đồng khách hàng (quyền contracts.*, phạm vi "chỉ của mình" khi không có view_all). */
class ContractController extends Controller
{
    public function __construct(private ContractService $contracts) {}

    private function filtered(Request $request)
    {
        return Contract::visibleTo($request->user())->with(['customer:id,name', 'assignee:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', $this->like($request->q))->orWhere('code', 'like', $this->like($request->q))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $this->like($request->q)))))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->input('state') === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($request->input('state') === 'terminated', fn ($q) => $q->where('status', 'terminated'))
            ->when($request->input('state') === 'active', fn ($q) => $q->where('status', 'signed')->where(fn ($w) => $w->whereNull('end_date')->orWhereDate('end_date', '>', today()->addDays(30)))->where(fn ($w) => $w->whereNull('start_date')->orWhereDate('start_date', '<=', today())))
            ->when($request->input('state') === 'expiring', fn ($q) => $q->where('status', 'signed')->whereBetween('end_date', [today()->toDateString(), today()->addDays(30)->toDateString()]))
            ->when($request->input('state') === 'expired', fn ($q) => $q->where('status', 'signed')->whereDate('end_date', '<', today()))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Contract::class);
        $request->validate(['state' => ['nullable', Rule::in(array_keys(Contract::STATES))], 'type' => ['nullable', Rule::in(array_keys(Contract::TYPES))]]);
        $all = Contract::visibleTo($request->user())->get(['id', 'status', 'start_date', 'end_date', 'value']);

        return view('contracts.index', [
            'contracts' => $this->applySort($this->filtered($request), $request, ['code', 'title', 'value', 'end_date', 'created_at'], 'created_at')->paginate($this->perPage($request))->withQueryString(),
            'summary' => ['active' => $all->whereIn('state', ['active', 'expiring'])->count(), 'value' => (float) $all->whereIn('state', ['active', 'expiring'])->sum('value'),
                'expiring' => $all->where('state', 'expiring')->count(), 'expired' => $all->where('state', 'expired')->count()],
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Contract::class);
        $opportunity = $request->filled('opportunity_id') ? Opportunity::visibleTo($request->user())->find($request->integer('opportunity_id')) : null;
        $contract = new Contract(['type' => 'sale', 'status' => 'draft', 'assigned_to' => $request->user()->id, 'start_date' => today(), 'end_date' => today()->addYear()->subDay(),
            'customer_id' => $opportunity?->customer_id ?? ($request->integer('customer_id') ?: null), 'opportunity_id' => $opportunity?->id,
            'title' => $opportunity?->title, 'value' => $opportunity?->value]);

        return view('contracts.form', ['contract' => $contract, ...$this->formData($request)]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Contract::class);
        $contract = Contract::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('contracts.show', $contract)->with('success', __('Đã tạo hợp đồng :c.', ['c' => $contract->fresh()->code]));
    }

    public function show(Contract $contract)
    {
        $this->authorize('view', $contract);
        $contract->load(['customer', 'contact', 'opportunity', 'assignee', 'renewedFrom', 'renewals']);
        $documents = \App\Models\Document::visibleTo(request()->user())->where('related_type', 'contract')->where('related_id', $contract->id)->with('latestVersion')->latest()->get();

        return view('contracts.show', compact('contract', 'documents'));
    }

    public function edit(Request $request, Contract $contract)
    {
        $this->authorize('update', $contract);
        abort_if($contract->status === 'terminated', 422, __('Hợp đồng đã chấm dứt, không sửa được.'));

        return view('contracts.form', ['contract' => $contract, ...$this->formData($request)]);
    }

    public function update(Request $request, Contract $contract)
    {
        $this->authorize('update', $contract);
        abort_if($contract->status === 'terminated', 422, __('Hợp đồng đã chấm dứt, không sửa được.'));
        $contract->fill($this->validated($request, $contract));
        if ($contract->isDirty('end_date') || $contract->isDirty('status')) {
            $contract->reminded_stage = null;              // đổi hạn → nhắc lại theo hạn mới
        }
        $contract->save();

        return redirect()->route('contracts.show', $contract)->with('success', __('Đã cập nhật hợp đồng.'));
    }

    public function destroy(Contract $contract)
    {
        $this->authorize('delete', $contract);
        $contract->delete();

        return redirect()->route('contracts.index')->with('success', __('Đã chuyển hợp đồng vào thùng rác.'));
    }

    public function terminate(Request $request, Contract $contract)
    {
        $this->authorize('update', $contract);
        abort_unless($contract->status === 'signed', 422, __('Chỉ chấm dứt được hợp đồng đã ký.'));
        $data = $request->validate(['terminated_on' => ['required', 'date'], 'termination_reason' => ['required', 'string', 'max:500']], [],
            ['terminated_on' => __('Ngày chấm dứt'), 'termination_reason' => __('Lý do chấm dứt')]);
        $contract->forceFill(['status' => 'terminated', 'terminated_on' => $data['terminated_on'], 'termination_reason' => $data['termination_reason']])->save();

        return back()->with('success', __('Đã chấm dứt hợp đồng.'));
    }

    public function renew(Request $request, Contract $contract)
    {
        $this->authorize('update', $contract);
        $this->authorize('create', Contract::class);
        $new = $this->contracts->renew($contract, $request->user());

        return redirect()->route('contracts.edit', $new)->with('success', __('Đã tạo bản gia hạn :c (nháp) — kiểm tra giá trị, điều khoản rồi chuyển sang “Đã ký”.', ['c' => $new->fresh()->code]));
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', Contract::class);
        $rows = $this->filtered($request)->orderBy('code')->get()->map(fn (Contract $c) => [$c->code, $c->title, $c->customer?->name, label_of(Contract::TYPES, $c->type), (float) $c->value,
            $c->signed_date?->format('d/m/Y'), $c->start_date?->format('d/m/Y'), $c->end_date?->format('d/m/Y'), label_of(Contract::STATES, $c->state), $c->assignee?->name]);

        return $exporter->download('hop_dong', [__('Mã'), __('Tên hợp đồng'), __('Khách hàng'), __('Loại'), __('Giá trị'), __('Ngày ký'), __('Hiệu lực từ'), __('Hết hạn'),
            __('Tình trạng'), __('Phụ trách')], $rows, $request->input('format', 'xlsx'));
    }

    private function formData(Request $request): array
    {
        return [
            'customers' => Lookup::customers($request->user()),
            'contacts' => Contact::visibleTo($request->user())->orderBy('full_name')->get(['id', 'full_name', 'customer_id']),
            'opportunities' => Opportunity::visibleTo($request->user())->latest()->limit(300)->get(['id', 'title', 'customer_id'])->mapWithKeys(fn ($o) => [$o->id => $o->title])->all(),
            'users' => Lookup::users(),
        ];
    }

    private function validated(Request $request, ?Contract $contract = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'integer', TenantRule::exists('customers')],
            'contact_id' => ['nullable', 'integer', TenantRule::exists('contacts')],
            'opportunity_id' => ['nullable', 'integer', TenantRule::exists('opportunities')],
            'type' => ['required', Rule::in(array_keys(Contract::TYPES))],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'signed_date' => ['nullable', 'required_if:status,signed', 'date', 'before_or_equal:today'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['draft', 'signed'])],
            'auto_renew' => ['nullable', 'boolean'],
            'terms' => ['nullable', 'string', 'max:20000'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at'), TenantRule::member()],
        ], [], ['title' => __('Tên hợp đồng'), 'customer_id' => __('Khách hàng'), 'value' => __('Giá trị'), 'signed_date' => __('Ngày ký'),
            'start_date' => __('Hiệu lực từ'), 'end_date' => __('Ngày hết hạn')]);
        $data['auto_renew'] = $request->boolean('auto_renew');

        // Liên hệ / cơ hội phải thuộc đúng khách hàng và nằm trong phạm vi người dùng được xem
        foreach (['contact_id' => Contact::class, 'opportunity_id' => Opportunity::class] as $field => $class) {
            if (! empty($data[$field])) {
                $related = $class::visibleTo($request->user())->find($data[$field]);
                if (! $related || (int) $related->customer_id !== (int) $data['customer_id']) {
                    throw ValidationException::withMessages([$field => __('Không thuộc khách hàng đã chọn.')]);
                }
            }
        }
        $customer = \App\Models\Customer::visibleTo($request->user())->find($data['customer_id']);
        if (! $customer) {
            throw ValidationException::withMessages(['customer_id' => __('Khách hàng không hợp lệ.')]);
        }

        return $data;
    }
}
