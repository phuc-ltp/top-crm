<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\Setting;
use App\Support\Tenancy\CurrentOrganization;
use Tests\TestCase;

/** Marketing nâng cao: chi phí thực tế, doanh thu / ROI theo chiến dịch, link theo dõi + UTM trên form công khai. */
class MarketingRoiTest extends TestCase
{
    private function campaign(array $data = []): Campaign
    {
        return Campaign::create($data + ['name' => 'Ra mắt gói Pro', 'type' => 'ads', 'budget' => 5000000, 'target_leads' => 50, 'status' => 'active',
            'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString()]);
    }

    public function test_expenses_revenue_and_roi(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $campaign = $this->campaign();
        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $campaign->tracking_code);

        $this->post(route('campaigns.expenses.store', $campaign), ['spent_on' => now()->toDateString(), 'category' => 'ads', 'amount' => 1500000, 'note' => 'Facebook Ads'])->assertSessionHas('success');
        $this->post(route('campaigns.expenses.store', $campaign), ['spent_on' => now()->toDateString(), 'category' => 'content', 'amount' => 500000])->assertSessionHas('success');
        $this->post(route('campaigns.expenses.store', $campaign), ['spent_on' => now()->toDateString(), 'category' => 'magic', 'amount' => 1])->assertSessionHasErrors('category');

        $customer = Customer::create(['name' => 'Khách ROI', 'type' => 'business']);
        $stages = Pipeline::default()->stages;
        foreach ([[10000000, $stages->firstWhere('is_won', true)], [4000000, $stages->first(fn ($s) => ! $s->is_won && ! $s->is_lost)], [9000000, $stages->firstWhere('is_lost', true)]] as $i => [$value, $stage]) {
            Opportunity::create(['title' => "Cơ hội $i", 'customer_id' => $customer->id, 'pipeline_stage_id' => $stage->id, 'value' => $value, 'probability' => 50, 'campaign_id' => $campaign->id]);
        }
        $p = $campaign->fresh()->performance();
        $this->assertSame([2000000.0, 10000000.0, 4000000.0, 400.0, 40.0], [$p['cost'], $p['won'], $p['open'], $p['roi'], $p['budget_used']]);   // (10tr − 2tr) / 2tr

        $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee('400%')->assertSee('Facebook Ads')->assertSee('Cơ hội 0');
        $this->get(route('reports.show', ['type' => 'campaigns', 'from' => now()->startOfYear()->toDateString(), 'to' => now()->endOfYear()->toDateString()]))->assertOk()->assertSee('400%');
        $this->get(route('campaigns.export'))->assertOk();

        $expense = $campaign->expenses()->first();
        $this->delete(route('campaigns.expenses.destroy', [$campaign, $expense]))->assertSessionHas('success');
        $this->assertSame(500000.0, $campaign->fresh()->performance()['cost']);

        // Người không có quyền sửa chiến dịch không ghi chi phí được
        $this->actingAs($this->userWithRole('Employee'))->post(route('campaigns.expenses.store', $campaign), ['spent_on' => now()->toDateString(), 'category' => 'ads', 'amount' => 1])->assertForbidden();
    }

    public function test_converted_lead_opportunity_is_attributed_to_campaign(): void
    {
        $this->actingAs($this->admin());
        $campaign = $this->campaign();
        $lead = Lead::create(['name' => 'Lead chiến dịch', 'email' => 'lead@roi.vn', 'status' => 'proposal']);
        $campaign->leads()->attach($lead->id, ['created_at' => now()]);

        $this->post(route('leads.convert.store', $lead), ['mode' => 'new', 'customer_name' => 'Công ty ROI', 'customer_type' => 'business',
            'create_opportunity' => 1, 'opportunity_title' => 'Bán gói Pro', 'opportunity_value' => 12000000])->assertRedirect();
        $this->assertSame($campaign->id, Opportunity::where('title', 'Bán gói Pro')->value('campaign_id'));
    }

    public function test_tracking_link_attaches_public_lead_with_utm_and_is_isolated(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('settings.leads.form'), ['lead_form_enabled' => '1'])->assertRedirect();
        $token = Setting::get('lead_form_token');
        $campaign = $this->campaign();
        $this->get(route('campaigns.show', $campaign))->assertSee('c='.$campaign->tracking_code, false);

        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        $campaignB = app(CurrentOrganization::class)->run($orgB->id, fn () => $this->campaign(['name' => 'Chiến dịch B']));

        $page = $this->get(route('lead-form.show', ['token' => $token, 'c' => $campaign->tracking_code, 'utm_source' => 'facebook', 'utm_medium' => 'cpc']))->assertOk()
            ->assertSee('name="c" value="'.$campaign->tracking_code.'"', false);
        preg_match('/name="stamp" value="([^"]+)"/', $page->getContent(), $m);
        $stamp = html_entity_decode($m[1]);
        $this->travel(5)->seconds();

        $send = fn ($name, $code) => $this->post(route('lead-form.submit', $token), ['stamp' => $stamp, 'name' => $name, 'phone' => '0912345678', 'c' => $code,
            'utm_source' => 'facebook', 'utm_medium' => 'cpc', 'utm_campaign' => 'ra-mat-pro'])->assertRedirect(route('lead-form.show', $token));
        $send('Khách từ quảng cáo', $campaign->tracking_code);
        $send('Khách mã tổ chức khác', $campaignB->tracking_code);
        $send('Khách mã sai', 'khongtontai');

        $lead = Lead::where('name', 'Khách từ quảng cáo')->firstOrFail();
        $this->assertSame(['facebook', 'cpc', 'ra-mat-pro'], [$lead->utm_source, $lead->utm_medium, $lead->utm_campaign]);
        $this->assertTrue($campaign->leads()->whereKey($lead->id)->exists());
        $this->assertSame(1, $campaign->leads()->count());
        $this->assertSame(0, app(CurrentOrganization::class)->run($orgB->id, fn () => $campaignB->leads()->count()));   // không gắn sang tổ chức khác

        // Form cơ hội không nhận chiến dịch của tổ chức khác
        $customer = Customer::create(['name' => 'Khách C', 'type' => 'business']);
        $stage = Pipeline::default()->stages->first();
        $this->post(route('opportunities.store'), ['title' => 'Cơ hội lạ', 'customer_id' => $customer->id, 'pipeline_stage_id' => $stage->id, 'value' => 1, 'probability' => 10,
            'campaign_id' => $campaignB->id])->assertSessionHasErrors('campaign_id');
    }
}
