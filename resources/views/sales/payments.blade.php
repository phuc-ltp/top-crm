@php($due = App\Services\PaymentService::due($doc))
<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>{{ __('Hóa đơn trong CRM là chứng từ nội bộ để theo dõi công nợ — không phải hóa đơn điện tử hợp lệ theo quy định thuế Việt Nam.') }}</div>
<div class="card mb-4">
    <div class="card-header"><span><i class="bi bi-cash-coin me-2 text-orange"></i>{{ __('Thanh toán (:count)', ['count' => $doc->payments->count()]) }}</span></div>
    <div class="card-body">
        @error('amount')<div class="alert alert-danger small">{{ $message }}</div>@enderror
        @error('payment')<div class="alert alert-danger small">{{ $message }}</div>@enderror
        @if ($doc->status === 'issued')
            @can('update', $doc)
                @if ((float) $due > 0)
                    <form method="POST" action="{{ route('payments.store', $doc) }}" class="row g-2 mb-3 p-3 bg-light rounded-3 needs-validation" novalidate>
                        @csrf
                        <div class="col-12 small fw-semibold">{{ __('Ghi nhận thanh toán') }}</div>
                        <div class="col-md-3"><label class="form-label small" for="amount">{{ __('Số tiền (₫)') }}</label><input type="number" name="amount" id="amount" class="form-control form-control-sm" required min="1" max="{{ (int) ceil((float) $due) }}" value="{{ (int) $due }}"></div>
                        <div class="col-md-3"><label class="form-label small" for="payment_date">{{ __('Ngày') }}</label><input type="date" name="payment_date" id="payment_date" class="form-control form-control-sm" required max="{{ today()->format('Y-m-d') }}" value="{{ today()->format('Y-m-d') }}"></div>
                        <div class="col-md-3"><label class="form-label small" for="method">{{ __('Phương thức') }}</label>
                            <select name="method" id="method" class="form-select form-select-sm">@foreach (App\Models\Payment::METHODS as $k => $m)<option value="{{ $k }}">{{ __($m) }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label small" for="reference">{{ __('Mã giao dịch') }}</label><input type="text" name="reference" id="reference" class="form-control form-control-sm" maxlength="100"></div>
                        <div class="col-md-9"><input type="text" name="notes" class="form-control form-control-sm" placeholder="{{ __('Ghi chú') }}" maxlength="500" aria-label="{{ __('Ghi chú') }}"></div>
                        <div class="col-md-3"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg"></i> {{ __('Ghi nhận') }}</button></div>
                    </form>
                @endif
                <details class="mb-3">
                    <summary class="small fw-semibold text-orange">{{ __('Hoàn tiền / phiếu giảm trừ') }}</summary>
                    <div class="row g-3 mt-1">
                        @if ((float) $doc->amount_paid > 0)
                            <div class="col-lg-6"><form method="POST" action="{{ route('payments.refund', $doc) }}" class="p-3 bg-light rounded-3 needs-validation" novalidate>@csrf
                                <div class="small fw-semibold mb-2">{{ __('Hoàn tiền cho khách') }} <span class="text-muted fw-normal">({{ __('tối đa :amount', ['amount' => money($doc->amount_paid)]) }})</span></div>
                                <div class="row g-2">
                                    <div class="col-6"><input type="number" name="amount" class="form-control form-control-sm" required min="1" max="{{ (int) $doc->amount_paid }}" placeholder="{{ __('Số tiền (₫)') }}" aria-label="{{ __('Số tiền hoàn') }}"></div>
                                    <div class="col-6"><input type="date" name="payment_date" class="form-control form-control-sm" required max="{{ today()->format('Y-m-d') }}" value="{{ today()->format('Y-m-d') }}" aria-label="{{ __('Ngày') }}"></div>
                                    <div class="col-6"><select name="method" class="form-select form-select-sm" aria-label="{{ __('Phương thức') }}">@foreach (App\Models\Payment::METHODS as $k => $m)<option value="{{ $k }}">{{ __($m) }}</option>@endforeach</select></div>
                                    <div class="col-6"><input type="text" name="reference" class="form-control form-control-sm" maxlength="100" placeholder="{{ __('Mã giao dịch') }}" aria-label="{{ __('Mã giao dịch') }}"></div>
                                    <div class="col-12"><input type="text" name="notes" class="form-control form-control-sm" required maxlength="500" placeholder="{{ __('Lý do hoàn tiền') }}" aria-label="{{ __('Lý do hoàn tiền') }}"></div>
                                    <div class="col-12"><button class="btn btn-light btn-sm text-danger w-100"><i class="bi bi-arrow-return-left me-1"></i>{{ __('Ghi nhận hoàn tiền') }}</button></div>
                                </div>
                            </form></div>
                        @endif
                        @if ((float) $doc->total > (float) $doc->credited_amount)
                            <div class="col-lg-6"><form method="POST" action="{{ route('payments.credit', $doc) }}" class="p-3 bg-light rounded-3 needs-validation" novalidate>@csrf
                                <div class="small fw-semibold mb-2">{{ __('Lập phiếu giảm trừ') }} <span class="text-muted fw-normal">({{ __('tối đa :amount', ['amount' => money((float) $doc->total - (float) $doc->credited_amount)]) }})</span></div>
                                <div class="row g-2">
                                    <div class="col-6"><input type="number" name="amount" class="form-control form-control-sm" required min="1" max="{{ (int) ((float) $doc->total - (float) $doc->credited_amount) }}" placeholder="{{ __('Số tiền (₫)') }}" aria-label="{{ __('Giá trị giảm trừ') }}"></div>
                                    <div class="col-6"><input type="date" name="issue_date" class="form-control form-control-sm" required max="{{ today()->format('Y-m-d') }}" value="{{ today()->format('Y-m-d') }}" aria-label="{{ __('Ngày') }}"></div>
                                    <div class="col-12"><input type="text" name="reason" class="form-control form-control-sm" required maxlength="500" placeholder="{{ __('Lý do giảm trừ (VD: trả hàng, giảm giá sau bán)') }}" aria-label="{{ __('Lý do giảm trừ') }}"></div>
                                    <div class="col-12"><button class="btn btn-light btn-sm w-100"><i class="bi bi-receipt-cutoff me-1"></i>{{ __('Lập phiếu giảm trừ') }}</button></div>
                                </div>
                            </form></div>
                        @endif
                    </div>
                </details>
            @endcan
        @elseif ($doc->status === 'draft')
            <div class="alert alert-info small"><i class="bi bi-info-circle"></i> {{ __('Hóa đơn đang ở trạng thái nháp. Hãy') }} <strong>{{ __('Phát hành') }}</strong> {{ __('để ghi nhận thanh toán.') }}</div>
        @endif

        @if ($doc->payments->isEmpty())
            <div class="text-muted small text-center py-3">{{ __('Chưa có thanh toán nào.') }}</div>
        @else
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>{{ __('Ngày') }}</th><th class="text-end">{{ __('Số tiền') }}</th><th>{{ __('Phương thức') }}</th><th>{{ __('Mã GD') }}</th><th>{{ __('Người ghi nhận') }}</th><th>{{ __('Đối soát') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($doc->payments as $p)
                    <tr><td>{{ vn_date($p->payment_date) }}@if ($p->type === 'refund')<div><span class="badge badge-soft-danger">{{ __('Hoàn tiền') }}</span></div>@endif</td>
                        <td class="text-end fw-semibold {{ (float) $p->amount < 0 ? 'text-danger' : 'text-success' }}">{{ money($p->amount) }}</td>
                        <td>{{ __(App\Models\Payment::METHODS[$p->method] ?? $p->method) }}</td><td class="small">{{ $p->reference ?? '—' }}@if ($p->notes)<div class="text-muted">{{ $p->notes }}</div>@endif</td>
                        <td class="small">{{ $p->recorder?->name }}</td>
                        <td class="small">@if ($p->reconciled_at)<i class="bi bi-check2-circle text-success"></i> {{ vn_date($p->reconciled_at) }}@else<span class="text-muted">—</span>@endif</td>
                        <td class="text-end">@if (! $p->reconciled_at)@can('invoices.delete')<x-delete-button :action="route('payments.destroy', $p)" :message="__('Xóa khoản thanh toán này? Số đã thu sẽ được tính lại.')" />@endcan @endif</td></tr>
                @endforeach
                </tbody></table></div>
        @endif

        @if ($doc->creditNotes->isNotEmpty())
            <div class="small fw-semibold mt-3 mb-1">{{ __('Phiếu giảm trừ') }}</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Số phiếu') }}</th><th>{{ __('Ngày') }}</th><th class="text-end">{{ __('Giá trị') }}</th><th>{{ __('Lý do') }}</th><th>{{ __('Người lập') }}</th></tr></thead>
                <tbody>@foreach ($doc->creditNotes as $cn)<tr><td>{{ $cn->number }}</td><td>{{ vn_date($cn->issue_date) }}</td><td class="text-end text-danger">− {{ money($cn->amount) }}</td><td class="small">{{ $cn->reason }}</td><td class="small">{{ $cn->creator?->name }}</td></tr>@endforeach</tbody>
            </table></div>
        @endif
    </div>
</div>

@php($history = App\Models\ActivityLog::with('user:id,name')->where('subject_type', $doc->getMorphClass())->where('subject_id', $doc->id)->whereIn('action', ['payment', 'updated', 'created'])->latest('created_at')->limit(30)->get())
    @if ($history->isNotEmpty())
        <div class="card">
            <div class="card-header">{{ __('Nhật ký tài chính') }}</div>
            <ul class="list-group list-group-flush small">
                @foreach ($history as $log)
                    <li class="list-group-item d-flex justify-content-between gap-2"><span>{{ $log->description }} <span class="text-muted">— {{ $log->user?->name ?? __('Hệ thống') }}</span></span><span class="text-muted text-nowrap">{{ vn_date($log->created_at, true) }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
