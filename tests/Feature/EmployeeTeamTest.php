<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\EmployeeProfile;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\SalesTarget;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\EmployeeKpiService;
use App\Services\PaymentService;
use App\Services\SalesDocumentService;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/** Bước 4.2: hồ sơ nhân viên (thông tin nhạy cảm), sơ đồ báo cáo, nhóm, chi nhánh, KPI, cách ly tổ chức. */
class EmployeeTeamTest extends TestCase
{
    private const SECRET_ID = '079123456789';

    private function body($response): string
    {
        $base = $response->baseResponse;

        return $base instanceof BinaryFileResponse ? file_get_contents($base->getFile()->getPathname()) : (string) $response->getContent();
    }

    private function profilePayload(array $overrides = []): array
    {
        return $overrides + ['employee_code' => 'NV001', 'job_title' => 'Chuyên viên kinh doanh', 'employment_status' => 'active', 'hire_date' => '2025-03-01',
            'work_phone' => '0281234567', 'date_of_birth' => '1995-05-20', 'id_number' => self::SECRET_ID, 'personal_phone' => '0909000111',
            'home_address' => '12 Đường Bí Mật', 'hr_notes' => 'Ghi chú lương thưởng'];
    }

    public function test_sensitive_profile_data_is_encrypted_and_only_visible_with_permission(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $staff = $this->userWithRole('Sales Employee', ['name' => 'Nhân viên A']);
        $colleague = $this->userWithRole('Employee');

        $this->actingAs($owner)->put(route('employees.update', $staff->id), $this->profilePayload())->assertRedirect(route('employees.show', $staff->id));
        $profile = EmployeeProfile::where('user_id', $staff->id)->firstOrFail();
        $this->assertSame(self::SECRET_ID, $profile->id_number);
        $raw = DB::table('employee_profiles')->where('id', $profile->id)->value('id_number');
        $this->assertNotSame(self::SECRET_ID, $raw, 'Số CCCD phải được mã hóa trong CSDL');
        $this->assertStringNotContainsString(self::SECRET_ID, (string) json_encode($profile));
        $this->assertStringNotContainsString(self::SECRET_ID, ActivityLog::all()->toJson(), 'Nhật ký không được chứa thông tin nhạy cảm');

        $this->get(route('employees.show', $staff->id))->assertOk()->assertSee(self::SECRET_ID)->assertSee('12 Đường Bí Mật');

        // Đồng nghiệp: thấy thông tin công việc, không thấy thông tin nhạy cảm, không sửa / xuất được
        $this->actingAs($colleague)->get(route('employees.index'))->assertOk()->assertSee('Nhân viên A')->assertSee('Chuyên viên kinh doanh')->assertDontSee(self::SECRET_ID);
        $this->get(route('employees.show', $staff->id))->assertOk()->assertSee('Chuyên viên kinh doanh')
            ->assertDontSee(self::SECRET_ID)->assertDontSee('12 Đường Bí Mật')->assertDontSee('Ghi chú lương thưởng');
        $this->get(route('employees.edit', $staff->id))->assertForbidden();
        $this->put(route('employees.update', $staff->id), $this->profilePayload(['job_title' => 'Bị sửa']))->assertForbidden();
        $this->get(route('employees.export'))->assertForbidden();
        $this->get(route('employees.index', ['q' => self::SECRET_ID]))->assertOk()->assertDontSee('Nhân viên A');
    }

    public function test_export_includes_sensitive_columns_only_with_permission(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $staff = $this->userWithRole('Sales Employee', ['name' => 'Nhân viên Xuất']);
        $this->actingAs($owner)->put(route('employees.update', $staff->id), $this->profilePayload());

        $this->assertStringContainsString(self::SECRET_ID, $this->body($this->get(route('employees.export', ['format' => 'csv']))->assertOk()));

        // Người chỉ có quyền xuất (không có quyền xem thông tin nhạy cảm)
        $hr = $this->userWithRole('Employee');
        $hr->givePermissionTo('employees.export');
        $csv = $this->body($this->actingAs($hr)->get(route('employees.export', ['format' => 'csv']))->assertOk());
        $this->assertStringContainsString('Nhân viên Xuất', $csv);
        $this->assertStringNotContainsString(self::SECRET_ID, $csv);
        $this->assertStringNotContainsString('12 Đường Bí Mật', $csv);

        // Người chỉ có quyền sửa hồ sơ (không xem nhạy cảm) không ghi đè được trường nhạy cảm
        $editor = $this->userWithRole('Employee');
        $editor->givePermissionTo('employees.update');
        $this->actingAs($editor)->put(route('employees.update', $staff->id), $this->profilePayload(['id_number' => '000', 'job_title' => 'Trưởng nhóm']))->assertRedirect();
        $this->assertSame(['Trưởng nhóm', self::SECRET_ID], [EmployeeProfile::where('user_id', $staff->id)->value('job_title'), EmployeeProfile::where('user_id', $staff->id)->first()->id_number]);
        $this->get(route('employees.edit', $staff->id))->assertOk()->assertDontSee(self::SECRET_ID);
    }

    public function test_reporting_lines_reject_loops_and_render_org_chart(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $a = $this->userWithRole('Sales Manager', ['name' => 'Giám đốc A']);
        $b = $this->userWithRole('Sales Employee', ['name' => 'Trưởng phòng B']);
        $c = $this->userWithRole('Sales Employee', ['name' => 'Nhân viên C']);
        $this->actingAs($owner);
        $base = ['employment_status' => 'active'];

        $this->put(route('employees.update', $b->id), $base + ['manager_id' => $a->id])->assertRedirect();
        $this->put(route('employees.update', $c->id), $base + ['manager_id' => $b->id])->assertRedirect();
        $this->put(route('employees.update', $a->id), $base + ['manager_id' => $c->id])->assertSessionHasErrors('manager_id');   // A → C → B → A
        $this->put(route('employees.update', $a->id), $base + ['manager_id' => $a->id])->assertSessionHasErrors('manager_id');

        $chart = $this->get(route('employees.chart'))->assertOk()->assertSeeInOrder(['Giám đốc A', 'Trưởng phòng B', 'Nhân viên C']);
        $this->assertTrue($chart->viewData('children')->get($a->id)->contains('id', $b->id));
        $this->get(route('employees.show', $b->id))->assertSee('Giám đốc A')->assertSee('Nhân viên C');
    }

    public function test_kpis_match_real_data(): void
    {
        $owner = $this->admin();
        $sale = $this->userWithRole('Sales Employee');
        $this->actingAs($owner);

        // Doanh thu: 2 khoản thu trong tháng (một hóa đơn của người khác không tính); chỉ tiêu 10 triệu
        $inv = app(SalesDocumentService::class)->save(new Invoice, ['customer_id' => $this->customer()->id, 'issue_date' => today(), 'due_date' => today()->addDays(10),
            'status' => 'issued', 'tax_rate' => 0, 'discount_amount' => 0, 'assigned_to' => $sale->id], [['description' => 'X', 'quantity' => 1, 'unit_price' => 5000000]]);
        app(PaymentService::class)->record($inv, ['amount' => 1500000], $owner);
        app(PaymentService::class)->record($inv, ['amount' => 1000000], $owner);
        $other = app(SalesDocumentService::class)->save(new Invoice, ['customer_id' => $this->customer()->id, 'issue_date' => today(), 'status' => 'issued',
            'tax_rate' => 0, 'discount_amount' => 0, 'assigned_to' => $owner->id], [['description' => 'Y', 'quantity' => 1, 'unit_price' => 900000]]);
        app(PaymentService::class)->record($other, ['amount' => 900000], $owner);
        SalesTarget::create(['user_id' => $sale->id, 'year' => now()->year, 'month' => now()->month, 'target_amount' => 10000000]);

        // Cơ hội thắng trong tháng: 1 (một cơ hội thắng từ năm ngoái không tính)
        $won = PipelineStage::where('is_won', true)->firstOrFail();
        Opportunity::create(['title' => 'Thắng', 'customer_id' => $this->customer()->id, 'pipeline_stage_id' => $won->id, 'value' => 3000000, 'assigned_to' => $sale->id, 'closed_at' => now()]);
        Opportunity::create(['title' => 'Thắng cũ', 'customer_id' => $this->customer()->id, 'pipeline_stage_id' => $won->id, 'value' => 1, 'assigned_to' => $sale->id, 'closed_at' => now()->subYear()]);

        // Việc: 2 hoàn thành trong tháng, 1 đúng hạn
        Task::create(['title' => 'Đúng hạn', 'priority' => 'low', 'status' => 'completed', 'assigned_to' => $sale->id, 'due_date' => now()->addDay(), 'completed_at' => now()]);
        Task::create(['title' => 'Trễ', 'priority' => 'low', 'status' => 'completed', 'assigned_to' => $sale->id, 'due_date' => now()->subDays(2), 'completed_at' => now()]);
        SupportTicket::create(['customer_id' => $this->customer()->id, 'subject' => 'T', 'description' => 'x', 'priority' => 'low', 'status' => 'resolved', 'assigned_to' => $sale->id, 'resolved_at' => now()]);

        [$from, $to] = EmployeeKpiService::period('month');
        $k = app(EmployeeKpiService::class)->forUsers([$sale->id], $from, $to)[$sale->id];
        $this->assertSame(['2500000.00', '10000000.00', 25, 1, '3000000.00', 2, 1, 50, 1],
            [$k['revenue'], $k['target'], $k['achievement'], $k['deals_won'], $k['deals_value'], $k['tasks_done'], $k['tasks_on_time'], $k['on_time_rate'], $k['tickets_resolved']]);

        $this->get(route('employees.show', $sale->id))->assertOk()->assertSee(money('2500000'))->assertSee('25%');
    }

    public function test_teams_are_managed_by_authorized_users_and_sum_member_kpis(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $lead = $this->userWithRole('Sales Manager', ['name' => 'Trưởng nhóm X']);
        $m1 = $this->userWithRole('Sales Employee');
        $this->actingAs($owner)->post(route('teams.store'), ['name' => 'Nhóm Bắc', 'leader_id' => $lead->id, 'members' => [$m1->id]])->assertRedirect();
        $team = Team::where('name', 'Nhóm Bắc')->firstOrFail();
        $this->assertEqualsCanonicalizing([$lead->id, $m1->id], $team->members()->pluck('users.id')->all(), 'Trưởng nhóm tự được thêm làm thành viên');
        $this->post(route('teams.store'), ['name' => 'Nhóm Bắc'])->assertSessionHasErrors('name');

        Task::create(['title' => 'A', 'priority' => 'low', 'status' => 'completed', 'assigned_to' => $lead->id, 'completed_at' => now()]);
        Task::create(['title' => 'B', 'priority' => 'low', 'status' => 'completed', 'assigned_to' => $m1->id, 'completed_at' => now()]);
        $page = $this->get(route('teams.show', $team))->assertOk()->assertSee('Trưởng nhóm X');
        $this->assertSame(2, $page->viewData('total')['tasks_done']);
        $this->get(route('teams.index'))->assertOk()->assertSee('Nhóm Bắc');

        $this->actingAs($m1)->get(route('teams.show', $team))->assertOk();
        $this->get(route('teams.create'))->assertForbidden();
        $this->put(route('teams.update', $team), ['name' => 'Đổi tên'])->assertForbidden();
        $this->delete(route('teams.destroy', $team))->assertForbidden();
        $this->get(route('branches.index'))->assertForbidden();

        $this->actingAs($owner)->delete(route('teams.destroy', $team))->assertRedirect(route('teams.index'));
        $this->assertSame(0, DB::table('team_members')->where('team_id', $team->id)->count());
    }

    public function test_branches_crud_and_cannot_delete_branch_in_use(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $staff = $this->userWithRole('Employee');
        $this->actingAs($owner)->post(route('branches.store'), ['name' => 'Chi nhánh Hà Nội', 'code' => 'HN', 'is_active' => '1'])->assertSessionHas('success');
        $branch = Branch::where('name', 'Chi nhánh Hà Nội')->firstOrFail();
        $this->put(route('branches.update', $branch), ['name' => 'Chi nhánh Hà Nội 1'])->assertSessionHas('success');
        $this->assertFalse($branch->fresh()->is_active);

        $this->put(route('employees.update', $staff->id), ['employment_status' => 'active', 'branch_id' => $branch->id])->assertRedirect();
        $this->delete(route('branches.destroy', $branch))->assertStatus(422);
        $this->get(route('employees.index', ['branch_id' => $branch->id]))->assertSee($staff->name);
    }

    public function test_employees_and_teams_are_isolated_between_organizations(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        app(\App\Support\Tenancy\RoleProvisioner::class)->provision($orgB->id);
        [$userB, $teamB, $branchB] = app(CurrentOrganization::class)->run($orgB->id, function () {
            $u = User::factory()->create(['name' => 'Nhân viên bí mật B', 'status' => 'active']);

            return [$u, Team::create(['name' => 'Nhóm bí mật B']), Branch::create(['name' => 'Chi nhánh B'])];
        });

        $this->actingAs($owner)->get(route('employees.index'))->assertOk()->assertDontSee('Nhân viên bí mật B');
        $this->get(route('employees.show', $userB->id))->assertNotFound();
        $this->put(route('employees.update', $userB->id), ['employment_status' => 'active'])->assertNotFound();
        $this->get(route('teams.index'))->assertDontSee('Nhóm bí mật B');
        $this->get(route('teams.show', $teamB))->assertNotFound();
        $this->put(route('employees.update', $owner->id), ['employment_status' => 'active', 'branch_id' => $branchB->id])->assertSessionHasErrors('branch_id');
        $this->put(route('employees.update', $owner->id), ['employment_status' => 'active', 'manager_id' => $userB->id])->assertSessionHasErrors('manager_id');
        $this->post(route('teams.store'), ['name' => 'Nhóm A', 'members' => [$userB->id]])->assertSessionHasErrors('members.0');
    }
}
