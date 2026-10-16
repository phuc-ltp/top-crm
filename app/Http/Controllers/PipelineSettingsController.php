<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Services\ActivityLogger;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Cài đặt → Pipeline: nhiều pipeline mỗi tổ chức, giai đoạn riêng từng pipeline.
 * Bất biến: mỗi pipeline luôn có ≥1 giai đoạn mở, ≥1 giai đoạn thắng, ≥1 giai đoạn thất bại; đúng một pipeline mặc định.
 */
class PipelineSettingsController extends Controller
{
    /** Giai đoạn mẫu cho pipeline mới: slug, tên, xác suất, màu, thắng, thua */
    private const TEMPLATE = [
        ['new', 'Cơ hội mới', 10, '#6B7280', false, false],
        ['in_progress', 'Đang xử lý', 50, '#3B82F6', false, false],
        ['won', 'Thắng', 100, '#16A34A', true, false],
        ['lost', 'Thất bại', 0, '#DC2626', false, true],
    ];

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('settings.manage'), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeManage($request);

        return view('settings.pipelines', [
            'pipelines' => Pipeline::ordered()->withCount('opportunities')
                ->with(['stages' => fn ($q) => $q->withCount('opportunities')])->get(),
            'organization' => app(CurrentOrganization::class)->get(),
        ]);
    }

    // ---------------- Pipeline ----------------

    public function storePipeline(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', TenantRule::unique('pipelines', 'name')]], [], ['name' => __('Tên pipeline')]);
        if (Pipeline::count() >= Pipeline::MAX_PER_ORGANIZATION) {
            return back()->with('error', __('Mỗi tổ chức có tối đa :max pipeline.', ['max' => Pipeline::MAX_PER_ORGANIZATION]));
        }
        DB::transaction(function () use ($data) {
            $pipeline = Pipeline::create(['name' => $data['name'], 'is_default' => false, 'position' => (int) Pipeline::max('position') + 1]);
            foreach (self::TEMPLATE as $i => [$slug, $name, $prob, $color, $won, $lost]) {
                PipelineStage::create(['pipeline_id' => $pipeline->id, 'slug' => $slug, 'name' => $name, 'position' => $i + 1,
                    'probability' => $prob, 'color' => $color, 'is_won' => $won, 'is_lost' => $lost]);
            }
        });

        return redirect()->route('settings.pipelines')->with('success', __('Đã tạo pipeline :name với các giai đoạn mẫu.', ['name' => $data['name']]));
    }

    public function updatePipeline(Request $request, Pipeline $pipeline)
    {
        $this->authorizeManage($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', TenantRule::unique('pipelines', 'name')->ignore($pipeline->id)]], [], ['name' => __('Tên pipeline')]);
        $makeDefault = $request->boolean('is_default');
        $active = $pipeline->is_default || $makeDefault || $request->boolean('is_active');   // pipeline mặc định luôn đang dùng

        DB::transaction(function () use ($pipeline, $data, $makeDefault, $active) {
            if ($makeDefault && ! $pipeline->is_default) {
                Pipeline::whereKeyNot($pipeline->id)->update(['is_default' => false]);
            }
            $pipeline->update(['name' => $data['name'], 'is_active' => $active, 'is_default' => $pipeline->is_default || $makeDefault]);
        });

        return redirect()->route('settings.pipelines')->with('success', __('Đã cập nhật pipeline :name.', ['name' => $pipeline->name]));
    }

    public function destroyPipeline(Request $request, Pipeline $pipeline)
    {
        $this->authorizeManage($request);
        abort_if($pipeline->is_default, 422, __('Không xóa được pipeline mặc định.'));
        abort_if(Opportunity::withTrashed()->where('pipeline_id', $pipeline->id)->exists(), 422, __('Pipeline còn cơ hội (kể cả đã xóa). Hãy ẩn pipeline thay vì xóa.'));
        $pipeline->delete();   // giai đoạn xóa theo (cascade)
        ActivityLogger::log('deleted', null, 'Xóa pipeline "'.$pipeline->name.'"');

        return redirect()->route('settings.pipelines')->with('success', __('Đã xóa pipeline :name.', ['name' => $pipeline->name]));
    }

    // ---------------- Giai đoạn ----------------

    public function storeStage(Request $request, Pipeline $pipeline)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [], ['name' => __('Tên giai đoạn'), 'probability' => __('Xác suất'), 'color' => __('Màu')]);
        abort_if($pipeline->stages()->count() >= 15, 422, __('Mỗi pipeline có tối đa 15 giai đoạn.'));

        DB::transaction(function () use ($pipeline, $data) {
            // Giai đoạn mới (đang mở) chèn trước giai đoạn đóng đầu tiên (thắng / thất bại luôn ở cuối)
            $before = (int) ($pipeline->stages()->where(fn ($q) => $q->where('is_won', true)->orWhere('is_lost', true))->min('position') ?? ($pipeline->stages()->max('position') + 1));
            PipelineStage::where('pipeline_id', $pipeline->id)->where('position', '>=', $before)->increment('position');
            $slug = Str::limit(Str::slug(Str::ascii($data['name']), '_'), 40, '') ?: 'stage';
            $base = $slug;
            for ($i = 2; PipelineStage::where('pipeline_id', $pipeline->id)->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'_'.$i;
            }
            PipelineStage::create(['pipeline_id' => $pipeline->id, 'slug' => $slug, 'name' => $data['name'], 'position' => $before,
                'probability' => $data['probability'], 'color' => strtoupper($data['color'])]);
        });

        return redirect()->route('settings.pipelines')->with('success', __('Đã thêm giai đoạn :name.', ['name' => $data['name']]));
    }

    public function updateStage(Request $request, PipelineStage $pipelineStage)
    {
        $stage = $pipelineStage;
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['nullable', 'integer', 'min:1', 'max:99'],
        ], [], ['name' => __('Tên giai đoạn'), 'probability' => __('Xác suất'), 'color' => __('Màu')]);

        $position = $stage->position;
        if (! $stage->isClosed() && isset($data['position'])) {
            // Giai đoạn mở chỉ sắp xếp trong vùng trước các giai đoạn đóng
            $firstClosed = (int) (PipelineStage::where('pipeline_id', $stage->pipeline_id)->where(fn ($q) => $q->where('is_won', true)->orWhere('is_lost', true))->min('position') ?? 99);
            $position = min((int) $data['position'], $firstClosed - 1);
        }
        $stage->update(['name' => $data['name'], 'probability' => $data['probability'], 'color' => strtoupper($data['color']), 'position' => max(1, $position)]);

        return redirect()->route('settings.pipelines')->with('success', __('Đã cập nhật giai đoạn :name.', ['name' => $stage->name]));
    }

    /** Xóa giai đoạn: cơ hội đang ở giai đoạn này chuyển sang giai đoạn khác CÙNG pipeline (kể cả cơ hội đã xóa mềm). */
    public function destroyStage(Request $request, PipelineStage $pipelineStage)
    {
        $stage = $pipelineStage;
        $this->authorizeManage($request);
        $siblings = PipelineStage::where('pipeline_id', $stage->pipeline_id)->whereKeyNot($stage->id)->get();
        $kind = fn ($s) => $s->is_won ? 'won' : ($s->is_lost ? 'lost' : 'open');
        abort_unless($siblings->contains(fn ($s) => $kind($s) === $kind($stage)), 422,
            __('Mỗi pipeline cần ít nhất một giai đoạn mở, một giai đoạn thắng và một giai đoạn thất bại.'));
        $data = $request->validate(['move_to' => ['required', Rule::in($siblings->pluck('id')->all())]], [], ['move_to' => __('Chuyển cơ hội sang')]);
        $target = $siblings->firstWhere('id', (int) $data['move_to']);

        $moved = DB::transaction(function () use ($stage, $target) {
            $moved = Opportunity::withTrashed()->where('pipeline_stage_id', $stage->id)->update([
                'pipeline_stage_id' => $target->id, 'probability' => $target->probability,
                'closed_at' => $target->isClosed() ? DB::raw('COALESCE(closed_at, NOW())') : null,
            ]);
            // Lịch sử giai đoạn giữ nguyên ý nghĩa: trỏ sang giai đoạn thay thế
            DB::table('opportunity_stage_histories')->where('from_stage_id', $stage->id)->update(['from_stage_id' => $target->id]);
            DB::table('opportunity_stage_histories')->where('to_stage_id', $stage->id)->update(['to_stage_id' => $target->id]);
            $stage->delete();

            return $moved;
        });
        ActivityLogger::log('updated', null, 'Xóa giai đoạn "'.$stage->name.'" (chuyển '.$moved.' cơ hội sang "'.$target->name.'")');

        return redirect()->route('settings.pipelines')->with('success', __('Đã xóa giai đoạn :name, chuyển :count cơ hội sang ":target".', ['name' => $stage->name, 'count' => $moved, 'target' => $target->name]));
    }
}
