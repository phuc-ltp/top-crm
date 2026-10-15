<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Cô lập dữ liệu giữa các tổ chức: người dùng tổ chức A (kể cả Super Admin có quyền xem tất cả)
 * không được thấy, sửa, xóa, tìm, xuất, tham chiếu hay giao việc cho dữ liệu / thành viên của tổ chức B.
 */
class TenantIsolationTest extends TestCase
{
    private const MARK = 'BIMAT-B';

    private User $userA;

    private User $userB;

    private Organization $orgB;

    /** @var array<string, \Illuminate\Database\Eloquent\Model> */
    private array $b = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Chủ sở hữu tổ chức mặc định (A): toàn quyền trong tổ chức nhưng KHÔNG phải quản trị nền tảng
        // (quản trị nền tảng được thấy mọi tổ chức theo thiết kế)
        $this->userA = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $this->orgB = Organization::create(['name' => 'Công ty '.self::MARK, 'slug' => 'cong-ty-b']);
        app(\App\Support\Tenancy\RoleProvisioner::class)->provision($this->orgB->id);

        app(CurrentOrganization::class)->run($this->orgB->id, function () {
            $this->userB = User::factory()->create(['name' => 'Nhân viên '.self::MARK, 'status' => 'active']);
            $this->userB->assignRole('Sales Employee');
            $stage = PipelineStage::create(['name' => 'Giai đoạn '.self::MARK, 'slug' => 'gd-b', 'position' => 1, 'probability' => 10]);
            $customer = Customer::create(['name' => 'Khách '.self::MARK, 'type' => 'business', 'status' => 'active', 'email' => 'b@bimat.test', 'assigned_to' => $this->userB->id]);
            $this->b = [
                'customer' => $customer,
                'contact' => Contact::create(['full_name' => 'Liên hệ '.self::MARK, 'customer_id' => $customer->id]),
                'lead' => Lead::create(['name' => 'Lead '.self::MARK, 'status' => 'new']),
                'product' => Product::create(['sku' => 'SKU-BIMAT', 'name' => 'Sản phẩm '.self::MARK, 'price' => 100, 'status' => 'active']),
                'opportunity' => Opportunity::create(['title' => 'Cơ hội '.self::MARK, 'customer_id' => $customer->id, 'pipeline_stage_id' => $stage->id, 'value' => 1000]),
                'task' => Task::create(['title' => 'Việc '.self::MARK, 'priority' => 'high', 'status' => 'pending', 'due_date' => now()->addDay()]),
                'ticket' => SupportTicket::create(['customer_id' => $customer->id, 'subject' => 'Ticket '.self::MARK, 'description' => 'x', 'priority' => 'high', 'status' => 'open']),
                'campaign' => Campaign::create(['name' => 'Chiến dịch '.self::MARK, 'type' => 'email', 'status' => 'active']),
                'stage' => $stage,
            ];
        });
        $this->actingAs($this->userA);
    }

    private function csv($response): string
    {
        $base = $response->baseResponse;

        return $base instanceof BinaryFileResponse ? file_get_contents($base->getFile()->getPathname()) : (string) $response->getContent();
    }

    public function test_lists_never_show_other_organization_data(): void
    {
        foreach (['customers.index', 'contacts.index', 'leads.index', 'opportunities.index', 'opportunities.list', 'products.index',
            'tasks.index', 'tickets.index', 'campaigns.index', 'users.index', 'dashboard'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee(self::MARK);
        }
    }

    public function test_direct_access_to_other_organization_records_returns_404(): void
    {
        $routes = [
            ['customers.show', 'customer'], ['customers.edit', 'customer'], ['contacts.show', 'contact'], ['leads.show', 'lead'],
            ['leads.edit', 'lead'], ['opportunities.show', 'opportunity'], ['products.show', 'product'], ['tasks.show', 'task'],
            ['tickets.show', 'ticket'], ['campaigns.show', 'campaign'],
        ];
        foreach ($routes as [$route, $key]) {
            $this->get(route($route, $this->b[$key]))->assertNotFound();
        }
        $this->get(route('users.show', $this->userB))->assertNotFound();
        $this->get(route('users.edit', $this->userB))->assertNotFound();
    }

    public function test_other_organization_records_cannot_be_modified_or_deleted(): void
    {
        $this->put(route('customers.update', $this->b['customer']), ['name' => 'Bị sửa', 'type' => 'business', 'status' => 'active'])->assertNotFound();
        $this->delete(route('customers.destroy', $this->b['customer']))->assertNotFound();
        $this->delete(route('leads.destroy', $this->b['lead']))->assertNotFound();
        $this->delete(route('tasks.destroy', $this->b['task']))->assertNotFound();
        $this->patchJson(route('opportunities.move', $this->b['opportunity']), ['pipeline_stage_id' => $this->b['stage']->id])->assertNotFound();
        $this->delete(route('users.destroy', $this->userB))->assertNotFound();

        app(CurrentOrganization::class)->run($this->orgB->id, function () {
            $this->assertSame('Khách '.self::MARK, $this->b['customer']->fresh()->name);
            $this->assertNull(Customer::withTrashed()->find($this->b['customer']->id)->deleted_at);
            $this->assertNotNull(Lead::find($this->b['lead']->id));
        });
    }

    public function test_search_and_exports_exclude_other_organizations(): void
    {
        $this->getJson(route('search', ['q' => self::MARK]))->assertOk()->assertDontSee(self::MARK);
        foreach (['customers.export', 'contacts.export', 'leads.export', 'opportunities.export', 'products.export', 'tickets.export', 'campaigns.export'] as $route) {
            $response = $this->get(route($route, ['format' => 'csv']));
            $response->assertOk();
            $this->assertStringNotContainsString(self::MARK, $this->csv($response), $route.' làm lộ dữ liệu tổ chức khác');
        }
    }

    public function test_reports_and_dashboard_numbers_only_count_own_organization(): void
    {
        $this->get(route('reports.show', 'customers'))->assertOk()->assertDontSee(self::MARK);
        $this->get(route('reports.show', 'employees'))->assertOk()->assertDontSee(self::MARK);
        $data = $this->getJson(route('dashboard.data'))->assertOk()->json('data');
        $this->assertNotContains('Giai đoạn '.self::MARK, $data['funnel']['labels']);
    }

    public function test_api_is_scoped_to_organization(): void
    {
        $this->getJson(route('api.customers.index'))->assertOk()->assertDontSee(self::MARK);
        $this->getJson(route('api.customers.show', $this->b['customer']))->assertNotFound();
        $this->getJson(route('api.leads.show', $this->b['lead']))->assertNotFound();
        $this->getJson(route('api.opportunities.show', $this->b['opportunity']))->assertNotFound();
        $this->putJson(route('api.customers.update', $this->b['customer']), ['name' => 'Bị sửa qua API'])->assertNotFound();
    }

    public function test_forms_reject_references_to_other_organization_records(): void
    {
        // Liên hệ gắn vào khách hàng của tổ chức B
        $this->post(route('contacts.store'), ['full_name' => 'Liên hệ A', 'customer_id' => $this->b['customer']->id])
            ->assertSessionHasErrors('customer_id');
        // Cơ hội dùng giai đoạn + khách hàng của tổ chức B
        $this->post(route('opportunities.store'), [
            'title' => 'Cơ hội A', 'customer_id' => $this->b['customer']->id, 'pipeline_stage_id' => $this->b['stage']->id, 'value' => 10,
        ])->assertSessionHasErrors(['customer_id', 'pipeline_stage_id']);
        $this->assertSame(0, Opportunity::where('title', 'Cơ hội A')->count());
    }

    public function test_work_cannot_be_assigned_to_members_of_another_organization(): void
    {
        $this->post(route('tasks.store'), ['title' => 'Việc A', 'priority' => 'low', 'status' => 'pending', 'assigned_to' => $this->userB->id])
            ->assertSessionHasErrors('assigned_to');
        $this->post(route('customers.bulk-assign'), ['ids' => [$this->customer()->id], 'assigned_to' => $this->userB->id])
            ->assertSessionHasErrors('assigned_to');
        $this->get(route('tasks.create'))->assertOk()->assertDontSee(self::MARK);   // không có trong danh sách chọn người phụ trách
    }

    public function test_same_codes_can_exist_in_each_organization(): void
    {
        $this->post(route('products.store'), ['sku' => 'SKU-BIMAT', 'name' => 'Sản phẩm A', 'price' => 50, 'status' => 'active', 'unit' => 'cái'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Product::where('sku', 'SKU-BIMAT')->count());   // chỉ thấy bản của A
        $this->post(route('products.store'), ['sku' => 'SKU-BIMAT', 'name' => 'Trùng trong A', 'price' => 50, 'status' => 'active', 'unit' => 'cái'])
            ->assertSessionHasErrors('sku');
    }

    public function test_new_records_are_created_in_the_users_organization(): void
    {
        $this->post(route('leads.store'), ['name' => 'Lead của A', 'status' => 'new'])->assertSessionHasNoErrors();
        $lead = Lead::where('name', 'Lead của A')->firstOrFail();
        $this->assertSame(Organization::DEFAULT_ID, (int) $lead->organization_id);
    }

    public function test_user_removed_from_organization_is_signed_out(): void
    {
        $this->get(route('dashboard'))->assertOk();
        $this->userA->organizations()->updateExistingPivot(Organization::DEFAULT_ID, ['status' => 'suspended']);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_of_suspended_organization_moves_to_another_active_one(): void
    {
        $this->userB->organizations()->attach(Organization::DEFAULT_ID, ['status' => 'active', 'joined_at' => now()]);
        $this->userB->assignRole('Sales Employee');   // vai trò ở tổ chức A (vai trò thuộc từng tổ chức)
        $this->userB->unsetRelation('roles');
        $this->orgB->update(['status' => 'suspended']);
        $this->actingAs($this->userB->fresh())->get(route('tasks.index'))->assertOk()->assertDontSee('Việc '.self::MARK);
        $this->assertSame(Organization::DEFAULT_ID, (int) $this->userB->fresh()->current_organization_id);
    }

    public function test_organization_b_sees_its_own_data_and_not_a(): void
    {
        $aCustomer = $this->customer(['name' => 'Khách riêng của A']);
        $this->actingAs($this->userB);
        $this->get(route('customers.index'))->assertOk()->assertSee('Khách '.self::MARK)->assertDontSee('Khách riêng của A');
        $this->get(route('customers.show', $aCustomer))->assertNotFound();
    }

    public function test_child_records_of_other_organizations_are_unreachable(): void
    {
        [$item, $interaction] = app(CurrentOrganization::class)->run($this->orgB->id, fn () => [
            $this->b['task']->checklist()->create(['content' => 'Mục '.self::MARK, 'position' => 1]),
            $this->b['customer']->interactions()->create(['type' => 'note', 'content' => 'Ghi chú '.self::MARK, 'user_id' => $this->userB->id]),
        ]);
        $this->patch(route('tasks.checklist.toggle', [$this->b['task'], $item]))->assertNotFound();
        $this->delete(route('tasks.checklist.destroy', [$this->b['task'], $item]))->assertNotFound();
        $this->delete(route('interactions.destroy', $interaction))->assertNotFound();
        // Ghép công việc của A với mục checklist của B cũng bị chặn
        $ownTask = Task::create(['title' => 'Việc của A', 'priority' => 'low', 'status' => 'pending']);
        $this->patch(route('tasks.checklist.toggle', [$ownTask, $item]))->assertNotFound();
        $this->assertFalse((bool) $item->fresh()->is_done);
    }

    public function test_records_cannot_be_moved_between_organizations(): void
    {
        $this->expectException(\LogicException::class);
        $customer = $this->customer();
        $customer->forceFill(['organization_id' => $this->orgB->id])->save();
    }
}
