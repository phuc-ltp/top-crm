<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\SupportTicket;
use App\Models\Task;
use Tests\TestCase;

/**
 * Trang chi tiết chỉ hiện bản ghi liên quan trong phạm vi quyền của người xem
 * (lỗ hổng phát hiện ở bước 2.2: trang khách hàng từng hiện cơ hội / ticket / công việc không được phép xem).
 */
class DetailPageVisibilityTest extends TestCase
{
    public function test_customer_page_hides_modules_without_permission(): void
    {
        $marketing = $this->userWithRole('Marketing Employee', ['email' => 'mk@example.com']);   // xem mọi khách hàng; không xem ticket, cơ hội
        $c = $this->customer(['name' => 'Khách Chung']);
        SupportTicket::create(['customer_id' => $c->id, 'subject' => 'Ticket BÍ MẬT', 'description' => 'x', 'priority' => 'high', 'status' => 'open']);
        Opportunity::create(['title' => 'Cơ hội BÍ MẬT', 'customer_id' => $c->id, 'pipeline_stage_id' => PipelineStage::first()->id, 'value' => 5000000]);

        $page = $this->actingAs($marketing)->get(route('customers.show', $c))->assertOk();
        $page->assertDontSee('Ticket BÍ MẬT')->assertDontSee('Cơ hội BÍ MẬT')
            ->assertDontSee(__('Ticket đang xử lý'))->assertDontSee(__('Đã thanh toán'))->assertDontSee(__('Cơ hội đang mở'));

        $this->actingAs($this->admin())->get(route('customers.show', $c))->assertSee('Ticket BÍ MẬT')->assertSee('Cơ hội BÍ MẬT');
    }

    public function test_staff_only_see_their_own_related_records(): void
    {
        $sale = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $other = $this->userWithRole('Sales Employee', ['email' => 'other@example.com']);
        $c = $this->customer(['assigned_to' => $sale->id]);
        $stage = PipelineStage::first()->id;
        Opportunity::create(['title' => 'Cơ hội của tôi', 'customer_id' => $c->id, 'pipeline_stage_id' => $stage, 'value' => 1, 'assigned_to' => $sale->id]);
        Opportunity::create(['title' => 'Cơ hội của đồng nghiệp', 'customer_id' => $c->id, 'pipeline_stage_id' => $stage, 'value' => 1, 'assigned_to' => $other->id]);
        Task::create(['title' => 'Việc của đồng nghiệp', 'priority' => 'low', 'status' => 'pending', 'due_date' => now()->addDay(), 'assigned_to' => $other->id,
            'related_type' => 'customer', 'related_id' => $c->id]);

        $this->actingAs($sale)->get(route('customers.show', $c))->assertOk()
            ->assertSee('Cơ hội của tôi')->assertDontSee('Cơ hội của đồng nghiệp')->assertDontSee('Việc của đồng nghiệp');
    }
}
