<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\CustomFieldDefinition;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\SupportTicket;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\OrganizationProvisioner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Bước 2.3: hồ sơ khách hàng đầy đủ, trường tùy biến, gộp trùng, nhập Excel, tệp đính kèm. */
class CustomerProfileTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + ['name' => 'Công ty Hoa Sen', 'type' => 'business', 'status' => 'active', 'marketing_consent' => '0'];
    }

    // ---------------- (a) Trường hồ sơ mới ----------------

    public function test_profile_fields_are_saved_and_tracked(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('customers.store'), $this->payload([
            'country' => 'VN', 'segment' => 'sme', 'lifecycle_stage' => 'new', 'preferred_channel' => 'zalo',
            'marketing_consent' => '1', 'marketing_consent_source' => 'Form website',
        ]))->assertRedirect();

        $c = Customer::where('name', 'Công ty Hoa Sen')->firstOrFail();
        $this->assertSame(['VN', 'sme', 'new', 'zalo'], [$c->country, $c->segment, $c->lifecycle_stage, $c->preferred_channel]);
        $this->assertTrue($c->marketing_consent);
        $this->assertNotNull($c->marketing_consent_at);
        $this->assertNotNull($c->lifecycle_changed_at);

        // Đổi vòng đời → thời điểm cập nhật; bỏ tích đồng ý (trường ẩn = 0) → rút lại đồng ý
        $this->travel(2)->days();
        $this->actingAs($admin)->put(route('customers.update', $c), $this->payload(['lifecycle_stage' => 'active', 'marketing_consent' => '0']))->assertRedirect();
        $c->refresh();
        $this->assertSame('active', $c->lifecycle_stage);
        $this->assertTrue($c->lifecycle_changed_at->isToday());
        $this->assertFalse($c->marketing_consent);

        $this->actingAs($admin)->get(route('customers.show', $c))->assertOk()->assertSee('Việt Nam')->assertSee(__('Doanh nghiệp vừa & nhỏ'));
    }

    public function test_consent_requires_a_source_and_values_are_validated(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('customers.store'), $this->payload(['marketing_consent' => '1']))->assertSessionHasErrors('marketing_consent_source');
        $this->actingAs($admin)->post(route('customers.store'), $this->payload(['country' => 'XX', 'segment' => 'hacker', 'lifecycle_stage' => 'x', 'preferred_channel' => 'fax']))
            ->assertSessionHasErrors(['country', 'segment', 'lifecycle_stage', 'preferred_channel']);
        $this->assertSame(0, Customer::count());
    }

    public function test_advanced_filters(): void
    {
        $admin = $this->admin();
        $this->customer(['name' => 'Khách Nhật SME', 'country' => 'JP', 'segment' => 'sme', 'lifecycle_stage' => 'loyal', 'marketing_consent' => true, 'marketing_consent_source' => 'x']);
        $this->customer(['name' => 'Khách Việt Lẻ', 'country' => 'VN', 'segment' => 'retail', 'lifecycle_stage' => 'churned']);

        $this->actingAs($admin)->get(route('customers.index', ['segment' => 'sme']))->assertSee('Khách Nhật SME')->assertDontSee('Khách Việt Lẻ');
        $this->actingAs($admin)->get(route('customers.index', ['lifecycle' => 'churned']))->assertSee('Khách Việt Lẻ')->assertDontSee('Khách Nhật SME');
        $this->actingAs($admin)->get(route('customers.index', ['country' => 'JP']))->assertSee('Khách Nhật SME')->assertDontSee('Khách Việt Lẻ');
        $this->actingAs($admin)->get(route('customers.index', ['consent' => '1']))->assertSee('Khách Nhật SME')->assertDontSee('Khách Việt Lẻ');
        $this->actingAs($admin)->get(route('customers.index', ['segment' => "x' OR 1=1"]))->assertOk()->assertSee('Khách Việt Lẻ');   // giá trị lạ bị bỏ qua
    }

    // ---------------- (b) Trường tùy biến ----------------

    public function test_custom_fields_are_managed_per_organization_and_used_in_forms(): void
    {
        $admin = $this->admin();
        $sale = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $this->actingAs($sale)->get(route('settings.custom-fields'))->assertForbidden();
        $this->actingAs($sale)->post(route('settings.custom-fields.store'), ['label' => 'X', 'type' => 'text'])->assertForbidden();

        $this->actingAs($admin)->post(route('settings.custom-fields.store'), ['label' => 'Mã số thuế', 'type' => 'text', 'is_required' => '1'])->assertRedirect();
        $this->actingAs($admin)->post(route('settings.custom-fields.store'), ['label' => 'Quy mô', 'type' => 'select', 'options' => "Dưới 10\n10–50\nTrên 50"])->assertRedirect();
        $this->actingAs($admin)->post(route('settings.custom-fields.store'), ['label' => 'Ngày thành lập', 'type' => 'date'])->assertRedirect();
        $this->assertSame(['ma_so_thue', 'quy_mo', 'ngay_thanh_lap'], CustomFieldDefinition::for('customer')->pluck('key')->all());

        $this->actingAs($admin)->get(route('customers.create'))->assertSee('Mã số thuế')->assertSee('Trên 50');
        // Bắt buộc + lựa chọn không hợp lệ
        $this->actingAs($admin)->post(route('customers.store'), $this->payload(['custom' => ['quy_mo' => 'Nghìn tỷ']]))
            ->assertSessionHasErrors(['custom.ma_so_thue', 'custom.quy_mo']);
        $this->actingAs($admin)->post(route('customers.store'), $this->payload(['custom' => ['ma_so_thue' => '0312345678', 'quy_mo' => '10–50', 'ngay_thanh_lap' => '2015-06-01']]))
            ->assertRedirect();
        $c = Customer::firstOrFail();
        $this->assertSame(['ma_so_thue' => '0312345678', 'quy_mo' => '10–50', 'ngay_thanh_lap' => '2015-06-01'], $c->custom_fields);
        $this->actingAs($admin)->get(route('customers.show', $c))->assertSee('0312345678')->assertSee('01/06/2015');

        // Ẩn trường: dữ liệu cũ được giữ khi cập nhật khách hàng
        $field = CustomFieldDefinition::where('key', 'quy_mo')->first();
        $this->actingAs($admin)->put(route('settings.custom-fields.update', $field), ['label' => 'Quy mô', 'options' => "Dưới 10\n10–50\nTrên 50", 'is_active' => '0'])->assertRedirect();
        $this->actingAs($admin)->put(route('customers.update', $c), $this->payload(['custom' => ['ma_so_thue' => '0399999999']]))->assertRedirect();
        $this->assertSame('10–50', $c->fresh()->custom_fields['quy_mo']);
        $this->assertSame('0399999999', $c->fresh()->custom_fields['ma_so_thue']);

        // Xuất file có cột trường tùy biến
        $csv = $this->actingAs($admin)->get(route('customers.export', ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('Mã số thuế', $csv);
        $this->assertStringContainsString('0399999999', $csv);
    }

    public function test_custom_fields_of_other_organization_are_invisible(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER, ['email' => 'a@example.com']);
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        $fieldB = app(CurrentOrganization::class)->run($orgB->id, fn () => CustomFieldDefinition::create(['entity' => 'customer', 'key' => 'bi_mat_b', 'label' => 'Trường BÍ MẬT B', 'type' => 'text']));

        $this->actingAs($owner)->get(route('customers.create'))->assertDontSee('Trường BÍ MẬT B');
        $this->actingAs($owner)->get(route('settings.custom-fields'))->assertDontSee('Trường BÍ MẬT B');
        $this->actingAs($owner)->put(route('settings.custom-fields.update', $fieldB->id), ['label' => 'Hack'])->assertNotFound();
        $this->actingAs($owner)->delete(route('settings.custom-fields.destroy', $fieldB->id))->assertNotFound();
    }

    // ---------------- (c) Gộp khách hàng ----------------

    public function test_merge_moves_everything_and_archives_duplicate(): void
    {
        $admin = $this->admin();
        $keep = $this->customer(['name' => 'Nguyễn An', 'phone' => '0901111111', 'notes' => 'Ghi chú A']);
        $dup = $this->customer(['name' => 'Nguyen An', 'email' => 'an@example.com', 'company' => 'Công ty An', 'notes' => 'Ghi chú B',
            'marketing_consent' => true, 'marketing_consent_source' => 'Hợp đồng', 'custom_fields' => ['mst' => '0301']]);
        $tag = Tag::create(['name' => 'VIP-test', 'color' => '#000']);
        $dup->tags()->attach($tag);
        Contact::create(['customer_id' => $dup->id, 'full_name' => 'Liên hệ của bản trùng']);
        Opportunity::create(['title' => 'Cơ hội bản trùng', 'customer_id' => $dup->id, 'pipeline_stage_id' => PipelineStage::first()->id, 'value' => 1]);
        SupportTicket::create(['customer_id' => $dup->id, 'subject' => 'Ticket bản trùng', 'description' => 'x', 'priority' => 'low', 'status' => 'open']);
        Task::create(['title' => 'Việc bản trùng', 'priority' => 'low', 'status' => 'pending', 'related_type' => 'customer', 'related_id' => $dup->id]);
        $dup->interactions()->create(['type' => 'call', 'status' => 'done', 'content' => 'Đã gọi bản trùng', 'user_id' => $admin->id]);
        $lead = Lead::create(['name' => 'Lead cũ', 'status' => 'converted', 'converted_customer_id' => $dup->id]);

        $this->actingAs($admin)->get(route('customers.merge', [$keep, 'with' => $dup->id]))->assertOk()->assertSee(__('Xem trước khi gộp'))->assertSee($dup->code);
        $this->actingAs($admin)->post(route('customers.merge.store', $keep), ['merge_id' => $dup->id])->assertSessionHasErrors('confirm');
        $this->actingAs($admin)->post(route('customers.merge.store', $keep), ['merge_id' => $dup->id, 'confirm' => '1'])->assertRedirect(route('customers.show', $keep));

        $keep->refresh();
        $this->assertSame(1, Contact::where('customer_id', $keep->id)->count());
        $this->assertSame(1, Opportunity::where('customer_id', $keep->id)->count());
        $this->assertSame(1, SupportTicket::where('customer_id', $keep->id)->count());
        $this->assertSame(1, Task::where('related_type', 'customer')->where('related_id', $keep->id)->count());
        $this->assertSame(1, Interaction::where('interactable_id', $keep->id)->where('interactable_type', 'customer')->count());
        $this->assertSame($keep->id, $lead->fresh()->converted_customer_id);
        $this->assertTrue($keep->tags->contains($tag));
        $this->assertSame('an@example.com', $keep->email);            // bổ sung chỗ trống
        $this->assertSame('0901111111', $keep->phone);                 // không ghi đè dữ liệu có sẵn
        $this->assertSame('Công ty An', $keep->company);
        $this->assertTrue($keep->marketing_consent);
        $this->assertSame('0301', $keep->custom_fields['mst']);
        $this->assertStringContainsString('Ghi chú B', $keep->notes);

        $archived = Customer::withTrashed()->find($dup->id);
        $this->assertNotNull($archived->deleted_at);
        $this->assertSame($keep->id, $archived->merged_into_id);
        $this->assertTrue(\App\Models\ActivityLog::withoutGlobalScopes()->where('action', 'merged')->where('subject_id', $keep->id)->exists());
    }

    public function test_merge_requires_permissions_and_same_organization(): void
    {
        $sale = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);   // không có quyền xóa khách hàng
        $a = $this->customer(['assigned_to' => $sale->id]);
        $b = $this->customer(['assigned_to' => $sale->id]);
        $this->actingAs($sale)->post(route('customers.merge.store', $a), ['merge_id' => $b->id, 'confirm' => '1'])->assertForbidden();
        $this->assertNull(Customer::find($b->id)->deleted_at);

        $owner = $this->admin();
        $this->actingAs($owner)->post(route('customers.merge.store', $a), ['merge_id' => $a->id, 'confirm' => '1'])->assertNotFound();   // không gộp với chính nó
        $orgB = Organization::create(['name' => 'B', 'slug' => 'b']);
        app(OrganizationProvisioner::class)->provision($orgB);
        $other = app(CurrentOrganization::class)->run($orgB->id, fn () => Customer::create(['name' => 'Khách B', 'type' => 'individual', 'status' => 'active']));
        $this->actingAs($this->userWithRole(\App\Support\PermissionCatalog::OWNER, ['email' => 'o@example.com']))
            ->post(route('customers.merge.store', $a), ['merge_id' => $other->id, 'confirm' => '1'])->assertNotFound();
        $this->assertNull(app(CurrentOrganization::class)->run($orgB->id, fn () => Customer::find($other->id))->deleted_at);
    }

    // ---------------- (d) Nhập Excel & tệp đính kèm ----------------

    public function test_xlsx_import_with_new_columns(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('settings.custom-fields.store'), ['label' => 'Mã số thuế', 'type' => 'text']);

        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['name', 'phone', 'email', 'country', 'segment', 'lifecycle_stage', 'marketing_consent', 'Mã số thuế'],
            ['Khách Excel 1', 912345678, 'ex1@example.com', 'Nhật Bản', 'enterprise', 'loyal', 'yes', '0311'],
            ['', '', '', '', '', '', '', ''],
            ['Khách Excel 2', '0987654321', 'email-sai', 'VN', 'sme', 'new', 'no', ''],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        (new Xlsx($sheet))->save($path);
        $file = new UploadedFile($path, 'khach.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($admin)->post(route('customers.import.store'), ['file' => $file])->assertRedirect(route('customers.import'));
        $c = Customer::where('name', 'Khách Excel 1')->firstOrFail();
        $this->assertSame('0912345678', $c->phone);   // bù số 0 đầu bị Excel làm mất
        $this->assertSame(['JP', 'enterprise', 'loyal'], [$c->country, $c->segment, $c->lifecycle_stage]);
        $this->assertTrue($c->marketing_consent);
        $this->assertStringContainsString('khach.xlsx', $c->marketing_consent_source);
        $this->assertSame('0311', $c->custom_fields['ma_so_thue']);
        $this->assertFalse(Customer::where('name', 'Khách Excel 2')->exists());   // email sai → bỏ qua, có báo lỗi
        $this->assertSame(1, session('import_result')['skipped']);
        @unlink($path);
    }

    public function test_customer_attachments_respect_permissions(): void
    {
        Storage::fake('local');
        $sale = $this->userWithRole('Sales Employee', ['email' => 'sale@example.com']);
        $other = $this->userWithRole('Sales Employee', ['email' => 'other@example.com']);
        $c = $this->customer(['assigned_to' => $sale->id]);

        $this->actingAs($sale)->post(route('customers.attachments.store', $c), ['file' => UploadedFile::fake()->create('hop-dong.pdf', 120, 'application/pdf')])->assertSessionHas('success');
        $this->actingAs($sale)->post(route('customers.attachments.store', $c), ['file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');
        $att = Attachment::firstOrFail();
        Storage::disk('local')->assertExists($att->path);
        $this->assertStringNotContainsString('hop-dong', $att->path);   // tên tệp lưu ngẫu nhiên

        $this->actingAs($sale)->get(route('customers.attachments.download', [$c, $att]))->assertOk();
        $this->actingAs($other)->get(route('customers.attachments.download', [$c, $att]))->assertForbidden();
        $this->actingAs($other)->delete(route('customers.attachments.destroy', [$c, $att]))->assertForbidden();

        // Tệp của khách hàng khác không tải được qua đường dẫn của khách hàng này
        $c2 = $this->customer(['assigned_to' => $sale->id]);
        $this->actingAs($sale)->get(route('customers.attachments.download', [$c2, $att]))->assertNotFound();

        $this->actingAs($sale)->delete(route('customers.attachments.destroy', [$c, $att]))->assertSessionHas('success');
        Storage::disk('local')->assertMissing($att->path);
    }
}
