<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\PipelineStage;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    /**
     * Chấm điểm lead (0–100) theo quy tắc:
     * thông tin liên hệ đầy đủ (tối đa 30), giá trị ước tính (tối đa 25), giai đoạn (tối đa 35),
     * nguồn giới thiệu (+10), số lần tương tác (5 điểm/lần, tối đa 15).
     */
    public function score(Lead $lead): int
    {
        $score = 0;
        $score += $lead->email ? 10 : 0;
        $score += $lead->phone ? 10 : 0;
        $score += $lead->company ? 10 : 0;

        $value = (float) $lead->estimated_value;
        $score += $value >= 100_000_000 ? 25 : ($value >= 30_000_000 ? 15 : ($value > 0 ? 5 : 0));

        $score += match ($lead->status) {
            'contacted' => 10, 'qualified' => 25, 'proposal', 'converted' => 35, default => 0,
        };

        if ($lead->source && mb_strtolower($lead->source->name) === 'giới thiệu') {
            $score += 10;
        }

        if ($lead->exists) {
            $score += min(15, $lead->interactions()->where('status', 'done')->count() * 5);
        }

        return min(100, $score);
    }

    public function save(Lead $lead, array $data, User $actor, bool $autoScore = true): Lead
    {
        $campaignIds = $data['campaign_ids'] ?? null;
        unset($data['campaign_ids'], $data['auto_score']);
        if (array_key_exists('score', $data)) {
            $data['score'] = (int) ($data['score'] ?? 0);
        }
        if (array_key_exists('estimated_value', $data)) {
            $data['estimated_value'] = (float) ($data['estimated_value'] ?? 0);
        }
        $previousAssignee = $lead->assigned_to;

        $lead = DB::transaction(function () use ($lead, $data, $actor, $autoScore, $campaignIds) {
            if (! $lead->exists) {
                $data['created_by'] = $actor->id;
            }
            $lead->fill($data);
            if ($autoScore) {
                $lead->score = $this->score($lead);
            }
            $lead->save();
            if ($campaignIds !== null) {
                $lead->campaigns()->syncWithPivotValues($campaignIds, ['created_at' => now()]);
            }

            return $lead;
        });

        if ($lead->assigned_to && $lead->assigned_to !== $previousAssignee && $lead->assigned_to !== $actor->id) {
            CrmNotification::send($lead->assignee, 'lead_assigned', 'Bạn được phân công Lead mới: '.$lead->name, route('leads.show', $lead), 'bi-funnel');
        }

        return $lead;
    }

    /**
     * Chuyển đổi Lead: tạo (hoặc liên kết) khách hàng, tùy chọn tạo cơ hội bán hàng.
     */
    public function convert(Lead $lead, array $options, User $actor): array
    {
        if ($lead->isConverted()) {
            throw ValidationException::withMessages(['lead' => __('Lead này đã được chuyển đổi trước đó.')]);
        }

        return DB::transaction(function () use ($lead, $options, $actor) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if (! empty($options['customer_id'])) {
                $customer = Customer::findOrFail($options['customer_id']);
            } else {
                $customer = app(CustomerService::class)->save(new Customer, [
                    'name' => $options['customer_name'] ?? ($lead->company ?: $lead->name),
                    'type' => $options['customer_type'] ?? ($lead->company ? 'business' : 'individual'),
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'company' => $lead->company,
                    'lead_source_id' => $lead->lead_source_id,
                    'status' => 'active',
                    'assigned_to' => $lead->assigned_to ?? $actor->id,
                    'notes' => __('Chuyển đổi từ Lead :code', ['code' => $lead->code]).($lead->notes ? "\n".$lead->notes : ''),
                    'is_demo' => $lead->is_demo,
                ], $actor);
            }

            $opportunity = null;
            if (! empty($options['create_opportunity'])) {
                $stage = PipelineStage::ordered()->where('is_won', false)->where('is_lost', false)->firstOrFail();
                $opportunity = Opportunity::create([
                    'title' => $options['opportunity_title'] ?? __('Cơ hội từ :name', ['name' => $lead->name]),
                    'customer_id' => $customer->id,
                    'lead_id' => $lead->id,
                    'pipeline_stage_id' => $stage->id,
                    'value' => $options['opportunity_value'] ?? $lead->estimated_value,
                    'probability' => $stage->probability,
                    'expected_close_date' => $options['expected_close_date'] ?? now()->addDays(30),
                    'assigned_to' => $lead->assigned_to ?? $actor->id,
                    'created_by' => $actor->id,
                    'is_demo' => $lead->is_demo,
                ]);
                OpportunityStageHistory::create([
                    'opportunity_id' => $opportunity->id, 'to_stage_id' => $stage->id,
                    'changed_by' => $actor->id, 'created_at' => now(),
                ]);
            }

            $lead->forceFill([
                'status' => 'converted',
                'converted_customer_id' => $customer->id,
                'converted_opportunity_id' => $opportunity?->id,
                'converted_at' => now(),
            ])->save();

            ActivityLogger::log('converted', $lead, 'Chuyển đổi Lead '.$lead->code.' thành khách hàng '.$customer->code
                .($opportunity ? ' và cơ hội '.$opportunity->code : ''));

            return ['customer' => $customer, 'opportunity' => $opportunity];
        });
    }
}
