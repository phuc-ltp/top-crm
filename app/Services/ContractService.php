<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/**
 * Hợp đồng: gia hạn (tạo hợp đồng nối tiếp từ ngày sau ngày hết hạn, cùng thời lượng), nhắc hết hạn ở các mốc
 * REMIND_STAGES ngày cho người phụ trách (không có → người quản lý hợp đồng xem toàn bộ), mỗi mốc một lần.
 */
class ContractService
{
    public const REMIND_STAGES = [30, 7, 0];

    /** Tạo hợp đồng gia hạn (bản nháp) nối tiếp hợp đồng hiện tại. */
    public function renew(Contract $contract, User $actor): Contract
    {
        abort_if($contract->status === 'terminated', 422, __('Hợp đồng đã chấm dứt, không gia hạn được.'));
        abort_unless($contract->end_date, 422, __('Hợp đồng không thời hạn, không cần gia hạn.'));
        abort_if($contract->renewals()->exists(), 422, __('Hợp đồng này đã có bản gia hạn.'));

        return DB::transaction(function () use ($contract, $actor) {
            $start = $contract->end_date->copy()->addDay();
            $months = $contract->start_date ? max(1, (int) round($contract->start_date->diffInMonths($contract->end_date->copy()->addDay()))) : 12;
            $new = Contract::create([
                'title' => $contract->title, 'customer_id' => $contract->customer_id, 'contact_id' => $contract->contact_id, 'type' => $contract->type,
                'value' => $contract->value, 'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addMonthsNoOverflow($months)->subDay()->toDateString(),
                'status' => 'draft', 'auto_renew' => $contract->auto_renew, 'terms' => $contract->terms,
                'assigned_to' => $contract->assigned_to ?? $actor->id, 'created_by' => $actor->id, 'is_demo' => $contract->is_demo,
            ]);
            $new->forceFill(['renewed_from_id' => $contract->id])->save();
            ActivityLogger::log('created', $new, 'Gia hạn hợp đồng '.$contract->code.' → '.$new->code);

            return $new;
        });
    }

    public function runReminders(): int
    {
        $count = 0;
        foreach (Organization::where('status', 'active')->pluck('id') as $orgId) {
            $count += app(CurrentOrganization::class)->run((int) $orgId, fn () => $this->remindCurrentOrganization());
        }

        return $count;
    }

    public function remindCurrentOrganization(): int
    {
        $count = 0;
        $managers = null;
        $due = Contract::with('assignee', 'customer:id,name')->where('status', 'signed')->whereNotNull('end_date')
            ->whereDate('end_date', '<=', today()->addDays(max(self::REMIND_STAGES)))->get();
        foreach ($due as $c) {
            $stage = collect(self::REMIND_STAGES)->filter(fn ($s) => $c->days_left <= $s)->min();
            if ($stage === null || ($c->reminded_stage !== null && $c->reminded_stage <= $stage)) {
                continue;
            }
            $managers ??= User::inOrganization()->get()->filter(fn ($u) => $u->can('contracts.view_all') && $u->can('contracts.update'));
            $to = $c->assignee && $c->assignee->isActive() ? collect([$c->assignee]) : $managers;
            $msg = $c->days_left < 0
                ? __('Hợp đồng :c (:k) đã hết hạn ngày :d.', ['c' => $c->code, 'k' => $c->customer?->name, 'd' => vn_date($c->end_date)])
                : __($c->auto_renew ? 'Hợp đồng :c (:k) hết hạn ngày :d (còn :n ngày) — tự gia hạn.' : 'Hợp đồng :c (:k) hết hạn ngày :d (còn :n ngày).',
                    ['c' => $c->code, 'k' => $c->customer?->name, 'd' => vn_date($c->end_date), 'n' => $c->days_left]);
            foreach ($to as $u) {
                CrmNotification::send($u, 'contract_expiring', $msg, route('contracts.show', $c), 'bi-file-earmark-text');
            }
            $c->forceFill(['reminded_stage' => $stage])->saveQuietly();
            $count++;
        }

        return $count;
    }
}
