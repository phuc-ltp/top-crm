@extends('layouts.app')
@section('title', __('Phân tích hỗ trợ'))
@section('content')
    @php($dur = fn ($m) => $m === null ? '—' : ($m < 60 ? __(':m phút', ['m' => $m]) : ($m < 1440 ? __(':h giờ', ['h' => round($m / 60, 1)]) : __(':d ngày', ['d' => round($m / 1440, 1)]))))
    <x-page-header :title="__('Phân tích hỗ trợ')" :breadcrumbs="['Hỗ trợ' => route('tickets.index'), 'Phân tích' => null]" :subtitle="vn_date($from).' → '.vn_date($to)">
        <div class="btn-group" role="group" aria-label="{{ __('Kỳ') }}">
            @foreach (['month' => 'Tháng này', 'quarter' => 'Quý này', 'year' => 'Năm nay'] as $k => $l)
                <a href="{{ route('tickets.analytics', ['period' => $k]) }}" class="btn {{ $period === $k ? 'btn-primary' : 'btn-light' }}">{{ __($l) }}</a>
            @endforeach
        </div>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-inbox" :label="__('Ticket mới')" :value="$summary['created']" :sub="__(':n đã giải quyết trong kỳ', ['n' => $summary['resolved']])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-reply" tone="blue" :label="__('Đạt SLA phản hồi đầu')" :value="$summary['response_rate'] !== null ? $summary['response_rate'].'%' : '—'" :sub="__('TB: :t', ['t' => $dur($summary['avg_response'])])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-check2-circle" tone="green" :label="__('Đạt SLA xử lý')" :value="$summary['resolution_rate'] !== null ? $summary['resolution_rate'].'%' : '—'" :sub="__('TB: :t', ['t' => $dur($summary['avg_resolution'])])" /></div>
        <div class="col-6 col-lg-3"><x-stat-card icon="bi-star" tone="yellow" :label="__('Hài lòng (CSAT)')" :value="$summary['csat'] !== null ? $summary['csat'].'/5' : '—'" :sub="__(':n lượt đánh giá', ['n' => $summary['csat_count']])" /></div>
    </div>

    <div class="row g-4">
        @foreach (['Theo danh mục' => $byCategory, 'Theo nhân viên' => $byAgent] as $title => $rows)
            <div class="col-12">
                <div class="card">
                    <div class="card-header">{{ __($title) }}</div>
                    @if ($rows->isEmpty())
                        <x-empty-state icon="bi-bar-chart" :title="__('Chưa có ticket trong kỳ')" />
                    @else
                        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                            <thead><tr><th></th><th class="text-end">{{ __('Ticket') }}</th><th class="text-end">{{ __('Đã giải quyết') }}</th><th class="text-end">{{ __('Vi phạm SLA') }}</th><th class="text-end">{{ __('TB xử lý') }}</th><th class="text-end">CSAT</th></tr></thead>
                            <tbody>
                            @foreach ($rows as $r)
                                <tr><td class="small fw-semibold text-nowrap">{{ $r['label'] }}</td><td class="text-end small">{{ $r['count'] }}</td><td class="text-end small">{{ $r['resolved'] }}</td>
                                    <td class="text-end small {{ $r['breached'] ? 'text-danger fw-semibold' : '' }}">{{ $r['breached'] }}</td><td class="text-end small">{{ $dur($r['avg_resolution']) }}</td><td class="text-end small">{{ $r['csat'] ?? '—' }}</td></tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    <div class="small text-muted mt-3">{{ __('Ticket mới = tạo trong kỳ. Tỉ lệ đạt SLA tính trên ticket tạo trong kỳ đã có kết quả (đạt hoặc vi phạm). Thời gian xử lý trừ thời gian chờ khách. CSAT = điểm trung bình (1–5) của ticket giải quyết trong kỳ.') }}</div>
@endsection
