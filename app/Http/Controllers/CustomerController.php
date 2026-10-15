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
            ->when($request->input('archived') === '1', fn ($q) => $q->onlyTrashed());
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $customers = $this->applySort($this->filtered($request), $request, ['code', 'name', 'created_at', 'status'])
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
            'assignee', 'creator', 'source', 'tags',
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

        $rows = $this->filtered($request)->orderBy('id')->limit(20000)->get()->map(fn (Customer $c) => [
            $c->code, $c->name, $c->phone, $c->email, $c->address, $c->company, Customer::TYPES[$c->type] ?? $c->type,
            $c->source?->label, label_of(Customer::STATUSES, $c->status), $c->assignee?->name,
            $c->tags->pluck('name')->implode(', '), vn_date($c->created_at, true), vn_date($c->updated_at, true),
        ]);

        return $exporter->download('khach_hang', [
            __('Mã KH'), __('Tên khách hàng'), __('Điện thoại'), 'Email', __('Địa chỉ'), __('Công ty'), __('Loại'), __('Nguồn'), __('Trạng thái'),
            __('Phụ trách'), __('Thẻ'), __('Ngày tạo'), __('Cập nhật'),
        ], $rows, $request->input('format', 'xlsx'));
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
            ."Nguyễn Văn A,0901234567,nguyenvana@example.com,\"12 Lê Lợi, Q.1, TP.HCM\",Công ty ABC,business,Website,active\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mau_nhap_khach_hang.csv"',
        ]);
    }

    public function import(Request $request, CustomerImportService $importer)
    {
        $this->authorize('create', Customer::class);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']], [], ['file' => __('Tệp CSV')]);

        $result = $importer->import($request->file('file'), $request->user());

        return redirect()->route('customers.import')->with('import_result', $result)
            ->with($result['created'] > 0 ? 'success' : 'warning', __('Đã nhập :created khách hàng, bỏ qua :skipped dòng.', ['created' => $result['created'], 'skipped' => $result['skipped']]));
    }

    private function formData(): array
    {
        return ['users' => Lookup::users(), 'sources' => Lookup::sources(), 'tags' => Lookup::tags()];
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
