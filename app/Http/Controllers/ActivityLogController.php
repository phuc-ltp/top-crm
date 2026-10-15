<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\Lookup;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('activity_logs.view'), 403);
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $logs = ActivityLog::with('user:id,name,avatar')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->subject_type))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', $this->like($request->q)))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest('created_at')->latest('id')
            ->paginate(30)->withQueryString();

        $types = ActivityLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type');

        return view('activity-logs.index', ['logs' => $logs, 'users' => Lookup::users(false), 'types' => $types]);
    }

    public function show(Request $request, ActivityLog $log)
    {
        abort_unless($request->user()->can('activity_logs.view'), 403);
        $log->load('user');

        return view('activity-logs.show', compact('log'));
    }
}
