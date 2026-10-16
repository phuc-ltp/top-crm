<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Interaction;
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

            Interaction::create([
                'interactable_type' => 'customer', 'interactable_id' => $customer->id,
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

        $this->command?->info('Đã tạo dữ liệu DEMO (is_demo = 1).');
    }

    /**
     * Bổ sung cho tính năng Phase 2 (chạy SAU cùng để không đổi dữ liệu ngẫu nhiên đã sinh ở trên):
     * phân khúc / vòng đời khách hàng, ngày lead đủ điều kiện, lý do thất bại, lịch hẹn sắp tới.
     */
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
            Interaction::create([
                'interactable_type' => 'customer', 'interactable_id' => $customer->id,
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
}
