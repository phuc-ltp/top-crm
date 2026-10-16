@extends('layouts.app')
@section('title', __('Thùng rác'))
@section('content')
    <x-page-header :title="__('Thùng rác')" :breadcrumbs="['Thùng rác' => null]"
                   :subtitle="__('Bản ghi đã xóa trong phạm vi bạn được xem. Có thể khôi phục bất kỳ lúc nào — dữ liệu không bị xóa vĩnh viễn.')" />

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card"><div class="list-group list-group-flush">
                @foreach ($counts as $key => $count)
                    <a href="{{ route('trash.index', ['type' => $key]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $type === $key ? 'active' : '' }}">
                        <span><i class="bi {{ $types[$key]['icon'] }} me-2"></i>{{ __($types[$key]['label']) }}</span>
                        <span class="badge {{ $type === $key ? 'bg-light text-dark' : 'badge-soft-secondary' }}">{{ $count }}</span>
                    </a>
                @endforeach
            </div></div>
        </div>
        <div class="col-lg-9">
            <div class="card">
                @if ($items->isEmpty())
                    <x-empty-state icon="bi-trash3" :title="__('Thùng rác trống')" :message="__('Không có :type nào đã xóa.', ['type' => mb_strtolower(__($types[$type]['label']))])" />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>{{ __('Tên') }}</th><th>{{ __('Ngày xóa') }}</th><th>{{ __('Người xóa') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr data-trash-row="{{ $item->id }}">
                                        <td class="min-w-0">
                                            <div class="fw-semibold text-truncate" style="max-width: 340px">{{ \App\Http\Controllers\TrashController::titleOf($item) }}</div>
                                            @if ($item->code ?? $item->sku ?? null)<div class="small text-muted">{{ $item->code ?? $item->sku }}</div>@endif
                                        </td>
                                        <td class="text-nowrap small">{{ vn_date($item->deleted_at, true) }}</td>
                                        <td class="small">{{ $deleters[$item->id]?->user?->name ?? '—' }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('trash.restore', [$type, $item->id]) }}" class="d-inline">@csrf
                                                <button class="btn btn-soft-primary btn-sm text-nowrap"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Khôi phục') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-pagination-bar :paginator="$items" />
                @endif
            </div>
        </div>
    </div>
@endsection
