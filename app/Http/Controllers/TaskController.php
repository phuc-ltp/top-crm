<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Notifications\CrmNotification;
use App\Services\UploadService;
use App\Support\Lookup;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    private function filtered(Request $request)
    {
        return Task::visibleTo($request->user())
            ->with(['assignee:id,name,avatar', 'related'])
            ->withCount(['checklist', 'checklist as checklist_done_count' => fn ($q) => $q->where('is_done', true)])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', $this->like($request->q)))
            ->when($request->filled('status'), fn ($q) => $request->status === 'open' ? $q->open() : $q->where('status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->input('mine') === '1', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->filled('due'), fn ($q) => match ($request->due) {
                'today' => $q->whereDate('due_date', today()),
                'week' => $q->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()]),
                'late' => $q->open()->where('due_date', '<', now()),
                default => $q,
            });
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

        return view('tasks.index', ['tasks' => $tasks, 'counts' => $counts, 'users' => Lookup::users()]);
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
        $task = new Task([
            'priority' => 'medium', 'status' => 'pending', 'assigned_to' => $request->user()->id,
            'due_date' => now()->addDay()->setTime(17, 0),
            'related_type' => $request->input('related_type'), 'related_id' => $request->integer('related_id') ?: null,
        ]);

        return view('tasks.form', ['task' => $task, 'users' => Lookup::users()] + $this->relatedOptions($request));
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

    public function show(Task $task)
    {
        $this->authorize('view', $task);
        $task->load(['assignee', 'creator', 'related', 'checklist', 'comments.user:id,name,avatar', 'attachments.user:id,name']);
        $history = \App\Models\ActivityLog::where('subject_type', 'task')->where('subject_id', $task->id)->with('user:id,name')->latest('created_at')->limit(20)->get();

        return view('tasks.show', compact('task', 'history'));
    }

    public function edit(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        return view('tasks.form', ['task' => $task, 'users' => Lookup::users()] + $this->relatedOptions($request));
    }

    public function update(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $previous = $task->assigned_to;
        $task->update($this->validated($request, $task));
        $this->notifyAssignee($task, $previous, $request);

        return redirect()->route('tasks.show', $task)->with('success', __('Đã cập nhật công việc.'));
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);
        $task->delete();

        return redirect()->route('tasks.index')->with('success', __('Đã xóa công việc.'));
    }

    public function updateStatus(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Task::STATUSES))]]);
        $task->update(['status' => $data['status'], 'completed_at' => $data['status'] === 'completed' ? now() : null]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => __('Đã cập nhật trạng thái.')]);
        }

        return back()->with('success', __('Trạng thái: :status.', ['status' => label_of(Task::STATUSES, $data['status'])]));
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
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'related_type' => ['nullable', Rule::in(array_keys(Task::RELATED_TYPES))],
            'related_id' => ['nullable', 'required_with:related_type', 'integer'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*' => ['nullable', 'string', 'max:255'],
        ], [], ['title' => __('Tiêu đề công việc'), 'related_id' => __('Đối tượng liên quan')]);
        unset($data['checklist']);

        if (! empty($data['related_type'])) {
            $class = Relation::getMorphedModel($data['related_type']);
            $related = $class::find($data['related_id']);
            abort_unless($related && $request->user()->can('view', $related), 422, __('Đối tượng liên quan không hợp lệ.'));
        } else {
            $data['related_type'] = null;
            $data['related_id'] = null;
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
