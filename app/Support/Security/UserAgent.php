<?php

namespace App\Support\Security;

/** Mô tả ngắn gọn trình duyệt · hệ điều hành từ chuỗi User-Agent (để người dùng nhận ra thiết bị). */
class UserAgent
{
    public static function describe(?string $agent): string
    {
        $agent = (string) $agent;
        $browser = match (true) {
            str_contains($agent, 'Edg') => 'Microsoft Edge',
            str_contains($agent, 'OPR') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'CocCoc') || str_contains($agent, 'coc_coc') => 'Cốc Cốc',
            str_contains($agent, 'Chrome') => 'Google Chrome',
            str_contains($agent, 'Firefox') => 'Mozilla Firefox',
            str_contains($agent, 'Safari') => 'Safari',
            default => __('Trình duyệt khác'),
        };
        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => __('Không rõ'),
        };

        return "$browser · $os";
    }
}
