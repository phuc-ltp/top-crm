@extends('layouts.app')
@section('title', __('Bảng lương :p', ['p' => $period->label]))
@section('content')
    @php($manage = auth()->user()->can('payroll.manage'))
    <x-page-header :title="__('Bảng lương :p', ['p' => $period->label])" :breadcrumbs="['Bảng lương' => route('payroll.index'), $period->label => null]"
                   :subtitle="$period->calculated_at ? __('Tính lần cuối: :t', ['t' => vn_date($period->calculated_at, true)]) : null">
        <span class="align-self-center">{!! badge(App\Models\PayrollPeriod::STATUSES, $period->status) !!}</span>
        <a href="{{ route('payroll.export', $period) }}" class="btn btn-light"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('Xuất Excel') }}</a>
        @if ($manage)
            @if ($period->status === 'draft')
                <form method="POST" action="{{ route('payroll.recalculate', $period) }}" class="d-inline">@csrf<button class="btn btn-light"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Tính lại') }}</button></form>
                <form method="POST" action="{{ route('payroll.transition', [$period, 'submit']) }}" class="d-inline">@csrf<button class="btn btn-primary">{{ __('Gửi duyệt') }}</button></form>
            @elseif ($period->status === 'submitted')
                <form method="POST" action="{{ route('payroll.transition', [$period, 'reopen']) }}" class="d-inline">@csrf<button class="btn btn-light">{{ __('Trả về nháp') }}</button></form>
                <form method="POST" action="{{ route('payroll.transition', [$period, 'approve']) }}" class="d-inline" data-confirm="{{ __('Duyệt bảng lương? Sau khi duyệt, số liệu bị khóa và nhân viên xem được phiếu lương.') }}" data-confirm-button="{{ __('Duyệt') }}">@csrf<button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>{{ __('Duyệt') }}</button></form>
            @elseif ($period->status === 'approved')
                <form method="POST" action="{{ route('payroll.transition', [$period, 'pay']) }}" class="d-inline" data-confirm="{{ __('Xác nhận đã chuyển lương cho nhân viên?') }}" data-confirm-button="{{ __('Đã trả') }}">@csrf<button class="btn btn-success"><i class="bi bi-cash-coin me-1"></i>{{ __('Đánh dấu đã trả') }}</button></form>
            @endif
        @endif
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-people" tone="blue" :label="__('Số phiếu lương')" :value="$totals['count']" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-wallet2" :label="__('Tổng thu nhập')" :value="money($totals['gross'])" :sub="__('Tăng ca: :a', ['a' => money($totals['ot'])])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-shield-check" tone="purple" :label="__('Bảo hiểm + thuế TNCN')" :value="money(App\Support\Decimal::add($totals['insurance'], $totals['tax']))" :sub="__('Thuế: :a', ['a' => money($totals['tax'])])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-cash-stack" tone="green" :label="__('Tổng thực nhận')" :value="money($totals['net'])" /></div>
    </div>

    @if ($period->isLocked())
        <div class="alert alert-info py-2 small"><i class="bi bi-lock me-1"></i>{{ __('Kỳ lương đã duyệt bởi :n lúc :t — số liệu đã khóa.', ['n' => $period->approver?->name ?? '—', 't' => vn_date($period->approved_at, true)]) }}</div>
    @endif

    <div class="card">
        @if ($slips->isEmpty())
            <x-empty-state icon="bi-receipt" :title="__('Chưa có phiếu lương')" :message="__('Chưa có nhân viên nào có công hoặc hồ sơ đang làm việc trong tháng này.')" />
        @else
            <div class="table-responsive"><table class="table table-hover align-middle mb-0 small text-nowrap">
                <thead><tr>
                    <th>{{ __('Nhân viên') }}</th><th class="text-end">{{ __('Ngày công') }}</th><th class="text-end">{{ __('Giờ công') }}</th><th class="text-end">{{ __('Đơn giá giờ') }}</th>
                    <th class="text-end">{{ __('Lương giờ làm') }}</th><th class="text-end">{{ __('Tăng ca') }}</th><th class="text-end">{{ __('Phụ cấp + thưởng') }}</th>
                    <th class="text-end">{{ __('Bảo hiểm') }}</th><th class="text-end">{{ __('Thuế TNCN') }}</th><th class="text-end">{{ __('Khấu trừ khác') }}</th><th class="text-end">{{ __('Thực nhận') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($slips as $s)
                    <tr>
                        <td class="text-nowrap"><div class="d-flex align-items-center gap-2"><x-avatar :user="$s->user" size="sm" /><span class="fw-semibold">{{ $s->user?->name }}</span></div></td>
                        <td class="text-end">{{ $s->work_days }}</td>
                        <td class="text-end">{{ $s->worked_hours }}</td>
                        <td class="text-end">{{ money($s->hourly_rate) }}</td>
                        <td class="text-end">{{ money($s->base_pay) }}</td>
                        <td class="text-end">{{ money($s->ot_pay) }}@if ((float) $s->ot_hours)<div class="text-muted">{{ (float) $s->ot_hours }}h</div>@endif</td>
                        <td class="text-end">{{ money(App\Support\Decimal::add($s->allowance, $s->bonus)) }}</td>
                        <td class="text-end">{{ money($s->insurance_total) }}</td>
                        <td class="text-end">{{ money($s->personal_income_tax) }}</td>
                        <td class="text-end">{{ money($s->other_deduction) }}</td>
                        <td class="text-end fw-semibold">{{ money($s->net_pay) }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('payslips.show', $s) }}" class="btn btn-sm btn-light" title="{{ __('Xem phiếu lương') }}" aria-label="{{ __('Xem phiếu lương') }}"><i class="bi bi-eye"></i></a>
                            @if ($manage && $period->status === 'draft')
                                <div class="dropdown d-inline">
                                    <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="{{ __('Phụ cấp / thưởng / khấu trừ') }}" aria-label="{{ __('Phụ cấp / thưởng / khấu trừ') }}"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" action="{{ route('payslips.update', $s) }}" class="dropdown-menu dropdown-menu-end p-3 text-start" style="min-width: 280px">@csrf @method('PUT')
                                        <div class="fw-semibold mb-2">{{ $s->user?->name }}</div>
                                        <label class="form-label small" for="al{{ $s->id }}">{{ __('Phụ cấp') }}</label>
                                        <input type="number" name="allowance" id="al{{ $s->id }}" class="form-control form-control-sm mb-2" min="0" step="1000" value="{{ (float) $s->allowance }}">
                                        <label class="form-label small" for="bo{{ $s->id }}">{{ __('Thưởng') }}</label>
                                        <input type="number" name="bonus" id="bo{{ $s->id }}" class="form-control form-control-sm mb-2" min="0" step="1000" value="{{ (float) $s->bonus }}">
                                        <label class="form-label small" for="de{{ $s->id }}">{{ __('Khấu trừ khác') }}</label>
                                        <input type="number" name="other_deduction" id="de{{ $s->id }}" class="form-control form-control-sm mb-2" min="0" step="1000" value="{{ (float) $s->other_deduction }}">
                                        <label class="form-label small" for="no{{ $s->id }}">{{ __('Ghi chú') }}</label>
                                        <input type="text" name="note" id="no{{ $s->id }}" class="form-control form-control-sm mb-2" maxlength="500" value="{{ $s->note }}">
                                        <button class="btn btn-sm btn-primary w-100">{{ __('Lưu và tính lại') }}</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr class="fw-semibold">
                    <td>{{ __('Tổng cộng') }}</td><td colspan="6"></td><td class="text-end">{{ money($totals['insurance']) }}</td><td class="text-end">{{ money($totals['tax']) }}</td><td></td><td class="text-end">{{ money($totals['net']) }}</td><td></td>
                </tr></tfoot>
            </table></div>
        @endif
    </div>

    @if ($manage && $period->status === 'draft')
        <div class="mt-3 text-end"><x-delete-button :action="route('payroll.destroy', $period)" :message="__('Xóa kỳ lương nháp này? Có thể tạo lại sau.')" :label="__('Xóa kỳ lương nháp')" class="btn btn-sm btn-outline-danger" /></div>
    @endif
@endsection
