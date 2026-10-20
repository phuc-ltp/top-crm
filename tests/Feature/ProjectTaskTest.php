<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Bước 4.1: dự án, thành viên, việc con, phụ thuộc, Kanban, khối lượng công việc, phân quyền & cách ly tổ chức. */
class ProjectTaskTest extends TestCase
{
    private function makeTask(array $attributes = []): Task
    {
        return Task::create($attributes + ['title' => 'Việc '.uniqid(), 'priority' => 'medium', 'status' => 'pending', 'due_date' => now()->addDays(2)]);
    }

    private function projectPayload(array $overrides = []): array
    {
        return $overrides + ['name' => 'Triển khai CRM', 'status' => 'active', 'priority' => 'high', 'start_date' => today()->toDateString(),
            'end_date' => today()->addMonth()->toDateString(), 'budget' => '50000000'];
    }

    public function test_project_manager_creates_project_members_are_notified_and_see_project_tasks(): void
    {
        $pm = $this->userWithRole('Project Manager');
        $member = $this->userWithRole('Employee');
        $outsider = $this->userWithRole('Employee');

        $this->actingAs($pm)->post(route('projects.store'), $this->projectPayload(['manager_id' => $pm->id, 'members' => [$member->id]]))
            ->assertRedirect()->assertSessionHas('success');
        $project = Project::firstOrFail();
        $this->assertMatchesRegularExpression('/^DA\d{5}$/', $project->code);
        $this->assertSame([$member->id], $project->members()->pluck('users.id')->all());
        $this->assertSame('project_member', $member->notifications()->first()->data['kind']);

        // Việc do PM tạo và giao cho PM: thành viên vẫn xem được vì cùng dự án
        $task = $this->makeTask(['project_id' => $project->id, 'assigned_to' => $pm->id, 'created_by' => $pm->id, 'estimated_hours' => 4]);
        $this->makeTask(['project_id' => $project->id, 'status' => 'completed', 'assigned_to' => $pm->id, 'created_by' => $pm->id]);
        $this->makeTask(['project_id' => $project->id, 'status' => 'cancelled', 'assigned_to' => $pm->id, 'created_by' => $pm->id]);
        $this->assertSame(50, Project::withProgress()->find($project->id)->progress, '1 xong / 2 việc không hủy');

        $this->actingAs($member)->get(route('projects.index'))->assertOk()->assertSee('Triển khai CRM');
        $this->get(route('projects.show', $project))->assertOk()->assertSee($task->title)->assertSee('50%');
        $this->get(route('tasks.show', $task))->assertOk();
        $this->get(route('tasks.index'))->assertSee($task->title);
        $this->get(route('projects.edit', $project))->assertForbidden();
        $this->patch(route('tasks.status', $task), ['status' => 'in_progress'])->assertForbidden();

        $this->actingAs($outsider)->get(route('projects.index'))->assertOk()->assertDontSee('Triển khai CRM');
        $this->get(route('projects.show', $project))->assertForbidden();
        $this->get(route('tasks.show', $task))->assertForbidden();
        $this->get(route('tasks.index'))->assertDontSee($task->title);

        // Người quản lý dự án sửa được việc trong dự án dù không phụ trách
        $other = $this->makeTask(['project_id' => $project->id, 'assigned_to' => $member->id, 'created_by' => $member->id]);
        $this->actingAs($pm)->patch(route('tasks.status', $other), ['status' => 'in_progress'])->assertRedirect();
        $this->assertSame('in_progress', $other->fresh()->status);
    }

    public function test_project_validation_and_non_member_cannot_put_tasks_into_project(): void
    {
        $pm = $this->userWithRole('Project Manager');
        $this->actingAs($pm)->post(route('projects.store'), $this->projectPayload(['manager_id' => $pm->id, 'end_date' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('end_date');
        $project = Project::create($this->projectPayload(['manager_id' => $pm->id, 'created_by' => $pm->id]));

        $employee = $this->userWithRole('Employee');
        $this->actingAs($employee)->post(route('tasks.store'), ['title' => 'Chen vào', 'priority' => 'low', 'status' => 'pending', 'project_id' => $project->id])
            ->assertSessionHasErrors('project_id');
        $this->post(route('projects.store'), $this->projectPayload(['manager_id' => $employee->id]))->assertForbidden();
    }

    public function test_subtasks_inherit_project_and_are_one_level_only(): void
    {
        $admin = $this->admin();
        $project = Project::create($this->projectPayload(['manager_id' => $admin->id]));
        $parent = $this->makeTask(['project_id' => $project->id, 'assigned_to' => $admin->id]);

        $this->actingAs($admin)->get(route('tasks.create', ['parent_id' => $parent->id]))->assertOk()->assertSee($parent->title);
        $this->post(route('tasks.store'), ['title' => 'Việc con A', 'priority' => 'low', 'status' => 'pending', 'parent_id' => $parent->id, 'project_id' => ''])
            ->assertRedirect();
        $child = Task::where('title', 'Việc con A')->firstOrFail();
        $this->assertSame($project->id, $child->project_id);
        $this->assertSame($parent->id, $child->parent_id);
        $this->get(route('tasks.show', $parent))->assertSee('Việc con A');

        $this->post(route('tasks.store'), ['title' => 'Cháu', 'priority' => 'low', 'status' => 'pending', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');
        $other = $this->makeTask();
        $this->put(route('tasks.update', $parent), ['title' => $parent->title, 'priority' => 'low', 'status' => 'pending', 'parent_id' => $other->id])
            ->assertSessionHasErrors('parent_id');
        $this->put(route('tasks.update', $parent), ['title' => $parent->title, 'priority' => 'low', 'status' => 'pending', 'parent_id' => $parent->id])
            ->assertSessionHasErrors('parent_id');

        // Đổi dự án của việc cha → việc con đi theo
        $project2 = Project::create($this->projectPayload(['name' => 'Dự án 2', 'manager_id' => $admin->id]));
        $this->put(route('tasks.update', $parent), ['title' => $parent->title, 'priority' => 'low', 'status' => 'pending', 'project_id' => $project2->id])->assertRedirect();
        $this->assertSame($project2->id, $child->fresh()->project_id);

        // Gửi thiếu trường dự án / việc cha (form, API cũ) → giữ nguyên, không vô tình gỡ liên kết
        $this->put(route('tasks.update', $child), ['title' => 'Việc con A', 'priority' => 'low', 'status' => 'pending'])->assertRedirect();
        $this->assertSame([$parent->id, $project2->id], [$child->fresh()->parent_id, $child->fresh()->project_id]);
    }

    public function test_dependencies_block_completion_and_reject_cycles(): void
    {
        $admin = $this->admin();
        $a = $this->makeTask(['title' => 'Thiết kế']);
        $b = $this->makeTask(['title' => 'Lập trình']);
        $c = $this->makeTask(['title' => 'Kiểm thử']);
        $this->actingAs($admin);

        $this->post(route('tasks.dependencies.store', $b), ['depends_on_id' => $a->id])->assertSessionHas('success');
        $this->post(route('tasks.dependencies.store', $c), ['depends_on_id' => $b->id])->assertSessionHas('success');
        $this->post(route('tasks.dependencies.store', $a), ['depends_on_id' => $c->id])->assertSessionHasErrors('depends_on_id');   // vòng A→C→B→A
        $this->post(route('tasks.dependencies.store', $a), ['depends_on_id' => $a->id])->assertSessionHasErrors('depends_on_id');
        $this->assertSame(2, \Illuminate\Support\Facades\DB::table('task_dependencies')->count());

        // B chưa hoàn thành được khi A chưa xong — cả form sửa, nút trạng thái và Kanban (JSON)
        $this->patch(route('tasks.status', $b), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->put(route('tasks.update', $b), ['title' => 'Lập trình', 'priority' => 'low', 'status' => 'completed'])->assertSessionHasErrors('status');
        $this->patchJson(route('tasks.status', $b), ['status' => 'completed'])->assertStatus(422)->assertJsonFragment(['Chưa thể hoàn thành: cần xong trước các việc: Thiết kế.']);
        $this->patchJson(route('tasks.status', $b), ['status' => 'in_progress'])->assertOk();
        $this->get(route('tasks.show', $b))->assertSee(__('Đang chờ việc này'), false)->assertSee('Kiểm thử');

        $this->patch(route('tasks.status', $a), ['status' => 'completed']);
        $this->patchJson(route('tasks.status', $b), ['status' => 'completed'])->assertOk();
        $this->assertSame('completed', $b->fresh()->status);

        $this->delete(route('tasks.dependencies.destroy', [$c, $b->id]))->assertSessionHas('success');
        $this->delete(route('tasks.dependencies.destroy', [$c, $b->id]))->assertNotFound();
    }

    public function test_board_and_workload_show_real_numbers(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $sale = $this->userWithRole('Sales Employee', ['name' => 'Nhân viên Kanban']);
        $this->makeTask(['title' => 'Việc trễ', 'assigned_to' => $sale->id, 'due_date' => now()->subDay(), 'estimated_hours' => 2.5]);
        $this->makeTask(['title' => 'Việc đang làm', 'status' => 'in_progress', 'assigned_to' => $sale->id, 'due_date' => now()->addDays(3), 'estimated_hours' => 1.5]);
        $this->makeTask(['title' => 'Việc xong', 'status' => 'completed', 'completed_at' => now(), 'assigned_to' => $sale->id]);
        $this->makeTask(['title' => 'Việc chưa giao']);

        $board = $this->actingAs($manager)->get(route('tasks.board'))->assertOk()->assertSee('Việc trễ')->assertSee('Việc xong');
        $this->assertCount(1, $board->viewData('tasks')['in_progress']);

        $rows = $this->get(route('tasks.workload'))->assertOk()->assertSee('Nhân viên Kanban')->viewData('rows');
        $row = $rows->firstWhere('id', $sale->id);
        $this->assertSame([2, 1, 1, 1, 4.0, 1], [$row->open, $row->doing, $row->late, $row->due_week, $row->hours, $row->done]);
        $this->assertSame(1, $this->get(route('tasks.workload'))->viewData('unassigned'));

        // Nhân viên chỉ thấy dòng của chính mình
        $this->actingAs($sale)->get(route('tasks.workload'))->assertOk()->assertDontSee($manager->name);
    }

    public function test_projects_are_isolated_between_organizations(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        app(\App\Support\Tenancy\RoleProvisioner::class)->provision($orgB->id);
        [$projectB, $taskB] = app(CurrentOrganization::class)->run($orgB->id, function () {
            $userB = User::factory()->create(['status' => 'active']);

            return [Project::create($this->projectPayload(['name' => 'Dự án bí mật B', 'manager_id' => $userB->id])), $this->makeTask(['title' => 'Việc bí mật B'])];
        });

        $this->actingAs($owner)->get(route('projects.index'))->assertOk()->assertDontSee('Dự án bí mật B');
        $this->get(route('projects.show', $projectB))->assertNotFound();
        $this->delete(route('projects.destroy', $projectB))->assertNotFound();
        $this->post(route('tasks.store'), ['title' => 'X', 'priority' => 'low', 'status' => 'pending', 'project_id' => $projectB->id])->assertSessionHasErrors('project_id');
        $this->post(route('tasks.store'), ['title' => 'Y', 'priority' => 'low', 'status' => 'pending', 'parent_id' => $taskB->id])->assertSessionHasErrors('parent_id');
        $mine = $this->makeTask();
        $this->post(route('tasks.dependencies.store', $mine), ['depends_on_id' => $taskB->id])->assertSessionHasErrors('depends_on_id');
        $this->get(route('tasks.board'))->assertDontSee('Việc bí mật B');
    }
}
