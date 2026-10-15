@extends('layouts.app')
@section('title', 'Nhập khách hàng từ CSV')
@section('content')
    <x-page-header title="Nhập khách hàng từ CSV" :breadcrumbs="['Khách hàng' => route('customers.index'), 'Nhập CSV' => null]" />

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Tải tệp lên</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('customers.import.store') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
                        @csrf
                        <x-input name="file" type="file" label="Tệp CSV" accept=".csv,text/csv" required help="Tối đa 5MB, 5.000 dòng. Mã hóa UTF-8. Phân cách bằng dấu phẩy hoặc chấm phẩy." />
                        <button class="btn btn-primary" data-loading-text="Đang nhập..."><i class="bi bi-upload me-1"></i>Nhập dữ liệu</button>
                        <a href="{{ route('customers.import.template') }}" class="btn btn-light"><i class="bi bi-download me-1"></i>Tải tệp mẫu</a>
                    </form>
                </div>
            </div>

            @if ($result = session('import_result'))
                <div class="card mt-4">
                    <div class="card-header">Kết quả nhập</div>
                    <div class="card-body">
                        <p><span class="badge badge-soft-success">{{ $result['created'] }} đã nhập</span> <span class="badge badge-soft-warning">{{ $result['skipped'] }} bỏ qua</span></p>
                        @if (!empty($result['errors']))
                            <ul class="small text-danger mb-0">@foreach ($result['errors'] as $err)<li>{{ $err }}</li>@endforeach</ul>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Hướng dẫn</div>
                <div class="card-body small">
                    <p>Dòng đầu tiên là tiêu đề cột. Các cột hỗ trợ:</p>
                    <table class="table table-sm">
                        <tr><td><code>name</code></td><td>Tên khách hàng (bắt buộc)</td></tr>
                        <tr><td><code>phone</code></td><td>Số điện thoại</td></tr>
                        <tr><td><code>email</code></td><td>Email</td></tr>
                        <tr><td><code>address</code></td><td>Địa chỉ</td></tr>
                        <tr><td><code>company</code></td><td>Công ty</td></tr>
                        <tr><td><code>type</code></td><td><code>individual</code> hoặc <code>business</code></td></tr>
                        <tr><td><code>source</code></td><td>Tên nguồn (VD: Website, Facebook)</td></tr>
                        <tr><td><code>status</code></td><td><code>active</code>, <code>potential</code>, <code>vip</code>, <code>inactive</code></td></tr>
                    </table>
                    <p class="mb-0 text-muted">Dòng trùng email/điện thoại với khách hàng đã có sẽ bị bỏ qua. Khách hàng nhập vào sẽ do bạn phụ trách.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
