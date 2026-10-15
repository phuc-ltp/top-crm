<div class="card">
    <div class="card-header"><span><i class="bi bi-cash-coin me-2 text-orange"></i>{{ __('Thanh toán (:count)', ['count' => $doc->payments->count()]) }}</span></div>
    <div class="card-body">
        @if ($doc->status === 'issued' && $doc->payment_status !== 'paid')
            @can('update', $doc)
                <form method="POST" action="{{ route('payments.store', $doc) }}" class="row g-2 mb-4 p-3 bg-light rounded-3 needs-validation" novalidate>
                    @csrf
                    <div class="col-md-3"><label class="form-label small" for="amount">{{ __('Số tiền (₫)') }}</label><input type="number" name="amount" id="amount" class="form-control form-control-sm" required min="1" max="{{ (int) ceil($doc->balance) }}" value="{{ (int) $doc->balance }}"></div>
                    <div class="col-md-3"><label class="form-label small" for="payment_date">{{ __('Ngày') }}</label><input type="date" name="payment_date" id="payment_date" class="form-control form-control-sm" required max="{{ today()->format('Y-m-d') }}" value="{{ today()->format('Y-m-d') }}"></div>
                    <div class="col-md-3"><label class="form-label small" for="method">{{ __('Phương thức') }}</label>
                        <select name="method" id="method" class="form-select form-select-sm">@foreach (App\Models\Payment::METHODS as $k => $m)<option value="{{ $k }}">{{ __($m) }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label small" for="reference">{{ __('Mã giao dịch') }}</label><input type="text" name="reference" id="reference" class="form-control form-control-sm" maxlength="100"></div>
                    <div class="col-md-9"><input type="text" name="notes" class="form-control form-control-sm" placeholder="{{ __('Ghi chú') }}" maxlength="500" aria-label="{{ __('Ghi chú') }}"></div>
                    <div class="col-md-3"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg"></i> {{ __('Ghi nhận') }}</button></div>
                </form>
            @endcan
        @elseif ($doc->status === 'draft')
            <div class="alert alert-info small"><i class="bi bi-info-circle"></i> {{ __('Hóa đơn đang ở trạng thái nháp. Hãy') }} <strong>{{ __('Phát hành') }}</strong> {{ __('để ghi nhận thanh toán.') }}</div>
        @endif

        @if ($doc->payments->isEmpty())
            <div class="text-muted small text-center py-3">{{ __('Chưa có thanh toán nào.') }}</div>
        @else
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>{{ __('Ngày') }}</th><th class="text-end">{{ __('Số tiền') }}</th><th>{{ __('Phương thức') }}</th><th>{{ __('Mã GD') }}</th><th>{{ __('Người ghi nhận') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($doc->payments as $p)
                    <tr><td>{{ vn_date($p->payment_date) }}</td><td class="text-end fw-semibold text-success">{{ money($p->amount) }}</td>
                        <td>{{ __(App\Models\Payment::METHODS[$p->method]?? $p->method) }}</td><td class="small">{{ $p->reference ?? '—' }}</td>
                        <td class="small">{{ $p->recorder?->name }}</td>
                        <td class="text-end">@can('invoices.delete')<x-delete-button :action="route('payments.destroy', $p)" :message="__('Xóa khoản thanh toán này? Số đã thu sẽ được tính lại.')" />@endcan</td></tr>
                @endforeach
                </tbody></table></div>
        @endif
    </div>
</div>
