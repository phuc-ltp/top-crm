<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Task;
use App\Support\Tenancy\CurrentOrganization;
use App\Notifications\CrmNotification;

class TaskReminderService
{
    /**
     * - Công việc đến hạn trong 24 giờ tới: gửi nhắc 1 lần (đánh dấu reminded_at).
     * - Công việc quá hạn còn mở: chuyển trạng thái "overdue" và thông báo.
     */
    public function run(): array
    {
        // Chạy riêng trong từng tổ chức đang hoạt động (dữ liệu công việc được giới hạn theo tổ chức)
        $totals = ['due_soon' => 0, 'overdue' => 0];
        foreach (Organization::where('status', 'active')->pluck('id') as $orgId) {
            $result = app(CurrentOrganization::class)->run((int) $orgId, fn () => $this->runForCurrentOrganization());
            $totals['due_soon'] += $result['due_soon'];
            $totals['overdue'] += $result['overdue'];
        }

        return $totals;
    }

    private function runForCurrentOrganization(): array
    {
        $dueSoon = 0;
        $overdue = 0;

        Task::with('assignee')->whereIn('status', ['pending', 'in_progress'])->whereNull('reminded_at')
            ->whereBetween('due_date', [now(), now()->addDay()])
            ->chunkById(200, function ($tasks) use (&$dueSoon) {
                foreach ($tasks as $task) {
                    CrmNotification::send($task->assignee, 'task_due', 'Công việc "'.$task->title.'" sắp đến hạn ('.vn_date($task->due_date, true).')',
                        route('tasks.show', $task), 'bi-alarm');
                    $task->forceFill(['reminded_at' => now()])->saveQuietly();
                    $dueSoon++;
                }
            });

        Task::with('assignee')->whereIn('status', ['pending', 'in_progress'])->where('due_date', '<', now())
            ->chunkById(200, function ($tasks) use (&$overdue) {
                foreach ($tasks as $task) {
                    $task->forceFill(['status' => 'overdue'])->saveQuietly();
                    CrmNotification::send($task->assignee, 'task_overdue', 'Công việc "'.$task->title.'" đã quá hạn!', route('tasks.show', $task), 'bi-exclamation-octagon');
                    $overdue++;
                }
            });

        return ['due_soon' => $dueSoon, 'overdue' => $overdue];
    }
}
