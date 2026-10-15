@php($dotTone = ['urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'neutral'])
@forelse ($schedule as $t)
    <a href="{{ route('tasks.show', $t) }}" class="sched-item tone-{{ $t->status === 'completed' ? 'success' : ($dotTone[$t->priority] ?? 'neutral') }}">
        <span class="sched-time">{{ $t->due_date->format('H:i') }}</span>
        <span class="sched-body">
            <span class="sched-title {{ $t->status === 'completed' ? 'text-decoration-line-through text-muted' : '' }}">{{ $t->title }}</span>
            <span class="sched-meta">{{ label_of(\App\Models\Task::PRIORITIES, $t->priority) }} · {{ label_of(\App\Models\Task::STATUSES, $t->status) }}</span>
        </span>
    </a>
@empty
    <x-empty-state icon="bi-calendar2-heart" title="Không có công việc nào trong ngày" />
@endforelse
