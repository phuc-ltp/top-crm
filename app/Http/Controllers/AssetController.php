<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\Supplier;
use App\Notifications\CrmNotification;
use App\Services\AssetService;
use App\Services\ExportService;
use App\Support\Lookup;
use App\Support\QrImage;
use App\Support\Tenancy\TenantRule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Quản lý tài sản (bước 5.5). Xem: assets.view (hoặc người đang giữ tài sản đó); thao tác: assets.manage.
 * Mã QR trỏ tới /a/{qr_token} — cần đăng nhập; người không có quyền nhận 404 (không lộ tài sản).
 */
class AssetController extends Controller
{
    public function __construct(private AssetService $assets) {}

    public function overview(Request $request)
    {
        abort_unless($request->user()->can('assets.view'), 403);

        return view('assets.overview', ['o' => $this->assets->overview()]);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('assets.view'), 403);
        $request->validate(['status' => ['nullable', Rule::in(array_keys(Asset::STATUSES))], 'category' => ['nullable', 'integer'], 'holder' => ['nullable', 'integer']]);
        $query = Asset::with(['category:id,name', 'holder:id,name,avatar', 'branch:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('code', 'like', '%'.$request->q.'%')->orWhere('serial_number', 'like', '%'.$request->q.'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status), fn ($q) => $q->where('status', '!=', 'disposed'))
            ->when($request->filled('category'), fn ($q) => $q->where('asset_category_id', $request->integer('category')))
            ->when($request->filled('holder'), fn ($q) => $q->where('holder_id', $request->integer('holder')));

        if ($request->input('export') === 'xlsx' && $request->user()->can('assets.manage')) {
            $rows = (clone $query)->orderBy('code')->get()->map(fn ($a) => [$a->code, $a->name, $a->category?->name, $a->serial_number, label_of(Asset::STATUSES, $a->status),
                $a->holder?->name, $a->branch?->name, $a->location, $a->purchase_date?->format('d/m/Y'), (float) $a->purchase_cost, (float) $this->assets->depreciation($a)['book_value']]);

            return app(ExportService::class)->download('tai-san', [__('Mã'), __('Tên tài sản'), __('Nhóm'), __('Số serial'), __('Trạng thái'), __('Người giữ'),
                __('Chi nhánh'), __('Vị trí'), __('Ngày mua'), __('Nguyên giá'), __('Giá trị còn lại')], $rows);
        }

        return view('assets.index', [
            'assets' => $query->orderBy('code')->paginate($this->perPage($request))->withQueryString(),
            'categories' => AssetCategory::orderBy('name')->pluck('name', 'id')->all(), 'users' => Lookup::users(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        $this->assets->ensureCategories();

        return view('assets.form', ['asset' => new Asset(['purchase_date' => today()]), ...$this->formData()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        $data = $this->validated($request);
        if (! array_key_exists('depreciation_months', $data) || $data['depreciation_months'] === null) {
            $data['depreciation_months'] = (int) AssetCategory::whereKey($data['asset_category_id'] ?? 0)->value('depreciation_months');
        }
        $asset = $this->assets->create($data, $request->user());

        return redirect()->route('assets.show', $asset)->with('success', __('Đã thêm tài sản :c.', ['c' => $asset->code]));
    }

    public function show(Request $request, Asset $asset)
    {
        $user = $request->user();
        $canView = $user->can('assets.view');
        abort_unless($canView || $asset->holder_id === $user->id, 404);

        return view('assets.show', [
            'asset' => $asset->load(['category', 'supplier', 'branch', 'holder', 'events.user:id,name', 'events.targetUser:id,name']),
            'canView' => $canView, 'dep' => $this->assets->depreciation($asset), 'users' => $user->can('assets.manage') ? Lookup::users() : [],
            'openAudits' => $user->can('assets.manage') ? AssetAudit::where('status', 'open')->latest()->get(['id', 'name']) : collect(),
            'qr' => QrImage::dataUri(route('assets.scan', $asset->qr_token), 5),
            'licenses' => $user->can('licenses.view') ? \App\Models\LicenseAssignment::with('license')->where('asset_id', $asset->id)->whereNull('released_at')->get()->filter(fn ($a) => $a->license && ! $a->license->trashed()) : collect(),
        ]);
    }

    public function edit(Request $request, Asset $asset)
    {
        abort_unless($request->user()->can('assets.manage'), 403);

        return view('assets.form', ['asset' => $asset, ...$this->formData()]);
    }

    public function update(Request $request, Asset $asset)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        $asset->update($this->validated($request, $asset));

        return redirect()->route('assets.show', $asset)->with('success', __('Đã cập nhật tài sản.'));
    }

    public function destroy(Request $request, Asset $asset)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        abort_if(in_array($asset->status, ['in_use', 'borrowed'], true), 422, __('Hãy thu hồi tài sản trước khi xóa.'));
        $asset->delete();

        return redirect()->route('assets.index')->with('success', __('Đã chuyển tài sản vào thùng rác.'));
    }

    /** Cấp phát / thu hồi / mượn / trả / bảo trì / đổi tình trạng / thanh lý. */
    public function act(Request $request, Asset $asset, string $action)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        abort_unless(isset(AssetService::ACTIONS[$action]), 404);
        $data = $request->validate([
            'target_user_id' => [AssetService::ACTIONS[$action][2] ? 'required' : 'prohibited', 'integer', TenantRule::member()],
            'due_back_at' => [$action === 'borrow' ? 'required' : 'prohibited', 'date', 'after_or_equal:today'],
            'status' => [$action === 'status' ? 'required' : 'prohibited', Rule::in(['available', 'broken', 'lost'])],
            'condition' => ['nullable', Rule::in(array_keys(\App\Models\AssetEvent::CONDITIONS))],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'note' => [in_array($action, ['status', 'dispose'], true) ? 'required' : 'nullable', 'string', 'max:1000'],
        ], [], ['target_user_id' => __('Người nhận'), 'due_back_at' => __('Hạn trả'), 'note' => __('Ghi chú'), 'cost' => __('Chi phí / giá trị')]);
        $asset = $this->assets->act($asset, $action, $data, $request->user());

        if (in_array($action, ['assign', 'borrow'], true) && $asset->holder) {
            CrmNotification::send($asset->holder, 'asset_assigned', __('Bạn được bàn giao tài sản :c — :n.', ['c' => $asset->code, 'n' => $asset->name]), route('assets.show', $asset), 'bi-pc-display');
        }

        return back()->with('success', __('Đã cập nhật: :a.', ['a' => label_of(\App\Models\AssetEvent::TYPES, ['assign' => 'assigned', 'recall' => 'recalled', 'borrow' => 'borrowed',
            'return' => 'returned', 'maintenance' => 'maintenance', 'repaired' => 'repaired', 'status' => 'status', 'dispose' => 'disposed'][$action])]));
    }

    /** PDF tem QR cho các tài sản đã chọn (hoặc theo bộ lọc hiện tại nếu không chọn). */
    public function labels(Request $request)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        $data = $request->validate(['ids' => ['nullable', 'array', 'max:500'], 'ids.*' => ['integer']]);
        $assets = Asset::with('category:id,name')->when(! empty($data['ids']), fn ($q) => $q->whereIn('id', $data['ids']), fn ($q) => $q->where('status', '!=', 'disposed'))
            ->orderBy('code')->limit(500)->get();
        abort_if($assets->isEmpty(), 422, __('Chưa chọn tài sản nào.'));
        $labels = $assets->map(fn ($a) => ['asset' => $a, 'qr' => QrImage::dataUri(route('assets.scan', $a->qr_token), 4)]);

        return Pdf::loadView('pdf.asset-labels', ['labels' => $labels, 'settings' => Setting::allCached()])->setPaper('a4')
            ->download('tem-tai-san-'.now()->format('Ymd_His').'.pdf');
    }

    /** Ảnh QR (PNG) của một tài sản — để tải về / in lẻ. */
    public function qr(Request $request, Asset $asset)
    {
        abort_unless($request->user()->can('assets.view'), 404);

        return response(QrImage::png(route('assets.scan', $asset->qr_token), 8), 200, ['Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$asset->code.'.png"', 'Cache-Control' => 'private, max-age=3600']);
    }

    /** Đích của mã QR: mở trang tài sản nếu có quyền (kèm kiểm kê nhanh khi có đợt đang mở). */
    public function scan(Request $request, string $token)
    {
        $asset = Asset::where('qr_token', $token)->first();
        abort_unless($asset && ($request->user()->can('assets.view') || $asset->holder_id === $request->user()->id), 404);

        return redirect()->route('assets.show', $asset);
    }

    public function mine(Request $request)
    {
        return view('assets.mine', ['assets' => Asset::with('category:id,name')->where('holder_id', $request->user()->id)->orderBy('code')->get(),
            'licenses' => \App\Models\LicenseAssignment::with('license')->where('user_id', $request->user()->id)->whereNull('released_at')->get()->filter(fn ($a) => $a->license && ! $a->license->trashed())]);
    }

    private function formData(): array
    {
        return [
            'categories' => AssetCategory::orderBy('name')->get(['id', 'name', 'depreciation_months']),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id')->all(),
            'branches' => Branch::orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    private function validated(Request $request, ?Asset $asset = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._\-\/]+$/', TenantRule::unique('assets', 'code')->ignore($asset?->id)],
            'name' => ['required', 'string', 'max:255'],
            'asset_category_id' => ['nullable', 'integer', TenantRule::exists('asset_categories')],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'supplier_id' => ['nullable', 'integer', TenantRule::exists('suppliers')],
            'branch_id' => ['nullable', 'integer', TenantRule::exists('branches')],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'salvage_value' => ['nullable', 'numeric', 'min:0', 'lte:purchase_cost'],
            'depreciation_months' => ['nullable', 'integer', 'between:0,600'],
            'warranty_until' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], [], ['code' => __('Mã tài sản'), 'name' => __('Tên tài sản'), 'purchase_cost' => __('Nguyên giá'), 'salvage_value' => __('Giá trị thu hồi'),
            'depreciation_months' => __('Số tháng khấu hao')]);
        $data['salvage_value'] ??= 0;
        if ($asset) {
            unset($data['code']);   // mã tài sản cố định sau khi tạo (đã in trên tem)
        } elseif (blank($data['code'] ?? null)) {
            unset($data['code']);
        }

        return $data;
    }
}
