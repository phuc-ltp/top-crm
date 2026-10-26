<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use App\Services\ContractService;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Quản lý hợp đồng: tạo / tình trạng hiệu lực, phạm vi xem, gia hạn, chấm dứt, nhắc hết hạn, cô lập tổ chức. */
class ContractTest extends TestCase
{
    private function payload(Customer $customer, array $data = []): array
    {
        return $data + ['title' => 'Hợp đồng CRM 2026', 'customer_id' => $customer->id, 'type' => 'sale', 'value' => 120000000, 'status' => 'signed',
            'signed_date' => now()->subDays(10)->toDateString(), 'start_date' => now()->subDays(5)->toDateString(), 'end_date' => now()->addYear()->toDateString()];
    }

    public function test_create_state_validation_and_scope(): void
    {
        $manager = $this->userWithRole('Sales Manager');
        $this->actingAs($manager);
        $customer = Customer::create(['name' => 'Công ty Hợp Đồng', 'type' => 'business']);
        $other = Customer::create(['name' => 'Công ty Khác', 'type' => 'business']);
        $wrongContact = Contact::create(['full_name' => 'Người công ty khác', 'customer_id' => $other->id]);

        $this->post(route('contracts.store'), $this->payload($customer, ['signed_date' => null]))->assertSessionHasErrors('signed_date');   // đã ký phải có ngày ký
        $this->post(route('contracts.store'), $this->payload($customer, ['contact_id' => $wrongContact->id]))->assertSessionHasErrors('contact_id');
        $this->post(route('contracts.store'), $this->payload($customer, ['end_date' => now()->subYear()->toDateString()]))->assertSessionHasErrors('end_date');
        $this->post(route('contracts.store'), $this->payload($customer))->assertSessionHas('success');
        $contract = Contract::firstOrFail();
        $this->assertSame('HDG'.str_pad((string) $contract->id, 5, '0', STR_PAD_LEFT), $contract->code);
        $this->assertSame('active', $contract->state);

        $contract->end_date = now()->addDays(20);
        $this->assertSame('expiring', $contract->state);
        $contract->end_date = now()->subDay();
        $this->assertSame('expired', $contract->state);
        $contract->start_date = now()->addDays(3);
        $contract->end_date = now()->addYear();
        $this->assertSame('pending', $contract->state);

        $this->get(route('contracts.index'))->assertOk()->assertSee($contract->code);
        $this->get(route('customers.show', $customer))->assertSee(__('Hợp đồng (:count)', ['count' => 1]));
        $this->get(route('contracts.export'))->assertOk();

        // Nhân viên kinh doanh chỉ thấy hợp đồng của mình; nhân viên thường không có quyền
        $sales = $this->userWithRole('Sales Employee');
        $this->actingAs($sales)->get(route('contracts.index'))->assertOk()->assertDontSee($contract->code);
        $this->get(route('contracts.show', $contract))->assertForbidden();
        $contract->forceFill(['assigned_to' => $sales->id])->save();
        $this->get(route('contracts.show', $contract))->assertOk();
        $this->actingAs($this->userWithRole('Employee'))->get(route('contracts.index'))->assertForbidden();
    }

    public function test_renew_and_terminate(): void
    {
        $this->actingAs($this->admin());
        $customer = Customer::create(['name' => 'Công ty Gia Hạn', 'type' => 'business']);
        $this->post(route('contracts.store'), $this->payload($customer, ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'signed_date' => '2025-12-20']));
        $contract = Contract::firstOrFail();

        $this->post(route('contracts.renew', $contract))->assertRedirect();
        $renewal = Contract::where('renewed_from_id', $contract->id)->firstOrFail();
        $this->assertSame(['draft', '2027-01-01', '2027-12-31', '120000000.00'], [$renewal->status, $renewal->start_date->toDateString(), $renewal->end_date->toDateString(), $renewal->value]);
        $this->post(route('contracts.renew', $contract))->assertStatus(422);                                   // chỉ gia hạn một lần
        $this->get(route('contracts.show', $contract))->assertSee($renewal->code);

        $this->post(route('contracts.terminate', $contract))->assertSessionHasErrors('termination_reason');
        $this->post(route('contracts.terminate', $contract), ['terminated_on' => now()->toDateString(), 'termination_reason' => 'Khách đổi nhà cung cấp'])->assertSessionHas('success');
        $this->assertSame('terminated', $contract->fresh()->state);
        $this->get(route('contracts.edit', $contract))->assertStatus(422);
        $this->post(route('contracts.terminate', $renewal), ['terminated_on' => now()->toDateString(), 'termination_reason' => 'x'])->assertStatus(422);   // nháp: chưa ký
    }

    public function test_expiry_reminders_follow_stages(): void
    {
        $admin = $this->admin();
        $owner = $this->userWithRole('Sales Employee');
        $this->actingAs($admin);
        $customer = Customer::create(['name' => 'Công ty Nhắc Hạn', 'type' => 'business']);
        $this->post(route('contracts.store'), $this->payload($customer, ['end_date' => now()->addDays(25)->toDateString(), 'assigned_to' => $owner->id, 'auto_renew' => 1]));
        $contract = Contract::firstOrFail();
        $service = app(ContractService::class);
        $count = fn () => $owner->notifications()->where('data->kind', 'contract_expiring')->count();

        $service->runReminders();
        $service->runReminders();
        $this->assertSame([1, 30], [$count(), $contract->fresh()->reminded_stage]);                              // không nhắc trùng
        $this->assertStringContainsString('tự gia hạn', $owner->notifications()->first()->data['message']);
        $contract->forceFill(['end_date' => now()->addDays(3)])->saveQuietly();
        $service->runReminders();
        $this->assertSame([2, 7], [$count(), $contract->fresh()->reminded_stage]);

        $this->put(route('contracts.update', $contract), $this->payload($customer, ['end_date' => now()->addYear()->toDateString(), 'assigned_to' => $owner->id]))->assertRedirect();
        $this->assertNull($contract->fresh()->reminded_stage);                                                   // đổi hạn → nhắc lại theo hạn mới
        $this->artisan('crm:contract-reminders')->assertSuccessful();
    }

    public function test_contracts_are_isolated_between_organizations(): void
    {
        $admin = $this->admin();
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $contractB = app(CurrentOrganization::class)->run($orgB->id, function () {
            $c = Customer::create(['name' => 'Khách B', 'type' => 'business']);

            return Contract::create(['title' => 'Hợp đồng bí mật B', 'customer_id' => $c->id, 'type' => 'sale', 'value' => 1, 'status' => 'draft']);
        });
        $customerB = Customer::withoutGlobalScopes()->find($contractB->customer_id);

        $this->actingAs($admin)->get(route('contracts.index'))->assertDontSee('Hợp đồng bí mật B');
        $this->get(route('contracts.show', $contractB))->assertNotFound();
        $this->post(route('contracts.store'), $this->payload($customerB))->assertSessionHasErrors('customer_id');
    }
}
