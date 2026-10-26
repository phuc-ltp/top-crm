<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Cài CRM như ứng dụng (PWA): manifest, icon, service worker không lưu dữ liệu, thẻ trong layout. */
class PwaTest extends TestCase
{
    public function test_manifest_icons_and_service_worker(): void
    {
        $res = $this->get(route('pwa.manifest'))->assertOk()->assertHeader('content-type', 'application/manifest+json');   // công khai, không cần đăng nhập
        $m = $res->json();
        $this->assertSame([route('dashboard'), 'standalone'], [$m['start_url'], $m['display']]);
        $this->assertNotEmpty($m['name']);
        $this->assertContains('maskable', array_column($m['icons'], 'purpose'));
        foreach ($m['icons'] as $icon) {
            $path = public_path(ltrim(parse_url($icon['src'], PHP_URL_PATH), '/'));
            $path = file_exists($path) ? $path : public_path('icons/'.basename($icon['src']));
            [$w, $h] = getimagesize($path);
            $this->assertSame($icon['sizes'], "{$w}x{$h}", 'Kích thước icon phải khớp manifest');
        }
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
        $this->assertFileExists(public_path('offline.html'));

        // Service worker: chỉ lưu sẵn trang mất kết nối + icon; không ghi phản hồi nào vào bộ nhớ đệm khi duyệt
        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringNotContainsString('cache.put', $sw);
        $this->assertStringNotContainsString('.put(', $sw);
        $this->assertStringContainsString("req.method !== 'GET'", $sw);
        $this->assertStringContainsString('offline.html', $sw);
    }

    public function test_layouts_link_manifest_and_install_button(): void
    {
        $admin = $this->admin();                       // chưa có quản trị viên thì /login chuyển sang /setup
        $this->get(route('login'))->assertOk()->assertSee(route('pwa.manifest'), false)->assertSee('apple-touch-icon.png', false);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee(route('pwa.manifest'), false)->assertSee('data-pwa-install', false)->assertSee(__('Cài ứng dụng lên máy'));
    }
}
