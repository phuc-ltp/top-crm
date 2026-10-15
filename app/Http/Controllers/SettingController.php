<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\UploadService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public const TIMEZONES = ['Asia/Ho_Chi_Minh', 'Asia/Bangkok', 'Asia/Singapore', 'Asia/Tokyo', 'UTC'];

    public const DATE_FORMATS = ['d/m/Y' => 'DD/MM/YYYY (31/12/2026)', 'd-m-Y' => 'DD-MM-YYYY', 'Y-m-d' => 'YYYY-MM-DD', 'm/d/Y' => 'MM/DD/YYYY'];

    public const CURRENCIES = ['VND' => 'VND – Việt Nam Đồng', 'USD' => 'USD – Đô la Mỹ'];

    public function edit(Request $request)
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        return view('settings.edit', ['s' => Setting::allCached(), 'organization' => app(\App\Support\Tenancy\CurrentOrganization::class)->get()]);
    }

    public function update(Request $request, UploadService $uploads)
    {
        abort_unless($request->user()->can('settings.manage'), 403);
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_email' => ['nullable', 'email', 'max:150'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'company_address' => ['nullable', 'string', 'max:300'],
            'brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'timezone' => ['required', 'in:'.implode(',', self::TIMEZONES)],
            'currency' => ['required', 'in:'.implode(',', array_keys(self::CURRENCIES))],
            'date_format' => ['required', 'in:'.implode(',', array_keys(self::DATE_FORMATS))],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'company_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ], ['brand_color.regex' => __('Màu thương hiệu phải có dạng #RRGGBB.')], ['company_name' => __('Tên công ty'), 'brand_color' => __('Màu thương hiệu')]);

        $current = Setting::allCached();
        if ($request->hasFile('company_logo')) {
            $data['company_logo'] = $uploads->replacePublicImage($request->file('company_logo'), 'branding', $current['company_logo'] ?: null);
        } elseif ($request->boolean('remove_logo')) {
            $uploads->deletePublic($current['company_logo'] ?: null);
            $data['company_logo'] = '';
        } else {
            unset($data['company_logo']);
        }
        unset($data['remove_logo']);

        foreach (['notify_lead_assigned', 'notify_task_due', 'notify_deal_stage', 'notify_ticket_new'] as $key) {
            Setting::put([$key => $request->boolean($key) ? '1' : '0'], 'notifications');
        }
        // Cài đặt bảo mật áp dụng toàn hệ thống → chỉ quản trị nền tảng được đổi
        if ($request->user()->isPlatformAdmin()) {
            $require = $request->boolean(\App\Http\Middleware\RequireTwoFactor::SETTING) ? '1' : '0';
            if ($require !== Setting::get(\App\Http\Middleware\RequireTwoFactor::SETTING, '0')) {
                Setting::put([\App\Http\Middleware\RequireTwoFactor::SETTING => $require], 'security');
                ActivityLogger::log('updated', null, $require === '1' ? 'Bật bắt buộc xác thực 2 lớp cho tài khoản quản trị' : 'Tắt bắt buộc xác thực 2 lớp cho tài khoản quản trị');
            }
        }
        Setting::put(array_map(fn ($v) => (string) $v, $data));
        ActivityLogger::log('updated', null, 'Cập nhật cài đặt tổ chức');

        return back()->with('success', __('Đã lưu cài đặt của tổ chức.'));
    }

    /** Trang đăng nhập & thương hiệu + đăng nhập mạng xã hội */
    public function branding(Request $request)
    {
        // Trang đăng nhập, Google/Facebook, trang pháp lý dùng chung cho MỌI tổ chức → chỉ quản trị nền tảng
        abort_unless($request->user()->can('platform'), 403);

        return view('settings.branding', [
            's' => Setting::allCached(),
            'social' => collect(['google', 'facebook'])->mapWithKeys(fn ($p) => [$p => [
                'enabled' => Setting::socialEnabled($p),
                'has_secret' => (bool) Setting::get($p.'_client_secret'),
                'callback' => route('social.callback', $p),
            ]])->all(),
        ]);
    }

    public function updateBranding(Request $request, UploadService $uploads)
    {
        abort_unless($request->user()->can('platform'), 403);
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:30'],
            'brand_tagline' => ['nullable', 'string', 'max:60'],
            'brand_slogan' => ['nullable', 'string', 'max:120'],
            'login_headline' => ['required', 'string', 'max:60'],
            'login_headline_accent' => ['nullable', 'string', 'max:60'],
            'login_description' => ['nullable', 'string', 'max:300'],
            'login_stats' => ['nullable', 'string', 'max:500'],
            'copyright_text' => ['required', 'string', 'max:120'],
            'copyright_owner' => ['nullable', 'string', 'max:120'],
            'page_terms' => ['nullable', 'string', 'max:20000'],
            'page_privacy' => ['nullable', 'string', 'max:20000'],
            'page_support' => ['nullable', 'string', 'max:20000'],
            'login_background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_background' => ['nullable', 'boolean'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'facebook_client_id' => ['nullable', 'string', 'max:255'],
            'facebook_client_secret' => ['nullable', 'string', 'max:255'],
            'clear_google' => ['nullable', 'boolean'],
            'clear_facebook' => ['nullable', 'boolean'],
        ], [], ['brand_name' => __('Tên thương hiệu'), 'login_headline' => __('Tiêu đề'), 'copyright_text' => __('Dòng bản quyền'), 'login_background' => __('Ảnh nền')]);

        $current = Setting::allCached();
        if ($request->hasFile('login_background')) {
            $data['login_background'] = $uploads->replacePublicImage($request->file('login_background'), 'branding', $current['login_background'] ?: null);
        } elseif ($request->boolean('remove_background')) {
            $uploads->deletePublic($current['login_background'] ?: null);
            $data['login_background'] = '';
        } else {
            unset($data['login_background']);
        }

        // Khóa bí mật OAuth: mã hóa bằng APP_KEY; để trống = giữ nguyên khóa cũ
        foreach (['google', 'facebook'] as $p) {
            if ($request->boolean('clear_'.$p)) {
                $data[$p.'_client_id'] = '';
                $data[$p.'_client_secret'] = '';
            } elseif (! empty($data[$p.'_client_secret'])) {
                $data[$p.'_client_secret'] = \Illuminate\Support\Facades\Crypt::encryptString($data[$p.'_client_secret']);
            } else {
                unset($data[$p.'_client_secret']);
            }
        }
        unset($data['remove_background'], $data['clear_google'], $data['clear_facebook']);

        Setting::put(array_map(fn ($v) => (string) ($v ?? ''), $data), 'branding');
        ActivityLogger::log('updated', null, 'Cập nhật trang đăng nhập & thương hiệu');

        return back()->with('success', __('Đã lưu cài đặt trang đăng nhập & thương hiệu.'));
    }
}
