@extends('layouts.app')
@section('title', __('Báo cáo'))
@section('content')
    <x-page-header :title="__('Báo cáo & Phân tích')" :breadcrumbs="['Báo cáo' => null]" :subtitle="__('Mọi chỉ số được tính từ dữ liệu thực, theo phạm vi quyền của bạn')" />
    <div class="row g-3">
        @foreach ($types as $key => [$label, $icon])
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('reports.show', $key) }}" class="card card-hover h-100 text-reset">
                    <div class="card-body">
                        <div class="stat-icon tone-orange mb-3" style="width:48px;height:48px;border-radius:13px;display:grid;place-items:center;font-size:1.35rem"><i class="bi {{ $icon }}"></i></div>
                        <div class="fw-semibold">{{ __($label) }}</div>
                        <div class="small text-muted mt-1">{{ __('Xem chi tiết, lọc và xuất CSV/Excel/PDF') }} <i class="bi bi-arrow-right"></i></div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection
