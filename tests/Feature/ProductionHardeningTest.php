<?php

namespace Tests\Feature;

use App\Http\Controllers\WebCronController;
use App\Models\Setting;
use App\Support\DatabaseBackup;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (glob(DatabaseBackup::directory().'/'.DatabaseBackup::PREFIX.'*') ?: [] as $f) {
            @unlink($f);   // dọn bản sao lưu tạo trong test
        }
        parent::tearDown();
    }

    public function test_http_redirects_to_https_in_production_only(): void
    {
        $this->admin();
        config(['app.url' => 'https://crm.example.com']);
        $this->get('http://crm.example.com/login?x=1')->assertOk();   // môi trường thử nghiệm: không ép

        $this->app['env'] = 'production';
        $this->get('http://crm.example.com/login?x=1')->assertStatus(301)->assertRedirect('https://crm.example.com/login?x=1');
        $this->get('https://crm.example.com/login')->assertOk();
    }

    public function test_robots_block_indexing(): void
    {
        $this->assertStringContainsString('Disallow: /', File::get(public_path('robots.txt')));
    }

    public function test_web_cron_requires_secret_token_and_records_run(): void
    {
        $admin = $this->admin();
        $this->get('/cron/'.str_repeat('a', 48))->assertNotFound();   // chưa có token → không chạy
        $this->actingAs($admin)->get(route('settings.maintenance'))->assertOk();   // mở trang tạo token
        $token = Setting::get(WebCronController::TOKEN_KEY);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $token);
        auth()->logout();

        $this->get('/cron/'.str_repeat('b', 48))->assertNotFound();
        $this->get(route('cron.run', $token))->assertOk()->assertSee('ok');
        $this->assertNotEmpty(Setting::get(WebCronController::LAST_RUN_KEY));

        $this->actingAs($admin)->get(route('settings.maintenance'))->assertSee(__('Đang chạy — lần gần nhất :time.', ['time' => vn_date(now(), true)]));
        $this->actingAs($admin)->post(route('settings.maintenance.regenerate'));
        auth()->logout();
        $this->get(route('cron.run', $token))->assertNotFound();   // token cũ hết hiệu lực
    }

    public function test_backup_runs_lists_downloads_and_rotates(): void
    {
        $admin = $this->admin();
        $r = DatabaseBackup::run(2);
        $this->assertGreaterThan(50, $r['tables']);
        $gz = gzdecode(file_get_contents(DatabaseBackup::directory().'/'.$r['file']));
        $this->assertStringContainsString('CREATE TABLE `users`', $gz);
        $this->assertStringEndsWith("-- HOÀN TẤT\n", $gz);

        $this->travel(2)->seconds();
        DatabaseBackup::run(2);
        $this->travel(2)->seconds();
        DatabaseBackup::run(2);
        $this->assertCount(2, array_filter(DatabaseBackup::list(), fn ($b) => str_starts_with($b['name'], DatabaseBackup::PREFIX)));   // giữ 2 bản

        $name = DatabaseBackup::list()[0]['name'];
        $this->actingAs($admin)->get(route('settings.maintenance.download', $name))->assertOk()->assertDownload($name);
        $this->actingAs($admin)->get('/settings/maintenance/backups/..%2F.env.sql.gz')->assertNotFound();
        $this->actingAs($admin)->post(route('settings.maintenance.backup'))->assertSessionHas('success');
    }

    public function test_maintenance_is_platform_only(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER, ['email' => 'owner@example.com']);
        DatabaseBackup::run();
        $name = DatabaseBackup::list()[0]['name'];
        $this->actingAs($owner)->get(route('settings.maintenance'))->assertForbidden();
        $this->actingAs($owner)->get(route('settings.maintenance.download', $name))->assertForbidden();
        $this->actingAs($owner)->post(route('settings.maintenance.backup'))->assertForbidden();
    }

    public function test_backup_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('crm:backup-db')->assertSuccessful();
    }
}
