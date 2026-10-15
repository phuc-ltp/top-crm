<?php

use App\Models\Setting;
use Illuminate\Support\Carbon;

if (! function_exists('money')) {
    /** Định dạng tiền theo cài đặt (mặc định VND: 1.250.000 ₫). */
    function money($amount, bool $withSymbol = true): string
    {
        $currency = Setting::get('currency', 'VND');
        $amount = (float) $amount;

        if ($currency === 'VND') {
            return number_format($amount, 0, ',', '.').($withSymbol ? ' ₫' : '');
        }

        return ($withSymbol ? $currency.' ' : '').number_format($amount, 2, '.', ',');
    }
}

if (! function_exists('money_short')) {
    /** Tiền dạng gọn cho thẻ KPI: 295,4 tr ₫ · 1,5 tỷ ₫ (số đầy đủ dùng money()). */
    function money_short($amount): string
    {
        $amount = (float) $amount;
        if (Setting::get('currency', 'VND') !== 'VND' || abs($amount) < 1_000_000) {
            return money($amount);
        }
        [$div, $unit] = abs($amount) >= 1_000_000_000 ? [1_000_000_000, 'tỷ'] : [1_000_000, 'tr'];
        $value = rtrim(rtrim(number_format($amount / $div, 1, ',', '.'), '0'), ',');

        return $value.' '.$unit.' ₫';
    }
}

if (! function_exists('vn_date')) {
    function vn_date($date, bool $withTime = false): string
    {
        if (! $date) {
            return '—';
        }
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $format = Setting::get('date_format', 'd/m/Y');

        return $date->format($withTime ? $format.' H:i' : $format);
    }
}

if (! function_exists('badge')) {
    /**
     * Badge trạng thái từ hằng số dạng ['key' => ['Nhãn', 'màu']].
     */
    function badge(array $map, ?string $value): string
    {
        [$label, $color] = $map[$value] ?? [$value ?? '—', 'secondary'];

        return '<span class="badge badge-soft-'.e($color).'">'.e($label).'</span>';
    }
}

if (! function_exists('label_of')) {
    function label_of(array $map, ?string $value): string
    {
        $item = $map[$value] ?? $value ?? '—';

        return is_array($item) ? $item[0] : $item;
    }
}
