@extends('layouts.app')
@section('title', 'Design System')
@section('content')
    <x-page-header title="Design System" :breadcrumbs="['Hệ thống' => route('settings.edit'), 'Design System' => null]"
                   subtitle="Bộ thành phần giao diện dùng chung cho mọi module TOP CRM — dùng để thống nhất và duyệt thiết kế">
        <button class="btn btn-light" type="button" data-theme-toggle><i class="bi bi-circle-half me-1"></i>Đổi sáng / tối</button>
    </x-page-header>

    <div class="row g-4">
        {{-- Màu --}}
        <div class="col-xl-7">
            <div class="card h-100"><div class="card-body">
                <div class="ds-section-title">Bảng màu chủ đạo</div>
                <div class="row g-3 mb-4">
                    @foreach ([['rgba(34,24,18,.74)', 'Kính nâu tối', 'Sidebar'], ['#EA7A2B', 'Cam đồng', 'Hành động chính'], ['rgba(248,245,242,.80)', 'Kính kem', 'Cửa sổ làm việc'], ['#FFFFFF', 'Trắng', 'Thẻ / bảng'], ['#262422', 'Than ấm', 'Thẻ (chế độ tối)']] as [$hex, $name, $use])
                        <div class="col"><div class="ds-swatch" style="background: {{ $hex }}"></div>
                            <div class="small fw-semibold mt-2">{{ $hex }}</div><div class="ds-label">{{ $name }} · {{ $use }}</div></div>
                    @endforeach
                </div>
                <div class="ds-section-title">Màu trạng thái</div>
                <div class="row g-3">
                    @foreach ([['success', 'Thành công'], ['danger', 'Cảnh báo / lỗi'], ['warning', 'Chờ xử lý'], ['info', 'Thông tin'], ['purple', 'Phụ trợ']] as [$k, $name])
                        <div class="col"><div class="ds-swatch" style="background: var(--crm-{{ $k }}); height: 44px"></div>
                            <div class="ds-swatch mt-1" style="background: var(--crm-{{ $k }}-soft); height: 28px"></div>
                            <div class="ds-label mt-1">{{ $name }}</div></div>
                    @endforeach
                </div>
            </div></div>
        </div>

        {{-- Chữ & lưới --}}
        <div class="col-xl-5">
            <div class="card h-100"><div class="card-body">
                <div class="ds-section-title">Typography — Be Vietnam Pro</div>
                <div class="d-flex gap-4 align-items-center mb-3">
                    <div style="font-size: 4rem; font-weight: 700; line-height: 1">Aa</div>
                    <div>
                        <div class="fw-bold" style="font-size:1.5rem">Tiêu đề trang 24px</div>
                        <div class="fw-semibold" style="font-size:1.1rem">Tiêu đề thẻ 16px</div>
                        <div>Nội dung 14px — Quản lý khách hàng chuyên nghiệp</div>
                        <div class="small text-muted">Chú thích 12px · màu phụ</div>
                    </div>
                </div>
                <div class="ds-section-title mt-4">Khoảng cách (lưới 8px) & bo góc</div>
                <div class="d-flex align-items-end gap-2 mb-3">
                    @foreach ([8, 16, 24, 32, 48] as $sp)<div class="text-center"><div class="ds-space" style="width: {{ $sp }}px"></div><div class="ds-label">{{ $sp }}</div></div>@endforeach
                </div>
                <div class="d-flex gap-3">
                    @foreach ([['10px', 'Nút, ô nhập'], ['12px', 'Thành phần'], ['14px', 'Thẻ']] as [$r, $u])
                        <div class="text-center"><div style="width:56px;height:40px;border:2px solid var(--crm-primary);border-radius: {{ $r }}"></div><div class="ds-label">{{ $r }} · {{ $u }}</div></div>
                    @endforeach
                </div>
            </div></div>
        </div>

        {{-- Nút & badge --}}
        <div class="col-xl-6">
            <div class="card h-100"><div class="card-body">
                <div class="ds-section-title">Nút bấm</div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tạo mới</button>
                    <button class="btn btn-light">Lưu thay đổi</button>
                    <button class="btn btn-outline-primary">Nút viền</button>
                    <button class="btn btn-soft-primary">Nút nhạt</button>
                    <button class="btn btn-danger">Xóa</button>
                    <button class="btn btn-link">Hủy</button>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
                    <button class="btn btn-primary btn-sm">Nhỏ</button>
                    <button class="btn btn-primary btn-lg">Lớn</button>
                    <button class="btn btn-primary btn-icon"><i class="bi bi-gear"></i></button>
                    <button class="btn btn-light btn-icon"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-primary" disabled><span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...</button>
                </div>
                <div class="ds-section-title">Thẻ trạng thái (badge)</div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge badge-soft-success badge-dot">Thành công</span>
                    <span class="badge badge-soft-info badge-dot">Đang xử lý</span>
                    <span class="badge badge-soft-warning badge-dot">Chờ duyệt</span>
                    <span class="badge badge-soft-danger badge-dot">Đã hủy</span>
                    <span class="badge badge-soft-orange badge-dot">VIP</span>
                    <span class="badge badge-soft-purple badge-dot">Tiềm năng</span>
                    <span class="badge badge-soft-secondary badge-dot">Tạm dừng</span>
                    <x-trend :value="12.5" /> <x-trend :value="-4.2" /> <x-trend :value="null" />
                </div>
            </div></div>
        </div>

        {{-- Form --}}
        <div class="col-xl-6">
            <div class="card h-100"><div class="card-body">
                <div class="ds-section-title">Ô nhập & biểu mẫu</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="ds1">Ô nhập mặc định <span class="req">*</span></label><input id="ds1" class="form-control" placeholder="Nhập dữ liệu..."></div>
                    <div class="col-md-6"><label class="form-label" for="ds2">Danh sách chọn</label><select id="ds2" class="form-select"><option>Chọn...</option><option>Khách hàng doanh nghiệp</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="ds3">Ngày tháng</label><input id="ds3" type="date" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label" for="ds4">Tìm kiếm</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input id="ds4" class="form-control" placeholder="Tìm kiếm..."></div></div>
                    <div class="col-md-6"><label class="form-label" for="ds5">Ô báo lỗi</label><input id="ds5" class="form-control is-invalid" value="email-sai"><div class="invalid-feedback">Email không hợp lệ.</div></div>
                    <div class="col-md-6 d-flex flex-column gap-2 justify-content-end">
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="ds6" checked><label class="form-check-label" for="ds6">Ô đánh dấu</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="ds7" checked><label class="form-check-label" for="ds7">Công tắc bật/tắt</label></div>
                    </div>
                </div>
            </div></div>
        </div>

        {{-- Thẻ thống kê --}}
        <div class="col-12">
            <div class="ds-section-title">Thẻ thống kê (KPI)</div>
            <div class="row g-3">
                @foreach ([['bi-people-fill', 'green', 'Tổng khách hàng', '1.248', 12], ['bi-funnel-fill', 'orange', 'Leads mới', '320', 18], ['bi-kanban-fill', 'yellow', 'Cơ hội đang xử lý', '86', -5], ['bi-cash-stack', 'blue', 'Doanh thu tháng', '520.000.000 ₫', 25]] as [$icon, $tone, $label, $value, $trend])
                    <div class="col-sm-6 col-xl-3"><div class="card"><div class="stat-card">
                        <div class="stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></div>
                        <div><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div><div class="stat-sub"><x-trend :value="$trend" /> so với kỳ trước</div></div>
                    </div></div></div>
                @endforeach
            </div>
            <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Số liệu trên trang này chỉ là mẫu minh họa thành phần giao diện, không phải dữ liệu kinh doanh.</div>
        </div>

        {{-- Bảng --}}
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><span class="card-title">Bảng dữ liệu</span><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Thêm mới</button></div>
                <div class="filter-bar">
                    <div class="input-group search-input"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" placeholder="Tìm kiếm..." aria-label="Tìm kiếm"></div>
                    <select class="form-select" aria-label="Lọc"><option>Mọi trạng thái</option></select>
                    <button class="btn btn-soft-primary"><i class="bi bi-funnel me-1"></i>Lọc</button>
                </div>
                <div class="table-responsive"><table class="table table-hover">
                    <thead><tr><th>Mã</th><th>Khách hàng</th><th>Liên hệ</th><th class="text-end">Giá trị</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                    @foreach ([['KH00001', 'Công ty ABC', 'contact@abc.vn', '120.000.000 ₫', ['Khách hàng', 'success']], ['KH00002', 'Nguyễn Văn A', '0987 654 321', '45.000.000 ₫', ['Tiềm năng', 'warning']], ['KH00003', 'Công ty XYZ', 'xyz@company.vn', '300.000.000 ₫', ['VIP', 'orange']]] as [$code, $name, $contact, $value, [$st, $tone]])
                        <tr><td class="small text-muted">{{ $code }}</td><td><a href="#" class="row-link">{{ $name }}</a></td><td class="small">{{ $contact }}</td><td class="text-end fw-semibold">{{ $value }}</td>
                            <td><span class="badge badge-soft-{{ $tone }}">{{ $st }}</span></td>
                            <td class="table-actions"><button class="btn btn-light btn-icon btn-sm" aria-label="Xem"><i class="bi bi-eye"></i></button> <button class="btn btn-light btn-icon btn-sm" aria-label="Sửa"><i class="bi bi-pencil"></i></button></td></tr>
                    @endforeach
                    </tbody></table></div>
            </div>
        </div>

        {{-- Trạng thái rỗng, skeleton, thông báo --}}
        <div class="col-xl-4">
            <div class="card mb-4"><div class="card-body p-0"><x-empty-state icon="bi-inbox" title="Trạng thái rỗng" message="Hiển thị khi danh sách chưa có dữ liệu."><button class="btn btn-primary btn-sm">Thêm dữ liệu</button></x-empty-state></div></div>
            <div class="card"><div class="card-body">
                <div class="ds-section-title">Đang tải (skeleton) & thông báo</div>
                <div class="skeleton mb-2" style="height:14px"></div><div class="skeleton mb-2 w-75" style="height:14px"></div><div class="skeleton mb-3 w-50" style="height:14px"></div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-light btn-sm" onclick="toast('success','Đã lưu thành công.')">Toast thành công</button>
                    <button class="btn btn-light btn-sm" onclick="toast('error','Có lỗi xảy ra.')">Toast lỗi</button>
                    <button class="btn btn-light btn-sm" onclick="confirmDialog('Bạn chắc chắn muốn xóa?','Xóa')">Hộp xác nhận</button>
                </div>
            </div></div>
        </div>

        {{-- Icon --}}
        <div class="col-12">
            <div class="card"><div class="card-body">
                <div class="ds-section-title">Icon sử dụng (Bootstrap Icons)</div>
                <div class="d-flex flex-wrap gap-3 fs-4 text-muted">
                    @foreach (['house', 'people', 'funnel', 'kanban', 'bag-check', 'receipt', 'box-seam', 'check2-square', 'megaphone', 'headset', 'bar-chart-line', 'gear', 'bell', 'search', 'calendar3', 'file-earmark-text', 'cash-stack', 'person-badge', 'shield-lock', 'cloud-arrow-down'] as $ic)
                        <i class="bi bi-{{ $ic }}" title="{{ $ic }}"></i>
                    @endforeach
                </div>
            </div></div>
        </div>
    </div>
@endsection
