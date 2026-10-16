<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Setting;
use App\Services\ExportService;
use App\Services\SalesDocumentService;
use App\Support\Lookup;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controller dùng chung cho Báo giá, Đơn hàng, Hóa đơn.
 * Lớp con khai báo cấu hình; tổng tiền luôn được tính lại bởi SalesDocumentService.
 */
abstract class SalesDocumentController extends Controller
{
    /** Class model (Quotation / SalesOrder / Invoice) */
    abstract protected function modelClass(): string;

    /** Tiền tố route: quotations | orders | invoices */
    protected string $route;

    /** Tên hiển thị: Báo giá | Đơn hàng | Hóa đơn */
    protected string $label;

    /** Cột ngày chính */
    protected string $dateField;

    public function __construct(protected SalesDocumentService $documents) {}

    /** Quy tắc riêng cho từng loại chứng từ. */
    abstract protected function extraRules(): array;

    /** Giá trị mặc định khi tạo mới. */
    abstract protected function defaults(Request $request): array;

    /** Chứng từ còn được sửa nội dung hay không. */
    protected function editable(Model $doc): bool
    {
        return true;
    }

    /** Tên chứng từ số ít, viết thường, đã dịch (vd: báo giá / quotation). */
    protected function docName(): string
    {
        return mb_strtolower($this->config()['doc']);
    }

    protected function config(): array
    {
        $class = $this->modelClass();

        return [
            'route' => $this->route,
            'label' => __($this->label),
            // Tên số ít đã dịch (vd: Báo giá / Quotation) để ghép câu "Tạo :doc"
            'doc' => __('labels.'.rtrim($this->route === 'orders' ? 'order' : $this->route, 's')),
            'dateField' => $this->dateField,
            'statuses' => $class::STATUSES,
            'class' => $class,
        ];
    }

    protected function filtered(Request $request)
    {
        $class = $this->modelClass();

        return $class::visibleTo($request->user())
            ->with(['customer:id,code,name', 'assignee:id,name'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('number', 'like', $term)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->payment_status))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->filled('from'), fn ($q) => $q->whereDate($this->dateField, '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate($this->dateField, '<=', $request->to));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', $this->modelClass());
        $docs = $this->applySort($this->filtered($request), $request, ['number', $this->dateField, 'total', 'status', 'created_at'], $this->dateField)
            ->paginate($this->perPage($request))->withQueryString();

        $totals = (clone $this->filtered($request))->toBase()->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total),0) as total')->first();

        return view('sales.index', ['docs' => $docs, 'totals' => $totals, 'users' => Lookup::users(), 'cfg' => $this->config()]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', $this->modelClass());
        $class = $this->modelClass();
        $doc = new $class($this->defaults($request) + [
            'customer_id' => $request->integer('customer_id') ?: null,
            $this->dateField => today(),
            'tax_rate' => (float) Setting::get('default_tax_rate', 10),
            'discount_amount' => 0,
            'assigned_to' => $request->user()->id,
        ]);

        return view('sales.form', $this->formData($request) + ['doc' => $doc, 'items' => [['quantity' => 1]]]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', $this->modelClass());
        [$attributes, $items] = $this->validated($request);
        $class = $this->modelClass();
        $attributes['created_by'] = $request->user()->id;

        $doc = $this->documents->save(new $class, $attributes, $items);

        return redirect()->route($this->route.'.show', $doc)->with('success', __('Đã tạo :doc :number.', ['doc' => $this->docName(), 'number' => $doc->number]));
    }

    public function show(Request $request, $id)
    {
        $doc = $this->find($id);
        $this->authorize('view', $doc);
        $doc->load($this->showRelations());

        return view('sales.show', ['doc' => $doc, 'cfg' => $this->config(), 'editable' => $this->editable($doc)]);
    }

    protected function showRelations(): array
    {
        return ['customer', 'items.product', 'creator', 'assignee'];
    }

    public function edit(Request $request, $id)
    {
        $doc = $this->find($id);
        $this->authorize('update', $doc);
        abort_unless($this->editable($doc), 403, __(':doc ở trạng thái hiện tại không thể chỉnh sửa.', ['doc' => $this->config()['doc']]));
        $doc->load('items');

        return view('sales.form', $this->formData($request) + [
            'doc' => $doc,
            'items' => $doc->items->map(fn ($i) => $i->only(['product_id', 'description', 'quantity', 'unit_price', 'discount_percent']))->all(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $doc = $this->find($id);
        $this->authorize('update', $doc);
        abort_unless($this->editable($doc), 403, __(':doc ở trạng thái hiện tại không thể chỉnh sửa.', ['doc' => $this->config()['doc']]));
        [$attributes, $items] = $this->validated($request, $doc);

        $this->documents->save($doc, $attributes, $items);

        return redirect()->route($this->route.'.show', $doc)->with('success', __('Đã cập nhật :doc.', ['doc' => $this->docName()]));
    }

    public function destroy($id)
    {
        $doc = $this->find($id);
        $this->authorize('delete', $doc);
        abort_unless($this->editable($doc), 403, __('Không thể xóa :doc ở trạng thái hiện tại.', ['doc' => $this->docName()]));
        $doc->delete();

        return redirect()->route($this->route.'.index')->with('success', __('Đã xóa :doc :number.', ['doc' => $this->docName(), 'number' => $doc->number]).' '.__('Có thể khôi phục trong Thùng rác.'));
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', $this->modelClass());
        $class = $this->modelClass();
        $rows = $this->filtered($request)->orderBy('id')->limit(20000)->get()->map(fn ($d) => array_merge([
            $d->number, vn_date($d->{$this->dateField}), $d->customer?->name, label_of($class::STATUSES, $d->status),
            (float) $d->subtotal, (float) $d->discount_amount, (float) $d->tax_amount, (float) $d->total, $d->assignee?->name,
        ], isset($d->payment_status) ? [label_of($class::PAYMENT_STATUSES, $d->payment_status), (float) $d->amount_paid] : []));

        $headings = [__('Số'), __('Ngày'), __('Khách hàng'), __('Trạng thái'), __('Tạm tính'), __('Chiết khấu'), __('Thuế'), __('Tổng cộng'), __('Phụ trách')];
        if ($this->route === 'invoices') {
            $headings = array_merge($headings, [__('Thanh toán'), __('Đã thu')]);
        }

        return $exporter->download($this->route, $headings, $rows, $request->input('format', 'xlsx'));
    }

    public function pdf($id)
    {
        $doc = $this->find($id);
        $this->authorize('view', $doc);
        $doc->load(['customer', 'items', 'creator']);

        $pdf = Pdf::loadView('pdf.document', [
            'doc' => $doc, 'cfg' => $this->config(), 'settings' => Setting::allCached(),
        ])->setPaper('a4');

        return $pdf->download($doc->number.'.pdf');
    }

    protected function find($id): Model
    {
        $class = $this->modelClass();

        return $class::findOrFail($id);
    }

    protected function validated(Request $request, ?Model $existing = null): array
    {
        $data = $request->validate(array_merge([
            'customer_id' => ['required', \App\Support\Tenancy\TenantRule::exists('customers')],
            $this->dateField => ['required', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at'), \App\Support\Tenancy\TenantRule::member()],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('products')],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], $this->extraRules()), [], [$this->dateField => __('Ngày')]);

        abort_unless(Customer::visibleTo($request->user())->whereKey($data['customer_id'])->exists(), 403, __('Bạn không có quyền với khách hàng này.'));

        $items = $data['items'];
        unset($data['items']);
        // Không dùng bất kỳ giá trị tổng tiền nào gửi từ trình duyệt
        unset($data['subtotal'], $data['tax_amount'], $data['total']);

        if (! $request->user()->can($this->modelClass()::permissionModule().'.view_all')) {
            $data['assigned_to'] = $existing ? $existing->assigned_to : $request->user()->id;
        }

        return [$data, $items];
    }

    protected function formData(Request $request): array
    {
        return [
            'cfg' => $this->config(),
            'customers' => Lookup::customers($request->user()),
            'products' => Lookup::products(),
            'users' => Lookup::users(),
        ];
    }
}
