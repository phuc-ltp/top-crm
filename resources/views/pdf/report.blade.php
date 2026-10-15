<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>{{ $report['title'] }}</title>
<style>
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { font-size: 9.5px; color: #1F2937; }
    h1 { font-size: 16px; color: #E65C00; margin: 0 0 4px; }
    .muted { color: #6B7280; }
    .head { border-bottom: 3px solid #FF6B00; padding-bottom: 8px; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    .summary td { background: #FFF1E6; padding: 8px; border: 2px solid #fff; }
    .summary .v { font-size: 13px; font-weight: bold; }
    .data th { background: #1F2937; color: #fff; padding: 5px; text-align: left; }
    .data td { padding: 5px; border-bottom: 1px solid #E5E7EB; }
    .right { text-align: right; }
</style>
</head>
<body>
<div class="head">
    <div class="muted">{{ $settings['company_name'] }}</div>
    <h1>{{ mb_strtoupper($report['title']) }}</h1>
    <div class="muted">Kỳ báo cáo: {{ $report['from']->format('d/m/Y') }} – {{ $report['to']->format('d/m/Y') }} · Xuất lúc {{ now()->format('d/m/Y H:i') }} bởi {{ auth()->user()->name }}</div>
</div>
<table class="summary"><tr>
    @foreach ($report['summary'] as [$label, $value])<td><div class="muted">{{ $label }}</div><div class="v">{{ $value }}</div></td>@endforeach
</tr></table>
<p class="muted"><strong>Định nghĩa:</strong> {{ $report['definition'] }}</p>
<table class="data">
    <thead><tr>@foreach ($report['headings'] as $i => $h)<th class="{{ in_array($i, $report['money_cols']) ? 'right' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($report['rows'] as $row)
        <tr>@foreach (array_values($row) as $i => $cell)<td class="{{ in_array($i, $report['money_cols']) ? 'right' : '' }}">{{ in_array($i, $report['money_cols']) ? money($cell) : $cell }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($report['headings']) }}">Không có dữ liệu.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
