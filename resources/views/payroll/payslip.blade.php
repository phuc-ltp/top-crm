@extends('layouts.app')
@section('title', __('Phiếu lương :p', ['p' => $slip->period->label]))
@section('content')
    @php($viewAll = auth()->user()->can('payroll.view_all'))
    <x-page-header :title="__('Phiếu lương :p', ['p' => $slip->period->label])"
                   :breadcrumbs="$viewAll ? ['Bảng lương' => route('payroll.index'), $slip->period->label => route('payroll.show', $slip->period), $slip->user->name => null] : ['Phiếu lương của tôi' => route('payslips.mine'), $slip->period->label => null]">
        <span class="align-self-center">{!! badge(App\Models\PayrollPeriod::STATUSES, $slip->period->status) !!}</span>
        <a href="{{ route('payslips.pdf', $slip) }}" class="btn btn-primary"><i class="bi bi-file-earmark-pdf me-1"></i>{{ __('Tải PDF') }}</a>
    </x-page-header>

    @if (! $slip->period->isPublished())
        <div class="alert alert-warning py-2 small">{{ __('Kỳ lương chưa được duyệt — số liệu có thể còn thay đổi và nhân viên chưa xem được phiếu này.') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3"><x-avatar :user="$slip->user" /><div><div class="fw-semibold">{{ $slip->user->name }}</div><div class="small text-muted">{{ $profile?->job_title ?? $slip->user->email }}</div></div></div>
                <dl class="row small mb-0">
                    @if ($profile?->employee_code)<dt class="col-6">{{ __('Mã nhân viên') }}</dt><dd class="col-6">{{ $profile->employee_code }}</dd>@endif
                    <dt class="col-6">{{ __('Ngày công') }}</dt><dd class="col-6">{{ $slip->work_days }}</dd>
                    <dt class="col-6">{{ __('Giờ công') }}</dt><dd class="col-6">{{ $slip->worked_hours }}</dd>
                    <dt class="col-6">{{ __('Đi trễ / về sớm') }}</dt><dd class="col-6">{{ __(':m phút', ['m' => $slip->late_minutes]) }}</dd>
                    <dt class="col-6">{{ __('Giờ tăng ca') }}</dt><dd class="col-6">{{ (float) $slip->ot_hours }}</dd>
                    <dt class="col-6">{{ __('Đơn giá giờ') }}</dt><dd class="col-6">{{ money($slip->hourly_rate) }}</dd>
                    <dt class="col-6">{{ __('Người phụ thuộc') }}</dt><dd class="col-6 mb-0">{{ $slip->dependents }}</dd>
                </dl>
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
                <tbody>
                @foreach (App\Services\PayrollService::lines($slip) as [$label, $amount, $detail, $kind])
                    <tr class="{{ $kind === 'total' ? 'table-success fw-bold fs-5' : ($kind === 'subtotal' ? 'fw-semibold table-light' : '') }}">
                        <td>{{ $label }}@if ($detail)<div class="small text-muted fw-normal">{{ $detail }}</div>@endif</td>
                        <td class="text-end text-nowrap {{ $kind === 'deduction' && (float) $amount ? 'text-danger' : '' }}">{{ $kind === 'deduction' && (float) $amount ? '− ' : '' }}{{ money($amount) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div></div>
        </div>
    </div>
@endsection
