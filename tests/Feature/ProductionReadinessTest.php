<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Sẵn sàng production: header bảo mật theo môi trường, lệnh kiểm tra go-live, xóa demo đầy đủ. */
class ProductionReadinessTest extends TestCase
{
    public function test_strict_security_headers_only_in_production(): void
    {
        $local = $this->get(route('login'));
        $local->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertFalse($local->headers->has('Content-Security-Policy'), 'Không bật CSP ở môi trường phát triển (Vite dev server)');

        $this->app['env'] = 'production';
        $prod = $this->get(route('login'));
        $csp = (string) $prod->headers->get('Content-Security-Policy');
        foreach (["default-src 'self'", "object-src 'none'", "frame-ancestors 'self'", 'https://fonts.googleapis.com'] as $rule) {
            $this->assertStringContainsString($rule, $csp);
        }
        $this->assertFalse($prod->headers->has('Strict-Transport-Security'), 'HSTS chỉ gửi qua HTTPS');
        $this->get(str_replace('http://', 'https://', route('login')))->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_production_check_reports_blocking_problems_without_leaking_secrets(): void
    {
        config(['app.debug' => true, 'database.connections.mysql.password' => 'mat-khau-bi-mat-xyz']);
        $this->artisan('crm:production-check')
            ->expectsOutputToContain('APP_ENV=production')
            ->expectsOutputToContain('APP_DEBUG=false')
            ->expectsOutputToContain('CHƯA sẵn sàng')
            ->doesntExpectOutputToContain('mat-khau-bi-mat-xyz')
            ->assertExitCode(1);
    }

    public function test_purge_demo_removes_demo_projects_users_and_their_notifications_only(): void
    {
        $real = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $demoUser = $this->userWithRole('Sales Employee', ['is_demo' => true]);
        CrmNotification::send($demoUser, 'task_due', 'Thông báo của demo');
        CrmNotification::send($real, 'task_due', 'Thông báo thật');
        Project::create(['name' => 'Dự án demo', 'status' => 'active', 'priority' => 'low', 'is_demo' => true]);
        $realProject = Project::create(['name' => 'Dự án thật', 'status' => 'active', 'priority' => 'low']);
        Customer::create(['name' => 'Khách demo', 'type' => 'individual', 'status' => 'active', 'is_demo' => true]);
        $realCustomer = $this->customer();

        $this->artisan('crm:purge-demo', ['--force' => true])->assertSuccessful();

        $this->assertSame([$realProject->id], Project::pluck('id')->all());
        $this->assertSame([$realCustomer->id], Customer::pluck('id')->all());
        $this->assertNull(User::withTrashed()->find($demoUser->id));
        $this->assertSame(['Thông báo thật'], DB::table('notifications')->pluck('data')->map(fn ($d) => json_decode($d, true)['message'])->all());
    }
}
