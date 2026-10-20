@foreach ($nodes as $u)
    <li>
        <div class="org-node">
            <x-avatar :user="$u" size="sm" />
            <div class="min-w-0"><a href="{{ route('employees.show', $u->id) }}" class="fw-semibold small">{{ $u->name }}</a>
                <div class="small text-muted text-truncate">{{ $u->employeeProfile?->job_title ?? '—' }}</div></div>
        </div>
        @if ($depth < 12 && $children->has($u->id))
            <ul>@include('employees._tree', ['nodes' => $children->get($u->id), 'children' => $children, 'depth' => $depth + 1])</ul>
        @endif
    </li>
@endforeach
