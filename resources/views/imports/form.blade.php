@extends('layouts.app')
@section('title', __($m['title']))
@section('content')
    <x-page-header :title="__($m['title'])" :breadcrumbs="[$m['crumb'] => route($m['key'].'.index'), 'Nhập dữ liệu' => null]" />

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">{{ __('Tải tệp lên') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route($m['key'].'.import.store') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
                        @csrf
                        <x-input name="file" type="file" :label="__('Tệp CSV hoặc Excel (.xlsx)')" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
                                 :help="__('Tối đa 5MB, 5.000 dòng. CSV mã hóa UTF-8, phân cách bằng dấu phẩy hoặc chấm phẩy. Excel: lấy trang tính đầu tiên.')" />
                        <button class="btn btn-primary" data-loading-text="{{ __('Đang nhập...') }}"><i class="bi bi-upload me-1"></i>{{ __('Nhập dữ liệu') }}</button>
                        <a href="{{ route($m['key'].'.import.template') }}" class="btn btn-light"><i class="bi bi-download me-1"></i>{{ __('Tải tệp mẫu') }}</a>
                    </form>
                </div>
            </div>

            @if ($result = session('import_result'))
                <div class="card mt-4">
                    <div class="card-header">{{ __('Kết quả nhập') }}</div>
                    <div class="card-body">
                        <p><span class="badge badge-soft-success">{{ __(':count đã nhập', ['count' => $result['created']]) }}</span> <span class="badge badge-soft-warning">{{ __(':count bỏ qua', ['count' => $result['skipped']]) }}</span></p>
                        @if (! empty($result['errors']))
                            <ul class="small text-danger mb-0">@foreach ($result['errors'] as $err)<li>{{ $err }}</li>@endforeach</ul>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Hướng dẫn') }}</div>
                <div class="card-body small">
                    <p>{{ __('Dòng đầu tiên là tiêu đề cột (tiếng Việt hoặc tiếng Anh). Các cột hỗ trợ:') }}</p>
                    <table class="table table-sm">
                        @foreach ($columns as $key => $desc)
                            <tr><td><code>{{ $key }}</code></td><td>{{ $desc }}</td></tr>
                        @endforeach
                    </table>
                    <p class="mb-2 text-muted">{{ __($m['note']) }}</p>
                    <p class="mb-0 text-muted">{{ __('Cả tệp được lưu trong một lần: nếu có lỗi hệ thống giữa chừng thì không dòng nào được lưu.') }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
