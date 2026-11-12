<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Setting;
use App\Models\SoftwareLicense;
use App\Support\KeyRotation;
use App\Support\Security\Totp;
use App\Support\Security\TwoFactor;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KeyRotationTest extends TestCase
{
    private string $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = storage_path('framework/testing/rotate-'.uniqid().'.env');
        @mkdir(dirname($this->env), 0755, true);
        file_put_contents($this->env, "APP_NAME=\"TOP CRM\"\nAPP_KEY=".config('app.key')."\nAPP_PREVIOUS_KEYS=base64:cũ\nDB_PASSWORD='{+giữ|nguyên?['\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->env);
        @unlink($this->env.'.rotating');
        parent::tearDown();
    }

    /** Tạo đủ các loại dữ liệu mã hóa thật của hệ thống. */
    private function seedSecrets(): array
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $user = $this->userWithRole('Sales Employee', ['email' => 'nv@example.com']);
        $secret = Totp::generateSecret();
        $codes = TwoFactor::enable($user, $secret, Totp::currentStep() - 5);
        $profile = EmployeeProfile::updateOrCreate(['user_id' => $user->id], ['employment_status' => 'active', 'hire_date' => today()]);
        $profile->forceFill(['id_number' => '001099012345', 'home_address' => '12 Phố Huế, Hà Nội', 'hr_notes' => 'Ghi chú mật'])->save();
        $lic = SoftwareLicense::create(['name' => 'Office', 'license_key' => 'ABCDE-12345-FGHIJ', 'seats' => 5]);
        Setting::put(['sepay_api_key' => Crypt::encryptString('sepay-bi-mat-123456')], 'payments');
        Setting::put(['mail_password' => Crypt::encryptString('mat-khau-gmail-ung-dung')], 'mail');

        return compact('user', 'secret', 'codes', 'profile', 'lic');
    }

    public function test_scan_finds_every_kind_of_encrypted_data(): void
    {
        $this->seedSecrets();
        $fields = KeyRotation::preflight(config('app.key'))['fields'];
        foreach (['employee_profiles.id_number', 'employee_profiles.home_address', 'employee_profiles.hr_notes', 'users.two_factor_secret',
            'users.two_factor_recovery_codes', 'software_licenses.license_key', 'organization_settings.value', 'settings.value'] as $f) {
            $this->assertArrayHasKey($f, $fields, "Không tìm thấy dữ liệu mã hóa ở $f");
        }
        $this->assertSame([], KeyRotation::preflight(config('app.key'))['unreadable']);
    }

    public function test_rotation_reencrypts_everything_and_retires_old_key(): void
    {
        $s = $this->seedSecrets();
        $oldKey = config('app.key');
        $newKey = KeyRotation::newKey();
        DB::table('sessions')->insert(['id' => 'abc', 'payload' => 'x', 'last_activity' => time()]);

        $r = KeyRotation::rotate($this->env, $newKey);
        $this->assertGreaterThanOrEqual(8, $r['reencrypted']);
        $this->assertSame(0, DB::table('sessions')->count());   // mọi người đăng nhập lại

        // .env: khóa mới, bỏ APP_PREVIOUS_KEYS, giữ nguyên các dòng khác
        $env = file_get_contents($this->env);
        $this->assertStringContainsString('APP_KEY='.$newKey, $env);
        $this->assertStringNotContainsString($oldKey, $env);
        $this->assertStringNotContainsString('APP_PREVIOUS_KEYS', $env);
        $this->assertStringContainsString("DB_PASSWORD='{+giữ|nguyên?['", $env);
        $this->assertFileDoesNotExist($this->env.'.rotating');

        // Ứng dụng chạy với khóa mới đọc được đúng mọi dữ liệu
        $this->switchKey($newKey);
        $this->assertSame([], KeyRotation::preflight($newKey)['unreadable']);
        $this->assertSame('001099012345', $s['profile']->fresh()->id_number);
        $this->assertSame('12 Phố Huế, Hà Nội', $s['profile']->fresh()->home_address);
        $this->assertSame('ABCDE-12345-FGHIJ', $s['lic']->fresh()->license_key);
        $this->assertSame($s['secret'], $s['user']->fresh()->two_factor_secret);
        $this->assertTrue(TwoFactor::useRecoveryCode($s['user']->fresh(), $s['codes'][0]));
        $this->assertSame('sepay-bi-mat-123456', Setting::secret('sepay_api_key'));
        $this->assertSame('mat-khau-gmail-ung-dung', Crypt::decryptString(Setting::get('mail_password')));

        // Khóa cũ không còn giải mã được dữ liệu nào
        $this->assertSame(KeyRotation::preflight($oldKey)['total'], count(KeyRotation::preflight($oldKey)['unreadable']));
    }

    public function test_refuses_when_some_data_is_unreadable_and_changes_nothing(): void
    {
        $s = $this->seedSecrets();
        $foreign = (new Encrypter(random_bytes(32), 'AES-256-CBC'))->encryptString('khóa khác');
        DB::table('employee_profiles')->where('id', $s['profile']->id)->update(['personal_email' => $foreign]);
        $before = DB::table('employee_profiles')->where('id', $s['profile']->id)->value('id_number');
        $envBefore = file_get_contents($this->env);

        try {
            KeyRotation::rotate($this->env);
            $this->fail('Phải dừng khi có dữ liệu không giải mã được');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('không giải mã được', $e->getMessage());
        }
        $this->assertSame($before, DB::table('employee_profiles')->where('id', $s['profile']->id)->value('id_number'));
        $this->assertSame($envBefore, file_get_contents($this->env));
    }

    public function test_refuses_when_env_does_not_match_running_key(): void
    {
        $this->seedSecrets();
        file_put_contents($this->env, "APP_KEY=base64:".base64_encode(random_bytes(32))."\n");
        $this->expectException(\RuntimeException::class);
        KeyRotation::rotate($this->env);
    }

    public function test_check_command_reports_without_changes(): void
    {
        $this->seedSecrets();
        $before = DB::table('software_licenses')->value('license_key');
        $this->artisan('crm:rotate-key', ['--check' => true])->expectsOutputToContain('giải mã được bằng khóa hiện tại')->assertSuccessful();
        $this->assertSame($before, DB::table('software_licenses')->value('license_key'));
    }

    private function switchKey(string $key): void
    {
        config(['app.key' => $key]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
        \App\Models\User::encryptUsing(null);
        \App\Models\Setting::flushCache();
    }
}
