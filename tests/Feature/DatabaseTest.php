<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\Product;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    public function test_required_tables_exist(): void
    {
        foreach (['users', 'roles', 'permissions', 'model_has_roles', 'role_has_permissions', 'departments', 'customers', 'contacts',
            'leads', 'lead_sources', 'opportunities', 'pipeline_stages', 'products', 'product_categories', 'quotations', 'quotation_items',
            'sales_orders', 'sales_order_items', 'invoices', 'invoice_items', 'payments', 'tasks', 'task_comments', 'campaigns',
            'campaign_leads', 'support_tickets', 'ticket_replies', 'notifications', 'activity_logs', 'attachments', 'settings'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Thiếu bảng $table");
        }
    }

    public function test_tables_use_innodb_utf8mb4(): void
    {
        $rows = DB::select('SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        foreach ($rows as $r) {
            $this->assertSame('InnoDB', $r->ENGINE, $r->TABLE_NAME);
            $this->assertStringStartsWith('utf8mb4', $r->TABLE_COLLATION, $r->TABLE_NAME);
        }
    }

    public function test_foreign_key_prevents_orphan_records(): void
    {
        $this->expectException(QueryException::class);
        DB::table('contacts')->insert(['full_name' => 'Mồ côi', 'customer_id' => 999999, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_restrict_delete_customer_with_invoice(): void
    {
        $customer = $this->customer();
        Invoice::create(['customer_id' => $customer->id, 'issue_date' => today(), 'total' => 1]);

        $this->expectException(QueryException::class);
        DB::table('customers')->where('id', $customer->id)->delete(); // xóa cứng bị chặn bởi khóa ngoại RESTRICT
    }

    public function test_cascade_and_null_on_delete(): void
    {
        $customer = $this->customer();
        $stage = PipelineStage::first();
        $opp = Opportunity::create(['title' => 'X', 'customer_id' => $customer->id, 'pipeline_stage_id' => $stage->id]);
        $opp->stageHistories()->create(['to_stage_id' => $stage->id, 'created_at' => now()]);

        DB::table('opportunities')->where('id', $opp->id)->delete();
        $this->assertSame(0, DB::table('opportunity_stage_histories')->where('opportunity_id', $opp->id)->count(), 'Cascade');

        $user = $this->userWithRole('Sales Staff');
        $c2 = $this->customer(['assigned_to' => $user->id]);
        DB::table('model_has_roles')->where('model_id', $user->id)->delete();
        DB::table('users')->where('id', $user->id)->delete();
        $this->assertNull($c2->fresh()->assigned_to, 'SET NULL khi xóa nhân viên');
    }

    public function test_unique_constraints(): void
    {
        Product::create(['sku' => 'DUP-1', 'name' => 'A', 'price' => 1]);
        $this->expectException(QueryException::class);
        Product::create(['sku' => 'DUP-1', 'name' => 'B', 'price' => 1]);
    }

    public function test_relationships(): void
    {
        $this->seed(DemoDataSeeder::class);
        $customer = Customer::has('contacts')->has('opportunities')->firstOrFail();
        $this->assertInstanceOf(\App\Models\Contact::class, $customer->contacts->first());
        $this->assertInstanceOf(PipelineStage::class, $customer->opportunities->first()->stage);
        $invoice = Invoice::has('items')->firstOrFail();
        $this->assertEqualsWithDelta((float) $invoice->subtotal, (float) $invoice->items->sum('line_total'), 0.01);
    }

    public function test_seeders_create_reference_and_demo_data(): void
    {
        $this->assertSame(7, DB::table('roles')->count());
        $this->assertSame(6, PipelineStage::count());
        $this->assertSame(0, Customer::count(), 'Không có dữ liệu demo khi CRM_DEMO_DATA=false');

        $this->seed(DemoDataSeeder::class);
        $this->assertSame(30, Customer::where('is_demo', true)->count());
        $this->assertSame(30, \App\Models\Lead::count());
        $this->assertSame(15, Opportunity::count());
        $this->assertSame(20, \App\Models\Task::count());
        $this->assertSame(10, Product::count());
        $this->assertSame(5, \App\Models\Campaign::count());
        $this->assertSame(10, \App\Models\SupportTicket::count());

        $this->seed(DemoDataSeeder::class); // chạy lại không nhân đôi
        $this->assertSame(30, Customer::count());

        // Tài khoản demo không thể đăng nhập bằng mật khẩu đoán được
        foreach (\App\Models\User::where('is_demo', true)->get() as $u) {
            foreach (['password', '123456', 'admin', 'demo'] as $guess) {
                $this->assertFalse(\Hash::check($guess, $u->password));
            }
        }
    }

    public function test_purge_demo_command(): void
    {
        $this->seed(DemoDataSeeder::class);
        $real = $this->customer(['name' => 'Khách thật']);

        $this->artisan('crm:purge-demo', ['--force' => true])->assertSuccessful();
        $this->assertSame(1, Customer::count());
        $this->assertTrue(Customer::whereKey($real->id)->exists());
        $this->assertSame(0, \App\Models\User::withTrashed()->where('is_demo', true)->count());
    }

    public function test_migrations_rollback_and_rerun(): void
    {
        $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('customers'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('customers'));
    }
}
