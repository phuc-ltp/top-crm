<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use App\Models\Task;
use App\Services\CalendarService;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Lịch chung (bước 4.4): lịch hẹn / hoạt động đã lên lịch + hạn công việc, xem theo tháng hoặc tuần.
 * Mặc định: lịch của chính mình (phụ trách, tự tạo hoặc tham dự). Xem lịch người khác: chỉ người có quyền xem tất cả công việc.
 */
class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $request->validate(['view' => ['nullable', 'in:month,week'], 'date' => ['nullable', 'date'], 'user_id' => ['nullable', 'integer']]);
        $view = $request->input('view', 'month');
        $date = Carbon::parse($request->input('date', now()->toDateString()));
        [$start, $end] = $view === 'week'
            ? [$date->copy()->startOfWeek(Carbon::MONDAY), $date->copy()->endOfWeek(Carbon::SUNDAY)]
            : [$date->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY), $date->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)];

        // Xem lịch của ai: mặc định chính mình; người khác chỉ khi có quyền xem tất cả công việc
        $canPickUser = $user->can('tasks.view_all');
        $whoId = $canPickUser && $request->filled('user_id') ? $request->integer('user_id') : $user->id;
        abort_unless($whoId === $user->id || array_key_exists($whoId, Lookup::users()), 404);

        $activities = Interaction::query()->visibleTo($user)->with(['interactable', 'assignee:id,name', 'attendees:id,name'])
            ->whereNotNull('scheduled_at')->whereBetween('scheduled_at', [$start, $end->copy()->endOfDay()])
            ->where(fn ($q) => $q->where('assigned_to', $whoId)
                ->orWhere(fn ($w) => $w->whereNull('assigned_to')->where('user_id', $whoId))
                ->orWhereHas('attendees', fn ($a) => $a->where('users.id', $whoId)))
            ->orderBy('scheduled_at')->get();
        $tasks = Task::visibleTo($user)->where('assigned_to', $whoId)->whereNotNull('due_date')
            ->whereBetween('due_date', [$start, $end->copy()->endOfDay()])->orderBy('due_date')->get(['id', 'title', 'due_date', 'status', 'priority']);

        $events = collect();
        foreach ($activities as $a) {
            $events->push((object) [
                'kind' => 'activity', 'at' => $a->scheduled_at, 'end' => CalendarService::endOf($a), 'title' => $a->subject ?: label_of(Interaction::TYPES, $a->type),
                'icon' => Interaction::TYPES[$a->type][1] ?? 'bi-calendar', 'url' => $a->subjectUrl(), 'done' => ! $a->isPlanned(),
                'late' => $a->isOverdue(), 'meta' => $a->subjectLabel(), 'location' => $a->location, 'model' => $a,
            ]);
        }
        foreach ($tasks as $t) {
            $events->push((object) [
                'kind' => 'task', 'at' => $t->due_date, 'end' => null, 'title' => $t->title, 'icon' => 'bi-check2-square',
                'url' => route('tasks.show', $t), 'done' => in_array($t->status, ['completed', 'cancelled'], true), 'late' => $t->is_late,
                'meta' => __('Hạn công việc'), 'location' => null, 'model' => $t,
            ]);
        }
        $byDay = $events->sortBy(fn ($e) => $e->at->timestamp)->groupBy(fn ($e) => $e->at->copy()->timezone(crm_timezone())->format('Y-m-d'));

        return view('calendar.index', compact('view', 'date', 'start', 'end', 'byDay', 'whoId', 'canPickUser') + ['users' => Lookup::users()]);
    }

    /** Tải tệp .ics của một lịch hẹn (chỉ người được xem hoạt động). */
    public function ics(Request $request, Interaction $interaction, CalendarService $calendar)
    {
        abort_unless(Interaction::query()->visibleTo($request->user())->whereKey($interaction->id)->exists() || $interaction->attendees()->whereKey($request->user()->id)->exists(), 404);
        abort_unless($interaction->scheduled_at, 404);

        return response($calendar->ics($interaction, $interaction->user?->name ?? config('app.name')), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="lich-hen-'.$interaction->id.'.ics"',
        ]);
    }
}
