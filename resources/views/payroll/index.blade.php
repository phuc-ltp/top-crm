@extends('layouts.app')
@section('title', __('Bảng lương'))
@section('content')
    <x-page-header :title="__('Bảng lương')" :breadcrumbs="['Chấm công & lương' => route('attendance.index'), 'Bảng lương' => null]"
                   :subtitle="__('Mỗi tháng một kỳ lương, tính từ bảng công và đơn tăng ca đã duyệt. Kỳ đã duyệt được khóa và nhân viên xem được phiếu lương của mình.')">
        @can('payroll.manage')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#periodModal"><i class="bi bi-plus-lg me-1"></i>{{ __('Tạo kỳ lương') }}</button>@endcan
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                @if ($periods->isEmpty())
                    <x-empty-state icon="bi-cash-stack" :title="__('Chưa có kỳ lương nào')" :message="__('Bấm “Tạo kỳ lương” để tính lương tháng từ bảng công.')" />
                @else
                    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                        <thead><tr><th>{{ __('Kỳ lương') }}</th><th>{{ __('Trạng thái') }}</th><th class="text-end">{{ __('Số phiếu') }}</th><th class="text-end">{{ __('Tổng thực nhận') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($periods as $p)
                            <tr>
                                <td class="fw-semibold"><a href="{{ route('payroll.show', $p) }}">{{ $p->label }}</a></td>
                                <td>{!! badge(App\Models\PayrollPeriod::STATUSES, $p->status) !!}</td>
                                <td class="text-end">{{ $p->payslips_count }}</td>
                                <td class="text-end">{{ money($p->payslips_sum_net_pay ?? 0) }}</td>
                                <td class="text-end"><a href="{{ route('payroll.show', $p) }}" class="btn btn-sm btn-light">{{ __('Xem') }}</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <x-pagination-bar :paginator="$periods" />
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <h6 class="mb-3">{{ __('Cài đặt bảo hiểm & thuế') }}</h6>
                @can('payroll.manage')
                    <form method="POST" action="{{ route('payroll.settings') }}" class="needs-validation" novalidate>@csrf @method('PUT')
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="payroll_insurance_enabled" value="1" id="insOn" @checked($s['payroll_insurance_enabled'] === '1')>
                            <label class="form-check-label" for="insOn">{{ __('Khấu trừ bảo hiểm (BHXH :a%, BHYT :b%, BHTN :c%)', ['a' => $rates['social'], 'b' => $rates['health'], 'c' => $rates['unemployment']]) }}</label></div>
                        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="payroll_pit_enabled" value="1" id="pitOn" @checked($s['payroll_pit_enabled'] === '1')>
                            <label class="form-check-label" for="pitOn">{{ __('Khấu trừ thuế TNCN') }}</label></div>
                        <x-input name="payroll_insurance_cap" type="number" :label="__('Trần lương đóng bảo hiểm')" :value="$s['payroll_insurance_cap']" required min="0" step="1000" append="₫" />
                        <x-input name="payroll_personal_deduction" type="number" :label="__('Giảm trừ bản thân / tháng')" :value="$s['payroll_personal_deduction']" required min="0" step="1000" append="₫" />
                        <x-input name="payroll_dependent_deduction" type="number" :label="__('Giảm trừ mỗi người phụ thuộc / tháng')" :value="$s['payroll_dependent_deduction']" required min="0" step="1000" append="₫" />
                        <button class="btn btn-primary w-100">{{ __('Lưu cài đặt') }}</button>
                    </form>
                @else
                    <dl class="small mb-0">
                        <dt>{{ __('Trần lương đóng bảo hiểm') }}</dt><dd>{{ money($s['payroll_insurance_cap']) }}</dd>
                        <dt>{{ __('Giảm trừ bản thân / tháng') }}</dt><dd>{{ money($s['payroll_personal_deduction']) }}</dd>
                        <dt>{{ __('Giảm trừ mỗi người phụ thuộc / tháng') }}</dt><dd class="mb-0">{{ money($s['payroll_dependent_deduction']) }}</dd>
                    </dl>
                @endcan
                <hr>
                <div class="small text-muted mb-1">{{ __('Biểu thuế TNCN lũy tiến (theo tháng)') }}</div>
                <table class="table table-sm small mb-2">
                    @php($lower = 0)
                    @foreach ($brackets as [$upper, $rate])
                        <tr><td>{{ $upper === null ? __('Trên :a', ['a' => money($lower)]) : __(':a – :b', ['a' => money($lower), 'b' => money($upper)]) }}</td><td class="text-end">{{ $rate }}%</td></tr>
                        @php($lower = $upper)
                    @endforeach
                </table>
                <div class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Các mức giảm trừ, trần bảo hiểm và biểu thuế cần kế toán xác nhận theo quy định hiện hành trước khi trả lương thật.') }}</div>
            </div></div>
        </div>
    </div>

    @can('payroll.manage')
        <div class="modal fade" id="periodModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.store') }}" class="needs-validation" novalidate>@csrf
                <div class="modal-header"><h5 class="modal-title">{{ __('Tạo kỳ lương') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
                <div class="modal-body">
                    <x-input name="month" type="month" :label="__('Tháng')" required :value="now()->format('Y-m')" :max="now()->format('Y-m')" />
                    <div class="small text-muted">{{ __('Hệ thống tự lập phiếu cho nhân viên đang làm việc và người có công / tăng ca trong tháng. Kỳ nháp có thể tính lại bất cứ lúc nào.') }}</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Tạo và tính lương') }}</button></div>
            </form>
        </div></div></div>
    @endcan
@endsection
