@props(['paginator'])
@if ($paginator->total() > 0)
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3 border-top">
        <div class="small text-muted">{{ __('Hiển thị :from–:to trên tổng :total bản ghi', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => number_format($paginator->total(), 0, ',', '.')]) }}</div>
        {{ $paginator->onEachSide(1)->links() }}
    </div>
@endif
