<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use Tests\TestCase;

class LeadPipelineTest extends TestCase
{
    public function test_lead_crud_with_auto_score_and_assign_notification(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $staff = $this->userWithRole('Sales Employee');
        $this->actingAs($manager);

        $this->post(route('leads.store'), [
            'name' => 'Lead Nóng', 'company' => 'ABC', 'email' => 'a@b.vn', 'phone' => '0900000000',
            'status' => 'qualified', 'estimated_value' => 150000000, 'assigned_to' => $staff->id, 'auto_score' => 1,
        ])->assertRedirect();

        $lead = Lead::where('name', 'Lead Nóng')->firstOrFail();
        $this->assertSame(10 + 10 + 10 + 25 + 25, $lead->score);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $staff->id]);

        $this->put(route('leads.update', $lead), ['name' => 'Lead Nóng', 'status' => 'lost', 'lost_reason_id' => \App\Models\LostReason::first()->id, 'auto_score' => 0, 'score' => 5])->assertRedirect();
        $this->assertSame(5, $lead->fresh()->score);

        // Không được tự đặt trạng thái "converted" qua form
        $this->put(route('leads.update', $lead), ['name' => 'Lead Nóng', 'status' => 'converted'])->assertSessionHasErrors('status');

        $this->delete(route('leads.destroy', $lead))->assertRedirect();
        $this->assertSoftDeleted($lead);
    }

    public function test_convert_lead_to_customer_and_opportunity(): void
    {
        $this->actingAs($this->admin());
        $lead = Lead::create(['name' => 'Trần B', 'company' => 'Công ty B', 'email' => 'b@b.vn', 'status' => 'proposal', 'estimated_value' => 50000000]);

        $this->post(route('leads.convert.store', $lead), [
            'mode' => 'new', 'customer_name' => 'Công ty B', 'customer_type' => 'business',
            'create_opportunity' => 1, 'opportunity_title' => 'Bán gói Pro', 'opportunity_value' => 60000000,
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame('converted', $lead->status);
        $this->assertNotNull($lead->converted_at);
        $customer = Customer::find($lead->converted_customer_id);
        $this->assertSame('Công ty B', $customer->name);
        $this->assertSame('b@b.vn', $customer->email);
        $opp = Opportunity::find($lead->converted_opportunity_id);
        $this->assertEquals(60000000, (float) $opp->value);
        $this->assertSame($customer->id, $opp->customer_id);
        $this->assertSame(1, $opp->stageHistories()->count());

        // Không chuyển đổi được lần hai
        $this->post(route('leads.convert.store', $lead), ['mode' => 'new', 'customer_name' => 'X'])->assertSessionHasErrors('lead');
        $this->assertSame(1, Customer::count());
    }

    public function test_convert_lead_to_existing_customer(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $lead = Lead::create(['name' => 'C', 'status' => 'qualified']);
        $this->post(route('leads.convert.store', $lead), ['mode' => 'existing', 'customer_id' => $customer->id, 'create_opportunity' => 0])
            ->assertRedirect(route('customers.show', $customer));
        $this->assertSame($customer->id, $lead->fresh()->converted_customer_id);
        $this->assertSame(1, Customer::count());
    }

    public function test_kanban_move_updates_stage_history_and_probability(): void
    {
        $owner = $this->userWithRole('Sales Employee');
        $manager = $this->userWithRole('Sales Manager');
        $customer = $this->customer(['assigned_to' => $owner->id]);
        $stages = PipelineStage::ordered()->get();
        $opp = Opportunity::create(['title' => 'Deal', 'customer_id' => $customer->id, 'pipeline_stage_id' => $stages[0]->id, 'value' => 1000, 'assigned_to' => $owner->id]);

        $won = $stages->firstWhere('is_won', true);
        $this->actingAs($manager)->patchJson(route('opportunities.move', $opp), ['pipeline_stage_id' => $won->id, 'position' => 0])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('probability', 100);

        $opp->refresh();
        $this->assertSame($won->id, $opp->pipeline_stage_id);
        $this->assertNotNull($opp->closed_at);
        $this->assertDatabaseHas('opportunity_stage_histories', ['opportunity_id' => $opp->id, 'from_stage_id' => $stages[0]->id, 'to_stage_id' => $won->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id]);
    }

    public function test_kanban_move_forbidden_for_other_staff(): void
    {
        $owner = $this->userWithRole('Sales Employee');
        $intruder = $this->userWithRole('Sales Employee');
        $stage = PipelineStage::ordered()->first();
        $opp = Opportunity::create(['title' => 'Deal', 'customer_id' => $this->customer()->id, 'pipeline_stage_id' => $stage->id, 'value' => 1, 'assigned_to' => $owner->id]);

        $this->actingAs($intruder)->patchJson(route('opportunities.move', $opp), ['pipeline_stage_id' => $stage->id, 'position' => 0])
            ->assertForbidden()->assertJsonPath('message', 'Bạn không có quyền thực hiện thao tác này.');
        $this->actingAs($this->userWithRole('Sales Manager'))->patchJson(route('opportunities.move', $opp), ['pipeline_stage_id' => 9999, 'position' => 0])->assertStatus(422);
    }

    public function test_opportunity_crud(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $stage = PipelineStage::ordered()->first();

        $this->post(route('opportunities.store'), ['title' => 'Cơ hội lớn', 'customer_id' => $customer->id, 'pipeline_stage_id' => $stage->id, 'value' => 99000000, 'probability' => 20])
            ->assertRedirect();
        $opp = Opportunity::where('title', 'Cơ hội lớn')->firstOrFail();
        $this->assertMatchesRegularExpression('/^CH\d{5}$/', $opp->code);
        $this->get(route('opportunities.index'))->assertSee('Cơ hội lớn');
    }
}
