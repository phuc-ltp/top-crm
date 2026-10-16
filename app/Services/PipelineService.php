<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\PipelineStage;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Models\LostReason;
use App\Models\OpportunityItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PipelineService
{
    /**
     * Di chuyển cơ hội sang giai đoạn khác (kéo thả Kanban), ghi lịch sử và sắp xếp lại vị trí.
     */
    public function move(Opportunity $opportunity, PipelineStage $stage, int $position, User $actor, ?int $lostReasonId = null, ?string $lostNote = null): Opportunity
    {
        // Chỉ di chuyển trong cùng pipeline; vào giai đoạn thất bại phải có lý do
        if ((int) $stage->pipeline_id !== (int) $opportunity->pipeline_id) {
            throw ValidationException::withMessages(['pipeline_stage_id' => __('Không thể chuyển cơ hội sang giai đoạn của pipeline khác.')]);
        }
        if ($stage->is_lost && (int) $opportunity->pipeline_stage_id !== (int) $stage->id) {
            $this->assertLostReason($lostReasonId);
        }

        return DB::transaction(function () use ($opportunity, $stage, $position, $actor, $lostReasonId, $lostNote) {
            $fromStageId = $opportunity->pipeline_stage_id;
            $changed = $fromStageId !== $stage->id;

            // Sắp xếp lại các thẻ trong cột đích
            $ids = Opportunity::where('pipeline_stage_id', $stage->id)->whereKeyNot($opportunity->id)
                ->orderBy('position')->orderBy('id')->pluck('id')->all();
            array_splice($ids, max(0, min($position, count($ids))), 0, [$opportunity->id]);
            foreach ($ids as $index => $id) {
                if ($id !== $opportunity->id) {
                    Opportunity::whereKey($id)->update(['position' => $index]);
                }
            }

            $opportunity->position = array_search($opportunity->id, $ids, true);

            if ($changed) {
                $opportunity->pipeline_stage_id = $stage->id;
                $opportunity->probability = $stage->probability;
                $opportunity->closed_at = $stage->isClosed() ? now() : null;
                if ($stage->is_lost) {
                    $opportunity->lost_reason_id = $lostReasonId;
                    $opportunity->lost_reason = $lostNote !== null && $lostNote !== '' ? mb_substr($lostNote, 0, 500) : null;
                } else {
                    $opportunity->lost_reason = null;
                    $opportunity->lost_reason_id = null;
                }
            }
            $opportunity->save();

            if ($changed) {
                $this->recordStageChange($opportunity, $fromStageId, $stage, $actor);
            }

            return $opportunity;
        });
    }

    /** Lý do thất bại phải là lý do đang dùng của tổ chức, áp dụng cho cơ hội. */
    public function assertLostReason(?int $lostReasonId): void
    {
        $ok = $lostReasonId && LostReason::whereKey($lostReasonId)->where('is_active', true)->whereIn('applies_to', ['both', 'opportunity'])->exists();
        if (! $ok) {
            throw ValidationException::withMessages(['lost_reason_id' => __('Vui lòng chọn lý do thất bại.')]);
        }
    }

    /**
     * Lưu dòng sản phẩm của cơ hội (thay toàn bộ). Thành tiền tính lại ở backend; trả về tổng giá trị.
     * Sản phẩm phải thuộc tổ chức (global scope); tên lấy từ sản phẩm nếu bỏ trống.
     */
    public function syncItems(Opportunity $opportunity, array $rows): float
    {
        $opportunity->items()->delete();
        $total = 0.0;
        foreach (array_values($rows) as $i => $row) {
            $product = ! empty($row['product_id']) ? Product::find($row['product_id']) : null;
            $name = trim((string) ($row['name'] ?? '')) ?: ($product?->name ?? '');
            if ($name === '') {
                continue;
            }
            $qty = (float) ($row['quantity'] ?? 1);
            $price = (float) ($row['unit_price'] ?? ($product?->price ?? 0));
            $discount = (float) ($row['discount_percent'] ?? 0);
            $line = OpportunityItem::lineTotal($qty, $price, $discount);
            $opportunity->items()->create(['product_id' => $product?->id, 'name' => mb_substr($name, 0, 255), 'quantity' => $qty,
                'unit_price' => $price, 'discount_percent' => $discount, 'line_total' => $line, 'position' => $i]);
            $total += $line;
        }

        return round($total, 2);
    }

    public function recordStageChange(Opportunity $opportunity, ?int $fromStageId, PipelineStage $stage, User $actor): void
    {
        OpportunityStageHistory::create([
            'opportunity_id' => $opportunity->id, 'from_stage_id' => $fromStageId, 'to_stage_id' => $stage->id,
            'changed_by' => $actor->id, 'created_at' => now(),
        ]);
        ActivityLogger::log('stage_changed', $opportunity, 'Chuyển cơ hội '.$opportunity->code.' sang giai đoạn "'.$stage->name.'"');

        $recipient = $opportunity->assignee;
        if ($recipient && $recipient->id !== $actor->id) {
            CrmNotification::send($recipient, 'deal_stage', 'Cơ hội "'.$opportunity->title.'" chuyển sang "'.$stage->name.'"',
                route('opportunities.show', $opportunity), $stage->is_won ? 'bi-trophy' : 'bi-kanban');
        }
    }
}
