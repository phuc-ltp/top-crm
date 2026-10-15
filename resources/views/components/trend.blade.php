@props(['value' => null, 'hint' => 'so với kỳ trước'])
@if ($value === null)
    <span class="trend flat" title="Kỳ trước chưa có dữ liệu để so sánh">—</span>
@elseif ($value > 0)
    <span class="trend up" title="{{ $hint }}"><i class="bi bi-arrow-up-short"></i>{{ rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',') }}%</span>
@elseif ($value < 0)
    <span class="trend down" title="{{ $hint }}"><i class="bi bi-arrow-down-short"></i>{{ rtrim(rtrim(number_format(abs($value), 1, ',', '.'), '0'), ',') }}%</span>
@else
    <span class="trend flat" title="{{ $hint }}">0%</span>
@endif
