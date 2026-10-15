<?php

namespace App\Services;

use App\Models\Task;
use App\Notifications\CrmNotification;

class TaskReminderService
{
    /**
     * - Công việc đến hạn trong 24 giờ tới: gửi nhắc 1 lần (đánh dấu reminded_at).
     * - Công việc quá hạn còn mở: chuyển trạng thái "overdue" và thông báo.
     */
    public function run(): array
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
