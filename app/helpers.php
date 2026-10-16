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
        [$div, $unit] = abs($amount) >= 1_000_000_000 ? [1_000_000_000, __('tỷ')] : [1_000_000, __('tr')];
        $value = rtrim(rtrim(number_format($amount / $div, 1, ',', '.'), '0'), ',');

        return $value.' '.$unit.' ₫';
    }
}

if (! function_exists('country_options')) {
    /** Danh sách quốc gia [mã => tên] theo ngôn ngữ giao diện, sắp theo tên (Việt Nam luôn đứng đầu). */
    function country_options(): array
    {
        $i = app()->getLocale() === 'en' ? 1 : 0;
        $list = array_map(fn ($names) => $names[$i], config('crm.countries', []));
        $vn = ['VN' => $list['VN'] ?? 'Việt Nam'];
        unset($list['VN']);
        asort($list);

        return $vn + $list;
    }
}

if (! function_exists('country_name')) {
    function country_name(?string $code): string
    {
        return $code ? (country_options()[strtoupper($code)] ?? strtoupper($code)) : '—';
    }
}

if (! function_exists('crm_timezone')) {
    /** Múi giờ hiển thị của tổ chức đang làm việc (chỉ chấp nhận giá trị trong danh sách cho phép). */
    function crm_timezone(): string
    {
        $tz = (string) Setting::get('timezone', config('app.timezone'));

        return in_array($tz, \App\Http\Controllers\SettingController::TIMEZONES, true) ? $tz : config('app.timezone');
    }
}

if (! function_exists('vn_date')) {
    function vn_date($date, bool $withTime = false): string
    {
        if (! $date) {
            return '—';
        }
        $date = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $format = Setting::get('date_format', 'd/m/Y');
        if (! $withTime) {
            return $date->format($format);   // ngày thuần (không giờ) không đổi múi giờ để không lệch ngày
        }

        return $date->timezone(crm_timezone())->format($format.' H:i');
    }
}

if (! function_exists('badge')) {
    /**
     * Badge trạng thái từ hằng số dạng ['key' => ['Nhãn', 'màu']]. Nhãn được dịch theo ngôn ngữ giao diện.
     */
    function badge(array $map, ?string $value): string
    {
        [$label, $color] = $map[$value] ?? [$value ?? '—', 'secondary'];

        return '<span class="badge badge-soft-'.e($color).'">'.e(__($label)).'</span>';
    }
}

if (! function_exists('label_of')) {
    function label_of(array $map, ?string $value): string
    {
        $item = $map[$value] ?? $value ?? '—';

        return __(is_array($item) ? $item[0] : $item);
    }
}
