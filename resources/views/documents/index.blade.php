@extends('layouts.app')
@section('title', __('Tài liệu'))
@section('content')
    <x-page-header :title="__('Tài liệu')" :breadcrumbs="['Tài liệu' => null]" :subtitle="__('Hợp đồng, báo giá, quy định... lưu an toàn, có phiên bản và phân quyền xem')">
        @can('documents.create')<a href="{{ route('documents.create', request()->only('related_type', 'related_id')) }}" class="btn btn-primary"><i class="bi bi-upload me-1"></i>{{ __('Tải lên tài liệu') }}</a>@endcan
    </x-page-header>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên tài liệu, mô tả, tên tệp...') }}"></div>
            <select name="category" class="form-select" aria-label="{{ __('Danh mục') }}"><option value="">{{ __('Mọi danh mục') }}</option>
                @foreach (App\Models\Document::CATEGORIES as $k => [$l])<option value="{{ $k }}" @selected(request('category') === $k)>{{ __($l) }}</option>@endforeach</select>
            <select name="visibility" class="form-select" aria-label="{{ __('Phạm vi xem') }}"><option value="">{{ __('Mọi phạm vi') }}</option>
                @foreach (App\Models\Document::VISIBILITIES as $k => [$l])<option value="{{ $k }}" @selected(request('visibility') === $k)>{{ __($l) }}</option>@endforeach</select>
            @if (request('related_type'))<input type="hidden" name="related_type" value="{{ request('related_type') }}"><input type="hidden" name="related_id" value="{{ request('related_id') }}">@endif
            <div class="form-check ms-1"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" @checked(request('mine') === '1') onchange="this.form.requestSubmit()"><label class="form-check-label small" for="mine">{{ __('Tài liệu của tôi') }}</label></div>
            @if (request()->hasAny(['q', 'category', 'visibility', 'related_type', 'mine']))<a href="{{ route('documents.index') }}" class="btn btn-link text-muted">{{ __('Xóa lọc') }}</a>@endif
        </form>
        @if ($documents->isEmpty())
            <x-empty-state icon="bi-folder2-open" :title="__('Chưa có tài liệu')" />
        @else
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>{{ __('Tài liệu') }}</th><th>{{ __('Danh mục') }}</th><th>{{ __('Liên quan') }}</th><th>{{ __('Phạm vi xem') }}</th><th>{{ __('Phiên bản') }}</th><th>{{ __('Cập nhật') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                <tbody>
                @foreach ($documents as $d)
                    @php($v = $d->latestVersion)
                    <tr>
                        <td><div class="d-flex align-items-center gap-2"><i class="bi {{ App\Models\Document::CATEGORIES[$d->category][1] ?? 'bi-file-earmark' }} fs-5 text-orange"></i>
                            <div class="min-w-0"><a href="{{ route('documents.show', $d) }}" class="row-link">{{ $d->title }}</a>
                                @if ($v)<div class="small text-muted text-truncate" style="max-width: 320px">{{ $v->original_name }} · {{ $v->human_size }}</div>@endif</div></div></td>
                        <td class="small">{{ label_of(App\Models\Document::CATEGORIES, $d->category) }}</td>
                        <td class="small">@if ($d->relatedUrl())<a href="{{ $d->relatedUrl() }}">{{ $d->related?->name ?? $d->related?->title ?? '—' }}</a>@else — @endif</td>
                        <td>{!! badge(App\Models\Document::VISIBILITIES, $d->visibility) !!}@if ($d->visibility === 'team' && $d->team)<div class="small text-muted">{{ $d->team->name }}</div>@endif</td>
                        <td class="small">v{{ $v?->version ?? 0 }}</td>
                        <td class="small text-muted">{{ vn_date($d->updated_at, true) }}<div>{{ $d->owner?->name }}</div></td>
                        <td class="table-actions">
                            @if ($v)
                                @if ($v->isPreviewable())<a href="{{ route('documents.preview', $d) }}" target="_blank" rel="noopener" class="btn btn-light btn-icon btn-sm" title="{{ __('Xem trước') }}" aria-label="{{ __('Xem trước') }}"><i class="bi bi-eye"></i></a>@endif
                                <a href="{{ route('documents.download', $d) }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Tải về') }}" aria-label="{{ __('Tải về') }}"><i class="bi bi-download"></i></a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
            <x-pagination-bar :paginator="$documents" />
        @endif
    </div>
@endsection
