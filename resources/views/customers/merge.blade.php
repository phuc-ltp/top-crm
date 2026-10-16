@extends('layouts.app')
@section('title', __('Gộp khách hàng trùng'))
@section('content')
    <x-page-header :title="__('Gộp khách hàng trùng')" :breadcrumbs="['Khách hàng' => route('customers.index'), $customer->name => route('customers.show', $customer), 'Gộp trùng' => null]"
                   :subtitle="__('Giữ lại :code — :name. Bản được chọn sẽ chuyển toàn bộ dữ liệu sang đây rồi được lưu trữ.', ['code' => $customer->code, 'name' => $customer->name])" />

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header"><span><i class="bi bi-lightbulb me-2 text-orange"></i>{{ __('Có thể trùng') }}</span></div>
                <div class="list-group list-group-flush">
                    @forelse ($suggestions as $s)
                        <a href="{{ route('customers.merge', [$customer, 'with' => $s->id]) }}" class="list-group-item list-group-item-action {{ $other?->id === $s->id ? 'active' : '' }}">
                            <div class="fw-semibold small">{{ $s->code }} — {{ $s->name }}</div>
                            <div class="small-xs {{ $other?->id === $s->id ? '' : 'text-muted' }}">{{ $s->phone ?? '—' }} · {{ $s->email ?? '—' }}</div>
                        </a>
                    @empty
                        <div class="p-3 small text-muted">{{ __('Không tìm thấy khách hàng trùng email, điện thoại hoặc tên.') }}</div>
                    @endforelse
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Tìm khách hàng khác') }}</div>
                <div class="card-body">
                    <form method="GET" class="input-group mb-2">
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tên, mã, email, điện thoại...') }}" aria-label="{{ __('Tìm khách hàng') }}">
                        <button class="btn btn-light"><i class="bi bi-search"></i></button>
                    </form>
                    @foreach ($results as $r)
                        <a href="{{ route('customers.merge', [$customer, 'with' => $r->id, 'q' => request('q')]) }}" class="d-block small py-1">{{ $r->code }} — {{ $r->name }} <span class="text-muted">· {{ $r->phone ?? $r->email }}</span></a>
                    @endforeach
                    @if (request()->filled('q') && $results->isEmpty())<div class="small text-muted">{{ __('Không có kết quả phù hợp.') }}</div>@endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @if (! $other)
                <div class="card"><x-empty-state icon="bi-intersect" :title="__('Chọn khách hàng cần gộp')" :message="__('Chọn một khách hàng ở cột bên trái để xem trước trước khi gộp.')" /></div>
            @else
                <div class="card">
                    <div class="card-header"><span><i class="bi bi-intersect me-2 text-orange"></i>{{ __('Xem trước khi gộp') }}</span></div>
                    <div class="card-body">
                        <div class="table-responsive mb-3">
                            <table class="table table-sm mb-0">
                                <thead><tr><th></th><th>{{ __('Giữ lại') }} · {{ $customer->code }}</th><th>{{ __('Gộp vào và lưu trữ') }} · {{ $other->code }}</th></tr></thead>
                                <tbody>
                                @foreach (['name' => 'Tên khách hàng', 'phone' => 'Điện thoại', 'email' => 'Email', 'company' => 'Công ty', 'address' => 'Địa chỉ'] as $f => $label)
                                    <tr><td class="small text-muted">{{ __($label) }}</td>
                                        <td class="small">{{ $customer->{$f} ?: '—' }}</td>
                                        <td class="small">{{ $other->{$f} ?: '—' }}@if (blank($customer->{$f}) && filled($other->{$f}) && $f !== 'name')<span class="badge badge-soft-success ms-1">{{ __('sẽ bổ sung') }}</span>@endif</td></tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="fw-semibold small mb-2">{{ __('Dữ liệu sẽ chuyển sang :code', ['code' => $customer->code]) }}</div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach (['contacts' => 'Người liên hệ', 'opportunities' => 'Cơ hội', 'quotations' => 'Báo giá', 'sales_orders' => 'Đơn hàng', 'invoices' => 'Hóa đơn',
                                'support_tickets' => 'Ticket hỗ trợ', 'tasks' => 'Công việc', 'interactions' => 'Hoạt động', 'attachments' => 'Tệp đính kèm', 'tags' => 'Thẻ', 'leads' => 'Lead đã chuyển đổi'] as $k => $label)
                                <span class="badge badge-soft-{{ ($preview[$k] ?? 0) > 0 ? 'orange' : 'secondary' }}">{{ __($label) }}: {{ $preview[$k] ?? 0 }}</span>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('customers.merge.store', $customer) }}" class="needs-validation" novalidate
                              data-confirm="{{ __('Gộp :from vào :to? Thao tác này chuyển toàn bộ dữ liệu và không tự hoàn tác được.', ['from' => $other->code, 'to' => $customer->code]) }}">
                            @csrf
                            <input type="hidden" name="merge_id" value="{{ $other->id }}">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm" required>
                                <label class="form-check-label small" for="confirm">{{ __('Tôi đã kiểm tra: đây là cùng một khách hàng.') }}</label>
                                <div class="invalid-feedback">{{ __('Vui lòng xác nhận trước khi gộp.') }}</div>
                            </div>
                            <button class="btn btn-primary"><i class="bi bi-intersect me-1"></i>{{ __('Gộp khách hàng') }}</button>
                            <a href="{{ route('customers.show', $customer) }}" class="btn btn-light">{{ __('Hủy') }}</a>
                        </form>
                        <p class="small text-muted mt-3 mb-0">{{ __('Bản bị gộp được lưu trữ (không xóa vĩnh viễn) và ghi nhật ký; ghi chú của nó được nối vào ghi chú của bản giữ lại.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
