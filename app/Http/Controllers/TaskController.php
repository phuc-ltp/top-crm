<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Notifications\CrmNotification;
use App\Services\TaskService;
use App\Services\UploadService;
use App\Support\Lookup;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function __construct(private TaskService $tasks) {}

    private function filtered(Request $request)
    {
        return Task::visibleTo($request->user())
            ->with(['assignee:id,name,avatar', 'related', 'project:id,code,name'])
            ->withCount(['checklist', 'checklist as checklist_done_count' => fn ($q) => $q->where('is_done', true), 'subtasks'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', $this->like($request->q)))
            ->when($request->filled('status'), fn ($q) => $request->status === 'open' ? $q->open() : $q->where('status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->filled('project_id'), fn ($q) => $request->project_id === 'none' ? $q->whereNull('project_id') : $q->where('project_id', $request->integer('project_id')))
            ->when($request->input('mine') === '1', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->filled('due'), fn ($q) => match ($request->due) {
                'today' => $q->whereDate('due_date', today()),
                'week' => $q->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()]),
                'late' => $q->open()->where('due_date', '<', now()),
                default => $q,
            });
    }

    /** Dự án người dùng được xem (cho bộ lọc / chọn dự án). */
    private function projectOptions(Request $request): array
    {
        return Project::visibleTo($request->user())->whereNotIn('status', ['cancelled'])->orderBy('name')->limit(500)
            ->get(['id', 'code', 'name'])->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->name])->all();
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);
        $tasks = $this->applySort($this->filtered($request), $request, ['title', 'due_date', 'priority', 'status', 'created_at'], 'due_date')
            ->paginate($this->perPage($request))->withQueryString();

        $base = Task::visibleTo($request->user());
        $counts = [
            'open' => (clone $base)->open()->count(),
            'late' => (clone $base)->open()->where('due_date', '<', now())->count(),
            'today' => (clone $base)->open()->whereDate('due_date', today())->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];

        return view('tasks.index', ['tasks' => $tasks, 'counts' => $counts, 'users' => Lookup::users(), 'projects' => $this->projectOptions($request)]);
    }

    /** Bảng Kanban: kéo thả thẻ giữa các cột trạng thái. Cột đã xong / đã hủy chỉ hiện việc cập nhật trong 30 ngày. */
    public function board(Request $request)
    {
        $this->authorize('viewAny', Task::class);
        $tasks = $this->filtered($request)
            ->where(fn ($q) => $q->open()->orWhere('tasks.updated_at', '>=', now()->subDays(30)))
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->limit(600)->get()
            ->groupBy('status');

        return view('tasks.board', ['tasks' => $tasks, 'users' => Lookup::users(), 'projects' => $this->projectOptions($request)]);
    }

    /** Khối lượng công việc theo người: việc đang mở, đang làm, trễ hạn, đến hạn 7 ngày tới, giờ ước tính, đã xong 30 ngày. */
    public function workload(Request $request)
    {
        $this->authorize('viewAny', Task::class);
        $user = $request->user();
        $users = $user->can('tasks.view_all') ? Lookup::users() : [$user->id => $user->name];
        $scope = fn () => Task::visibleTo($user)->whereIn('assigned_to', array_keys($users))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')));

        $now = now();
        $open = $scope()->open()->groupBy('assigned_to')->selectRaw(
            'assigned_to, COUNT(*) AS open_count,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS doing,
             SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) AS late,
             SUM(CASE WHEN due_date >= ? AND due_date < ? THEN 1 ELSE 0 END) AS due_week,
             COALESCE(SUM(estimated_hours), 0) AS hours',
            ['in_progress', $now, $now, $now->copy()->addDays(7)]
        )->get()->keyBy('assigned_to');
        $done = $scope()->where('status', 'completed')->where('completed_at', '>=', $now->copy()->subDays(30))
            ->groupBy('assigned_to')->selectRaw('assigned_to, COUNT(*) AS c')->pluck('c', 'assigned_to');

        $rows = collect($users)->map(fn ($name, $id) => (object) [
            'id' => $id, 'name' => $name,
            'open' => (int) ($open[$id]->open_count ?? 0), 'doing' => (int) ($open[$id]->doing ?? 0),
            'late' => (int) ($open[$id]->late ?? 0), 'due_week' => (int) ($open[$id]->due_week ?? 0),
            'hours' => (float) ($open[$id]->hours ?? 0), 'done' => (int) ($done[$id] ?? 0),
        ])->sortByDesc('open')->values();
        $unassigned = Task::visibleTo($user)->open()->whereNull('assigned_to')
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))->count();

        return view('tasks.workload', ['rows' => $rows, 'max' => max(1, (int) $rows->max('open')), 'unassigned' => $unassigned, 'projects' => $this->projectOptions($request)]);
    }

    /** Xuất công việc theo bộ lọc đang xem — chỉ những việc người dùng được xem (không có quyền xuất riêng). */
    public function export(Request $request, \App\Services\ExportService $exporter)
    {
        $this->authorize('viewAny', Task::class);
        $rows = $this->filtered($request)->orderBy('due_date')->limit(20000)->get()->map(fn (Task $t) => [
            $t->title, $t->project?->name, label_of(Task::STATUSES, $t->status), label_of(Task::PRIORITIES, $t->priority),
            $t->due_date ? vn_date($t->due_date, true) : '', $t->completed_at ? vn_date($t->completed_at, true) : '',
            $t->assignee?->name, $t->estimated_hours, vn_date($t->created_at),
        ]);
        \App\Services\ActivityLogger::log('exported', null, 'Xuất danh sách công việc ('.$rows->count().' dòng)');

        return $exporter->download('cong_viec', [__('Tiêu đề'), __('Dự án'), __('Trạng thái'), __('Mức ưu tiên'), __('Hạn chót'), __('Hoàn thành lúc'), __('Người phụ trách'), __('Giờ ước tính'), __('Ngày tạo')],
            $rows, $request->input('format', 'xlsx'));
    }

    public function calendar(Request $request)
    {
        $this->authorize('viewAny', Task::class);
        $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m'))) ?: now();
        $month = $month->startOfMonth();
        $start = $month->copy()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $tasks = Task::visibleTo($request->user())->whereBetween('due_date', [$start, $end])
            ->when($request->input('mine') === '1', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->orderBy('due_date')->get(['id', 'title', 'due_date', 'status', 'priority'])
            ->groupBy(fn ($t) => $t->due_date->format('Y-m-d'));

        return view('tasks.calendar', compact('month', 'start', 'end', 'tasks'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Task::class);
        $parent = $request->filled('parent_id') ? Task::visibleTo($request->user())->find($request->integer('parent_id')) : null;
        $task = new Task([
            'priority' => 'medium', 'status' => 'pending', 'assigned_to' => $request->user()->id,
            'due_date' => now()->addDay()->setTime(17, 0),
            'related_type' => $request->input('related_type'), 'related_id' => $request->integer('related_id') ?: null,
            'parent_id' => $parent?->id,
            'project_id' => $parent ? $parent->project_id : ($request->integer('project_id') ?: null),
        ]);

        return view('tasks.form', ['task' => $task, 'parent' => $parent, 'users' => Lookup::users(), 'projects' => $this->projectOptions($request)] + $this->relatedOptions($request));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Task::class);
        $data = $this->validated($request);
        $checklist = array_filter(array_map('trim', $request->input('checklist', [])));

        $task = DB::transaction(function () use ($data, $request, $checklist) {
            $task = Task::create($data + ['created_by' => $request->user()->id]);
            foreach (array_values($checklist) as $i => $content) {
                $task->checklist()->create(['content' => mb_substr($content, 0, 255), 'position' => $i]);
            }

            return $task;
        });

        $this->notifyAssignee($task, null, $request);

        return redirect()->route('tasks.show', $task)->with('success', __('Đã tạo công việc.'));
    }

    public function show(Request $request, Task $task)
    {
        $this->authorize('view', $task);
        $task->load(['assignee', 'creator', 'related', 'checklist', 'comments.user:id,name,avatar', 'attachments.user:id,name',
            'project:id,code,name,manager_id', 'parent:id,title,status', 'subtasks.assignee:id,name,avatar',
            'dependencies', 'dependents']);
        $history = \App\Models\ActivityLog::where('subject_type', 'task')->where('subject_id', $task->id)->with('user:id,name')->latest('created_at')->limit(20)->get();

        // Ứng viên phụ thuộc: việc cùng dự án (hoặc việc không thuộc dự án nếu việc này không thuộc dự án) mà người dùng được xem
        $dependencyOptions = [];
        if ($request->user()->can('update', $task)) {
            $taken = $task->dependencies->pluck('id')->push($task->id)->all();
            $dependencyOptions = Task::visibleTo($request->user())->whereNotIn('id', $taken)
                ->when($task->project_id, fn ($q) => $q->where('project_id', $task->project_id), fn ($q) => $q->whereNull('project_id'))
                ->orderByDesc('created_at')->limit(300)->pluck('title', 'id')->all();
        }

        return view('tasks.show', compact('task', 'history', 'dependencyOptions'));
    }

    public function edit(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $task->load('parent:id,title');

        return view('tasks.form', ['task' => $task, 'parent' => $task->parent, 'users' => Lookup::users(), 'projects' => $this->projectOptions($request)] + $this->relatedOptions($request));
    }

    public function update(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $previous = $task->assigned_to;
        $data = $this->validated($request, $task);
        DB::transaction(function () use ($task, $data) {
            $task->update($data);
            // Việc con luôn cùng dự án với việc cha
            $task->subtasks()->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', '!=', $task->project_id ?? 0))
                ->get()->each(fn (Task $s) => $s->update(['project_id' => $task->project_id]));
        });
        $this->notifyAssignee($task, $previous, $request);

        return redirect()->route('tasks.show', $task)->with('success', __('Đã cập nhật công việc.'));
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);
        $task->delete();

        return redirect()->route('tasks.index')->with('success', __('Đã xóa công việc.').' '.__('Có thể khôi phục trong Thùng rác.'));
    }

    public function updateStatus(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Task::STATUSES))]]);
        $this->tasks->assertCanSetStatus($task, $data['status']);
        $task->update(['status' => $data['status'], 'completed_at' => $data['status'] === 'completed' ? ($task->completed_at ?? now()) : null]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => __('Đã cập nhật trạng thái.')]);
        }

        return back()->with('success', __('Trạng thái: :status.', ['status' => label_of(Task::STATUSES, $data['status'])]));
    }

    public function addDependency(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $data = $request->validate(['depends_on_id' => ['required', 'integer']], [], ['depends_on_id' => __('Việc phải xong trước')]);
        $other = Task::visibleTo($request->user())->find($data['depends_on_id']);
        if (! $other) {
            throw ValidationException::withMessages(['depends_on_id' => __('Công việc đã chọn không hợp lệ.')]);
        }
        $this->tasks->addDependency($task, $other);
        \App\Services\ActivityLogger::log('updated', $task, 'Thêm phụ thuộc: cần xong "'.$other->title.'" trước');

        return back()->with('success', __('Đã thêm việc phải xong trước.'));
    }

    public function removeDependency(Task $task, int $dependency)
    {
        $this->authorize('update', $task);
        abort_unless($task->dependencies()->detach($dependency) > 0, 404);

        return back()->with('success', __('Đã bỏ phụ thuộc.'));
    }

    public function addChecklist(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $data = $request->validate(['content' => ['required', 'string', 'max:255']], [], ['content' => __('Nội dung')]);
        $task->checklist()->create($data + ['position' => (int) $task->checklist()->max('position') + 1]);

        return back()->with('success', __('Đã thêm mục checklist.'));
    }

    public function toggleChecklist(Request $request, Task $task, TaskChecklistItem $item)
    {
        $this->authorize('update', $task);
        abort_unless($item->task_id === $task->id, 404);
        $item->update(['is_done' => ! $item->is_done]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'is_done' => $item->is_done]);
        }

        return back();
    }

    public function deleteChecklist(Task $task, TaskChecklistItem $item)
    {
        $this->authorize('update', $task);
        abort_unless($item->task_id === $task->id, 404);
        $item->delete();

        return back()->with('success', __('Đã xóa mục checklist.'));
    }

    public function addComment(Request $request, Task $task)
    {
        $this->authorize('view', $task);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], [], ['body' => __('Bình luận')]);
        $task->comments()->create($data + ['user_id' => $request->user()->id]);

        foreach ([$task->assignee, $task->creator] as $u) {
            if ($u && $u->id !== $request->user()->id) {
                CrmNotification::send($u, 'task_comment', $request->user()->name.' bình luận trong công việc "'.$task->title.'"', route('tasks.show', $task), 'bi-chat-dots');
            }
        }

        return back()->with('success', __('Đã thêm bình luận.'));
    }

    public function addAttachment(Request $request, Task $task, UploadService $uploads)
    {
        $this->authorize('update', $task);
        $request->validate([
            'file' => ['required', 'file', 'mimes:'.config('crm.attachment_mimes'), 'max:'.config('crm.attachment_max_kb')],
        ], [], ['file' => __('Tệp đính kèm')]);

        $uploads->attach($task, $request->file('file'));

        return back()->with('success', __('Đã tải lên tệp đính kèm.'));
    }

    public function downloadAttachment(Task $task, Attachment $attachment)
    {
        $this->authorize('view', $task);
        abort_unless($attachment->attachable_type === 'task' && $attachment->attachable_id === $task->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404, __('Tệp không còn tồn tại.'));

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function deleteAttachment(Request $request, Task $task, Attachment $attachment)
    {
        $this->authorize('update', $task);
        abort_unless($attachment->attachable_type === 'task' && $attachment->attachable_id === $task->id, 404);
        $attachment->delete();

        return back()->with('success', __('Đã xóa tệp đính kèm.'));
    }

    private function validated(Request $request, ?Task $existing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at'), \App\Support\Tenancy\TenantRule::member()],
            'project_id' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'integer'],
            'related_type' => ['nullable', Rule::in(array_keys(Task::RELATED_TYPES))],
            'related_id' => ['nullable', 'required_with:related_type', 'integer'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*' => ['nullable', 'string', 'max:255'],
        ], [], ['title' => __('Tiêu đề công việc'), 'related_id' => __('Đối tượng liên quan'), 'start_date' => __('Ngày bắt đầu'), 'estimated_hours' => __('Giờ ước tính')]);
        unset($data['checklist']);

        if (! empty($data['related_type'])) {
            $class = Relation::getMorphedModel($data['related_type']);
            $related = $class::find($data['related_id']);
            abort_unless($related && $request->user()->can('view', $related), 422, __('Đối tượng liên quan không hợp lệ.'));
        } else {
            $data['related_type'] = null;
            $data['related_id'] = null;
        }

        // Trường không gửi lên (form / API cũ) → giữ nguyên giá trị hiện có, không vô tình gỡ dự án / việc cha
        foreach (['project_id', 'parent_id'] as $key) {
            if (! $request->exists($key)) {
                $data[$key] = $existing?->{$key};
            }
        }

        // Việc cha: phải là việc người dùng được xem; việc con luôn cùng dự án với việc cha
        if (! empty($data['parent_id'])) {
            $unchanged = (int) $data['parent_id'] === (int) $existing?->parent_id;
            $parent = $unchanged ? Task::find($data['parent_id']) : Task::visibleTo($request->user())->find($data['parent_id']);
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => __('Việc cha không hợp lệ.')]);
            }
            if (! $unchanged) {
                $this->tasks->assertValidParent($existing, $parent);
            }
            $data['project_id'] = $parent->project_id;
        } else {
            $data['parent_id'] = null;
        }

        // Dự án: phải là dự án người dùng được xem (giữ nguyên dự án cũ khi không đổi)
        if (! empty($data['project_id']) && (int) $data['project_id'] !== (int) $existing?->project_id) {
            $project = Project::find($data['project_id']);
            if (! $project || ! $request->user()->can('view', $project)) {
                throw ValidationException::withMessages(['project_id' => __('Dự án không hợp lệ.')]);
            }
        }
        $data['project_id'] = $data['project_id'] ?: null;

        if (! empty($data['start_date']) && ! empty($data['due_date']) && Carbon::parse($data['start_date'])->gt(Carbon::parse($data['due_date'])->startOfDay())) {
            throw ValidationException::withMessages(['start_date' => __('Ngày bắt đầu phải trước hoặc bằng hạn chót.')]);
        }
        if ($existing) {
            $this->tasks->assertCanSetStatus($existing, $data['status']);
        }

        if (! $request->user()->can('tasks.assign')) {
            $data['assigned_to'] = $existing ? $existing->assigned_to : $request->user()->id;
        }
        $data['completed_at'] = $data['status'] === 'completed' ? ($existing?->completed_at ?? now()) : null;
        if ($existing && $existing->due_date?->format('Y-m-d H:i') !== Carbon::parse($data['due_date'] ?? null)->format('Y-m-d H:i')) {
            $data['reminded_at'] = null; // đổi hạn thì nhắc lại
        }

        return $data;
    }

    private function relatedOptions(Request $request): array
    {
        $user = $request->user();

        return ['relatedOptions' => [
            'customer' => \App\Models\Customer::visibleTo($user)->orderBy('name')->limit(500)->pluck('name', 'id')->all(),
            'lead' => \App\Models\Lead::visibleTo($user)->orderBy('name')->limit(500)->pluck('name', 'id')->all(),
            'opportunity' => \App\Models\Opportunity::visibleTo($user)->orderBy('title')->limit(500)->pluck('title', 'id')->all(),
        ]];
    }

    private function notifyAssignee(Task $task, ?int $previous, Request $request): void
    {
        if ($task->assigned_to && $task->assigned_to !== $previous && $task->assigned_to !== $request->user()->id) {
            CrmNotification::send($task->assignee, 'task_assigned', 'Bạn được giao công việc: '.$task->title
                .($task->due_date ? ' (hạn '.vn_date($task->due_date, true).')' : ''), route('tasks.show', $task), 'bi-check2-square');
        }
    }
}
