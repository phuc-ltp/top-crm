<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\LostReason;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Support\Lookup;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Cài đặt → Cấu hình lead: giai đoạn lead, lý do thất bại, form thu lead từ website (theo tổ chức). */
class LeadSettingsController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('settings.manage'), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeManage($request);
        $s = Setting::allCached();

        return view('settings.leads', [
            'stages' => LeadStage::orderBy('position')->orderBy('id')->withCount('leads')->get(),
            'reasons' => LostReason::orderBy('position')->orderBy('name')->get(),
            'form' => [
                'enabled' => $s['lead_form_enabled'] === '1',
                'token' => $s['lead_form_token'],
                'assignee' => $s['lead_form_assignee'],
                'source' => $s['lead_form_source'],
                'thanks' => $s['lead_form_thanks'],
            ],
            'users' => Lookup::users(),
            'sources' => Lookup::sources(),
            'organization' => app(CurrentOrganization::class)->get(),
        ]);
    }

    // ---------------- Giai đoạn ----------------

    public function storeStage(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', Rule::in(array_keys(LeadStage::COLORS))],
        ], [], ['name' => __('Tên giai đoạn')]);
        if (LeadStage::count() >= 15) {
            return back()->with('error', __('Mỗi tổ chức có tối đa 15 giai đoạn lead.'));
        }
        // Giai đoạn mới chèn trước "Đã chuyển đổi" (các giai đoạn hệ thống luôn ở cuối)
        $before = (int) (LeadStage::where('key', LeadStage::CONVERTED)->value('position') ?? (LeadStage::max('position') + 1));
        LeadStage::where('position', '>=', $before)->increment('position');
        $key = Str::limit(Str::slug(Str::ascii($data['name']), '_'), 40, '') ?: 'stage';
        $base = $key;
        for ($i = 2; LeadStage::where('key', $key)->exists() || in_array($key, [LeadStage::CONVERTED, LeadStage::LOST], true); $i++) {
            $key = $base.'_'.$i;
        }
        LeadStage::create(['key' => $key, 'name' => $data['name'], 'color' => $data['color'], 'position' => $before]);

        return redirect()->route('settings.leads')->with('success', __('Đã thêm giai đoạn :name.', ['name' => $data['name']]));
    }

    public function updateStage(Request $request, LeadStage $stage)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', Rule::in(array_keys(LeadStage::COLORS))],
            'position' => ['nullable', 'integer', 'min:1', 'max:99'],
            'is_qualified' => ['nullable', 'boolean'],
        ], [], ['name' => __('Tên giai đoạn')]);

        DB::transaction(function () use ($request, $stage, $data) {
            $qualified = ! $stage->is_system && $request->boolean('is_qualified');
            if ($qualified) {
                LeadStage::whereKeyNot($stage->id)->update(['is_qualified' => false]);   // chỉ một giai đoạn "đủ điều kiện"
            }
            $stage->update([
                'name' => $data['name'], 'color' => $data['color'], 'is_qualified' => $qualified,
                // Giai đoạn hệ thống giữ ở cuối; giai đoạn thường đổi thứ tự trong phạm vi trước "Đã chuyển đổi"
                'position' => $stage->is_system ? $stage->position : min((int) ($data['position'] ?? $stage->position),
                    (int) LeadStage::where('key', LeadStage::CONVERTED)->value('position') - 1),
            ]);
        });

        return redirect()->route('settings.leads')->with('success', __('Đã cập nhật giai đoạn :name.', ['name' => $stage->name]));
    }

    /** Xóa giai đoạn thường; lead đang ở giai đoạn này được chuyển sang giai đoạn khác do người dùng chọn. */
    public function destroyStage(Request $request, LeadStage $stage)
    {
        $this->authorizeManage($request);
        abort_if($stage->is_system, 422, __('Không xóa được giai đoạn hệ thống.'));
        $openKeys = LeadStage::whereKeyNot($stage->id)->whereNotIn('key', [LeadStage::CONVERTED, LeadStage::LOST])->pluck('key')->all();
        abort_if(! $openKeys, 422, __('Cần giữ ít nhất một giai đoạn đang mở.'));
        $data = $request->validate(['move_to' => ['required', Rule::in($openKeys)]], [], ['move_to' => __('Chuyển lead sang')]);

        $moved = DB::transaction(function () use ($stage, $data) {
            $moved = Lead::withTrashed()->where('status', $stage->key)->update(['status' => $data['move_to']]);
            $stage->delete();

            return $moved;
        });
        ActivityLogger::log('updated', null, 'Xóa giai đoạn lead "'.$stage->name.'" (chuyển '.$moved.' lead sang "'.$data['move_to'].'")');

        return redirect()->route('settings.leads')->with('success', __('Đã xóa giai đoạn :name, chuyển :count lead sang giai đoạn đã chọn.', ['name' => $stage->name, 'count' => $moved]));
    }

    // ---------------- Lý do thất bại ----------------

    public function storeReason(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', TenantRule::unique('lost_reasons', 'name')],
            'applies_to' => ['required', Rule::in(array_keys(LostReason::APPLIES))],
        ], [], ['name' => __('Lý do')]);
        LostReason::create($data + ['position' => (int) LostReason::max('position') + 1]);

        return redirect()->route('settings.leads')->with('success', __('Đã thêm lý do :name.', ['name' => $data['name']]));
    }

    public function updateReason(Request $request, LostReason $reason)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', TenantRule::unique('lost_reasons', 'name')->ignore($reason->id)],
            'applies_to' => ['required', Rule::in(array_keys(LostReason::APPLIES))],
        ], [], ['name' => __('Lý do')]);
        $reason->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('settings.leads')->with('success', __('Đã cập nhật lý do.'));
    }

    public function destroyReason(Request $request, LostReason $reason)
    {
        $this->authorizeManage($request);
        $reason->delete();   // lead đang dùng lý do này giữ trạng thái thất bại, lý do để trống (nullOnDelete)

        return redirect()->route('settings.leads')->with('success', __('Đã xóa lý do :name.', ['name' => $reason->name]));
    }

    // ---------------- Form website ----------------

    public function updateForm(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'lead_form_assignee' => ['nullable', TenantRule::member()],
            'lead_form_source' => ['nullable', TenantRule::exists('lead_sources')],
            'lead_form_thanks' => ['nullable', 'string', 'max:300'],
        ], [], ['lead_form_assignee' => __('Người nhận lead'), 'lead_form_source' => __('Nguồn lead')]);

        $values = [
            'lead_form_enabled' => $request->boolean('lead_form_enabled') ? '1' : '0',
            'lead_form_assignee' => (string) ($data['lead_form_assignee'] ?? ''),
            'lead_form_source' => (string) ($data['lead_form_source'] ?? ''),
            'lead_form_thanks' => (string) ($data['lead_form_thanks'] ?? ''),
        ];
        if ($values['lead_form_enabled'] === '1' && ! Setting::get('lead_form_token')) {
            $values['lead_form_token'] = Str::random(40);
        }
        Setting::put($values, 'leads');
        ActivityLogger::log('updated', null, ($values['lead_form_enabled'] === '1' ? 'Bật' : 'Tắt').' form thu lead từ website');

        return redirect()->route('settings.leads')->with('success', __('Đã lưu cấu hình form thu lead.'));
    }

    /** Đổi đường dẫn form (đường dẫn cũ ngừng hoạt động ngay) — dùng khi bị spam. */
    public function regenerateToken(Request $request)
    {
        $this->authorizeManage($request);
        Setting::put(['lead_form_token' => Str::random(40)], 'leads');
        ActivityLogger::log('updated', null, 'Tạo đường dẫn mới cho form thu lead');

        return redirect()->route('settings.leads')->with('success', __('Đã tạo đường dẫn mới. Đường dẫn cũ không còn dùng được.'));
    }
}
