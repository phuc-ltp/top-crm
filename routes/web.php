<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesTargetController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Trang công khai (ai cũng xem được)
Route::get('/dieu-khoan-su-dung', [PageController::class, 'show'])->defaults('slug', 'terms')->name('pages.terms');
Route::get('/chinh-sach-bao-mat', [PageController::class, 'show'])->defaults('slug', 'privacy')->name('pages.privacy');
Route::get('/ho-tro', [PageController::class, 'show'])->defaults('slug', 'support')->name('pages.support');

// Đổi ngôn ngữ giao diện (khách và người dùng đã đăng nhập)
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update')->middleware('throttle:30,1');

/*
|--------------------------------------------------------------------------
| Khách (chưa đăng nhập)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/setup', [SetupController::class, 'show'])->name('setup');
    Route::post('/setup', [SetupController::class, 'store'])->name('setup.store')->middleware('throttle:5,1');

    Route::get('/login', [LoginController::class, 'show'])->name('login')->middleware('setup.redirect');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1');

    // Đăng nhập Google / Facebook (chỉ tài khoản đã được cấp)
    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->name('social.redirect')
        ->whereIn('provider', ['google', 'facebook'])->middleware('throttle:20,1');
    Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])->name('social.callback')
        ->whereIn('provider', ['google', 'facebook'])->middleware('throttle:20,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update')->middleware('throttle:5,1');
});

/*
|--------------------------------------------------------------------------
| Người dùng đã đăng nhập
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Chuyển tổ chức (không gian làm việc)
    Route::post('/workspace/switch', [\App\Http\Controllers\WorkspaceController::class, 'switch'])->name('workspace.switch')->middleware('throttle:30,1');

    // Giao diện sáng / tối của người dùng
    Route::post('/preferences/theme', [\App\Http\Controllers\ThemeController::class, 'update'])->name('preferences.theme')->middleware('throttle:30,1');

    // Khu vực quản trị nền tảng — chỉ quản trị nền tảng
    Route::middleware('can:platform')->prefix('platform')->name('platform.')->group(function () {
        Route::resource('organizations', \App\Http\Controllers\Platform\OrganizationController::class)->except(['show', 'destroy']);
    });

    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/dashboard/widget/{widget}', [DashboardController::class, 'widget'])->whereIn('widget', ['revenue', 'schedule'])->name('dashboard.widget');
    Route::get('/search', SearchController::class)->name('search');

    // Hồ sơ
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password')->middleware('throttle:10,1');
    Route::get('/profile/sessions', [ProfileController::class, 'sessions'])->name('profile.sessions');
    Route::delete('/profile/sessions', [ProfileController::class, 'destroyOtherSessions'])->name('profile.sessions.destroy');

    // Thông báo
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Lịch sử tương tác / ghi chú
    Route::post('/interactions/{type}/{id}', [InteractionController::class, 'store'])->name('interactions.store')
        ->whereIn('type', ['customer', 'lead', 'contact', 'opportunity'])->whereNumber('id');
    Route::delete('/interactions/{interaction}', [InteractionController::class, 'destroy'])->name('interactions.destroy');

    // Khách hàng
    Route::get('/customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::get('/customers/import', [CustomerController::class, 'importForm'])->name('customers.import');
    Route::post('/customers/import', [CustomerController::class, 'import'])->name('customers.import.store');
    Route::get('/customers/import/template', [CustomerController::class, 'importTemplate'])->name('customers.import.template');
    Route::get('/customers/check-duplicate', [CustomerController::class, 'checkDuplicate'])->name('customers.check-duplicate');
    Route::post('/customers/bulk-assign', [CustomerController::class, 'bulkAssign'])->name('customers.bulk-assign');
    Route::resource('customers', CustomerController::class);

    Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
    Route::resource('contacts', ContactController::class);

    // Leads
    Route::get('/leads/export', [LeadController::class, 'export'])->name('leads.export');
    Route::get('/leads/{lead}/convert', [LeadController::class, 'convertForm'])->name('leads.convert');
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert.store');
    Route::resource('leads', LeadController::class);

    // Pipeline
    Route::get('/opportunities/list', [OpportunityController::class, 'list'])->name('opportunities.list');
    Route::get('/opportunities/export', [OpportunityController::class, 'export'])->name('opportunities.export');
    Route::patch('/opportunities/{opportunity}/move', [OpportunityController::class, 'move'])->name('opportunities.move');
    Route::resource('opportunities', OpportunityController::class);

    // Công việc
    Route::get('/tasks/calendar', [TaskController::class, 'calendar'])->name('tasks.calendar');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::post('/tasks/{task}/checklist', [TaskController::class, 'addChecklist'])->name('tasks.checklist.store');
    Route::patch('/tasks/{task}/checklist/{item}', [TaskController::class, 'toggleChecklist'])->name('tasks.checklist.toggle');
    Route::delete('/tasks/{task}/checklist/{item}', [TaskController::class, 'deleteChecklist'])->name('tasks.checklist.destroy');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'addComment'])->name('tasks.comments.store');
    Route::post('/tasks/{task}/attachments', [TaskController::class, 'addAttachment'])->name('tasks.attachments.store');
    Route::get('/tasks/{task}/attachments/{attachment}', [TaskController::class, 'downloadAttachment'])->name('tasks.attachments.download');
    Route::delete('/tasks/{task}/attachments/{attachment}', [TaskController::class, 'deleteAttachment'])->name('tasks.attachments.destroy');
    Route::resource('tasks', TaskController::class);

    // Sản phẩm
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::resource('products', ProductController::class);

    // Bán hàng
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');
    Route::get('/quotations/export', [QuotationController::class, 'export'])->name('quotations.export');
    Route::resource('quotations', QuotationController::class);

    Route::get('/orders/export', [SalesOrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order}/pdf', [SalesOrderController::class, 'pdf'])->name('orders.pdf');
    Route::post('/orders/{order}/approve', [SalesOrderController::class, 'approve'])->name('orders.approve');
    Route::post('/orders/{order}/invoice', [SalesOrderController::class, 'createInvoice'])->name('orders.invoice');
    Route::resource('orders', SalesOrderController::class)->parameters(['orders' => 'order']);

    Route::get('/invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
    Route::resource('invoices', InvoiceController::class);

    Route::get('/targets', [SalesTargetController::class, 'index'])->name('targets.index');
    Route::post('/targets', [SalesTargetController::class, 'store'])->name('targets.store');
    Route::delete('/targets/{target}', [SalesTargetController::class, 'destroy'])->name('targets.destroy');

    // Marketing
    Route::get('/campaigns/export', [CampaignController::class, 'export'])->name('campaigns.export');
    Route::post('/campaigns/{campaign}/leads', [CampaignController::class, 'attachLeads'])->name('campaigns.leads.attach');
    Route::delete('/campaigns/{campaign}/leads/{lead}', [CampaignController::class, 'detachLead'])->name('campaigns.leads.detach');
    Route::resource('campaigns', CampaignController::class);

    // Hỗ trợ
    Route::get('/tickets/export', [SupportTicketController::class, 'export'])->name('tickets.export');
    Route::post('/tickets/{ticket}/replies', [SupportTicketController::class, 'reply'])->name('tickets.reply');
    Route::resource('tickets', SupportTicketController::class);

    // Báo cáo
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{type}/export/{format}', [ReportController::class, 'export'])->name('reports.export')
        ->whereIn('format', ['csv', 'xlsx', 'pdf']);

    // Quản trị
    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('/users/{user}/send-reset', [UserController::class, 'sendReset'])->name('users.send-reset');
    Route::put('/users/{user}/password', [UserController::class, 'setPassword'])->name('users.password');
    Route::resource('users', UserController::class);

    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/{log}', [ActivityLogController::class, 'show'])->name('activity-logs.show');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/settings/branding', [SettingController::class, 'branding'])->name('settings.branding');
    Route::get('/design-system', function () {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        return view('design-system');
    })->name('design-system');
    Route::put('/settings/branding', [SettingController::class, 'updateBranding'])->name('settings.branding.update');
});
