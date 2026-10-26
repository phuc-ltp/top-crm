<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\PipelineStage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesTarget;
use App\Models\SupportTicket;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SalesDocumentService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DỮ LIỆU DEMO — chỉ phục vụ thử nghiệm. Mọi bản ghi có is_demo = 1.
 * Tài khoản demo có mật khẩu ngẫu nhiên (không đăng nhập được) cho tới khi quản trị viên đặt lại.
 * Xóa toàn bộ bằng: php artisan crm:purge-demo
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Customer::where('is_demo', true)->exists()) {
            $this->command?->warn('Dữ liệu demo đã tồn tại — bỏ qua.');

            return;
        }

        $faker = Faker::create('vi_VN');
        $faker->seed(2026);
        mt_srand(2026);

        $users = $this->users();
        $sales = collect([$users['Sales Manager'], $users['Sales Staff'], $users['Sales Staff 2']]);
        $sources = LeadSource::pluck('id')->all();
        $tags = Tag::pluck('id')->all();
        $stages = PipelineStage::where('pipeline_id', \App\Models\Pipeline::default()?->id)->ordered()->get();

        // Sản phẩm
        $catalog = [
            ['SW-CRM-STD', 'Phần mềm CRM - Gói Tiêu chuẩn', 'Phần mềm', 12000000, 'gói'],
            ['SW-CRM-PRO', 'Phần mềm CRM - Gói Chuyên nghiệp', 'Phần mềm', 25000000, 'gói'],
            ['SW-ERP-LITE', 'Phần mềm ERP Lite', 'Phần mềm', 45000000, 'gói'],
            ['SV-IMPL', 'Dịch vụ triển khai', 'Dịch vụ', 15000000, 'dự án'],
            ['SV-CUSTOM', 'Phát triển tính năng theo yêu cầu', 'Dịch vụ', 800000, 'giờ'],
            ['HW-SERVER', 'Máy chủ Dell PowerEdge T150', 'Phần cứng', 38000000, 'cái'],
            ['HW-POS', 'Máy bán hàng POS', 'Phần cứng', 9500000, 'cái'],
            ['TR-USER', 'Đào tạo người dùng', 'Đào tạo', 5000000, 'buổi'],
            ['SP-YEAR', 'Gói hỗ trợ kỹ thuật 12 tháng', 'Gói hỗ trợ', 18000000, 'năm'],
            ['SP-CLOUD', 'Lưu trữ đám mây 1TB', 'Gói hỗ trợ', 3600000, 'năm'],
        ];
        $categories = ProductCategory::pluck('id', 'name');
        $products = collect($catalog)->map(fn ($p) => Product::create([
            'sku' => $p[0], 'name' => $p[1], 'product_category_id' => $categories[$p[2]] ?? null,
            'description' => 'Sản phẩm demo: '.$p[1], 'price' => $p[3], 'unit' => $p[4], 'status' => 'active', 'is_demo' => true,
        ]));

        // Khách hàng (30)
        $customers = collect();
        for ($i = 0; $i < 30; $i++) {
            $isBusiness = $i % 3 !== 0;
            $created = Carbon::now()->subDays(mt_rand(0, 360))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $customer = Customer::create([
                'name' => $isBusiness ? $this->companyName($faker) : $this->personName($faker),
                'phone' => '09'.mt_rand(10000000, 99999999),
                'email' => 'khachhang'.($i + 1).'@demo.orangecrm.local',
                'address' => $faker->address(),
                'company' => $isBusiness ? null : ($i % 2 ? $this->companyName($faker) : null),
                'type' => $isBusiness ? 'business' : 'individual',
                'lead_source_id' => $faker->randomElement($sources),
                'status' => $faker->randomElement(['active', 'active', 'potential', 'vip', 'inactive']),
                'assigned_to' => $sales->random()->id,
                'created_by' => $users['Sales Manager']->id,
                'notes' => 'Khách hàng demo.',
                'is_demo' => true,
            ]);
            $customer->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();
            $customer->tags()->attach($faker->randomElements($tags, mt_rand(0, 2)));
            $customers->push($customer);

            Contact::create([
                'customer_id' => $customer->id, 'full_name' => $this->personName($faker),
                'position' => $faker->randomElement(['Giám đốc', 'Trưởng phòng mua hàng', 'Kế toán trưởng', 'Trưởng phòng IT']),
                'email' => 'lienhe'.($i + 1).'@demo.orangecrm.local', 'phone' => '09'.mt_rand(10000000, 99999999),
                'company' => $customer->company ?? $customer->name, 'created_by' => $customer->assigned_to, 'is_demo' => true,
            ]);

            $customer->interactions()->create([
                'type' => $faker->randomElement(['call', 'email', 'meeting', 'note']),
                'subject' => 'Trao đổi nhu cầu', 'content' => 'Nội dung tương tác demo với khách hàng.',
                'occurred_at' => $created->copy()->addDays(mt_rand(0, 5)), 'user_id' => $customer->assigned_to,
            ]);
        }

        // Chiến dịch (5)
        $campaignDefs = [
            ['Ra mắt CRM Pro Q1', 'event', 'completed', -300, -240, 50000000, 40],
            ['Quảng cáo Facebook mùa hè', 'social', 'completed', -150, -90, 30000000, 60],
            ['Google Ads doanh nghiệp SME', 'ads', 'active', -60, 30, 45000000, 80],
            ['Email chăm sóc khách hàng cũ', 'email', 'active', -30, 60, 5000000, 30],
            ['Hội thảo chuyển đổi số cuối năm', 'event', 'planned', 20, 50, 80000000, 100],
        ];
        $campaigns = collect($campaignDefs)->map(fn ($c) => Campaign::create([
            'name' => $c[0], 'type' => $c[1], 'status' => $c[2],
            'start_date' => today()->addDays($c[3]), 'end_date' => today()->addDays($c[4]),
            'budget' => $c[5], 'target_leads' => $c[6], 'description' => 'Chiến dịch demo.',
            'owner_id' => $users['Marketing Staff']->id, 'created_by' => $users['Marketing Staff']->id, 'is_demo' => true,
        ]));

        // Leads (30)
        $leads = collect();
        $statuses = ['new', 'new', 'contacted', 'contacted', 'qualified', 'proposal', 'converted', 'lost'];
        for ($i = 0; $i < 30; $i++) {
            $status = $statuses[$i % count($statuses)];
            $created = Carbon::now()->subDays(mt_rand(0, 330))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $lead = Lead::create([
                'name' => $this->personName($faker), 'company' => $this->companyName($faker),
                'email' => 'lead'.($i + 1).'@demo.orangecrm.local', 'phone' => '03'.mt_rand(10000000, 99999999),
                'lead_source_id' => $faker->randomElement($sources), 'status' => $status,
                'score' => mt_rand(10, 95), 'estimated_value' => mt_rand(5, 120) * 1000000,
                'assigned_to' => $sales->random()->id, 'created_by' => $users['Marketing Staff']->id,
                'notes' => 'Lead demo.', 'is_demo' => true,
            ]);
            if ($status === 'converted') {
                $lead->forceFill([
                    'converted_customer_id' => $customers->random()->id,
                    'converted_at' => $created->copy()->addDays(mt_rand(3, 20)),
                ])->saveQuietly();
            }
            $lead->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();
            $lead->campaigns()->attach($campaigns->random()->id, ['created_at' => $created]);
            $leads->push($lead);
        }

        // Cơ hội (15)
        $titles = ['Triển khai CRM', 'Nâng cấp ERP', 'Mua máy chủ', 'Gói hỗ trợ năm', 'Đào tạo nhân viên', 'Trang bị POS'];
        for ($i = 0; $i < 15; $i++) {
            $stage = $stages[$i % $stages->count()];
            $customer = $customers->random();
            $created = Carbon::now()->subDays(mt_rand(10, 300));
            $opp = Opportunity::create([
                'title' => $faker->randomElement($titles).' - '.Str::limit($customer->name, 30, ''),
                'customer_id' => $customer->id, 'contact_id' => $customer->contacts()->value('id'),
                'pipeline_stage_id' => $stage->id, 'value' => mt_rand(10, 300) * 1000000,
                'probability' => $stage->probability, 'expected_close_date' => today()->addDays(mt_rand(-30, 90)),
                'closed_at' => $stage->isClosed() ? $created->copy()->addDays(mt_rand(5, 40)) : null,
                'lost_reason' => $stage->is_lost ? 'Khách chọn nhà cung cấp khác (demo)' : null,
                'position' => $i, 'assigned_to' => $customer->assigned_to, 'created_by' => $users['Sales Manager']->id,
                'notes' => 'Cơ hội demo.', 'is_demo' => true,
            ]);
            $opp->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();
            OpportunityStageHistory::create([
                'opportunity_id' => $opp->id, 'from_stage_id' => null, 'to_stage_id' => $stage->id,
                'changed_by' => $users['Sales Manager']->id, 'created_at' => $created,
            ]);
        }

        // Báo giá, đơn hàng, hóa đơn, thanh toán
        $docs = app(SalesDocumentService::class);
        $payments = app(PaymentService::class);
        for ($i = 0; $i < 12; $i++) {
            $customer = $customers->random();
            $date = Carbon::now()->subDays(mt_rand(5, 340));
            $items = collect($products->random(mt_rand(1, 3)))->map(fn ($p) => [
                'product_id' => $p->id, 'quantity' => mt_rand(1, 3), 'unit_price' => $p->price, 'discount_percent' => $faker->randomElement([0, 0, 5, 10]),
            ])->all();

            $quotation = $docs->save(new Quotation, [
                'customer_id' => $customer->id, 'issue_date' => $date, 'valid_until' => $date->copy()->addDays(30),
                'status' => $i < 9 ? 'accepted' : $faker->randomElement(['draft', 'sent', 'rejected']),
                'discount_amount' => 0, 'tax_rate' => 10, 'notes' => 'Báo giá demo.',
                'created_by' => $customer->assigned_to, 'assigned_to' => $customer->assigned_to, 'is_demo' => true,
            ], $items);

            if ($i >= 9) {
                continue;
            }
            $order = $docs->save(new SalesOrder, [
                'customer_id' => $customer->id, 'quotation_id' => $quotation->id, 'order_date' => $date->copy()->addDays(2),
                'status' => $i < 7 ? 'completed' : 'confirmed', 'discount_amount' => 0, 'tax_rate' => 10,
                'created_by' => $customer->assigned_to, 'assigned_to' => $customer->assigned_to,
                'approved_by' => $users['Sales Manager']->id, 'approved_at' => $date->copy()->addDays(2), 'is_demo' => true,
            ], $items);

            if ($i >= 8) {
                continue;
            }
            $invoice = $docs->save(new \App\Models\Invoice, [
                'customer_id' => $customer->id, 'sales_order_id' => $order->id, 'issue_date' => $date->copy()->addDays(3),
                'due_date' => $date->copy()->addDays(18), 'status' => 'issued', 'discount_amount' => 0, 'tax_rate' => 10,
                'created_by' => $customer->assigned_to, 'assigned_to' => $customer->assigned_to, 'is_demo' => true,
            ], $items);

            if ($i < 6) {
                $part = $i % 3 === 0 ? 0.5 : 1;
                $payments->record($invoice, [
                    'amount' => round((float) $invoice->total * $part), 'payment_date' => $date->copy()->addDays(mt_rand(4, 15))->min(now()),
                    'method' => 'bank_transfer', 'reference' => 'DEMO-'.($i + 1),
                ], $users['Sales Manager']);
            }
        }

        // Công việc (20)
        $taskTitles = ['Gọi điện tư vấn', 'Gửi báo giá', 'Hẹn gặp demo sản phẩm', 'Theo dõi thanh toán', 'Chuẩn bị hợp đồng', 'Khảo sát nhu cầu'];
        for ($i = 0; $i < 20; $i++) {
            $status = $faker->randomElement(['pending', 'pending', 'in_progress', 'completed', 'completed', 'cancelled']);
            $due = Carbon::now()->addDays(mt_rand(-20, 25))->setTime(mt_rand(8, 17), 0);
            if (in_array($status, ['pending', 'in_progress']) && $due->isPast()) {
                $status = 'overdue';
            }
            $customer = $customers->random();
            $task = Task::create([
                'title' => $faker->randomElement($taskTitles).' - '.Str::limit($customer->name, 30, ''),
                'description' => 'Công việc demo.', 'priority' => $faker->randomElement(['low', 'medium', 'medium', 'high', 'urgent']),
                'status' => $status, 'due_date' => $due, 'completed_at' => $status === 'completed' ? $due->copy()->subDay() : null,
                'related_type' => 'customer', 'related_id' => $customer->id,
                'assigned_to' => $sales->random()->id, 'created_by' => $users['Sales Manager']->id, 'is_demo' => true,
            ]);
            $task->checklist()->createMany([
                ['content' => 'Chuẩn bị tài liệu', 'is_done' => true, 'position' => 1],
                ['content' => 'Liên hệ khách hàng', 'is_done' => $status === 'completed', 'position' => 2],
            ]);
        }

        // Ticket hỗ trợ (10)
        $subjects = ['Không đăng nhập được', 'Lỗi xuất báo cáo', 'Yêu cầu thêm người dùng', 'Hỏi về gia hạn', 'Máy POS không in hóa đơn'];
        for ($i = 0; $i < 10; $i++) {
            $status = $faker->randomElement(['open', 'in_progress', 'waiting', 'resolved', 'closed']);
            $created = Carbon::now()->subDays(mt_rand(0, 90));
            $customer = $customers->random();
            $ticket = SupportTicket::create([
                'customer_id' => $customer->id, 'subject' => $faker->randomElement($subjects),
                'description' => 'Mô tả sự cố demo.', 'priority' => $faker->randomElement(['low', 'medium', 'high', 'urgent']),
                'status' => $status, 'assigned_to' => $users['Customer Support']->id, 'created_by' => $users['Customer Support']->id,
                'resolved_at' => in_array($status, ['resolved', 'closed']) ? $created->copy()->addHours(mt_rand(2, 72)) : null,
                'closed_at' => $status === 'closed' ? $created->copy()->addDays(4) : null, 'is_demo' => true,
            ]);
            $ticket->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();
            $ticket->replies()->create(['user_id' => $users['Customer Support']->id, 'body' => 'Đã tiếp nhận yêu cầu (demo).', 'is_internal' => false]);
        }

        // Chỉ tiêu doanh số tháng hiện tại
        foreach ($sales as $u) {
            for ($m = 0; $m < 3; $m++) {
                $d = now()->subMonths($m);
                SalesTarget::firstOrCreate(['user_id' => $u->id, 'year' => $d->year, 'month' => $d->month], ['target_amount' => 150000000]);
            }
        }

        $this->phase2Extras($customers, $sales);
        $this->phase3Extras($users['Sales Manager']);
        $this->phase45Extras($users, $customers);

        $this->command?->info('Đã tạo dữ liệu DEMO (is_demo = 1).');
    }

    /**
     * Bổ sung cho tính năng Phase 2 (chạy SAU cùng để không đổi dữ liệu ngẫu nhiên đã sinh ở trên):
     * phân khúc / vòng đời khách hàng, ngày lead đủ điều kiện, lý do thất bại, lịch hẹn sắp tới.
     */
    /**
     * Bổ sung cho Phase 3 (chạy sau cùng): dịch vụ không theo dõi tồn kho, giá vốn, nhà cung cấp,
     * tồn kho ban đầu bằng phiếu nhập kho (ghi sổ qua InventoryService, đánh dấu demo).
     */
    private function phase3Extras(User $actor): void
    {
        // Mã SV- / TR- / SP- là dịch vụ (triển khai, đào tạo, gói hỗ trợ, lưu trữ)
        Product::where('is_demo', true)->where(fn ($q) => $q->where('sku', 'like', 'SV-%')->orWhere('sku', 'like', 'TR-%')->orWhere('sku', 'like', 'SP-%'))
            ->update(['type' => 'service']);

        $suppliers = collect([
            ['Công ty Phân phối Thiết bị Sao Việt (demo)', 'Anh Quang', '0283456789', 'saoviet@demo.orangecrm.local', '0312000001'],
            ['Công ty Phần mềm Bản quyền An Tâm (demo)', 'Chị Mai', '0249876543', 'antam@demo.orangecrm.local', '0101000002'],
        ])->map(fn ($s) => \App\Models\Supplier::create(['name' => $s[0], 'contact_name' => $s[1], 'phone' => $s[2], 'email' => $s[3],
            'tax_code' => $s[4], 'status' => 'active', 'created_by' => $actor->id, 'is_demo' => true]));

        $stocked = Product::where('is_demo', true)->where('type', 'product')->get();
        foreach ($stocked as $p) {
            $p->forceFill([
                'purchase_price' => round((float) $p->price * 0.65, -3),
                'supplier_id' => str_starts_with($p->sku, 'HW-') ? $suppliers[0]->id : $suppliers[1]->id,
                'reorder_level' => str_starts_with($p->sku, 'HW-') ? 3 : null,
            ])->saveQuietly();
        }
        $warehouse = \App\Models\Warehouse::default();
        if ($warehouse && $stocked->isNotEmpty()) {
            app(\App\Services\InventoryService::class)->post([
                'type' => 'receipt', 'warehouse_id' => $warehouse->id, 'supplier_id' => $suppliers[0]->id,
                'document_date' => today()->subDays(20)->toDateString(), 'reference' => 'DEMO-NK-01', 'notes' => 'Tồn kho ban đầu (demo)', 'is_demo' => true,
            ], $stocked->map(fn ($p) => ['product_id' => $p->id, 'quantity' => str_starts_with($p->sku, 'HW-') ? 8 : 50, 'unit_cost' => $p->purchase_price])->values()->all(), $actor);
        }
    }

    private function phase2Extras($customers, $sales): void
    {
        $segments = array_keys(Customer::SEGMENTS);
        $stages = array_keys(Customer::LIFECYCLE_STAGES);
        $channels = array_keys(Customer::CHANNELS);
        foreach ($customers->values() as $i => $c) {
            $c->forceFill([
                'segment' => $segments[$i % count($segments)],
                'lifecycle_stage' => $stages[$i % count($stages)],
                'preferred_channel' => $channels[$i % count($channels)],
                'country' => 'VN',
            ])->saveQuietly();
        }

        // Lead đủ điều kiện / đã chuyển đổi: có ngày đạt điều kiện; lead mất: có lý do (danh mục lý do của tổ chức)
        $leadReason = \App\Models\LostReason::whereIn('applies_to', ['both', 'lead'])->orderBy('position')->value('id');
        $oppReason = \App\Models\LostReason::whereIn('applies_to', ['both', 'opportunity'])->orderBy('position')->value('id');
        $qualified = app(\App\Support\LeadStages::class)->all()->where('is_qualified', true)->pluck('key')->push('converted')->all();
        foreach (\App\Models\Lead::where('is_demo', true)->get() as $lead) {
            if (in_array($lead->status, $qualified, true)) {
                $lead->forceFill(['qualified_at' => $lead->converted_at?->copy()->subDays(2) ?? $lead->created_at->copy()->addDays(3)])->saveQuietly();
            } elseif ($lead->status === 'lost' && $leadReason) {
                $lead->forceFill(['lost_reason_id' => $leadReason, 'lost_note' => 'Lý do demo.'])->saveQuietly();
            }
        }
        if ($oppReason) {
            Opportunity::where('is_demo', true)->whereHas('stage', fn ($q) => $q->where('is_lost', true))->update(['lost_reason_id' => $oppReason]);
        }

        // Lịch hẹn: 6 việc theo dõi sắp tới + 2 đã quá hẹn (để thấy nhắc việc & trang Hoạt động)
        foreach (range(0, 7) as $i) {
            $customer = $customers[$i * 3];
            $customer->interactions()->create([
                'type' => ['call', 'meeting', 'follow_up', 'email'][$i % 4], 'status' => 'planned',
                'subject' => ['Gọi lại tư vấn gói', 'Hẹn demo sản phẩm', 'Theo dõi báo giá', 'Gửi tài liệu'][$i % 4].' (demo)',
                'scheduled_at' => now()->addDays($i < 6 ? $i + 1 : -($i - 4))->setTime(9 + $i, 0),
                'user_id' => $customer->assigned_to, 'assigned_to' => $customer->assigned_to,
            ]);
        }
    }

    private function companyName(\Faker\Generator $faker): string
    {
        $types = ['Công ty TNHH', 'Công ty Cổ phần', 'Công ty TNHH MTV', 'Tập đoàn'];
        $names = ['Minh Phát', 'Hoàng Long', 'An Khang', 'Thành Công', 'Việt Tiến', 'Đại Dương', 'Sao Mai', 'Phú Thịnh', 'Bình Minh', 'Tân Á', 'Hưng Thịnh', 'Nam Việt', 'Kim Ngân', 'Hòa Bình', 'Trường Sơn'];
        $fields = ['Thương mại', 'Công nghệ', 'Xây dựng', 'Dịch vụ', 'Sản xuất', 'Logistics', 'Thực phẩm'];

        return $faker->randomElement($types).' '.$faker->randomElement($fields).' '.$faker->randomElement($names);
    }

    private function personName(\Faker\Generator $faker): string
    {
        return $faker->lastName().' '.$faker->middleName().' '.$faker->firstName();
    }

    private function users(): array
    {
        $dept = Department::pluck('id', 'code');
        $defs = [
            'Administrator' => ['Trần Minh Quân', 'quantri', 'BGD', 'Quản trị hệ thống'],
            'Sales Manager' => ['Lê Thu Hà', 'truongkinhdoanh', 'KD', 'Trưởng phòng kinh doanh'],
            'Sales Staff' => ['Nguyễn Văn Nam', 'kinhdoanh1', 'KD', 'Nhân viên kinh doanh'],
            'Sales Staff 2' => ['Phạm Thị Lan', 'kinhdoanh2', 'KD', 'Nhân viên kinh doanh'],
            'Marketing Staff' => ['Hoàng Đức Huy', 'marketing', 'MKT', 'Chuyên viên marketing'],
            'Customer Support' => ['Vũ Ngọc Anh', 'hotro', 'CSKH', 'Chuyên viên CSKH'],
            'Viewer' => ['Đỗ Thanh Tùng', 'xem', 'KT', 'Kế toán'],
        ];

        $users = [];
        foreach ($defs as $key => [$name, $email, $code, $position]) {
            $user = User::firstOrCreate(['email' => $email.'@demo.orangecrm.local'], [
                'name' => $name,
                'password' => Hash::make(Str::random(40)), // không đăng nhập được cho tới khi admin đặt mật khẩu
                'position' => $position,
                'phone' => '09'.mt_rand(10000000, 99999999), 'status' => 'active', 'is_demo' => true,
            ]);
            $roleMap = ['Administrator' => 'Organization Administrator', 'Sales Manager' => 'Sales Manager', 'Sales Staff' => 'Sales Employee',
                'Marketing Staff' => 'Marketing Employee', 'Customer Support' => 'Support Agent', 'Viewer' => 'Read-only Auditor'];
            $user->syncRoles([$roleMap[str_replace(' 2', '', $key)]]);
            $user->assignDepartment($dept[$code] ?? null);
            $users[$key] = $user;
        }

        return $users;
    }
    /**
     * Bổ sung cho Phase 4–5 (chạy sau cùng): dự án, hồ sơ nhân viên + bậc lương, chấm công 2 tháng, tăng ca, kỳ lương tháng trước (đã duyệt),
     * tài sản (cấp phát / mượn quá hạn / bảo trì), bản quyền phần mềm. Bảng không gắn tài khoản được đánh dấu is_demo;
     * dữ liệu gắn tài khoản demo (hồ sơ, chấm công, tăng ca, phiếu lương) tự xóa theo khóa ngoại khi crm:purge-demo xóa tài khoản demo.
     */
    private function phase45Extras(array $users, $customers): void
    {
        $tz = crm_timezone();
        $admin = $users['Administrator'];
        $manager = $users['Sales Manager'];
        $staff = collect($users)->except('Administrator')->values();

        // Dự án: gắn một phần công việc demo
        foreach ([['Triển khai CRM cho khách hàng (demo)', 'active', 120000000], ['Chiến dịch ra mắt gói Chuyên nghiệp (demo)', 'planning', 45000000]] as $i => [$name, $status, $budget]) {
            $project = \App\Models\Project::create(['name' => $name, 'description' => 'Dự án demo.', 'customer_id' => $customers->get($i)?->id, 'manager_id' => $manager->id,
                'status' => $status, 'priority' => $i ? 'medium' : 'high', 'start_date' => now()->subDays(20 - $i * 10)->toDateString(), 'end_date' => now()->addDays(40 + $i * 20)->toDateString(),
                'budget' => $budget, 'created_by' => $manager->id, 'is_demo' => true]);
            $project->members()->syncWithoutDetaching($staff->take(4)->pluck('id')->all());
            Task::where('is_demo', true)->whereNull('project_id')->limit(5)->update(['project_id' => $project->id]);
        }

        // Hồ sơ nhân viên + bậc lương mặc định theo vai trò
        app(\App\Services\PayRateService::class)->ensureDefaults();
        $i = 0;
        foreach ($users as $key => $u) {
            \App\Models\EmployeeProfile::updateOrCreate(['user_id' => $u->id], [
                'employee_code' => sprintf('DEMO-%02d', ++$i), 'job_title' => $u->position, 'manager_id' => $key === 'Administrator' ? null : ($key === 'Sales Manager' ? $admin->id : $manager->id),
                'hire_date' => now()->subMonths(6 + $i * 3)->toDateString(), 'employment_status' => 'active', 'dependents' => $i % 3, 'attendance_consent_at' => now()->subMonths(2),
            ]);
        }

        // Chấm công: từ đầu tháng trước đến hôm qua, ngày làm việc; thỉnh thoảng đi trễ / về sớm / quên chấm ra / nghỉ
        $attendance = app(\App\Services\AttendanceService::class);
        foreach ($users as $u) {
            for ($d = now($tz)->subMonthNoOverflow()->startOfMonth(); $d->lt(today($tz)); $d->addDay()) {
                if (! $attendance->isWorkDay($d) || mt_rand(1, 100) <= 4) {
                    continue;                                                     // ngày nghỉ hoặc vắng
                }
                $roll = mt_rand(1, 100);
                $in = $d->copy()->setTime(7, mt_rand(10, 29));
                $out = $d->copy()->setTime(17, mt_rand(31, 55));
                if ($roll <= 10) {
                    $in = $d->copy()->setTime(7, mt_rand(40, 59));                // đi trễ
                } elseif ($roll <= 14) {
                    $out = $d->copy()->setTime(16, mt_rand(30, 59));              // về sớm
                } elseif ($roll <= 16) {
                    $out = null;                                                  // quên chấm ra
                }
                $r = (new \App\Models\AttendanceRecord(['user_id' => $u->id, 'work_date' => $d->toDateString()]))
                    ->forceFill(['check_in_at' => $in, 'check_out_at' => $out, 'check_in_ip' => '127.0.0.1']);
                $attendance->recalculate($r);
                $r->save();
            }
        }

        // Tăng ca: tháng trước 2 đơn đã duyệt / người kinh doanh, tháng này 1 đơn chờ duyệt
        $ot = app(\App\Services\OvertimeService::class);
        foreach ([$users['Sales Staff'], $users['Sales Staff 2'], $users['Marketing Staff']] as $n => $u) {
            foreach ([[now()->subMonthNoOverflow()->startOfMonth()->addDays(8 + $n), 'approved', 'high'], [now()->subMonthNoOverflow()->startOfMonth()->addDays(15 + $n), 'approved', 'medium'],
                [now()->subDays(1 + $n), 'pending', 'low']] as [$date, $status, $level]) {
                $req = \App\Models\OvertimeRequest::create(['user_id' => $u->id, 'work_date' => $date->toDateString(), 'start_time' => '18:00', 'end_time' => $level === 'low' ? '19:30' : '21:00',
                    'hours' => $level === 'low' ? 1.5 : 3, 'level' => $level, 'reason' => 'Hoàn thiện hồ sơ khách hàng (demo)', 'status' => $status]);
                if ($status === 'approved') {
                    $req->forceFill(['approver_id' => $manager->id, 'decided_at' => $date->copy()->addDay(), 'rate' => $ot->rateFor($u, $level)])->save();
                }
            }
        }

        // Nghỉ phép: đã duyệt (tháng trước), đang chờ (tuần sau)
        $leaveSvc = app(\App\Services\LeaveService::class);
        foreach ([[$users['Sales Staff'], now()->subMonthNoOverflow()->startOfMonth()->addWeekdays(10), 'annual', 'full', 'approved'], [$users['Customer Support'], now()->subMonthNoOverflow()->startOfMonth()->addWeekdays(12), 'personal', 'pm', 'approved'],
            [$users['Marketing Staff'], now()->addWeek()->startOfWeek()->addDay(), 'annual', 'full', 'pending']] as [$u, $d, $type, $part, $status]) {
            $l = \App\Models\LeaveRequest::create(['user_id' => $u->id, 'type' => $type, 'start_date' => $d->toDateString(), 'end_date' => $d->toDateString(), 'part' => $part,
                'days' => $leaveSvc->workingDays($d->toDateString(), $d->toDateString(), $part), 'reason' => 'Việc gia đình (demo)', 'status' => $status]);
            if ($status === 'approved') {
                $l->forceFill(['approver_id' => $manager->id, 'decided_at' => $d->copy()->subDays(3)])->save();
                \App\Models\AttendanceRecord::where('user_id', $u->id)->whereDate('work_date', $d)->when($part === 'full', fn ($q) => $q->delete());
            }
        }

        // Bảng lương tháng trước — đã tính và đã duyệt
        $period = \App\Models\PayrollPeriod::create(['period' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'status' => 'draft']);
        $period->forceFill(['created_by' => $admin->id, 'is_demo' => true])->save();
        app(\App\Services\PayrollService::class)->calculate($period);
        \App\Models\Payslip::where('payroll_period_id', $period->id)->where('user_id', $manager->id)->update(['allowance' => 1500000]);
        foreach (\App\Models\Payslip::where('payroll_period_id', $period->id)->get() as $slip) {
            app(\App\Services\PayrollService::class)->compute($slip)->save();
        }
        $period->forceFill(['status' => 'approved', 'submitted_at' => now()->subDays(3), 'approved_at' => now()->subDays(2), 'approved_by' => $admin->id])->save();

        // Tài sản
        $assets = app(\App\Services\AssetService::class);
        $assets->ensureCategories();
        $cat = \App\Models\AssetCategory::pluck('id', 'name');
        $catOf = fn ($name) => $cat[__($name)] ?? null;
        $list = [
            ['Laptop Dell Latitude 5440', 'Máy tính & thiết bị CNTT', 28500000, 36, 14, 'DL5440-D01'], ['Laptop MacBook Air M3', 'Máy tính & thiết bị CNTT', 32900000, 36, 5, 'MBA-D02'],
            ['Laptop HP ProBook 450', 'Máy tính & thiết bị CNTT', 19800000, 36, 22, 'HP450-D03'], ['Màn hình LG 27 inch', 'Máy tính & thiết bị CNTT', 6200000, 36, 10, 'LG27-D04'],
            ['Máy in Canon LBP2900', 'Thiết bị văn phòng', 3900000, 36, 30, 'CN2900-D05'], ['Máy chiếu Epson EB-X51', 'Thiết bị văn phòng', 11500000, 36, 18, 'EPX51-D06'],
            ['Bàn họp 12 chỗ', 'Nội thất', 18000000, 60, 26, null], ['Ghế công thái học', 'Nội thất', 4500000, 60, 8, null],
            ['Xe máy Honda Vision', 'Phương tiện vận tải', 32000000, 72, 18, 'VIS-D09'], ['Máy khoan Bosch GSB', 'Công cụ dụng cụ', 2900000, 24, 3, 'BOSCH-D10'],
        ];
        $created = [];
        foreach ($list as $k => [$name, $category, $cost, $months, $age, $sn]) {
            $a = $assets->create(['name' => $name.' (demo)', 'asset_category_id' => $catOf($category), 'purchase_cost' => $cost, 'salvage_value' => 0, 'depreciation_months' => $months,
                'purchase_date' => now()->subMonths($age)->toDateString(), 'serial_number' => $sn, 'location' => $k % 2 ? 'Tầng 2' : 'Tầng 1',
                'warranty_until' => $k === 3 ? now()->addDays(15)->toDateString() : now()->addMonths(12)->toDateString()], $admin);
            $a->forceFill(['is_demo' => true])->saveQuietly();
            $created[] = $a;
        }
        foreach ([0 => 'Sales Staff', 1 => 'Sales Manager', 2 => 'Sales Staff 2', 3 => 'Marketing Staff'] as $k => $who) {
            $assets->act($created[$k], 'assign', ['target_user_id' => $users[$who]->id, 'condition' => 'good'], $admin);
        }
        $assets->act($created[5], 'borrow', ['target_user_id' => $users['Customer Support']->id, 'due_back_at' => now()->addDays(5)->toDateString(), 'condition' => 'good'], $admin);
        $created[5]->refresh()->forceFill(['due_back_at' => now()->subDays(2)->toDateString()])->saveQuietly();    // mượn quá hạn để thử nhắc nhở
        $assets->act($created[4], 'maintenance', ['note' => 'Kẹt giấy, chờ thay trục (demo)', 'cost' => 350000], $admin);

        // Bản quyền phần mềm
        $licenses = app(\App\Services\LicenseService::class);
        foreach ([
            ['Microsoft 365 Business Standard', 'Microsoft', 'subscription', 8, 300, 26400000, 'yearly', 4],
            ['Adobe Creative Cloud', 'Adobe', 'subscription', 2, 20, 1300000, 'monthly', 1],
            ['Windows 11 Pro', 'Microsoft', 'oem', 4, null, 0, 'one_time', 0],
            ['Kaspersky Endpoint Security', 'Kaspersky', 'subscription', 10, -3, 9000000, 'yearly', 0],
        ] as [$name, $vendor, $type, $seats, $days, $cost, $cycle, $assignUsers]) {
            $l = \App\Models\SoftwareLicense::create(['name' => $name.' (demo)', 'vendor' => $vendor, 'license_type' => $type, 'seats' => $seats, 'cost' => $cost, 'billing_cycle' => $cycle,
                'expires_at' => $days === null ? null : now()->addDays($days)->toDateString(), 'license_key' => 'DEMO-'.strtoupper(Str::random(5)).'-'.strtoupper(Str::random(5)), 'owner_id' => $admin->id]);
            $l->forceFill(['is_demo' => true])->saveQuietly();
            foreach ($staff->take($assignUsers) as $u) {
                $licenses->assign($l, $u->id, null, null, $admin);
            }
            if ($type === 'oem') {
                foreach (array_slice($created, 0, 3) as $device) {
                    $licenses->assign($l, null, $device->id, null, $admin);
                }
            }
        }
    }
}
