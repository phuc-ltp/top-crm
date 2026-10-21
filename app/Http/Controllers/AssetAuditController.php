<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAudit;
use App\Services\AssetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kiểm kê tài sản (bước 5.5): mở đợt → quét QR / đánh dấu từng tài sản (có mặt / hư hỏng / không thấy) → đóng đợt
 * (tài sản chưa kiểm ghi "không tìm thấy"). Xem: assets.view; thao tác: assets.manage.
 */
class AssetAuditController extends Controller
{
    public function __construct(private AssetService $assets) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('assets.view'), 403);
        $audits = AssetAudit::with('creator:id,name')->withCount(['items', 'items as missing_count' => fn ($q) => $q->where('result', 'missing'),
            'items as damaged_count' => fn ($q) => $q->where('result', 'damaged')])->latest()->paginate($this->perPage($request));

        return view('assets.audits.index', ['audits' => $audits, 'activeCount' => Asset::where('status', '!=', 'disposed')->count()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:1000']], [], ['name' => __('Tên đợt kiểm kê')]);
        $audit = AssetAudit::create($data);
        $audit->forceFill(['created_by' => $request->user()->id])->save();

        return redirect()->route('assets.audits.show', $audit)->with('success', __('Đã mở đợt kiểm kê. Quét mã QR trên tài sản hoặc đánh dấu trong danh sách.'));
    }

    public function show(Request $request, AssetAudit $audit)
    {
        abort_unless($request->user()->can('assets.view'), 403);
        $items = $audit->items()->with(['asset:id,code,name,location,status,deleted_at', 'checker:id,name'])->get()->keyBy('asset_id');
        $pending = $audit->isOpen()
            ? Asset::where('status', '!=', 'disposed')->whereNotIn('id', $items->keys())->with('holder:id,name')->orderBy('code')->get(['id', 'code', 'name', 'location', 'holder_id'])
            : collect();

        return view('assets.audits.show', ['audit' => $audit, 'items' => $items->values(), 'pending' => $pending]);
    }

    public function check(Request $request, AssetAudit $audit)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        $data = $request->validate([
            'asset_id' => ['required', 'integer'],
            'result' => ['required', Rule::in(array_keys(AssetAudit::RESULTS))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $asset = Asset::findOrFail($data['asset_id']);   // lọc theo tổ chức (global scope)
        $this->assets->check($audit, $asset, $data['result'], $data['note'] ?? null, $request->user());

        return back()->with('success', __('Đã ghi nhận kiểm kê :c.', ['c' => $asset->code]));
    }

    public function close(Request $request, AssetAudit $audit)
    {
        abort_unless($request->user()->can('assets.manage'), 403);
        $missing = $this->assets->close($audit, $request->user());

        return back()->with('success', __('Đã đóng đợt kiểm kê. Số tài sản không tìm thấy: :n.', ['n' => $missing]));
    }
}
