<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\PaymentService;
use App\Services\SalesDocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Trung tâm thông báo: nhóm, đọc / chưa đọc, xóa, tùy chọn từng người (ứng dụng / email), thông báo thanh toán, dọn cũ. */
class NotificationCenterTest extends TestCase
{
    public function test_preferences_control_delivery_and_security_is_mandatory(): void
    {
        $u = $this->userWithRole('Sales Employee');
        $this->assertTrue(CrmNotification::wants($u, 'low_stock', 'app'));
        $this->assertFalse(CrmNotification::wants($u, 'low_stock', 'mail'));

        // Tắt "tồn kho thấp" trong ứng dụng; không gửi "security" lên (vẫn luôn bật)
        $this->actingAs($u)->put(route('notifications.preferences.update'), ['app' => ['task_due' => '1'], 'mail' => ['task_due' => '1']])->assertSessionHas('success');
        $u->refresh();
        CrmNotification::send($u, 'low_stock', 'Tồn kho thấp X');
        CrmNotification::send($u, 'security', 'Đăng nhập lạ');
        CrmNotification::send($u, 'task_due', 'Việc sắp đến hạn');
        $this->assertEqualsCanonicalizing(['Việc sắp đến hạn', 'Đăng nhập lạ'], $u->notifications()->get()->pluck('data.message')->all());
        $this->get(route('notifications.preferences'))->assertOk()->assertSee(__('Luôn bật'))
            ->assertSee(__('Hệ thống chưa cấu hình máy chủ gửi thư nên chưa gửi được email. Lựa chọn email được lưu lại và có tác dụng khi quản trị viên cấu hình máy chủ thư.'));
    }

    public function test_email_is_only_sent_when_mail_is_configured_and_opted_in(): void
    {
        Notification::fake();
        $u = $this->userWithRole('Sales Employee');
        $u->forceFill(['notification_preferences' => ['task_due' => ['app' => true, 'mail' => true]]])->save();

        CrmNotification::send($u, 'task_due', 'A');   // MAIL_MAILER=log → chỉ trong ứng dụng
        Notification::assertSentTo($u, CrmNotification::class, fn ($n, $channels) => $channels === ['database']);

        config(['mail.default' => 'smtp', 'mail.from.address' => 'crm@example.com']);
        CrmNotification::send($u, 'task_due', 'B');
        Notification::assertSentTo($u, CrmNotification::class, fn ($n, $channels) => $n->message === 'B' && $channels === ['database', 'mail']);
        CrmNotification::send($u, 'deal_stage', 'C');   // chưa bật email cho loại này
        Notification::assertSentTo($u, CrmNotification::class, fn ($n, $channels) => $n->message === 'C' && $channels === ['database']);
    }

    public function test_groups_read_toggle_delete_and_ownership(): void
    {
        $me = $this->userWithRole('Sales Employee');
        $other = $this->userWithRole('Sales Employee');
        CrmNotification::send($me, 'invoice_overdue', 'Hóa đơn quá hạn');
        CrmNotification::send($me, 'task_due', 'Việc đến hạn');
        // Thông báo cũ (trước nâng cấp) không có "group" → vẫn vào đúng nhóm theo loại
        DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => CrmNotification::class, 'notifiable_type' => $me->getMorphClass(), 'notifiable_id' => $me->id,
            'data' => json_encode(['kind' => 'low_stock', 'message' => 'Thông báo cũ kho', 'url' => null, 'icon' => 'bi-box']), 'created_at' => now(), 'updated_at' => now()]);
        CrmNotification::send($other, 'task_due', 'Của người khác');

        $page = $this->actingAs($me)->get(route('notifications.index'))->assertOk()->assertSee(__('Hôm nay'))->assertDontSee('Của người khác');
        $this->assertEquals(['inventory' => 1, 'sales' => 1, 'work' => 1], $page->viewData('unreadByGroup')->sortKeys()->all());
        $this->get(route('notifications.index', ['group' => 'inventory']))->assertSee('Thông báo cũ kho')->assertDontSee('Hóa đơn quá hạn');
        $this->get(route('notifications.index', ['group' => 'bogus']))->assertSessionHasErrors('group');

        $n = $me->notifications()->where('data->message', 'Việc đến hạn')->first();
        $this->post(route('notifications.toggle', $n->id))->assertRedirect();
        $this->assertNotNull($n->fresh()->read_at);
        $this->post(route('notifications.toggle', $n->id));
        $this->assertNull($n->fresh()->read_at);

        $theirs = $other->notifications()->first();
        $this->post(route('notifications.toggle', $theirs->id))->assertNotFound();
        $this->delete(route('notifications.destroy', $theirs->id))->assertNotFound();

        $this->post(route('notifications.read-all'), ['group' => 'sales']);
        $this->assertSame(2, $me->unreadNotifications()->count(), 'Chỉ đánh dấu nhóm Bán hàng');
        $this->delete(route('notifications.clear-read'))->assertSessionHas('success');
        $this->assertSame(2, $me->notifications()->count());

        $feed = $this->getJson(route('notifications.feed'))->assertOk()->json();
        $this->assertSame(2, $feed['unread']);
        $this->assertNotNull($feed['latest']);
        $this->assertContains($feed['items'][0]['tone'], ['purple', 'green']);
    }

    public function test_payment_notifications_and_prune(): void
    {
        $admin = $this->admin();
        $sale = $this->userWithRole('Sales Employee');
        $this->actingAs($admin);
        $inv = app(SalesDocumentService::class)->save(new Invoice, ['customer_id' => $this->customer()->id, 'issue_date' => today(), 'due_date' => today()->addDays(5),
            'status' => 'issued', 'tax_rate' => 0, 'discount_amount' => 0, 'assigned_to' => $sale->id, 'created_by' => $admin->id],
            [['description' => 'X', 'quantity' => 1, 'unit_price' => 1000000]]);
        app(PaymentService::class)->record($inv, ['amount' => 400000], $admin);
        app(PaymentService::class)->record($inv, ['amount' => 600000], $admin);
        $this->assertEqualsCanonicalizing(['payment_received', 'invoice_paid'], $sale->notifications()->get()->pluck('data.kind')->all());
        $this->assertSame(0, $admin->notifications()->count(), 'Người tự ghi nhận không nhận thông báo');

        DB::table('notifications')->where('notifiable_id', $sale->id)->update(['created_at' => now()->subDays(100), 'read_at' => now()]);
        $this->artisan('crm:prune-notifications')->assertSuccessful();
        $this->assertSame(0, $sale->notifications()->count());
    }
}
