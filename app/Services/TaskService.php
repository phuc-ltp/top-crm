<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Luật nghiệp vụ công việc nâng cao (bước 4.1):
 *  - Việc con chỉ một cấp: việc cha không được là việc con của việc khác; việc đang có việc con không thể thành việc con.
 *  - Phụ thuộc "A phải xong trước B": không tự phụ thuộc, không tạo vòng (A → B → … → A).
 *  - Không hoàn thành được việc khi còn việc phải xong trước chưa hoàn thành / chưa hủy.
 */
class TaskService
{
    /** Kiểm tra việc cha hợp lệ cho $task (null = việc mới). */
    public function assertValidParent(?Task $task, Task $parent): void
    {
        $error = match (true) {
            $task && $parent->id === $task->id => __('Công việc không thể là việc con của chính nó.'),
            $parent->parent_id !== null => __('Chỉ hỗ trợ một cấp việc con: việc cha đã chọn đang là việc con của việc khác.'),
            $task && $task->subtasks()->exists() => __('Công việc đang có việc con nên không thể trở thành việc con.'),
            default => null,
        };
        if ($error) {
            throw ValidationException::withMessages(['parent_id' => $error]);
        }
    }

    /** Thêm phụ thuộc: $task chỉ được bắt đầu / hoàn thành sau khi $dependsOn xong. */
    public function addDependency(Task $task, Task $dependsOn): void
    {
        if ($task->id === $dependsOn->id) {
            throw ValidationException::withMessages(['depends_on_id' => __('Công việc không thể phụ thuộc chính nó.')]);
        }
        if ($this->reaches($dependsOn->id, $task->id)) {
            throw ValidationException::withMessages(['depends_on_id' => __('Không thể thêm: phụ thuộc này tạo thành vòng lặp (các việc chờ lẫn nhau).')]);
        }
        $task->dependencies()->syncWithoutDetaching([$dependsOn->id]);
    }

    /** Có đường phụ thuộc từ $from tới $target không (duyệt theo chiều "phải xong trước"). */
    private function reaches(int $from, int $target): bool
    {
        $seen = [$from => true];
        $frontier = [$from];
        while ($frontier) {
            $next = DB::table('task_dependencies')->whereIn('task_id', $frontier)->pluck('depends_on_id')->all();
            $frontier = [];
            foreach ($next as $id) {
                if ($id === $target) {
                    return true;
                }
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $frontier[] = $id;
                }
            }
        }

        return false;
    }

    /** Chặn chuyển sang "Hoàn thành" khi còn việc phải xong trước. */
    public function assertCanSetStatus(Task $task, string $status): void
    {
        if ($status !== 'completed' || ! $task->exists || $task->status === 'completed') {
            return;
        }
        $blockers = $task->openBlockers()->limit(3)->pluck('title');
        if ($blockers->isNotEmpty()) {
            throw ValidationException::withMessages(['status' => __('Chưa thể hoàn thành: cần xong trước các việc: :list.', ['list' => $blockers->implode(', ')])]);
        }
    }
}
