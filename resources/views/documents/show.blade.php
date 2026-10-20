@extends('layouts.app')
@section('title', $document->title)
@section('content')
    @php($current = $document->versions->first())
    @php($canManage = $document->manageableBy(auth()->user()))
    <x-page-header :title="$document->title" :breadcrumbs="['Tài liệu' => route('documents.index'), 'Chi tiết' => null]">
        @if ($current)
            @if ($current->isPreviewable())<a href="{{ route('documents.preview', $document) }}" target="_blank" rel="noopener" class="btn btn-light"><i class="bi bi-eye me-1"></i>{{ __('Xem trước') }}</a>@endif
            <a href="{{ route('documents.download', $document) }}" class="btn btn-primary"><i class="bi bi-download me-1"></i>{{ __('Tải về') }}</a>
        @endif
        @if ($canManage)
            <a href="{{ route('documents.edit', $document) }}" class="btn btn-light"><i class="bi bi-pencil me-1"></i>{{ __('Chỉnh sửa') }}</a>
            <x-delete-button :action="route('documents.destroy', $document)" class="btn btn-light text-danger" :label="__('Xóa')" />
        @endif
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-8">
            @if ($document->description)<div class="card mb-4"><div class="card-body small" style="white-space: pre-line">{{ $document->description }}</div></div>@endif
            <div class="card">
                <div class="card-header">{{ __('Lịch sử phiên bản (:count)', ['count' => $document->versions->count()]) }}</div>
                <div class="table-responsive"><table class="table mb-0 align-middle">
                    <thead><tr><th>{{ __('Phiên bản') }}</th><th>{{ __('Tệp') }}</th><th>{{ __('Người tải lên') }}</th><th>{{ __('Thời điểm') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                    <tbody>
                    @foreach ($document->versions as $v)
                        <tr>
                            <td><span class="badge {{ $loop->first ? 'badge-soft-orange' : 'badge-soft-secondary' }}">v{{ $v->version }}</span>@if ($loop->first) <span class="small text-muted">{{ __('hiện tại') }}</span>@endif</td>
                            <td class="small">{{ $v->original_name }}<div class="text-muted">{{ $v->human_size }}@if ($v->note) · {{ $v->note }}@endif</div></td>
                            <td class="small">{{ $v->uploader?->name ?? '—' }}</td>
                            <td class="small text-muted">{{ vn_date($v->created_at, true) }}</td>
                            <td class="table-actions">
                                @if ($v->isPreviewable())<a href="{{ route('documents.preview', [$document, $v]) }}" target="_blank" rel="noopener" class="btn btn-light btn-icon btn-sm" title="{{ __('Xem trước') }}" aria-label="{{ __('Xem trước') }}"><i class="bi bi-eye"></i></a>@endif
                                <a href="{{ route('documents.download', [$document, $v]) }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Tải về') }}" aria-label="{{ __('Tải về') }}"><i class="bi bi-download"></i></a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                @if ($canManage)
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('documents.versions.store', $document) }}" enctype="multipart/form-data" class="row g-2 needs-validation" novalidate>@csrf
                            <div class="col-md-6"><input type="file" name="file" class="form-control form-control-sm @error('file') is-invalid @enderror" required aria-label="{{ __('Tệp phiên bản mới') }}">
                                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-4"><input type="text" name="note" class="form-control form-control-sm" maxlength="500" placeholder="{{ __('Ghi chú thay đổi (không bắt buộc)') }}" aria-label="{{ __('Ghi chú phiên bản') }}"></div>
                            <div class="col-md-2"><button class="btn btn-soft-primary btn-sm w-100"><i class="bi bi-upload me-1"></i>{{ __('Bản mới') }}</button></div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body">
                <dl class="detail-list">
                    <dt>{{ __('Danh mục') }}</dt><dd>{{ label_of(App\Models\Document::CATEGORIES, $document->category) }}</dd>
                    <dt>{{ __('Phạm vi xem') }}</dt><dd>{!! badge(App\Models\Document::VISIBILITIES, $document->visibility) !!}@if ($document->team) <span class="small">{{ $document->team->name }}</span>@endif</dd>
                    <dt>{{ __('Liên quan') }}</dt><dd>@if ($document->relatedUrl())<a href="{{ $document->relatedUrl() }}">{{ $document->related?->name ?? $document->related?->title }}</a>@else — @endif</dd>
                    <dt>{{ __('Người tải lên') }}</dt><dd>{{ $document->owner?->name ?? '—' }}</dd>
                    <dt>{{ __('Ngày tạo') }}</dt><dd>{{ vn_date($document->created_at, true) }}</dd>
                    @if ($current)<dt>SHA-256</dt><dd class="small text-break text-muted" title="{{ __('Mã kiểm tra tính toàn vẹn của tệp hiện tại') }}">{{ $current->sha256 }}</dd>@endif
                </dl>
            </div></div>
            <div class="card">
                <div class="card-header">{{ __('Lượt xem / tải gần đây') }}</div>
                <div class="card-body">
                    @forelse ($downloads as $log)
                        <div class="small py-1 border-bottom"><strong>{{ $log->user?->name ?? __('Hệ thống') }}</strong> <span class="text-muted">· {{ vn_date($log->created_at, true) }}</span><div class="text-muted">{{ $log->description }}</div></div>
                    @empty
                        <div class="small text-muted">{{ __('Chưa có ai tải tài liệu này.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
