@props(['paginator'])
@if ($paginator->total() > 0)
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3 border-top">
        <div class="small text-muted">Hiển thị {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} trên tổng {{ number_format($paginator->total(), 0, ',', '.') }} bản ghi</div>
        {{ $paginator->onEachSide(1)->links() }}
    </div>
@endif
