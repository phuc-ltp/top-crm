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
        $totals = ['due_soon' => 0, 'overdue' => 0, 'activities' => 0];
        foreach (Organization::where('status', 'active')->pluck('id') as $orgId) {
            $result = app(CurrentOrganization::class)->run((int) $orgId, fn () => $this->runForCurrentOrganization());
            $totals['due_soon'] += $result['due_soon'];
            $totals['overdue'] += $result['overdue'];
            $totals['activities'] += $result['activities'];
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

        // Hoạt động đã lên lịch (gọi, hẹn, theo dõi...) trong 1 giờ tới: nhắc người phụ trách 1 lần
        $activities = 0;
        \App\Models\Interaction::with(['assignee', 'interactable'])->planned()->whereNull('reminded_at')
            ->whereBetween('scheduled_at', [now()->subMinutes(15), now()->addHour()])
            ->chunkById(200, function ($items) use (&$activities) {
                foreach ($items as $a) {
                    $who = $a->assignee ?? $a->user;
                    if ($who) {
                        CrmNotification::send($who, 'task_due', __('Sắp đến giờ: :title (:subject) lúc :time', [
                            'title' => $a->subject ?: label_of(\App\Models\Interaction::TYPES, $a->type), 'subject' => $a->subjectLabel(), 'time' => vn_date($a->scheduled_at, true),
                        ], $who->preferredLocale()), $a->subjectUrl(), 'bi-alarm');
                    }
                    $a->forceFill(['reminded_at' => now()])->saveQuietly();
                    $activities++;
                }
            });

        return ['due_soon' => $dueSoon, 'overdue' => $overdue, 'activities' => $activities];
    }
}
