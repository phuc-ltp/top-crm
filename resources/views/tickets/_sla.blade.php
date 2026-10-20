{{-- Huy hiệu tình trạng SLA. $state: none | pending | paused | met | breached; $due: hạn (Carbon|null) --}}
@php($map = ['pending' => ['Còn hạn', 'info'], 'paused' => ['Tạm dừng (chờ khách)', 'warning'], 'met' => ['Đạt', 'success'], 'breached' => ['Vi phạm', 'danger']])
@if ($state === 'none')
    <span class="text-muted">—</span>
@else
    <span class="badge badge-soft-{{ $map[$state][1] }}">{{ __($map[$state][0]) }}</span>
    @if ($due && in_array($state, ['pending', 'breached'], true))
        <span class="small {{ $state === 'breached' ? 'text-danger' : 'text-muted' }}" title="{{ vn_date($due, true) }}">{{ $state === 'breached' ? __('quá :t', ['t' => $due->diffForHumans(null, true)]) : __('còn :t', ['t' => $due->diffForHumans(null, true)]) }}</span>
    @endif
@endif
