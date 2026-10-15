<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\PipelineStage;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Support\Facades\DB;

class PipelineService
{
    /**
     * Di chuyển cơ hội sang giai đoạn khác (kéo thả Kanban), ghi lịch sử và sắp xếp lại vị trí.
     */
    public function move(Opportunity $opportunity, PipelineStage $stage, int $position, User $actor): Opportunity
    {
        return DB::transaction(function () use ($opportunity, $stage, $position, $actor) {
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
                if (! $stage->is_lost) {
                    $opportunity->lost_reason = null;
                }
            }
            $opportunity->save();

            if ($changed) {
                $this->recordStageChange($opportunity, $fromStageId, $stage, $actor);
            }

            return $opportunity;
        });
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
