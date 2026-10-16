<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Task;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/**
 * Bước 2.8 — Thùng rác: xem & khôi phục bản ghi đã xóa (xóa mềm) theo quyền xóa, phạm vi xem và tổ chức.
 */
class TrashTest extends TestCase
{
    public function test_deleted_record_can_be_restored_with_clear_log(): void
    {
        $admin = $this->admin();
        $lead = Lead::create(['name' => 'Lead Thùng Rác', 'status' => 'new']);

        $this->actingAs($admin)->delete(route('leads.destroy', $lead))
            ->assertSessionHas('success', fn ($m) => str_contains($m, __('Có thể khôi phục trong Thùng rác.')));
        $this->assertSoftDeleted($lead);

        $page = $this->get(route('trash.index', ['type' => 'leads']))->assertOk()->assertSee('Lead Thùng Rác');
        $this->assertSame($admin->name, $page->viewData('deleters')[$lead->id]->user->name);   // cột "Người xóa"
        $this->post(route('trash.restore', ['leads', $lead->id]))->assertSessionHas('success');
        $this->assertNotSoftDeleted($lead);
        $this->get(route('leads.show', $lead))->assertOk();

        $logs = ActivityLog::where('subject_type', $lead->getMorphClass())->where('subject_id', $lead->id)->pluck('action')->all();
        $this->assertContains('deleted', $logs);
        $this->assertContains('restored', $logs);
        $this->assertNotContains('updated', $logs, 'Khôi phục không được ghi thành "Cập nhật"');
        $this->get(route('trash.index', ['type' => 'leads']))->assertDontSee('Lead Thùng Rác');
    }

    public function test_only_types_the_user_may_delete_are_shown(): void
    {
        $sale = $this->userWithRole('Sales Employee');   // không có quyền xóa module nào
        $this->actingAs($sale)->get(route('trash.index'))->assertForbidden();
        $this->assertNotContains('trash.index', \App\Support\Menu::flatRoutes($sale));
        $this->assertContains('trash.index', \App\Support\Menu::flatRoutes($this->admin()));

        $taskOnly = User::factory()->create(['status' => 'active']);
        $taskOnly->givePermissionTo(['tasks.view', 'tasks.delete']);
        $lead = Lead::create(['name' => 'Lead bí mật', 'status' => 'new']);
        $lead->delete();

        $this->actingAs($taskOnly)->get(route('trash.index'))->assertOk()->assertDontSee(__('Leads'));
        $this->get(route('trash.index', ['type' => 'leads']))->assertSessionHasErrors('type');
        $this->post(route('trash.restore', ['leads', $lead->id]))->assertForbidden();
        $this->assertSoftDeleted($lead);
    }

    public function test_staff_only_see_and_restore_their_own_deleted_records(): void
    {
        $me = User::factory()->create(['status' => 'active']);
        $me->givePermissionTo(['tasks.view', 'tasks.delete']);   // không có tasks.view_all
        $other = $this->userWithRole('Sales Employee');
        $mine = Task::create(['title' => 'Việc của tôi', 'priority' => 'low', 'status' => 'pending', 'assigned_to' => $me->id]);
        $theirs = Task::create(['title' => 'Việc của đồng nghiệp', 'priority' => 'low', 'status' => 'pending', 'assigned_to' => $other->id]);
        $mine->delete();
        $theirs->delete();

        $this->actingAs($me)->get(route('trash.index', ['type' => 'tasks']))->assertOk()
            ->assertSee('Việc của tôi')->assertDontSee('Việc của đồng nghiệp');
        $this->post(route('trash.restore', ['tasks', $theirs->id]))->assertNotFound();
        $this->assertSoftDeleted($theirs);
        $this->post(route('trash.restore', ['tasks', $mine->id]))->assertSessionHas('success');
        $this->assertNotSoftDeleted($mine);
    }

    public function test_other_organizations_trash_is_unreachable(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);   // không phải quản trị nền tảng
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $product = app(CurrentOrganization::class)->run($orgB->id, function () {
            $p = Product::create(['sku' => 'B-01', 'name' => 'Sản phẩm của B', 'price' => 1, 'status' => 'active']);
            $p->delete();

            return $p;
        });

        $this->actingAs($owner)->get(route('trash.index', ['type' => 'products']))->assertOk()->assertDontSee('Sản phẩm của B');
        $this->post(route('trash.restore', ['products', $product->id]))->assertNotFound();
        $this->assertTrue(Product::withoutGlobalScopes()->withTrashed()->find($product->id)->trashed());
    }

    public function test_invalid_type_and_guest_are_rejected(): void
    {
        $this->get(route('trash.index'))->assertRedirect(route('login'));
        $this->actingAs($this->admin())->post('/trash/users/1/restore')->assertNotFound();
        $this->get(route('trash.index'))->assertOk()->assertSee(__('Thùng rác trống'));
    }
}
