<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskReminderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    public function test_task_lifecycle_checklist_comments_attachments(): void
    {
        Storage::fake('local');
        $manager = $this->userWithRole('Sales Manager');
        $staff = $this->userWithRole('Sales Employee');
        $this->actingAs($manager);

        $this->post(route('tasks.store'), [
            'title' => 'Gọi khách', 'priority' => 'high', 'status' => 'pending', 'due_date' => now()->addDay()->format('Y-m-d H:i'),
            'assigned_to' => $staff->id, 'checklist' => ['Chuẩn bị', 'Gọi'],
        ])->assertRedirect();
        $task = Task::firstOrFail();
        $this->assertSame(2, $task->checklist()->count());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $staff->id]);

        $this->actingAs($staff);
        $item = $task->checklist()->first();
        $this->patch(route('tasks.checklist.toggle', [$task, $item]));
        $this->assertTrue($item->fresh()->is_done);
        $this->post(route('tasks.comments.store', $task), ['body' => 'Đã gọi lần 1'])->assertSessionHas('success');

        $this->post(route('tasks.attachments.store', $task), ['file' => UploadedFile::fake()->create('bao-gia.pdf', 100, 'application/pdf')])->assertSessionHas('success');
        $attachment = $task->attachments()->first();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('tasks.attachments.download', [$task, $attachment]))->assertOk();
        $this->post(route('tasks.attachments.store', $task), ['file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');

        $this->patch(route('tasks.status', $task), ['status' => 'completed'])->assertSessionHas('success');
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_other_staff_cannot_download_attachment(): void
    {
        Storage::fake('local');
        $owner = $this->userWithRole('Sales Employee');
        $task = Task::create(['title' => 'Riêng', 'priority' => 'low', 'status' => 'pending', 'assigned_to' => $owner->id, 'created_by' => $owner->id]);
        $att = app(\App\Services\UploadService::class)->attach($task, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));

        $this->actingAs($this->userWithRole('Sales Employee'))->get(route('tasks.attachments.download', [$task, $att]))->assertForbidden();
    }

    public function test_task_reminders_and_overdue(): void
    {
        $u = $this->userWithRole('Sales Employee');
        $soon = Task::create(['title' => 'Sắp hạn', 'priority' => 'low', 'status' => 'pending', 'due_date' => now()->addHours(3), 'assigned_to' => $u->id]);
        $late = Task::create(['title' => 'Trễ', 'priority' => 'low', 'status' => 'in_progress', 'due_date' => now()->subHour(), 'assigned_to' => $u->id]);

        $result = app(TaskReminderService::class)->run();
        $this->assertSame(['due_soon' => 1, 'overdue' => 1, 'activities' => 0], $result);
        $this->assertSame('overdue', $late->fresh()->status);
        $this->assertNotNull($soon->fresh()->reminded_at);
        $this->assertSame(2, $u->notifications()->count());
        $this->assertSame(['due_soon' => 0, 'overdue' => 0, 'activities' => 0], app(TaskReminderService::class)->run()); // không nhắc lặp
    }

    public function test_campaign_and_lead_linking(): void
    {
        $this->actingAs($this->userWithRole('Marketing Employee'));
        $this->post(route('campaigns.store'), ['name' => 'Tết 2027', 'type' => 'social', 'budget' => 10000000, 'target_leads' => 10, 'status' => 'active'])->assertRedirect();
        $campaign = Campaign::firstOrFail();
        $leads = collect(range(1, 4))->map(fn ($i) => Lead::create(['name' => "L$i", 'status' => $i === 1 ? 'converted' : 'new']));

        $this->post(route('campaigns.leads.attach', $campaign), ['lead_ids' => $leads->pluck('id')->all()])->assertSessionHas('success');
        $campaign = Campaign::withCount(['leads', 'leads as converted_leads_count' => fn ($q) => $q->where('status', 'converted')])->find($campaign->id);
        $this->assertSame(4, $campaign->actual_leads);
        $this->assertEquals(25.0, $campaign->conversion_rate);
        $this->assertEquals(2500000, $campaign->cost_per_lead);
    }

    public function test_ticket_flow_with_internal_notes(): void
    {
        $agent = $this->userWithRole('Support Agent');
        $this->actingAs($this->userWithRole('Sales Manager'));
        $customer = $this->customer();

        $this->actingAs($agent)->post(route('tickets.store'), [
            'customer_id' => $customer->id, 'subject' => 'Lỗi in', 'description' => 'Máy không in', 'priority' => 'high', 'status' => 'open',
        ])->assertRedirect();
        $ticket = SupportTicket::firstOrFail();
        $this->assertMatchesRegularExpression('/^TK\d{5}$/', $ticket->code);

        $this->post(route('tickets.reply', $ticket), ['body' => 'Ghi chú nội bộ', 'is_internal' => 1]);
        $this->post(route('tickets.reply', $ticket), ['body' => 'Đã xử lý', 'status' => 'resolved']);
        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame(1, $ticket->replies()->where('is_internal', true)->count());
    }

    public function test_user_management(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('users.store'), [
            'name' => 'Nhân viên mới', 'email' => 'moi@example.com', 'status' => 'active', 'role' => 'Sales Employee',
            'password' => 'Abcdef1234', 'password_confirmation' => 'Abcdef1234',
        ])->assertRedirect();
        $user = User::where('email', 'moi@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Sales Employee'));

        $this->post(route('users.toggle-status', $user));
        $this->assertSame('suspended', $user->membershipStatus(), 'Khóa = tạm ngưng thành viên trong tổ chức');
        $this->put(route('users.password', $user), ['password' => 'MoiMoi12345', 'password_confirmation' => 'MoiMoi12345'])->assertSessionHas('success');
        $this->post(route('users.send-reset', $user))->assertSessionHas('success');
        $this->delete(route('users.destroy', $user))->assertRedirect();
        $this->assertSoftDeleted($user);

        $this->delete(route('users.destroy', auth()->user()))->assertStatus(422); // không tự xóa mình
    }

    public function test_settings_update(): void
    {
        $this->actingAs($this->admin());
        $this->put(route('settings.update'), [
            'company_name' => 'Công ty Cam', 'brand_color' => '#FF6B00', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND',
            'date_format' => 'd/m/Y', 'default_tax_rate' => 8, 'notify_task_due' => 1,
        ])->assertSessionHas('success');
        $this->assertSame('Công ty Cam', \App\Models\Setting::get('company_name'));
        $this->assertSame('0', \App\Models\Setting::get('notify_deal_stage'));
        $this->put(route('settings.update'), ['company_name' => 'X', 'brand_color' => 'red'])->assertSessionHasErrors('brand_color');
    }

    public function test_notifications_mark_read(): void
    {
        $u = $this->userWithRole('Read-only Auditor');
        \App\Notifications\CrmNotification::send($u, 'assigned', 'Xin chào', route('dashboard'));
        $id = $u->notifications()->first()->id;
        $this->actingAs($u)->get(route('notifications.open', $id))->assertRedirect(route('dashboard'));
        $this->assertSame(0, $u->unreadNotifications()->count());
        $this->postJson(route('notifications.read-all'))->assertOk();
    }
}
