<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();   // tránh ảnh hưởng test khác (giá trị tĩnh)
        parent::tearDown();
    }

    private function bootWith(?string $proxies): void
    {
        config(['app.trusted_proxies' => $proxies]);
        TrustProxies::flushState();
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_forwarded_https_from_trusted_proxy_generates_https_links(): void
    {
        $this->admin();   // có quản trị → trang đăng nhập không chuyển sang /setup
        $this->bootWith('127.0.0.1');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'abc.trycloudflare.com'])
            ->get('/login')->assertOk()->assertSee('https://abc.trycloudflare.com', false);
    }

    public function test_forwarded_headers_are_ignored_from_untrusted_address(): void
    {
        $this->admin();
        $this->bootWith('127.0.0.1');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example.com'])
            ->get('/login')->assertOk()->assertDontSee('evil.example.com', false);
    }
}
