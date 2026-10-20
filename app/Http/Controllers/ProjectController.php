<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Task;
use App\Notifications\CrmNotification;
use App\Services\ExportService;
use App\Support\Lookup;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Dự án: danh sách / tìm / lọc / xuất / tạo / xem (tiến độ, công việc, thành viên) / sửa / xóa (vào thùng rác). */
class ProjectController extends Controller
{
    private function filtered(Request $request)
    {
        return Project::visibleTo($request->user())->withProgress()
            ->with(['manager:id,name,avatar', 'customer:id,name', 'members:id,name,avatar'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $request->status === 'open'
                ? $q->whereIn('status', ['planning', 'active', 'on_hold']) : $q->where('status', $request->status))
            ->when($request->filled('manager_id'), fn ($q) => $q->where('manager_id', $request->integer('manager_id')));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);
        $request->validate(['status' => ['nullable', Rule::in(array_merge(['open'], array_keys(Project::STATUSES)))]]);
        $projects = $this->applySort($this->filtered($request), $request, ['code', 'name', 'end_date', 'created_at'])
            ->paginate($this->perPage($request))->withQueryString();

        $base = Project::visibleTo($request->user());
        $counts = [
            'open' => (clone $base)->whereIn('status', ['planning', 'active', 'on_hold'])->count(),
            'late' => (clone $base)->whereIn('status', ['planning', 'active', 'on_hold'])->whereDate('end_date', '<', today())->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];

        return view('projects.index', ['projects' => $projects, 'counts' => $counts, 'users' => Lookup::users()]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Project::class);
        $project = new Project([
            'status' => 'planning', 'priority' => 'medium', 'manager_id' => $request->user()->id,
            'customer_id' => $request->integer('customer_id') ?: null, 'start_date' => today(),
        ]);

        return view('projects.form', $this->formData($request, $project));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Project::class);
        [$data, $members] = $this->validated($request);
        $project = DB::transaction(function () use ($data, $members, $request) {
            $project = Project::create($data + ['created_by' => $request->user()->id]);
            $project->members()->sync($members);

            return $project;
        });
        $this->notifyMembers($project, $members, $request);

        return redirect()->route('projects.show', $project)->with('success', __('Đã tạo dự án :name.', ['name' => $project->name]));
    }

    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);
        $project->load(['manager:id,name,avatar,email', 'customer:id,code,name', 'members:id,name,avatar,email', 'creator:id,name']);
        $project->loadCount([
            'tasks as tasks_active_count' => fn ($q) => $q->where('status', '!=', 'cancelled'),
            'tasks as tasks_done_count' => fn ($q) => $q->where('status', 'completed'),
            'tasks as tasks_late_count' => fn ($q) => $q->open()->where('due_date', '<', now()),
        ]);

        // Công việc của dự án mà người dùng được xem (thành viên thấy mọi việc của dự án)
        $tasks = Task::visibleTo($request->user())->where('project_id', $project->id)
            ->with(['assignee:id,name,avatar'])->withCount(['subtasks', 'openBlockers as blockers_count'])
            ->orderByRaw("FIELD(status, 'overdue', 'in_progress', 'pending', 'completed', 'cancelled')")
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->limit(300)->get();
        $byStatus = $tasks->countBy('status');
        $hours = [
            'estimated' => (float) $tasks->where('status', '!=', 'cancelled')->sum('estimated_hours'),
            'remaining' => (float) $tasks->whereIn('status', ['pending', 'in_progress', 'overdue'])->sum('estimated_hours'),
        ];
        $history = \App\Models\ActivityLog::where('subject_type', 'project')->where('subject_id', $project->id)
            ->with('user:id,name')->latest('created_at')->limit(15)->get();

        return view('projects.show', compact('project', 'tasks', 'byStatus', 'hours', 'history'));
    }

    public function edit(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        $project->load('members:id');

        return view('projects.form', $this->formData($request, $project));
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        [$data, $members] = $this->validated($request, $project);
        $before = $project->members()->pluck('users.id')->all();
        DB::transaction(function () use ($project, $data, $members) {
            $project->update($data);
            $project->members()->sync($members);
        });
        $this->notifyMembers($project, array_diff($members, $before), $request);

        return redirect()->route('projects.show', $project)->with('success', __('Đã cập nhật dự án.'));
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index')->with('success', __('Đã xóa dự án :name.', ['name' => $project->name]).' '.__('Có thể khôi phục trong Thùng rác.'));
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', Project::class);
        $rows = $this->filtered($request)->orderBy('code')->limit(20000)->get()->map(fn (Project $p) => [
            $p->code, $p->name, $p->customer?->name, $p->manager?->name, label_of(Project::STATUSES, $p->status),
            $p->start_date ? vn_date($p->start_date) : '', $p->end_date ? vn_date($p->end_date) : '',
            $p->tasks_done_count.'/'.$p->tasks_active_count, $p->progress.'%', $p->members->count(), $p->budget,
        ]);
        \App\Services\ActivityLogger::log('exported', null, 'Xuất danh sách dự án ('.$rows->count().' dòng)');

        return $exporter->download('du_an', [__('Mã'), __('Tên dự án'), __('Khách hàng'), __('Người quản lý'), __('Trạng thái'), __('Ngày bắt đầu'), __('Ngày kết thúc'),
            __('Việc hoàn thành'), __('Tiến độ'), __('Số thành viên'), __('Ngân sách')], $rows, $request->input('format', 'xlsx'));
    }

    private function formData(Request $request, Project $project): array
    {
        return ['project' => $project, 'users' => Lookup::users(), 'customers' => Lookup::customers($request->user())];
    }

    /** @return array{0: array, 1: int[]} [dữ liệu dự án, id thành viên] */
    private function validated(Request $request, ?Project $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'customer_id' => ['nullable', 'integer'],
            'manager_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at'), TenantRule::member()],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'members' => ['nullable', 'array', 'max:100'],
            'members.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at'), TenantRule::member()],
        ], [], ['name' => __('Tên dự án'), 'manager_id' => __('Người quản lý'), 'end_date' => __('Ngày kết thúc'), 'members.*' => __('Thành viên')]);

        // Khách hàng phải nằm trong phạm vi người dùng được xem (giữ nguyên khách cũ khi không đổi)
        if (! empty($data['customer_id']) && (int) $data['customer_id'] !== (int) $existing?->customer_id) {
            $customer = Customer::find($data['customer_id']);
            if (! $customer || ! $request->user()->can('view', $customer)) {
                throw ValidationException::withMessages(['customer_id' => __('Khách hàng không hợp lệ.')]);
            }
        }
        $data['customer_id'] = $data['customer_id'] ?? null;
        $members = array_values(array_unique(array_map('intval', $data['members'] ?? [])));
        unset($data['members']);

        return [$data, $members];
    }

    private function notifyMembers(Project $project, array $userIds, Request $request): void
    {
        foreach (\App\Models\User::whereIn('id', $userIds)->where('id', '!=', $request->user()->id)->get() as $u) {
            CrmNotification::send($u, 'project_member', __(':name đã thêm bạn vào dự án ":project".', ['name' => $request->user()->name, 'project' => $project->name]),
                route('projects.show', $project), 'bi-kanban');
        }
    }
}
