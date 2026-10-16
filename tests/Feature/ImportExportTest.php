<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Task;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Bước 2.8 — nhập CSV / Excel cho lead, người liên hệ, sản phẩm; xuất công việc.
 * Mỗi dòng qua đúng quy tắc như nhập tay; dòng lỗi / trùng bị bỏ qua và báo số dòng.
 */
class ImportExportTest extends TestCase
{
    private function csv(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "\xEF\xBB\xBF".$content);
    }

    public function test_lead_import_with_vietnamese_headers_and_row_checks(): void
    {
        $admin = $this->admin();
        Lead::create(['name' => 'Lead cũ', 'status' => 'new', 'email' => 'cu@example.com']);
        $file = $this->csv('leads.csv', implode("\n", [
            'Tên;Điện thoại;Email;Nguồn;Giai đoạn;Giá trị dự kiến',
            'Anh Nhập 1;912345678;nhap1@example.com;Website;Đã liên hệ;15.000.000',   // số 0 đầu bị mất, giai đoạn theo tên
            'Chị Nhập 2;;;;;',                                                          // tối thiểu: chỉ có tên
            'Trùng Email;;CU@example.com;;;',                                           // trùng lead đã có
            'Đã chuyển;;;;converted;',                                                  // không nhập được giai đoạn chuyển đổi
            ';0900000000;;;;',                                                          // thiếu tên
            ';;;;;',                                                                    // dòng trống — bỏ qua im lặng
            'Nguồn lạ;;;Tiktok;;',                                                      // nguồn không có
            'Giá sai;;;;;abc',
        ]));

        $this->actingAs($admin)->post(route('leads.import.store'), ['file' => $file])->assertRedirect(route('leads.import'));
        $r = session('import_result');
        $this->assertSame(2, $r['created']);
        $this->assertSame(5, $r['skipped']);
        $this->assertCount(5, $r['errors']);
        $this->assertStringStartsWith(__('Dòng :line: :error', ['line' => 4, 'error' => '']), $r['errors'][0]);

        $l = Lead::where('name', 'Anh Nhập 1')->firstOrFail();
        $this->assertSame(['0912345678', 'contacted', 15000000.0, $admin->id, $admin->id], [$l->phone, $l->status, (float) $l->estimated_value, $l->assigned_to, $l->created_by]);
        $this->assertSame('Website', $l->source->name);
        $this->assertGreaterThan(0, $l->score);   // chấm điểm như khi tạo tay
        $this->assertSame(array_key_first(Lead::statuses()), Lead::where('name', 'Chị Nhập 2')->value('status'));
        $this->assertTrue(ActivityLog::where('action', 'imported')->where('description', 'like', 'Nhập 2 lead%')->exists());
    }

    public function test_contact_import_links_only_visible_customers(): void
    {
        $sale = $this->userWithRole('Sales Employee');
        $mine = $this->customer(['name' => 'Khách Của Tôi', 'assigned_to' => $sale->id]);
        $this->customer(['name' => 'Khách Người Khác', 'assigned_to' => $this->admin()->id]);
        $this->customer(['name' => 'Tên Trùng', 'assigned_to' => $sale->id]);
        $this->customer(['name' => 'Tên Trùng', 'assigned_to' => $sale->id]);
        Contact::create(['full_name' => 'Có sẵn', 'email' => 'san@example.com']);

        $file = $this->csv('contacts.csv', implode("\n", [
            'full_name,position,email,phone,customer',
            'Liên hệ 1,Giám đốc,lh1@example.com,0901111111,'.$mine->code,
            'Liên hệ 2,,,,Khách Của Tôi',
            'Liên hệ 3,,,,Khách Người Khác',   // không có quyền với khách hàng này
            'Liên hệ 4,,,,Tên Trùng',          // mơ hồ
            'Liên hệ 5,,SAN@example.com,,',    // trùng email
        ]));
        $this->actingAs($sale)->post(route('contacts.import.store'), ['file' => $file]);
        $r = session('import_result');

        $this->assertSame([2, 3], [$r['created'], $r['skipped']]);
        $this->assertSame($mine->id, Contact::where('full_name', 'Liên hệ 1')->value('customer_id'));
        $this->assertSame($mine->id, Contact::where('full_name', 'Liên hệ 2')->value('customer_id'));
        $this->assertSame($sale->id, Contact::where('full_name', 'Liên hệ 1')->value('created_by'));
        $this->assertFalse(Contact::where('full_name', 'Liên hệ 3')->exists());
    }

    public function test_product_import_from_excel_never_overwrites_existing_skus(): void
    {
        $admin = $this->admin();
        Product::create(['sku' => 'CO-SAN', 'name' => 'Sản phẩm gốc', 'price' => 100, 'unit' => 'Cái', 'status' => 'active']);
        $trashed = Product::create(['sku' => 'DA-XOA', 'name' => 'Đã xóa', 'price' => 1, 'unit' => 'Cái', 'status' => 'active']);
        $trashed->delete();

        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['Mã sản phẩm', 'Tên sản phẩm', 'Danh mục', 'Đơn giá', 'ĐVT', 'Trạng thái'],
            ['SP-01', 'Gói CRM', 'Phần mềm', 12000000, 'Gói', ''],
            ['SP-02', 'Tư vấn', 'Dịch vụ', '1.500.000', 'Giờ', 'ngừng kinh doanh'],
            ['CO-SAN', 'Ghi đè?', '', 999, 'Cái', ''],       // SKU đã có
            ['DA-XOA', 'Ghi đè?', '', 999, 'Cái', ''],       // SKU của sản phẩm trong thùng rác
            ['SP 03', 'SKU có dấu cách', '', 1, 'Cái', ''],
            ['SP-04', 'Danh mục lạ', 'Không có', 1, 'Cái', ''],
            ['SP-01', 'Trùng trong tệp', '', 1, 'Cái', ''],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        (new Xlsx($sheet))->save($path);
        $file = new UploadedFile($path, 'san-pham.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($admin)->post(route('products.import.store'), ['file' => $file]);
        $r = session('import_result');

        $this->assertSame([2, 5], [$r['created'], $r['skipped']]);
        $this->assertSame('Sản phẩm gốc', Product::where('sku', 'CO-SAN')->value('name'));
        $p = Product::where('sku', 'SP-02')->firstOrFail();
        $this->assertSame([1500000.0, 'inactive', 'Dịch vụ'], [(float) $p->price, $p->status, $p->category->name]);
        $this->assertSame(1, Product::where('sku', 'SP-01')->count());
    }

    public function test_import_requires_create_permission_and_stays_in_organization(): void
    {
        $sale = $this->userWithRole('Sales Employee');   // tạo lead được, tạo sản phẩm không
        $this->actingAs($sale)->get(route('leads.import'))->assertOk();
        $this->get(route('products.import'))->assertForbidden();
        $this->post(route('products.import.store'), ['file' => $this->csv('p.csv', "sku,name,price,unit\nX-1,A,1,Cái")])->assertForbidden();
        $auditor = $this->userWithRole('Read-only Auditor');
        $this->actingAs($auditor)->get(route('leads.import'))->assertForbidden();

        // SKU đã có ở tổ chức khác không chặn tổ chức này; bản ghi nhập vào thuộc tổ chức đang làm việc
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        app(CurrentOrganization::class)->run($orgB->id, fn () => Product::create(['sku' => 'CHUNG', 'name' => 'Của B', 'price' => 1, 'unit' => 'Cái', 'status' => 'active']));
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('products.import.store'), ['file' => $this->csv('p.csv', "sku,name,price,unit\nCHUNG,Của A,5,Cái")]);
        $this->assertSame(1, session('import_result')['created']);
        $this->assertSame('Của A', Product::where('sku', 'CHUNG')->value('name'));
    }

    public function test_templates_and_bad_files(): void
    {
        $admin = $this->admin();
        foreach (['leads' => 'name', 'contacts' => 'full_name', 'products' => 'sku'] as $module => $first) {
            $res = $this->actingAs($admin)->get(route($module.'.import.template'))->assertOk();
            $this->assertStringStartsWith("\xEF\xBB\xBF".$first.',', $res->getContent());
            $this->get(route($module.'.import'))->assertOk()->assertSee('<code>'.$first.'</code>', false);
        }
        $this->post(route('leads.import.store'), ['file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');
        $this->post(route('leads.import.store'), ['file' => $this->csv('x.csv', "cot_la,khac\n1,2")]);
        $this->assertSame(0, session('import_result')['created']);
        $this->assertStringContainsString('name', session('import_result')['errors'][0]);
    }

    public function test_task_export_only_contains_visible_tasks(): void
    {
        $sale = $this->userWithRole('Sales Employee');
        Task::create(['title' => 'Việc của tôi', 'priority' => 'high', 'status' => 'pending', 'assigned_to' => $sale->id, 'due_date' => now()->addDay()]);
        Task::create(['title' => 'Việc người khác', 'priority' => 'low', 'status' => 'pending', 'assigned_to' => $this->admin()->id]);

        $csv = $this->actingAs($sale)->get(route('tasks.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Việc của tôi', $csv);
        $this->assertStringNotContainsString('Việc người khác', $csv);
        $this->assertTrue(ActivityLog::where('action', 'exported')->where('user_id', $sale->id)->exists());
        $this->get(route('tasks.index'))->assertSee(route('tasks.export'), false);
    }
}
