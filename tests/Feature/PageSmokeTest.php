<?php

namespace Tests\Feature;

use Database\Seeders\DemoDataSeeder;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use Tests\TestCase;

/** Mở mọi trang chính (danh sách, chi tiết, form) để bảo đảm không có lỗi 500. */
class PageSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \App\Services\ActivityLogger::$enabled = false;
        $this->seed(DemoDataSeeder::class);
        \App\Services\ActivityLogger::$enabled = true;
    }

    public function test_all_index_and_create_pages_render_for_super_admin(): void
    {
        $this->actingAs($this->admin());

        $routes = [
            'dashboard', 'customers.index', 'customers.create', 'customers.import', 'contacts.index', 'contacts.create',
            'leads.index', 'leads.create', 'opportunities.index', 'opportunities.list', 'opportunities.create',
            'tasks.index', 'tasks.create', 'tasks.calendar', 'products.index', 'products.create',
            'quotations.index', 'quotations.create', 'orders.index', 'orders.create', 'invoices.index', 'invoices.create',
            'targets.index', 'campaigns.index', 'campaigns.create', 'tickets.index', 'tickets.create', 'reports.index',
            'users.index', 'users.create', 'roles.index', 'roles.create', 'departments.index', 'activity-logs.index',
            'settings.edit', 'settings.branding', 'design-system', 'profile.edit', 'profile.sessions', 'notifications.index',
        ];

        foreach ($routes as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_detail_and_edit_pages_render(): void
    {
        $this->actingAs($this->admin());

        $models = [
            'customers' => Customer::first(), 'contacts' => Contact::first(), 'leads' => Lead::where('status', '!=', 'converted')->first(),
            'opportunities' => Opportunity::first(), 'tasks' => Task::first(), 'products' => Product::first(),
            'campaigns' => Campaign::first(), 'tickets' => SupportTicket::first(), 'users' => User::where('is_demo', true)->first(),
            'quotations' => Quotation::where('status', 'draft')->first() ?? Quotation::first(), 'orders' => SalesOrder::first(), 'invoices' => Invoice::first(),
        ];

        foreach ($models as $prefix => $model) {
            $this->assertNotNull($model, "Thiếu dữ liệu demo cho $prefix");
            $this->get(route($prefix.'.show', $model))->assertOk();
        }
        foreach (['customers', 'contacts', 'leads', 'opportunities', 'tasks', 'products', 'campaigns', 'tickets', 'users'] as $prefix) {
            $this->get(route($prefix.'.edit', $models[$prefix]))->assertOk();
        }
        $this->get(route('leads.convert', $models['leads']))->assertOk();
        $this->get(route('roles.edit', \Spatie\Permission\Models\Role::findByName('Sales Staff')))->assertOk();
    }

    public function test_reports_render_and_export(): void
    {
        $this->actingAs($this->admin());

        foreach (array_keys(\App\Services\ReportService::TYPES) as $type) {
            $this->get(route('reports.show', $type))->assertOk();
            $this->get(route('reports.export', [$type, 'csv']))->assertOk();
        }
        $this->get(route('reports.export', ['revenue', 'xlsx']))->assertOk();
        $this->get(route('reports.export', ['employees', 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_and_exports_work(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('quotations.pdf', Quotation::first()))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('invoices.pdf', Invoice::first()))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('orders.pdf', SalesOrder::first()))->assertOk();
        foreach (['customers', 'contacts', 'leads', 'opportunities', 'products', 'quotations', 'orders', 'invoices', 'campaigns', 'tickets'] as $prefix) {
            $this->get(route($prefix.'.export', ['format' => 'csv']))->assertOk();
        }
        $this->get(route('customers.export', ['format' => 'xlsx']))->assertOk();
    }

    public function test_ajax_endpoints_return_json(): void
    {
        $this->actingAs($this->admin());

        $this->getJson(route('dashboard.data', ['period' => 'this_year']))->assertOk()->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['revenue', 'funnel', 'leads', 'growth', 'performance', 'target']]);
        $this->getJson(route('search', ['q' => 'Công ty']))->assertOk()->assertJsonStructure(['groups']);
        $this->getJson(route('notifications.feed'))->assertOk()->assertJsonStructure(['items', 'unread']);
        $this->getJson(route('api.stats'))->assertOk()->assertJsonPath('success', true);
        $this->getJson(route('api.customers.index'))->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->getJson(route('api.leads.index'))->assertOk();
        $this->getJson(route('api.opportunities.index'))->assertOk();
    }

    public function test_every_role_can_open_dashboard_and_menu_pages(): void
    {
        foreach (['Administrator', 'Sales Manager', 'Sales Staff', 'Marketing Staff', 'Customer Support', 'Viewer'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('dashboard'))->assertOk();
            foreach (\App\Support\Menu::flatRoutes($user) as $route) {
                $this->get(route($route))->assertOk();
            }
        }
    }
}
