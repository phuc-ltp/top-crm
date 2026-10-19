<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\ActivityLogger;
use App\Services\CustomerImportService;
use App\Services\CustomerService;
use App\Services\ExportService;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $service) {}

    private function filtered(Request $request)
    {
        $user = $request->user();

        return Customer::query()
            ->visibleTo($user)
            ->with(['assignee:id,name,avatar', 'source:id,name', 'tags:id,name,color'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term)
                    ->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('company', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('source'), fn ($q) => $q->where('lead_source_id', $request->source))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->filled('tag'), fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $request->tag)))
            // Lọc nâng cao (chỉ nhận giá trị trong danh mục cho phép)
            ->when(array_key_exists((string) $request->segment, Customer::SEGMENTS), fn ($q) => $q->where('segment', $request->segment))
            ->when(array_key_exists((string) $request->lifecycle, Customer::LIFECYCLE_STAGES), fn ($q) => $q->where('lifecycle_stage', $request->lifecycle))
            ->when(array_key_exists(strtoupper((string) $request->country), config('crm.countries', [])), fn ($q) => $q->where('country', strtoupper($request->country)))
            ->when(in_array($request->consent, ['0', '1'], true), fn ($q) => $q->where('marketing_consent', $request->consent === '1'))
            ->when($this->validDate($request->created_from), fn ($q) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($request->created_from)->startOfDay()))
            ->when($this->validDate($request->created_to), fn ($q) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($request->created_to)->endOfDay()))
            ->when($request->input('archived') === '1', fn ($q) => $q->onlyTrashed());
    }

    private function validDate($value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $customers = $this->applySort($this->filtered($request), $request, ['code', 'name', 'created_at', 'updated_at', 'status', 'segment', 'lifecycle_stage'])
            ->paginate($this->perPage($request))->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'users' => Lookup::users(),
            'sources' => Lookup::sources(),
            'tags' => Lookup::tags(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', $this->formData() + ['customer' => new Customer([
            'type' => 'individual', 'status' => 'active', 'assigned_to' => $request->user()->id,
        ])]);
    }

    public function store(CustomerRequest $request)
    {
        $this->authorize('create', Customer::class);
        $data = $this->guardAssignment($request, $request->validated());

        if (! $request->boolean('confirm_duplicate')) {
            $duplicates = $this->service->findDuplicates($data['email'] ?? null, $data['phone'] ?? null);
            if ($duplicates->isNotEmpty()) {
                return back()->withInput()->with('duplicates', $duplicates)
                    ->with('warning', __('Phát hiện khách hàng có thể bị trùng. Kiểm tra lại hoặc chọn "Vẫn tạo khách hàng".'));
            }
        }

        $customer = $this->service->save(new Customer, $data, $request->user());

        return redirect()->route('customers.show', $customer)->with('success', __('Đã tạo khách hàng :name.', ['name' => $customer->name]));
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        // Bản ghi liên quan chỉ hiện trong phạm vi quyền của người xem (không có quyền module → không thấy)
        $user = request()->user();
        $customer->load([
            'assignee', 'creator', 'source', 'tags', 'attachments.user:id,name',
            'contacts' => fn ($q) => $this->onlyVisible($q, 'contacts', $user),
            'opportunities' => fn ($q) => $this->onlyVisible($q, 'opportunities', $user)->with('stage')->latest()->limit(10),
            'invoices' => fn ($q) => $this->onlyVisible($q, 'invoices', $user)->latest('issue_date')->limit(10),
            'tickets' => fn ($q) => $this->onlyVisible($q, 'tickets', $user)->latest()->limit(10),
            'tasks' => fn ($q) => $this->onlyVisible($q, 'tasks', $user)->with('assignee:id,name')->latest()->limit(10),
        ]);

        $stats = [
            'revenue' => $user->can('invoices.view') ? (float) $this->onlyVisible($customer->invoices(), 'invoices', $user)->where('status', 'issued')->sum('amount_paid') : null,
            'open_deals' => $user->can('opportunities.view') ? $this->onlyVisible($customer->opportunities(), 'opportunities', $user)->open()->sum('value') : null,
            'tickets_open' => $user->can('tickets.view') ? $this->onlyVisible($customer->tickets(), 'tickets', $user)->whereIn('status', ['open', 'in_progress', 'waiting'])->count() : null,
        ];

        return view('customers.show', compact('customer', 'stats'));
    }

    public function edit(Customer $customer)
    {
        $this->authorize('update', $customer);

        return view('customers.edit', $this->formData() + ['customer' => $customer->load('tags')]);
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $data = $this->guardAssignment($request, $request->validated(), $customer);

        if (! $request->boolean('confirm_duplicate')) {
            $duplicates = $this->service->findDuplicates($data['email'] ?? null, $data['phone'] ?? null, $customer->id);
            if ($duplicates->isNotEmpty()) {
                return back()->withInput()->with('duplicates', $duplicates)
                    ->with('warning', __('Email hoặc số điện thoại trùng với khách hàng khác.'));
            }
        }

        $this->service->save($customer, $data + ['tags' => $request->input('tags', [])], $request->user());

        return redirect()->route('customers.show', $customer)->with('success', __('Đã cập nhật khách hàng.'));
    }

    public function destroy(Request $request, $id)
    {
        $customer = Customer::withTrashed()->findOrFail($id);
        $this->authorize('delete', $customer);

        if ($customer->trashed()) {
            // Khôi phục từ lưu trữ
            $customer->restore();

            return back()->with('success', __('Đã khôi phục khách hàng :name.', ['name' => $customer->name]));
        }

        $customer->delete(); // xóa mềm (lưu trữ)

        return redirect()->route('customers.index')->with('success', __('Đã lưu trữ khách hàng :name. Có thể khôi phục trong mục "Đã lưu trữ".', ['name' => $customer->name]));
    }

    public function checkDuplicate(Request $request)
    {
        $this->authorize('viewAny', Customer::class);
        $request->validate(['email' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:30', 'except' => 'nullable|integer']);

        $items = $this->service->findDuplicates($request->email, $request->phone, $request->integer('except') ?: null);

        return response()->json([
            'duplicates' => $items->map(fn ($c) => [
                'code' => $c->code, 'name' => $c->name, 'email' => $c->email, 'phone' => $c->phone,
                'assignee' => $c->assignee?->name,
                'url' => $request->user()->can('view', $c) ? route('customers.show', $c) : null,
            ]),
        ]);
    }

    public function bulkAssign(Request $request)
    {
        $this->authorize('assign', Customer::class);
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'],
            'assigned_to' => ['required', Rule::exists('users', 'id')->where('status', 'active'), \App\Support\Tenancy\TenantRule::member()],
        ], [], ['ids' => __('Khách hàng được chọn')]);

        $count = Customer::visibleTo($request->user())->whereIn('id', $data['ids'])->update(['assigned_to' => $data['assigned_to'], 'updated_by' => $request->user()->id]);
        $assignee = User::find($data['assigned_to']);
        ActivityLogger::log('assigned', null, "Phân công $count khách hàng cho {$assignee->name}");
        if ($assignee->id !== $request->user()->id) {
            CrmNotification::send($assignee, 'assigned', "Bạn được phân công phụ trách $count khách hàng", route('customers.index', ['assigned_to' => $assignee->id]), 'bi-people');
        }

        return back()->with('success', __('Đã phân công :count khách hàng cho :name.', ['count' => $count, 'name' => $assignee->name]));
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', Customer::class);

        $customFields = \App\Models\CustomFieldDefinition::for('customer')->active()->get();
        $rows = $this->filtered($request)->orderBy('id')->limit(20000)->get()->map(fn (Customer $c) => [
            $c->code, $c->name, $c->phone, $c->email, $c->address, $c->country ? country_name($c->country) : '', $c->company, __(Customer::TYPES[$c->type] ?? $c->type),
            $c->source?->label, label_of(Customer::STATUSES, $c->status),
            $c->segment ? label_of(Customer::SEGMENTS, $c->segment) : '', $c->lifecycle_stage ? label_of(Customer::LIFECYCLE_STAGES, $c->lifecycle_stage) : '',
            $c->preferred_channel ? __(Customer::CHANNELS[$c->preferred_channel] ?? $c->preferred_channel) : '',
            $c->marketing_consent ? __('Có') : __('Không'), $c->marketing_consent_source, $c->assignee?->name,
            $c->tags->pluck('name')->implode(', '), vn_date($c->created_at, true), vn_date($c->updated_at, true),
            ...$customFields->map(fn ($f) => ($v = ($c->custom_fields[$f->key] ?? null)) === null ? '' : $f->display($v))->all(),
        ]);

        return $exporter->download('khach_hang', [
            __('Mã KH'), __('Tên khách hàng'), __('Điện thoại'), 'Email', __('Địa chỉ'), __('Quốc gia'), __('Công ty'), __('Loại'), __('Nguồn'), __('Trạng thái'),
            __('Phân khúc'), __('Giai đoạn vòng đời'), __('Kênh liên lạc ưa thích'), __('Đồng ý marketing'), __('Nguồn đồng ý'),
            __('Phụ trách'), __('Thẻ'), __('Ngày tạo'), __('Cập nhật'), ...$customFields->pluck('label')->all(),
        ], $rows, $request->input('format', 'xlsx'));
    }

    /**
     * Gộp khách hàng trùng: $customer là bản GIỮ LẠI, ?with= là bản sẽ bị gộp (chuyển hết dữ liệu rồi lưu trữ).
     * Cần quyền sửa bản giữ lại và quyền xóa (lưu trữ) bản bị gộp.
     */
    public function mergeForm(Request $request, Customer $customer, \App\Services\CustomerMergeService $merger)
    {
        $this->authorize('update', $customer);
        $user = $request->user();

        $suggestions = $this->service->findDuplicates($customer->email, $customer->phone, $customer->id)
            ->merge(Customer::visibleTo($user)->whereKeyNot($customer->id)->where('name', $customer->name)->limit(10)->get())
            ->unique('id')->filter(fn ($c) => $user->can('delete', $c))->values();
        $results = $request->filled('q')
            ? Customer::visibleTo($user)->whereKeyNot($customer->id)
                ->where(fn ($w) => $w->where('name', 'like', $this->like($request->q))->orWhere('code', 'like', $this->like($request->q))
                    ->orWhere('email', 'like', $this->like($request->q))->orWhere('phone', 'like', $this->like($request->q)))
                ->limit(15)->get()->filter(fn ($c) => $user->can('delete', $c))->values()
            : collect();

        $other = null;
        $preview = null;
        if ($request->filled('with')) {
            $other = Customer::visibleTo($user)->whereKeyNot($customer->id)->findOrFail($request->integer('with'));
            $this->authorize('delete', $other);
            $preview = $merger->preview($other);
        }

        return view('customers.merge', compact('customer', 'suggestions', 'results', 'other', 'preview'));
    }

    public function merge(Request $request, Customer $customer, \App\Services\CustomerMergeService $merger)
    {
        $this->authorize('update', $customer);
        $request->validate(['merge_id' => ['required', 'integer'], 'confirm' => ['accepted']], [], ['confirm' => __('Xác nhận')]);
        $other = Customer::visibleTo($request->user())->whereKeyNot($customer->id)->findOrFail($request->integer('merge_id'));
        $this->authorize('delete', $other);

        $result = $merger->merge($customer, $other, $request->user());
        $total = array_sum($result['moved']);

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Đã gộp :from vào :to: chuyển :count bản ghi liên quan. Bản :from đã được lưu trữ.', ['from' => $other->code, 'to' => $customer->code, 'count' => $total]));
    }

    public function addAttachment(Request $request, Customer $customer, \App\Services\UploadService $uploads)
    {
        $this->authorize('update', $customer);
        $request->validate([
            'file' => ['required', 'file', 'mimes:'.config('crm.attachment_mimes'), 'max:'.config('crm.attachment_max_kb')],
        ], [], ['file' => __('Tệp đính kèm')]);
        $attachment = $uploads->attach($customer, $request->file('file'));
        ActivityLogger::log('updated', $customer, 'Tải lên tệp đính kèm: '.$attachment->original_name);

        return back()->with('success', __('Đã tải lên tệp đính kèm.'));
    }

    public function downloadAttachment(Customer $customer, \App\Models\Attachment $attachment)
    {
        $this->authorize('view', $customer);
        abort_unless($attachment->attachable_type === 'customer' && (int) $attachment->attachable_id === (int) $customer->id, 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($attachment->path), 404, __('Tệp không còn tồn tại.'));

        return \Illuminate\Support\Facades\Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function deleteAttachment(Customer $customer, \App\Models\Attachment $attachment)
    {
        $this->authorize('update', $customer);
        abort_unless($attachment->attachable_type === 'customer' && (int) $attachment->attachable_id === (int) $customer->id, 404);
        $attachment->delete();
        ActivityLogger::log('updated', $customer, 'Xóa tệp đính kèm: '.$attachment->original_name);

        return back()->with('success', __('Đã xóa tệp đính kèm.'));
    }

    public function importForm()
    {
        $this->authorize('create', Customer::class);

        return view('customers.import');
    }

    public function importTemplate()
    {
        $this->authorize('create', Customer::class);
        $csv = "\xEF\xBB\xBF".implode(',', CustomerImportService::TEMPLATE_HEADINGS)."\n"
            ."Nguyễn Văn A,0901234567,nguyenvana@example.com,\"12 Lê Lợi, Q.1, TP.HCM\",VN,Công ty ABC,business,Website,active,sme,new,phone,yes,Form website\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mau_nhap_khach_hang.csv"',
        ]);
    }

    public function import(Request $request, CustomerImportService $importer)
    {
        $this->authorize('create', Customer::class);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120']], [], ['file' => __('Tệp CSV / Excel')]);

        $result = $importer->import($request->file('file'), $request->user());

        return redirect()->route('customers.import')->with('import_result', $result)
            ->with($result['created'] > 0 ? 'success' : 'warning', __('Đã nhập :created khách hàng, bỏ qua :skipped dòng.', ['created' => $result['created'], 'skipped' => $result['skipped']]));
    }

    private function formData(): array
    {
        return ['users' => Lookup::users(), 'sources' => Lookup::sources(), 'tags' => Lookup::tags(),
            'priceLists' => \App\Models\PriceList::orderBy('name')->get(['id', 'name', 'is_active'])->mapWithKeys(fn ($l) => [$l->id => $l->name.($l->is_active ? '' : ' ('.__('đang tắt').')')])->all(),
            'customFields' => \App\Models\CustomFieldDefinition::for('customer')->active()->get()];
    }

    /** Người không có quyền phân công không được tự đổi người phụ trách. */
    private function guardAssignment(Request $request, array $data, ?Customer $existing = null): array
    {
        if (! $request->user()->can('customers.assign')) {
            $data['assigned_to'] = $existing ? $existing->assigned_to : $request->user()->id;
        }

        return $data;
    }
}
