<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Danh sách hoạt động (lịch hẹn, cuộc gọi, việc theo dõi...) — chỉ những hoạt động người dùng được xem. */
class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'view' => ['nullable', Rule::in(['mine', 'all'])],
            'status' => ['nullable', Rule::in(['overdue', 'today', 'upcoming', 'planned', 'done'])],
            'type' => ['nullable', Rule::in(array_keys(Interaction::TYPES))],
            'subject_type' => ['nullable', Rule::in(array_keys(Interaction::SUBJECTS))],
            'assigned_to' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $view = $request->input('view', 'mine');
        $status = $request->input('status', $view === 'mine' ? 'planned' : null);

        $base = Interaction::query()->visibleTo($user)->with(['interactable', 'assignee:id,name', 'user:id,name'])
            ->when($view === 'mine', fn ($q) => $q->ownedBy($user));

        $activities = (clone $base)
            ->when($status === 'overdue', fn ($q) => $q->overdue())
            ->when($status === 'today', fn ($q) => $q->planned()->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()]))
            ->when($status === 'upcoming', fn ($q) => $q->planned()->where('scheduled_at', '>=', now()))
            ->when($status === 'planned', fn ($q) => $q->planned())
            ->when($status === 'done', fn ($q) => $q->where('status', 'done'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('interactable_type', $request->subject_type))
            ->when($view === 'all' && $request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('subject', 'like', $term)->orWhere('content', 'like', $term));
            });

        // Việc đã lên lịch: gần nhất trước (quá hạn lên đầu); việc đã xong: mới nhất trước
        $activities = in_array($status, ['overdue', 'today', 'upcoming', 'planned'], true)
            ? $activities->orderBy('scheduled_at')
            : $activities->orderByRaw('COALESCE(scheduled_at, occurred_at, created_at) DESC');

        $mine = Interaction::query()->visibleTo($user)->ownedBy($user)->planned();
        $counts = [
            'overdue' => (clone $mine)->where('scheduled_at', '<', now())->count(),
            'today' => (clone $mine)->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'week' => (clone $mine)->whereBetween('scheduled_at', [now(), now()->addDays(7)->endOfDay()])->count(),
            'done' => Interaction::query()->visibleTo($user)->ownedBy($user)->where('status', 'done')->where('completed_at', '>=', now()->subDays(30))->count(),
        ];

        return view('activities.index', [
            'activities' => $activities->paginate($this->perPage($request))->withQueryString(),
            'counts' => $counts,
            'view' => $view,
            'status' => $status,
            'users' => Lookup::users(),
        ]);
    }
}
