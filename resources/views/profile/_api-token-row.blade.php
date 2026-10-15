<tr class="{{ $t->isExpired() ? 'opacity-50' : '' }}">
    <td>
        <div class="fw-semibold">{{ $t->name }}</div>
        @if ($showOwner)<div class="small-xs text-muted">{{ $t->tokenable?->name ?? __('Tài khoản đã xóa') }} · {{ $t->tokenable?->email }}</div>@endif
        <div class="small-xs text-muted">{{ __('Tạo lúc :time', ['time' => vn_date($t->created_at, true)]) }}</div>
    </td>
    <td class="small">
        @foreach ((array) $t->abilities as $ability)<code class="d-inline-block me-1">{{ $ability }}</code>@endforeach
    </td>
    <td class="small text-nowrap">
        {{ $t->last_used_at ? vn_date($t->last_used_at, true) : __('Chưa dùng') }}
        @if ($t->last_used_ip)<div class="small-xs text-muted">IP {{ $t->last_used_ip }}</div>@endif
    </td>
    <td class="small text-nowrap">
        @if ($t->isExpired())
            <span class="badge badge-soft-secondary">{{ __('Đã hết hạn') }}</span>
        @else
            {{ $t->expires_at ? vn_date($t->expires_at) : '—' }}
        @endif
    </td>
    <td class="text-end">
        <form method="POST" action="{{ route('api-tokens.destroy', $t->id) }}" data-confirm="{{ __('Thu hồi khóa “:name”? Ứng dụng đang dùng khóa này sẽ bị từ chối ngay.', ['name' => $t->name]) }}">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger text-nowrap" title="{{ __('Thu hồi') }}"><i class="bi bi-x-circle me-1"></i>{{ __('Thu hồi') }}</button>
        </form>
    </td>
</tr>
