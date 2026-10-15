@if ($task->related)
    @php($routes = ['customer' => 'customers.show', 'lead' => 'leads.show', 'opportunity' => 'opportunities.show'])
    <span class="text-muted">{{ App\Models\Task::RELATED_TYPES[$task->related_type] ?? '' }}:</span>
    <a href="{{ route($routes[$task->related_type], $task->related) }}">{{ $task->related->name ?? $task->related->title }}</a>
@else
    —
@endif
