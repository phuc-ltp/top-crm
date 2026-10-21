<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\AttendanceService;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bước 5.2: chấm công selfie + GPS, tính giờ công theo ca, bảng công, điều chỉnh, quyền xem ảnh, dọn ảnh quá hạn. */
class AttendanceTest extends TestCase
{
    private const OFFICE = [10.7769, 106.7009];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-10-21 '.$time, crm_timezone());   // Thứ Tư
    }

    private function punch(User $user, string $type, array $coords = [10.7770, 106.7010])
    {
        return $this->actingAs($user)->post(route('attendance.punch'), ['type' => $type, 'latitude' => $coords[0], 'longitude' => $coords[1], 'accuracy' => 12,
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 320, 240)]);
    }

    private function consentedUser(string $role = 'Sales Employee'): User
    {
        $u = $this->userWithRole($role);
        EmployeeProfile::create(['user_id' => $u->id, 'employment_status' => 'active', 'attendance_consent_at' => now()]);

        return $u;
    }

    public function test_worked_late_and_early_minutes_follow_split_shift(): void
    {
        $s = app(AttendanceService::class);
        $d = $this->at('00:00');
        $this->assertSame(['worked' => 480, 'late' => 0, 'early' => 0], $s->compute($d, $this->at('07:30'), $this->at('17:30')));
        $this->assertSame(['worked' => 450, 'late' => 30, 'early' => 0], $s->compute($d, $this->at('08:00'), $this->at('17:30')));
        $this->assertSame(['worked' => 477, 'late' => 0, 'early' => 0], $s->compute($d, $this->at('07:33'), $this->at('17:30')), 'Trễ trong 5 phút cho phép không tính');
        $this->assertSame(['worked' => 240, 'late' => 240, 'early' => 0], $s->compute($d, $this->at('13:20'), $this->at('17:40')), 'Chỉ làm buổi chiều');
        $this->assertSame(['worked' => 420, 'late' => 0, 'early' => 60], $s->compute($d, $this->at('07:00'), $this->at('16:30')));
        $this->assertSame(['worked' => 480, 'late' => 0, 'early' => 0], $s->compute($d, $this->at('07:30'), $this->at('20:00')), 'Giờ ngoài ca chuẩn không tính vào giờ công (tăng ca tính riêng)');
        $this->assertSame(480, $s->scheduledMinutes($d));
        $this->assertTrue($s->isWorkDay($d));
        $this->assertFalse($s->isWorkDay(Carbon::parse('2026-10-25')));   // Chủ nhật
    }

    public function test_punch_in_and_out_with_selfie_gps_and_server_time(): void
    {
        WorkLocation::create(['name' => 'Văn phòng HCM', 'latitude' => self::OFFICE[0], 'longitude' => self::OFFICE[1], 'radius_m' => 150]);
        $user = $this->userWithRole('Sales Employee');

        $this->travelTo($this->at('07:35'));
        $this->punch($user, 'in')->assertForbidden();   // chưa đồng ý thu thập ảnh / vị trí
        $this->post(route('attendance.consent'), ['agree' => 1])->assertSessionHas('success');

        $this->punch($user, 'in')->assertRedirect(route('attendance.index'))->assertSessionMissing('warning');
        $this->punch($user, 'in')->assertSessionHasErrors('type');   // không chấm vào hai lần
        $r = AttendanceRecord::firstOrFail();
        $this->assertTrue($r->check_in_within);
        $this->assertLessThan(30, $r->check_in_distance);
        $this->assertSame('incomplete', $r->status);
        Storage::disk('local')->assertExists($r->check_in_selfie);
        $this->assertStringStartsWith('attendance/', $r->check_in_selfie);
        $this->assertArrayNotHasKey('check_in_lat', $r->toArray(), 'Tọa độ không lộ ra JSON');

        $this->travelTo($this->at('17:31'));
        $this->punch($user, 'out')->assertSessionHas('success');
        $r->refresh();
        $this->assertSame([475, 0, 'present'], [$r->worked_minutes, $r->late_minutes, $r->status]);
        $this->get(route('attendance.index'))->assertOk()->assertSee('07:35')->assertSee('17:31')->assertSee(__('Bạn đã chấm công đủ hôm nay.'));
    }

    public function test_out_of_area_is_flagged_and_json_warns(): void
    {
        WorkLocation::create(['name' => 'Văn phòng HCM', 'latitude' => self::OFFICE[0], 'longitude' => self::OFFICE[1], 'radius_m' => 150]);
        $user = $this->consentedUser();
        $this->travelTo($this->at('07:30'));
        $this->actingAs($user)->postJson(route('attendance.punch'), ['type' => 'in', 'latitude' => 10.80, 'longitude' => 106.70, 'accuracy' => 10,
            'selfie' => UploadedFile::fake()->image('s.jpg')])->assertOk()->assertJsonPath('warning', __('Vị trí của bạn nằm ngoài khu vực văn phòng — bản ghi được đánh dấu để quản lý xem xét.'));
        $r = AttendanceRecord::firstOrFail();
        $this->assertFalse($r->check_in_within);
        $this->assertTrue($r->needs_review);
        $this->assertGreaterThan(2000, $r->check_in_distance);
        $this->postJson(route('attendance.punch'), ['type' => 'out', 'latitude' => 10.8, 'longitude' => 106.7, 'selfie' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertStatus(422);
    }

    public function test_board_selfie_and_adjustment_permissions(): void
    {
        $user = $this->consentedUser();
        $colleague = $this->consentedUser();
        $accountant = $this->userWithRole('Accountant');
        $manager = $this->userWithRole('Finance Manager');
        $this->travelTo($this->at('08:10'));
        $this->punch($user, 'in');
        $r = AttendanceRecord::firstOrFail();

        $this->actingAs($colleague)->get(route('attendance.records.show', $r))->assertNotFound();
        $this->get(route('attendance.records.selfie', [$r, 'in']))->assertNotFound();
        $this->get(route('attendance.board'))->assertForbidden();
        $this->actingAs($user)->get(route('attendance.records.selfie', [$r, 'in']))->assertOk();   // chính chủ

        $this->actingAs($accountant)->get(route('attendance.board', ['month' => '2026-10']))->assertOk()->assertSee($user->name);
        $this->get(route('attendance.records.selfie', [$r, 'in']))->assertOk();
        $this->put(route('attendance.records.adjust', $r), ['check_in_time' => '07:30', 'reason' => 'Quên'])->assertForbidden();   // chỉ xem

        $this->actingAs($manager)->put(route('attendance.records.adjust', $r), ['check_in_time' => '07:30', 'check_out_time' => '17:30'])->assertSessionHasErrors('reason');
        $this->put(route('attendance.records.adjust', $r), ['check_in_time' => '07:30', 'check_out_time' => '17:30', 'reason' => 'Kẹt thang máy, có xác nhận bảo vệ'])->assertSessionHas('success');
        $r->refresh();
        $this->assertSame([480, 0, 'present', $manager->id], [$r->worked_minutes, $r->late_minutes, $r->status, $r->adjusted_by]);
        $this->assertTrue(\App\Models\ActivityLog::where('description', 'like', '%Kẹt thang máy%')->exists());

        $this->post(route('attendance.records.store'), ['user_id' => $colleague->id, 'work_date' => '2026-10-20', 'check_in_time' => '07:30', 'check_out_time' => '11:30', 'reason' => 'Quên chấm'])
            ->assertSessionHas('success');
        $this->assertSame(240, AttendanceRecord::where('user_id', $colleague->id)->value('worked_minutes'));
    }

    public function test_settings_locations_prune_and_isolation(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $this->actingAs($owner)->put(route('attendance.settings.update'), ['work_morning_start' => '08:00', 'work_morning_end' => '12:00', 'work_afternoon_start' => '13:00',
            'work_afternoon_end' => '17:00', 'work_ot_start' => '18:00', 'work_ot_end' => '21:00', 'work_days' => [1, 2, 3, 4, 5, 6], 'attendance_late_grace' => 10, 'attendance_selfie_days' => 30])
            ->assertSessionHas('success');
        // Ca mới 8:00–12:00, 13:00–17:00, cho phép trễ 10 phút: vào 8:05 → không tính trễ, giờ công 475 phút
        $this->assertSame(['worked' => 475, 'late' => 0, 'early' => 0], app(AttendanceService::class)->compute($this->at('00:00'), $this->at('08:05'), $this->at('17:00')));
        $this->put(route('attendance.settings.update'), ['work_morning_start' => '12:00', 'work_morning_end' => '08:00', 'work_afternoon_start' => '13:00', 'work_afternoon_end' => '17:00',
            'work_ot_start' => '18:00', 'work_ot_end' => '21:00', 'work_days' => [1], 'attendance_late_grace' => 5, 'attendance_selfie_days' => 30])->assertSessionHasErrors('work_morning_start');

        $this->post(route('attendance.locations.store'), ['name' => 'Kho Bình Dương', 'latitude' => 10.98, 'longitude' => 106.65, 'radius_m' => 200])->assertSessionHas('success');
        $this->post(route('attendance.locations.store'), ['name' => 'Sai', 'latitude' => 100, 'longitude' => 106.65, 'radius_m' => 200])->assertSessionHasErrors('latitude');
        $this->actingAs($this->userWithRole('Sales Employee'))->get(route('attendance.settings'))->assertForbidden();

        // Dọn ảnh selfie quá 30 ngày
        $user = $this->consentedUser();
        $this->travelTo(now()->subDays(40));
        $this->punch($user, 'in');
        $this->travelBack();
        $old = AttendanceRecord::firstOrFail();
        $path = $old->check_in_selfie;
        $this->artisan('crm:prune-attendance-selfies')->assertSuccessful();
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($old->fresh()->check_in_selfie);
        $this->assertGreaterThanOrEqual(0, $old->fresh()->worked_minutes);

        // Tổ chức khác: văn phòng không được dùng để tính khoảng cách, bản ghi không xem được
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $recordB = app(CurrentOrganization::class)->run($orgB->id, function () {
            WorkLocation::create(['name' => 'VP B', 'latitude' => 21.0, 'longitude' => 105.8, 'radius_m' => 5000]);
            $u = User::factory()->create(['status' => 'active']);

            return AttendanceRecord::create(['user_id' => $u->id, 'work_date' => '2026-10-21']);
        });
        $this->assertSame('Kho Bình Dương', app(AttendanceService::class)->nearest(21.0, 105.8)[0]->name);
        $this->actingAs($owner)->get(route('attendance.records.show', $recordB))->assertNotFound();
    }
}
