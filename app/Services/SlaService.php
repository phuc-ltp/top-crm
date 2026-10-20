<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Carbon;

/**
 * SLA ticket hỗ trợ (bước 4.3).
 *  - Hạn phản hồi đầu / hạn xử lý = thời điểm tạo + số giờ theo mức ưu tiên (cài đặt từng tổ chức, mặc định DEFAULTS).
 *    Tính theo giờ đồng hồ, chưa trừ giờ ngoài làm việc.
 *  - Trạng thái "Chờ phản hồi" (chờ khách) tạm dừng đồng hồ: khi tiếp tục, các hạn chưa đạt được dời thêm đúng khoảng đã chờ.
 *  - Leo thang (tác vụ định kỳ): mức 1 khi còn ≤ 1 giờ tới hạn → báo người phụ trách; mức 2 khi đã quá hạn
 *    → báo quản lý trực tiếp của người phụ trách và quản lý CSKH. Mỗi mức chỉ báo một lần.
 */
class SlaService
{
    /** Giờ phản hồi đầu / giờ xử lý xong theo mức ưu tiên. */
    public const DEFAULTS = [
        'urgent' => [1, 8],
        'high' => [4, 24],
        'medium' => [8, 48],
        'low' => [24, 96],
    ];

    public const OPEN_STATUSES = ['open', 'in_progress', 'waiting'];

    /** @return array<string, array{0: int, 1: int}> */
    public static function policy(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $priority => [$response, $resolve]) {
            $out[$priority] = [
                (int) (Setting::get("sla_response_$priority") ?: $response),
                (int) (Setting::get("sla_resolve_$priority") ?: $resolve),
            ];
        }

        return $out;
    }

    /** Đặt hạn SLA cho ticket mới (gọi khi tạo). */
    public function applyDueDates(SupportTicket $ticket): void
    {
        [$response, $resolve] = self::policy()[$ticket->priority] ?? self::DEFAULTS['medium'];
        $start = $ticket->created_at ?? now();
        $ticket->first_response_due_at ??= $start->copy()->addHours($response);
        $ticket->resolution_due_at ??= $start->copy()->addHours($resolve);
    }

    /** Cập nhật đồng hồ SLA khi đổi trạng thái (gọi trước khi lưu). */
    public function onStatusChange(SupportTicket $ticket, ?string $from, string $to): void
    {
        if ($to === 'waiting' && ! $ticket->paused_at) {
            $ticket->paused_at = now();

            return;
        }
        if ($from === 'waiting' && $ticket->paused_at) {
            $minutes = (int) $ticket->paused_at->diffInMinutes(now());
            $ticket->paused_minutes += $minutes;
            if ($ticket->first_response_due_at && ! $ticket->first_responded_at) {
                $ticket->first_response_due_at = $ticket->first_response_due_at->copy()->addMinutes($minutes);
            }
            if ($ticket->resolution_due_at && ! in_array($to, ['resolved', 'closed'], true)) {
                $ticket->resolution_due_at = $ticket->resolution_due_at->copy()->addMinutes($minutes);
            }
            $ticket->paused_at = null;
            $ticket->escalation_level = min($ticket->escalation_level, 0);   // hạn mới → được cảnh báo lại
        }
    }

    /** Ghi nhận phản hồi đầu tiên gửi khách (không tính ghi chú nội bộ). */
    public function markResponded(SupportTicket $ticket): void
    {
        if (! $ticket->first_responded_at) {
            $ticket->forceFill(['first_responded_at' => now()])->save();
        }
    }

    /**
     * Tình trạng SLA: none (ticket cũ, không có SLA) / pending (còn hạn) / met (đạt) / breached (vi phạm) / paused (đang chờ khách).
     * @return array{response: string, resolution: string}
     */
    public static function state(SupportTicket $ticket): array
    {
        $pausedFor = $ticket->paused_at ? (int) $ticket->paused_at->diffInMinutes(now()) : 0;
        $judge = function (?Carbon $due, ?Carbon $doneAt) use ($ticket, $pausedFor): string {
            if (! $due) {
                return 'none';
            }
            if ($doneAt) {
                return $doneAt->lte($due) ? 'met' : 'breached';
            }
            if ($ticket->paused_at) {
                return now()->lte($due->copy()->addMinutes($pausedFor)) ? 'paused' : 'breached';
            }

            return now()->lte($due) ? 'pending' : 'breached';
        };

        return [
            'response' => $judge($ticket->first_response_due_at, $ticket->first_responded_at),
            'resolution' => $judge($ticket->resolution_due_at, $ticket->resolved_at),
        ];
    }

    /** Tác vụ định kỳ: cảnh báo sắp trễ và leo thang ticket quá hạn ở mọi tổ chức đang hoạt động. */
    public function runEscalations(): array
    {
        $totals = ['warned' => 0, 'escalated' => 0];
        foreach (Organization::where('status', 'active')->pluck('id') as $orgId) {
            $r = app(CurrentOrganization::class)->run((int) $orgId, fn () => $this->escalateCurrentOrganization());
            $totals['warned'] += $r['warned'];
            $totals['escalated'] += $r['escalated'];
        }

        return $totals;
    }

    private function escalateCurrentOrganization(): array
    {
        $warned = 0;
        $escalated = 0;
        $active = fn () => SupportTicket::with('assignee')->whereIn('status', ['open', 'in_progress'])->whereNull('paused_at');

        // Mức 2: đã quá hạn phản hồi đầu (chưa phản hồi) hoặc quá hạn xử lý
        $active()->where('escalation_level', '<', 2)
            ->where(fn ($q) => $q->where('resolution_due_at', '<', now())
                ->orWhere(fn ($w) => $w->whereNull('first_responded_at')->where('first_response_due_at', '<', now())))
            ->chunkById(200, function ($tickets) use (&$escalated) {
                foreach ($tickets as $t) {
                    $message = __('Ticket :code vi phạm SLA: ":subject" (phụ trách: :who).', ['code' => $t->code, 'subject' => $t->subject, 'who' => $t->assignee?->name ?? __('chưa giao')]);
                    foreach ($this->supervisors($t) as $u) {
                        CrmNotification::send($u, 'ticket_escalated', $message, route('tickets.show', $t), 'bi-exclamation-triangle');
                    }
                    if ($t->assignee) {
                        CrmNotification::send($t->assignee, 'ticket_escalated', $message, route('tickets.show', $t), 'bi-exclamation-triangle');
                    }
                    $t->forceFill(['escalation_level' => 2, 'escalated_at' => now()])->saveQuietly();
                    $escalated++;
                }
            });

        // Mức 1: còn ≤ 1 giờ tới một trong hai hạn
        $soon = now()->addHour();
        $active()->where('escalation_level', 0)
            ->where(fn ($q) => $q->whereBetween('resolution_due_at', [now(), $soon])
                ->orWhere(fn ($w) => $w->whereNull('first_responded_at')->whereBetween('first_response_due_at', [now(), $soon])))
            ->chunkById(200, function ($tickets) use (&$warned) {
                foreach ($tickets as $t) {
                    if ($t->assignee) {
                        CrmNotification::send($t->assignee, 'ticket_sla_warning', __('Ticket :code sắp trễ SLA (còn dưới 1 giờ): ":subject".', ['code' => $t->code, 'subject' => $t->subject]),
                            route('tickets.show', $t), 'bi-alarm');
                    }
                    $t->forceFill(['escalation_level' => 1])->saveQuietly();
                    $warned++;
                }
            });

        return ['warned' => $warned, 'escalated' => $escalated];
    }

    /** Người nhận leo thang: quản lý trực tiếp của người phụ trách + quản lý CSKH; không có ai → chủ sở hữu tổ chức. */
    private function supervisors(SupportTicket $t)
    {
        $ids = User::inOrganization()->role('Customer Support Manager')->pluck('users.id');
        if ($t->assigned_to && ($manager = EmployeeProfile::where('user_id', $t->assigned_to)->value('manager_id'))) {
            $ids->push($manager);
        }
        if ($ids->isEmpty()) {
            $ids = User::inOrganization()->role(\App\Support\PermissionCatalog::OWNER)->pluck('users.id');
        }

        return User::inOrganization()->whereIn('id', $ids->unique()->reject(fn ($id) => (int) $id === (int) $t->assigned_to))->get();
    }
}
