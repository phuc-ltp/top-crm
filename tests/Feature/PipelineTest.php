<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Department;
use App\Models\Interaction;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LostReason;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Product;
use App\Models\SalesTarget;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\OrganizationProvisioner;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bước 2.5: nhiều pipeline, giai đoạn cấu hình được, sản phẩm trong cơ hội, lý do thất bại, dự báo. */
class PipelineTest extends TestCase
{
    private function stage(string $slug, ?Pipeline $pipeline = null): PipelineStage
    {
        return PipelineStage::where('pipeline_id', ($pipeline ?? Pipeline::default())->id)->where('slug', $slug)->firstOrFail();
    }

    private function opp(array $attrs = []): Opportunity
    {
        return Opportunity::create($attrs + ['title' => 'Cơ hội '.uniqid(), 'customer_id' => $this->customer()->id,
            'pipeline_stage_id' => $this->stage('new')->id, 'value' => 1000000, 'probability' => 10]);
    }

    private function secondPipeline(User $admin): Pipeline
    {
        $this->actingAs($admin)->post(route('settings.pipelines.store'), ['name' => 'Dự án'])->assertRedirect();

        return Pipeline::where('name', 'Dự án')->firstOrFail();
    }

    // ---------------- Pipeline & giai đoạn ----------------

    public function test_default_pipeline_and_opportunities_follow_their_stage(): void
    {
        $default = Pipeline::default();
        $this->assertTrue($default->is_default);
        $this->assertSame(['new', 'qualification', 'proposal', 'negotiation', 'won', 'lost'], $default->stages->pluck('slug')->all());
        $o = $this->opp();
        $this->assertSame($default->id, (int) $o->pipeline_id);   // tự lấy theo giai đoạn
    }

    public function test_manage_pipelines_and_stages(): void
    {
        $admin = $this->admin();
        $this->actingAs($this->userWithRole('Sales Employee', ['email' => 's@example.com']))->get(route('settings.pipelines'))->assertForbidden();

        $project = $this->secondPipeline($admin);
        $this->assertSame(['new', 'in_progress', 'won', 'lost'], $project->stages()->pluck('slug')->all());

        // Thêm giai đoạn → chèn trước giai đoạn đóng
        $this->actingAs($admin)->post(route('settings.pipelines.stages.store', $project), ['name' => 'Khảo sát', 'probability' => 30, 'color' => '#8b5cf6'])->assertRedirect();
        $this->assertSame(['new', 'in_progress', 'khao_sat', 'won', 'lost'], $project->stages()->pluck('slug')->all());

        // Đặt làm mặc định → pipeline cũ mất cờ mặc định
        $this->actingAs($admin)->put(route('settings.pipelines.update', $project), ['name' => 'Dự án', 'is_default' => '1'])->assertRedirect();
        $this->assertSame($project->id, Pipeline::default()->id);
        $this->assertSame(1, Pipeline::where('is_default', true)->count());

        // Pipeline có cơ hội không xóa được; pipeline trống & không mặc định xóa được
        $old = Pipeline::where('name', 'Pipeline bán hàng')->first();
        $this->opp(['pipeline_stage_id' => $this->stage('new', $old)->id]);
        $this->actingAs($admin)->delete(route('settings.pipelines.destroy', $old))->assertStatus(422);
        $this->actingAs($admin)->post(route('settings.pipelines.store'), ['name' => 'Tạm']);
        $tmp = Pipeline::where('name', 'Tạm')->first();
        $this->actingAs($admin)->delete(route('settings.pipelines.destroy', $tmp))->assertRedirect();
        $this->assertNull(Pipeline::find($tmp->id));
        $this->assertSame(0, PipelineStage::where('pipeline_id', $tmp->id)->count());
        $this->actingAs($admin)->delete(route('settings.pipelines.destroy', $project))->assertStatus(422);   // mặc định
    }

    public function test_deleting_stage_moves_opportunities_and_keeps_invariants(): void
    {
        $admin = $this->admin();
        $proposal = $this->stage('proposal');
        $o = $this->opp(['pipeline_stage_id' => $proposal->id]);
        $this->actingAs($admin)->delete(route('settings.pipelines.stages.destroy', $proposal), ['move_to' => $this->stage('negotiation')->id])->assertRedirect();
        $this->assertSame($this->stage('negotiation')->id, (int) $o->fresh()->pipeline_stage_id);
        $this->assertNull(PipelineStage::find($proposal->id));

        // Không xóa được giai đoạn thắng / thất bại duy nhất
        $won = $this->stage('won');
        $this->actingAs($admin)->delete(route('settings.pipelines.stages.destroy', $won), ['move_to' => $this->stage('new')->id])->assertStatus(422);
        // Không chuyển sang giai đoạn của pipeline khác
        $project = $this->secondPipeline($admin);
        $this->actingAs($admin)->delete(route('settings.pipelines.stages.destroy', $this->stage('qualification')), ['move_to' => $this->stage('new', $project)->id])
            ->assertSessionHasErrors('move_to');
    }

    public function test_pipelines_are_isolated_per_organization(): void
    {
        $owner = $this->userWithRole(PermissionCatalog::OWNER, ['email' => 'a@example.com']);
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        [$pipelineB, $stageB] = app(CurrentOrganization::class)->run($orgB->id, fn () => [Pipeline::default(), Pipeline::default()->stages->first()]);
        $this->assertNotNull($pipelineB);   // tổ chức mới có pipeline mặc định

        $this->actingAs($owner)->put(route('settings.pipelines.update', $pipelineB->id), ['name' => 'Hack'])->assertNotFound();
        $this->actingAs($owner)->put(route('settings.pipelines.stages.update', $stageB->id), ['name' => 'Hack', 'probability' => 1, 'color' => '#000000'])->assertNotFound();
        $this->actingAs($owner)->get(route('opportunities.index', ['pipeline' => $pipelineB->id]))->assertOk()->assertSee(Pipeline::default()->name);   // pipeline lạ → về mặc định
    }

    // ---------------- Kanban ----------------

    public function test_kanban_moves_stay_inside_pipeline_and_lost_needs_reason(): void
    {
        $admin = $this->admin();
        $project = $this->secondPipeline($admin);
        $o = $this->opp(['title' => 'Cơ hội Kanban']);
        $reason = LostReason::where('applies_to', 'both')->first();

        $this->actingAs($admin)->get(route('opportunities.index', ['pipeline' => $project->id]))->assertOk()->assertDontSee('Cơ hội Kanban');
        $this->actingAs($admin)->get(route('opportunities.index'))->assertSee('Cơ hội Kanban');

        $this->actingAs($admin)->patchJson(route('opportunities.move', $o), ['pipeline_stage_id' => $this->stage('new', $project)->id, 'position' => 0])->assertStatus(422);
        $this->actingAs($admin)->patchJson(route('opportunities.move', $o), ['pipeline_stage_id' => $this->stage('lost')->id, 'position' => 0])->assertStatus(422);
        $this->assertSame($this->stage('new')->id, (int) $o->fresh()->pipeline_stage_id);

        $this->actingAs($admin)->patchJson(route('opportunities.move', $o), ['pipeline_stage_id' => $this->stage('lost')->id, 'position' => 0, 'lost_reason_id' => $reason->id, 'lost_reason' => 'Ngân sách bị cắt'])->assertOk();
        $o->refresh();
        $this->assertSame($reason->id, $o->lost_reason_id);
        $this->assertSame('Ngân sách bị cắt', $o->lost_reason);
        $this->assertNotNull($o->closed_at);

        $this->actingAs($admin)->patchJson(route('opportunities.move', $o), ['pipeline_stage_id' => $this->stage('negotiation')->id, 'position' => 0])->assertOk();
        $this->assertNull($o->fresh()->lost_reason_id);
        $this->assertNull($o->fresh()->closed_at);
    }

    public function test_lost_reason_from_other_organization_is_rejected(): void
    {
        $owner = $this->userWithRole(PermissionCatalog::OWNER, ['email' => 'a@example.com']);
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        $reasonB = app(CurrentOrganization::class)->run($orgB->id, fn () => LostReason::first());
        $o = $this->opp();
        $this->actingAs($owner)->patchJson(route('opportunities.move', $o), ['pipeline_stage_id' => $this->stage('lost')->id, 'position' => 0, 'lost_reason_id' => $reasonB->id])->assertStatus(422);
        $this->assertNull($o->fresh()->lost_reason_id);
    }

    // ---------------- Form: sản phẩm, theo dõi, tệp ----------------

    public function test_items_compute_value_on_server_and_follow_up_is_scheduled(): void
    {
        $admin = $this->admin();
        $c = $this->customer();
        $product = Product::create(['sku' => 'SP-01', 'name' => 'Gói Pro', 'price' => 2000000, 'status' => 'active']);

        $this->actingAs($admin)->post(route('opportunities.store'), [
            'title' => 'Cơ hội có sản phẩm', 'customer_id' => $c->id, 'pipeline_stage_id' => $this->stage('new')->id, 'probability' => 20,
            'value' => 1, 'value_from_items' => '1', 'follow_up_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'items' => [
                ['product_id' => $product->id, 'name' => '', 'quantity' => 3, 'unit_price' => 2000000, 'discount_percent' => 10],
                ['name' => 'Phí triển khai', 'quantity' => 1, 'unit_price' => 1500000, 'discount_percent' => 0],
                ['name' => '', 'quantity' => 1, 'unit_price' => 999],   // dòng trống bị bỏ qua
            ],
        ])->assertRedirect();

        $o = Opportunity::where('title', 'Cơ hội có sản phẩm')->firstOrFail();
        $this->assertSame(2, $o->items()->count());
        $this->assertSame('Gói Pro', $o->items->first()->name);
        $this->assertEquals(3 * 2000000 * 0.9 + 1500000, (float) $o->value);   // 6.900.000 — tính lại ở máy chủ, bỏ qua value=1
        $this->assertSame(1, Interaction::where('interactable_type', 'opportunity')->where('interactable_id', $o->id)->where('status', 'planned')->count());
        $this->actingAs($admin)->get(route('opportunities.show', $o))->assertSee('Phí triển khai')->assertSee('6.900.000');

        // Sản phẩm của tổ chức khác bị từ chối
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        $productB = app(CurrentOrganization::class)->run($orgB->id, fn () => Product::create(['sku' => 'B-1', 'name' => 'SP B', 'price' => 1, 'status' => 'active']));
        $this->actingAs($admin)->put(route('opportunities.update', $o), ['title' => $o->title, 'customer_id' => $c->id, 'pipeline_stage_id' => $o->pipeline_stage_id,
            'probability' => 20, 'value' => 0, 'items' => [['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 1]]])->assertSessionHasErrors('items.0.product_id');
    }

    public function test_form_requires_reason_when_saving_as_lost(): void
    {
        $admin = $this->admin();
        $o = $this->opp();
        $payload = ['title' => $o->title, 'customer_id' => $o->customer_id, 'pipeline_stage_id' => $this->stage('lost')->id, 'probability' => 0, 'value' => 1000];
        $this->actingAs($admin)->put(route('opportunities.update', $o), $payload)->assertSessionHasErrors('lost_reason_id');
        $this->actingAs($admin)->put(route('opportunities.update', $o), $payload + ['lost_reason_id' => LostReason::first()->id])->assertRedirect();
        $this->assertNotNull($o->fresh()->lost_reason_id);
    }

    public function test_opportunity_attachments_respect_permissions(): void
    {
        Storage::fake('local');
        $sale = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $other = $this->userWithRole('Sales Employee', ['email' => 'other@example.com']);
        $o = $this->opp(['assigned_to' => $sale->id, 'customer_id' => $this->customer(['assigned_to' => $sale->id])->id]);

        $this->actingAs($sale)->post(route('opportunities.attachments.store', $o), ['file' => UploadedFile::fake()->create('de-xuat.pdf', 50, 'application/pdf')])->assertSessionHas('success');
        $att = Attachment::firstOrFail();
        $this->actingAs($sale)->get(route('opportunities.attachments.download', [$o, $att]))->assertOk();
        $this->actingAs($other)->get(route('opportunities.attachments.download', [$o, $att]))->assertForbidden();
        $this->actingAs($sale)->delete(route('opportunities.attachments.destroy', [$o, $att]))->assertSessionHas('success');
        Storage::disk('local')->assertMissing($att->path);
    }

    // ---------------- Dự báo ----------------

    public function test_forecast_numbers_and_visibility(): void
    {
        $admin = $this->admin();
        $sale = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $this->opp(['value' => 10000000, 'probability' => 50, 'expected_close_date' => now()->endOfMonth()->toDateString(), 'assigned_to' => $sale->id]);
        $this->opp(['value' => 4000000, 'probability' => 25, 'expected_close_date' => now()->addMonth()->startOfMonth()->addDays(3)->toDateString(), 'assigned_to' => $admin->id]);
        $won = $this->opp(['value' => 3000000, 'assigned_to' => $sale->id]);
        $won->update(['pipeline_stage_id' => $this->stage('won')->id, 'closed_at' => now()]);
        $this->opp(['value' => 7000000, 'probability' => 50, 'expected_close_date' => now()->subMonths(2)->toDateString()]);   // quá hạn
        SalesTarget::create(['user_id' => $sale->id, 'year' => now()->year, 'month' => now()->month, 'target_amount' => 20000000]);

        $page = $this->actingAs($admin)->get(route('opportunities.forecast', ['months' => 3]))->assertOk();
        $first = $page->viewData('rows')->first();
        $this->assertEquals(5000000, $first['weighted']);
        $this->assertEquals(3000000, $first['won']);
        $this->assertEquals(8000000, $first['forecast']);
        $this->assertEquals(40.0, $first['attainment']);
        $this->assertEquals(1000000, $page->viewData('rows')[1]['weighted']);
        $this->assertSame(1, $page->viewData('overdue')['count']);

        $own = $this->actingAs($sale)->get(route('opportunities.forecast', ['months' => 3]))->assertOk();
        $this->assertEquals(0, $own->viewData('rows')[1]['weighted']);   // cơ hội của người khác không tính
        $this->assertTrue($own->viewData('ownTargetsOnly'));
    }

    // ---------------- Chuyển đổi lead & khởi tạo dữ liệu ----------------

    public function test_lead_conversion_uses_default_pipeline(): void
    {
        $admin = $this->admin();
        $project = $this->secondPipeline($admin);
        $this->actingAs($admin)->put(route('settings.pipelines.update', $project), ['name' => 'Dự án', 'is_default' => '1']);
        $lead = Lead::create(['name' => 'Lead chuyển đổi', 'status' => 'qualified']);

        $this->actingAs($admin)->post(route('leads.convert.store', $lead), ['mode' => 'new', 'customer_name' => 'KH mới', 'create_opportunity' => '1', 'opportunity_title' => 'CH từ lead'])->assertRedirect();
        $o = Opportunity::where('title', 'CH từ lead')->firstOrFail();
        $this->assertSame($project->id, (int) $o->pipeline_id);
        $this->assertSame($this->stage('new', $project)->id, (int) $o->pipeline_stage_id);
    }

    public function test_reference_data_is_not_recreated_after_user_deletes_it(): void
    {
        Department::where('code', 'MKT')->delete();
        LeadSource::where('name', 'Zalo')->delete();
        $stage = $this->stage('proposal');
        $stage->delete();

        (new ReferenceDataSeeder)->run();   // chạy mỗi lần khởi động (crm:install)

        $this->assertFalse(Department::where('code', 'MKT')->exists());
        $this->assertFalse(LeadSource::where('name', 'Zalo')->exists());
        $this->assertFalse(PipelineStage::where('slug', 'proposal')->exists());

        // Tổ chức mới vẫn nhận đủ dữ liệu mặc định
        $org = Organization::create(['name' => 'Mới', 'slug' => 'moi']);
        app(OrganizationProvisioner::class)->provision($org);
        app(CurrentOrganization::class)->run($org->id, function () {
            $this->assertSame(5, Department::count());
            $this->assertTrue(LeadSource::where('name', 'Zalo')->exists());
            $this->assertSame(6, Pipeline::default()->stages()->count());
        });
    }
}
