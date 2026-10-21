<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\LicenseAssignment;
use App\Models\SoftwareLicense;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\LicenseService;
use App\Support\Decimal;
use App\Support\Lookup;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bản quyền phần mềm (bước 5.6). Xem: licenses.view; thêm / sửa / gán / xem khóa: licenses.manage.
 * Khóa bản quyền chỉ hiện khi bấm "Hiện khóa" (ghi nhật ký), một lần qua session flash.
 */
class SoftwareLicenseController extends Controller
{
    public function __construct(private LicenseService $licenses) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('licenses.view'), 403);
        $request->validate(['state' => ['nullable', Rule::in(array_keys(SoftwareLicense::STATES))]]);
        $all = SoftwareLicense::withCount('activeAssignments')->with('owner:id,name')->orderByRaw('expires_at is null')->orderBy('expires_at')->orderBy('name')->get();
        $list = $all->when($request->filled('q'), fn ($c) => $c->filter(fn ($l) => str_contains(mb_strtolower($l->name.' '.$l->vendor), mb_strtolower($request->q))))
            ->when($request->filled('state'), fn ($c) => $c->where('state', $request->state));
        $yearly = fn ($l) => match ($l->billing_cycle) { 'monthly' => Decimal::mul($l->cost, '12'), 'yearly' => Decimal::of($l->cost), default => '0' };

        return view('licenses.index', ['licenses' => $list, 'summary' => [
            'total' => $all->count(), 'seats' => $all->sum('seats'), 'used' => $all->sum('active_assignments_count'),
            'expiring' => $all->where('state', 'expiring')->count(), 'expired' => $all->where('state', 'expired')->count(),
            'yearly' => Decimal::sum($all->where('state', '!=', 'expired')->map($yearly)),
        ]]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('licenses.manage'), 403);

        return view('licenses.form', ['license' => new SoftwareLicense(['seats' => 1, 'license_type' => 'subscription', 'billing_cycle' => 'yearly']), ...$this->formData()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('licenses.manage'), 403);
        $license = SoftwareLicense::create($this->validated($request));

        return redirect()->route('licenses.show', $license)->with('success', __('Đã thêm giấy phép.'));
    }

    public function show(Request $request, SoftwareLicense $license)
    {
        abort_unless($request->user()->can('licenses.view'), 403);
        $manage = $request->user()->can('licenses.manage');

        return view('licenses.show', [
            'license' => $license->load(['owner:id,name', 'supplier:id,name']),
            'assignments' => $license->assignments()->with(['user:id,name,avatar', 'asset:id,code,name', 'assigner:id,name', 'releaser:id,name'])
                ->orderByRaw('released_at is not null')->latest('assigned_at')->get(),
            'manage' => $manage, 'users' => $manage ? Lookup::users() : [],
            'assets' => $manage ? Asset::where('status', '!=', 'disposed')->orderBy('code')->get(['id', 'code', 'name'])->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.$a->name])->all() : [],
        ]);
    }

    public function edit(Request $request, SoftwareLicense $license)
    {
        abort_unless($request->user()->can('licenses.manage'), 403);

        return view('licenses.form', ['license' => $license, ...$this->formData()]);
    }

    public function update(Request $request, SoftwareLicense $license)
    {
        abort_unless($request->user()->can('licenses.manage'), 403);
        $data = $this->validated($request, $license);
        if (blank($data['license_key'] ?? null)) {
            unset($data['license_key']);                       // để trống = giữ khóa cũ
        }
        if ($request->boolean('clear_key')) {
            $data['license_key'] = null;
        }
        $license->fill($data);
        if ($license->isDirty('expires_at')) {
            $license->reminded_stage = null;                   // gia hạn → nhắc lại từ đầu cho hạn mới
        }
        $license->save();

        return redirect()->route('licenses.show', $license)->with('success', __('Đã cập nhật giấy phép.'));
    }

    public function destroy(Request $request, SoftwareLicense $license)
    {
        abort_unless($request->user()->can('licenses.manage'), 403);
        abort_if($license->activeAssignments()->exists(), 422, __('Hãy thu hồi các lần gán trước khi xóa giấy phép.'));
        $license->delete();

        return redirect()->route('licenses.index')->with('success', __('Đã chuyển giấy phép vào thùng rác.'));
    }

    public function revealKey(Request $request, SoftwareLicense $license)
    {
        abort_unless($request->user()->can('licenses.manage'), 403);
        abort_if(blank($license->license_key), 404);
        ActivityLogger::log('viewed_secret', $license, 'Xem khóa bản quyền '.$license->name);

        return back()->with('license_key', $license->license_key);
    }

    public function assign(Request $request, SoftwareLicense $license)
    {
        abort_unless($request->user()->can('licenses.manage'), 403);
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', TenantRule::member()],
            'asset_id' => ['nullable', 'integer', TenantRule::exists('assets')],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['user_id' => __('Người dùng'), 'asset_id' => __('Thiết bị')]);
        $this->licenses->assign($license, $data['user_id'] ?? null, $data['asset_id'] ?? null, $data['note'] ?? null, $request->user());

        return back()->with('success', __('Đã gán giấy phép.'));
    }

    public function release(Request $request, SoftwareLicense $license, LicenseAssignment $assignment)
    {
        abort_unless($request->user()->can('licenses.manage'), 403);
        abort_unless($assignment->software_license_id === $license->id, 404);
        $this->licenses->release($assignment, $request->user());

        return back()->with('success', __('Đã thu hồi giấy phép.'));
    }

    private function formData(): array
    {
        return ['suppliers' => Supplier::orderBy('name')->pluck('name', 'id')->all(), 'users' => Lookup::users()];
    }

    private function validated(Request $request, ?SoftwareLicense $license = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'edition' => ['nullable', 'string', 'max:150'],
            'license_type' => ['required', Rule::in(array_keys(SoftwareLicense::TYPES))],
            'license_key' => ['nullable', 'string', 'max:5000'],
            'seats' => ['required', 'integer', 'between:1,100000', function ($attr, $value, $fail) use ($license) {
                if ($license && $value < $license->activeAssignments()->count()) {
                    $fail(__('Số chỗ không được nhỏ hơn số lần gán đang dùng (:n).', ['n' => $license->activeAssignments()->count()]));
                }
            }],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'billing_cycle' => ['required', Rule::in(array_keys(SoftwareLicense::CYCLES))],
            'supplier_id' => ['nullable', 'integer', TenantRule::exists('suppliers')],
            'owner_id' => ['nullable', 'integer', TenantRule::member()],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], ['name' => __('Tên phần mềm'), 'seats' => __('Số chỗ'), 'expires_at' => __('Ngày hết hạn'), 'license_key' => __('Khóa bản quyền')]);
        $data['cost'] ??= 0;

        return $data;
    }
}
