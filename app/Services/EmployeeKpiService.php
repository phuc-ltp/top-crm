<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\SalesTarget;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Support\Decimal;
use Illuminate\Support\Carbon;

/**
 * KPI cá nhân / nhóm tính từ dữ liệu thật trong kỳ [from, to] (bước 4.2). Định nghĩa thống nhất với các trang sẵn có:
 *  - revenue:       tiền đã thu (payments, gồm hoàn tiền âm) của hóa đơn đã phát hành do người đó phụ trách,
 *                   theo ngày thanh toán — giống trang Chỉ tiêu doanh số.
 *  - target:        tổng chỉ tiêu doanh số các tháng nằm trong kỳ (sales_targets).
 *  - deals_won / deals_value: cơ hội ở giai đoạn thắng, closed_at trong kỳ — giống trang Dự báo.
 *  - tasks_done / tasks_on_time: việc hoàn thành trong kỳ / trong số đó hoàn thành không trễ hạn (không có hạn = đúng hạn).
 *  - tickets_resolved: ticket do người đó phụ trách có resolved_at trong kỳ.
 */
class EmployeeKpiService
{
    /** @return array<int, array<string, int|float|string>> theo user_id */
    public function forUsers(array $userIds, Carbon $from, Carbon $to): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (! $userIds) {
            return [];
        }
        [$start, $end] = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $revenue = Payment::join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->where('invoices.status', 'issued')->whereNull('invoices.deleted_at')
            ->whereBetween('payments.payment_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('invoices.assigned_to', $userIds)
            ->selectRaw('invoices.assigned_to AS uid, SUM(payments.amount) AS total')
            ->groupBy('invoices.assigned_to')->pluck('total', 'uid');

        $target = SalesTarget::whereIn('user_id', $userIds)
            ->whereRaw('(year * 100 + month) BETWEEN ? AND ?', [(int) $start->format('Ym'), (int) $end->format('Ym')])
            ->selectRaw('user_id, SUM(target_amount) AS total')->groupBy('user_id')->pluck('total', 'user_id');

        $deals = Opportunity::whereIn('assigned_to', $userIds)->whereBetween('closed_at', [$start, $end])
            ->whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->selectRaw('assigned_to, COUNT(*) AS c, SUM(value) AS v')->groupBy('assigned_to')->get()->keyBy('assigned_to');

        $tasks = Task::whereIn('assigned_to', $userIds)->where('status', 'completed')->whereBetween('completed_at', [$start, $end])
            ->selectRaw('assigned_to, COUNT(*) AS done, SUM(CASE WHEN due_date IS NULL OR completed_at <= due_date THEN 1 ELSE 0 END) AS on_time')
            ->groupBy('assigned_to')->get()->keyBy('assigned_to');

        $tickets = SupportTicket::whereIn('assigned_to', $userIds)->whereBetween('resolved_at', [$start, $end])
            ->selectRaw('assigned_to, COUNT(*) AS c')->groupBy('assigned_to')->pluck('c', 'assigned_to');

        $out = [];
        foreach ($userIds as $id) {
            $rev = Decimal::of($revenue[$id] ?? 0);
            $tgt = Decimal::of($target[$id] ?? 0);
            $done = (int) ($tasks[$id]->done ?? 0);
            $onTime = (int) ($tasks[$id]->on_time ?? 0);
            $out[$id] = [
                'revenue' => $rev,
                'target' => $tgt,
                'achievement' => bccomp($tgt, '0', 2) > 0 ? (int) floor((float) bcdiv(bcmul($rev, '100', 4), $tgt, 4)) : null,
                'deals_won' => (int) ($deals[$id]->c ?? 0),
                'deals_value' => Decimal::of($deals[$id]->v ?? 0),
                'tasks_done' => $done,
                'tasks_on_time' => $onTime,
                'on_time_rate' => $done > 0 ? (int) floor($onTime * 100 / $done) : null,
                'tickets_resolved' => (int) ($tickets[$id] ?? 0),
            ];
        }

        return $out;
    }

    /** Cộng KPI của nhiều người (KPI nhóm). */
    public function sum(array $rows): array
    {
        $t = ['revenue' => '0.00', 'target' => '0.00', 'deals_won' => 0, 'deals_value' => '0.00', 'tasks_done' => 0, 'tasks_on_time' => 0, 'tickets_resolved' => 0];
        foreach ($rows as $r) {
            foreach (['revenue', 'target', 'deals_value'] as $k) {
                $t[$k] = bcadd($t[$k], $r[$k], 2);
            }
            foreach (['deals_won', 'tasks_done', 'tasks_on_time', 'tickets_resolved'] as $k) {
                $t[$k] += $r[$k];
            }
        }
        $t['achievement'] = bccomp($t['target'], '0', 2) > 0 ? (int) floor((float) bcdiv(bcmul($t['revenue'], '100', 4), $t['target'], 4)) : null;
        $t['on_time_rate'] = $t['tasks_done'] > 0 ? (int) floor($t['tasks_on_time'] * 100 / $t['tasks_done']) : null;

        return $t;
    }

    /** Kỳ báo cáo từ tham số: month (mặc định) / quarter / year. @return array{0: Carbon, 1: Carbon, 2: string} */
    public static function period(?string $period): array
    {
        $now = now();

        return match ($period) {
            'quarter' => [$now->copy()->firstOfQuarter(), $now->copy()->lastOfQuarter(), 'quarter'],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), 'year'],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'month'],
        };
    }
}
