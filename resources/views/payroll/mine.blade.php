@extends('layouts.app')
@section('title', __('Phiếu lương của tôi'))
@section('content')
    <x-page-header :title="__('Phiếu lương của tôi')" :breadcrumbs="['Chấm công & lương' => route('attendance.index'), 'Phiếu lương' => null]"
                   :subtitle="__('Phiếu lương hiện ở đây sau khi bảng lương tháng được duyệt.')" />

    <div class="card">
        @if ($slips->isEmpty())
            <x-empty-state icon="bi-receipt" :title="__('Chưa có phiếu lương nào')" />
        @else
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Kỳ lương') }}</th><th>{{ __('Trạng thái') }}</th><th class="text-end">{{ __('Ngày công') }}</th><th class="text-end">{{ __('Tổng thu nhập') }}</th><th class="text-end">{{ __('Thực nhận') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($slips as $s)
                    <tr>
                        <td class="fw-semibold"><a href="{{ route('payslips.show', $s) }}">{{ $s->period->label }}</a></td>
                        <td>{!! badge(App\Models\PayrollPeriod::STATUSES, $s->period->status) !!}</td>
                        <td class="text-end">{{ $s->work_days }}</td>
                        <td class="text-end">{{ money($s->gross_pay) }}</td>
                        <td class="text-end fw-semibold">{{ money($s->net_pay) }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('payslips.show', $s) }}" class="btn btn-sm btn-light">{{ __('Xem') }}</a>
                            <a href="{{ route('payslips.pdf', $s) }}" class="btn btn-sm btn-light" title="{{ __('Tải PDF') }}" aria-label="{{ __('Tải PDF') }}"><i class="bi bi-file-earmark-pdf"></i></a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
@endsection
