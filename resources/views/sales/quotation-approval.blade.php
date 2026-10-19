{{-- Duyệt chiết khấu + lưu làm mẫu (bước 3.4) --}}
@php
    $threshold = App\Services\QuotationApprovalService::threshold();
    $pct = App\Services\QuotationApprovalService::discountPercent($doc);
@endphp
@if ($doc->approval_status || auth()->user()->can('quotations.approve'))
    <div class="card mb-4">
        <div class="card-header"><span><i class="bi bi-shield-check me-1"></i>{{ __('Duyệt chiết khấu') }}</span>
            <span class="small text-muted">{{ __('Tổng chiết khấu') }}: <strong>{{ $pct !== null ? rtrim(rtrim(number_format($pct, 2, ',', '.'), '0'), ',').'%' : '—' }}</strong>
                · {{ __('Ngưỡng') }}: <strong>{{ $threshold !== null ? (float) $threshold.'%' : __('tắt') }}</strong></span></div>
        <div class="card-body">
            @error('status')<div class="alert alert-danger small">{{ $message }}</div>@enderror
            @if ($doc->approval_status === 'pending')
                <p class="small mb-2">{{ __('Báo giá đang chờ duyệt — chưa gửi khách / tạo đơn hàng được.') }}</p>
                @can('quotations.approve')
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <form method="POST" action="{{ route('quotations.approve', $doc) }}">@csrf<button class="btn btn-success btn-sm"><i class="bi bi-check2 me-1"></i>{{ __('Duyệt') }}</button></form>
                        <form method="POST" action="{{ route('quotations.reject', $doc) }}" class="d-flex gap-2 flex-grow-1">@csrf
                            <input name="note" class="form-control form-control-sm" maxlength="500" required placeholder="{{ __('Lý do từ chối') }}" aria-label="{{ __('Lý do từ chối') }}">
                            <button class="btn btn-light btn-sm text-danger text-nowrap"><i class="bi bi-x-lg me-1"></i>{{ __('Từ chối') }}</button></form>
                    </div>
                @endcan
            @elseif ($doc->approval_status)
                <p class="small mb-0">{!! badge(App\Models\Quotation::APPROVAL_STATUSES, $doc->approval_status) !!} {{ $doc->approver?->name }} · {{ vn_date($doc->approved_at, true) }}@if ($doc->approval_note) — {{ $doc->approval_note }}@endif</p>
            @else
                <p class="small text-muted mb-0">{{ __('Báo giá không cần duyệt.') }}</p>
            @endif

            @can('quotations.approve')
                <hr>
                <form method="POST" action="{{ route('quotation-templates.store') }}" class="d-flex gap-2">@csrf
                    <input type="hidden" name="quotation_id" value="{{ $doc->id }}">
                    <input name="name" class="form-control form-control-sm" maxlength="150" required placeholder="{{ __('Tên mẫu, VD: Gói triển khai CRM chuẩn') }}" aria-label="{{ __('Tên mẫu') }}">
                    <button class="btn btn-soft-primary btn-sm text-nowrap"><i class="bi bi-files me-1"></i>{{ __('Lưu làm mẫu') }}</button>
                </form>
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @endcan
        </div>
    </div>
@endif
