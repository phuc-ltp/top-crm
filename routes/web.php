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

// Form thu lead công khai của từng tổ chức (không cần đăng nhập; chống spam: token, honeypot, thời gian điền, giới hạn tần suất)
Route::get('/f/{token}', [\App\Http\Controllers\WebLeadFormController::class, 'show'])->name('lead-form.show')->middleware('throttle:60,1');
Route::post('/f/{token}', [\App\Http\Controllers\WebLeadFormController::class, 'submit'])->name('lead-form.submit')->middleware('throttle:5,1');

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

    // Bước 2 đăng nhập: mã xác thực 2 lớp (người dùng CHƯA đăng nhập cho tới khi nhập đúng)
    Route::get('/two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'store'])->name('two-factor.verify')->middleware('throttle:10,1');
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

    // Khóa API cá nhân (theo tổ chức đang làm việc)
    Route::get('/profile/api-tokens', [\App\Http\Controllers\ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/profile/api-tokens', [\App\Http\Controllers\ApiTokenController::class, 'store'])->name('api-tokens.store')->middleware('throttle:10,1');
    Route::delete('/profile/api-tokens/{token}', [\App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy')->whereNumber('token');

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
    Route::get('/search', SearchController::class)->name('search')->middleware('throttle:120,1');
    Route::get('/search/results', [SearchController::class, 'results'])->name('search.results')->middleware('throttle:60,1');
    Route::delete('/search/recent', [SearchController::class, 'clearRecent'])->name('search.recent.clear');
    Route::get('/trash', [\App\Http\Controllers\TrashController::class, 'index'])->name('trash.index');
    Route::post('/trash/{type}/{id}/restore', [\App\Http\Controllers\TrashController::class, 'restore'])
        ->whereIn('type', array_keys(\App\Http\Controllers\TrashController::types()))->whereNumber('id')->name('trash.restore');

    // Hồ sơ
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password')->middleware('throttle:10,1');
    Route::get('/profile/sessions', [ProfileController::class, 'sessions'])->name('profile.sessions');
    Route::delete('/profile/sessions', [ProfileController::class, 'destroyOtherSessions'])->name('profile.sessions.destroy');

    // Bảo mật tài khoản: xác thực 2 lớp, lịch sử đăng nhập, xác minh email
    Route::get('/profile/security', [\App\Http\Controllers\SecurityController::class, 'show'])->name('profile.security');
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/profile/two-factor', [\App\Http\Controllers\SecurityController::class, 'enable'])->name('two-factor.enable');
        Route::post('/profile/two-factor/confirm', [\App\Http\Controllers\SecurityController::class, 'confirm'])->name('two-factor.confirm');
        Route::delete('/profile/two-factor', [\App\Http\Controllers\SecurityController::class, 'disable'])->name('two-factor.disable');
        Route::post('/profile/two-factor/recovery-codes', [\App\Http\Controllers\SecurityController::class, 'regenerateCodes'])->name('two-factor.recovery-codes');
    });
    Route::post('/email/verification-notification', [\App\Http\Controllers\SecurityController::class, 'sendVerification'])->name('verification.send')->middleware('throttle:3,10');
    Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\SecurityController::class, 'verify'])->name('verification.verify')->middleware(['signed', 'throttle:6,1']);

    // Thông báo
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Lịch sử tương tác / ghi chú
    Route::post('/interactions/{type}/{id}', [InteractionController::class, 'store'])->name('interactions.store')
        ->whereIn('type', ['customer', 'lead', 'contact', 'opportunity'])->whereNumber('id');
    Route::put('/interactions/{interaction}', [InteractionController::class, 'update'])->name('interactions.update');
    Route::patch('/interactions/{interaction}/complete', [InteractionController::class, 'complete'])->name('interactions.complete');
    Route::delete('/interactions/{interaction}', [InteractionController::class, 'destroy'])->name('interactions.destroy');
    Route::get('/activities', [\App\Http\Controllers\ActivityController::class, 'index'])->name('activities.index');

    // Khách hàng
    Route::get('/customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::get('/customers/import', [CustomerController::class, 'importForm'])->name('customers.import');
    Route::post('/customers/import', [CustomerController::class, 'import'])->name('customers.import.store');
    Route::get('/customers/import/template', [CustomerController::class, 'importTemplate'])->name('customers.import.template');
    Route::get('/customers/check-duplicate', [CustomerController::class, 'checkDuplicate'])->name('customers.check-duplicate');
    Route::post('/customers/bulk-assign', [CustomerController::class, 'bulkAssign'])->name('customers.bulk-assign');
    Route::post('/customers/{customer}/attachments', [CustomerController::class, 'addAttachment'])->name('customers.attachments.store')->middleware('throttle:30,1');
    Route::get('/customers/{customer}/attachments/{attachment}', [CustomerController::class, 'downloadAttachment'])->name('customers.attachments.download');
    Route::delete('/customers/{customer}/attachments/{attachment}', [CustomerController::class, 'deleteAttachment'])->name('customers.attachments.destroy');
    Route::get('/customers/{customer}/merge', [CustomerController::class, 'mergeForm'])->name('customers.merge');
    Route::post('/customers/{customer}/merge', [CustomerController::class, 'merge'])->name('customers.merge.store')->middleware('throttle:20,1');
    Route::resource('customers', CustomerController::class);

    Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
    Route::get('/contacts/import', [\App\Http\Controllers\ImportController::class, 'form'])->defaults('module', 'contacts')->name('contacts.import');
    Route::post('/contacts/import', [\App\Http\Controllers\ImportController::class, 'store'])->defaults('module', 'contacts')->name('contacts.import.store');
    Route::get('/contacts/import/template', [\App\Http\Controllers\ImportController::class, 'template'])->defaults('module', 'contacts')->name('contacts.import.template');
    Route::resource('contacts', ContactController::class);

    // Leads
    Route::get('/leads/export', [LeadController::class, 'export'])->name('leads.export');
    Route::get('/leads/import', [\App\Http\Controllers\ImportController::class, 'form'])->defaults('module', 'leads')->name('leads.import');
    Route::post('/leads/import', [\App\Http\Controllers\ImportController::class, 'store'])->defaults('module', 'leads')->name('leads.import.store');
    Route::get('/leads/import/template', [\App\Http\Controllers\ImportController::class, 'template'])->defaults('module', 'leads')->name('leads.import.template');
    Route::get('/leads/{lead}/convert', [LeadController::class, 'convertForm'])->name('leads.convert');
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert.store');
    Route::get('/leads/analytics', [\App\Http\Controllers\LeadAnalyticsController::class, 'index'])->name('leads.analytics');
    Route::resource('leads', LeadController::class);

    // Pipeline
    Route::get('/opportunities/list', [OpportunityController::class, 'list'])->name('opportunities.list');
    Route::get('/opportunities/export', [OpportunityController::class, 'export'])->name('opportunities.export');
    Route::patch('/opportunities/{opportunity}/move', [OpportunityController::class, 'move'])->name('opportunities.move');
    Route::get('/opportunities/forecast', [\App\Http\Controllers\ForecastController::class, 'index'])->name('opportunities.forecast');
    Route::post('/opportunities/{opportunity}/attachments', [OpportunityController::class, 'addAttachment'])->name('opportunities.attachments.store')->middleware('throttle:30,1');
    Route::get('/opportunities/{opportunity}/attachments/{attachment}', [OpportunityController::class, 'downloadAttachment'])->name('opportunities.attachments.download');
    Route::delete('/opportunities/{opportunity}/attachments/{attachment}', [OpportunityController::class, 'deleteAttachment'])->name('opportunities.attachments.destroy');
    Route::resource('opportunities', OpportunityController::class);

    // Công việc
    Route::get('/tasks/calendar', [TaskController::class, 'calendar'])->name('tasks.calendar');
    Route::get('/tasks/export', [TaskController::class, 'export'])->name('tasks.export');
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
    Route::get('/products/import', [\App\Http\Controllers\ImportController::class, 'form'])->defaults('module', 'products')->name('products.import');
    Route::post('/products/import', [\App\Http\Controllers\ImportController::class, 'store'])->defaults('module', 'products')->name('products.import.store');
    Route::get('/products/import/template', [\App\Http\Controllers\ImportController::class, 'template'])->defaults('module', 'products')->name('products.import.template');
    Route::resource('products', ProductController::class);
    Route::get('/price-lists/for-customer', [\App\Http\Controllers\PriceListController::class, 'forCustomer'])->name('price-lists.for-customer');
    Route::resource('price-lists', \App\Http\Controllers\PriceListController::class)->except(['show']);
    Route::get('/suppliers/export', [\App\Http\Controllers\SupplierController::class, 'export'])->name('suppliers.export');

    // Kho hàng (bước 3.2)
    Route::get('/inventory', [\App\Http\Controllers\InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/movements', [\App\Http\Controllers\InventoryController::class, 'movements'])->name('inventory.movements');
    Route::resource('stock-documents', \App\Http\Controllers\StockDocumentController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('warehouses', \App\Http\Controllers\WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('suppliers', \App\Http\Controllers\SupplierController::class);

    // Bán hàng
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');
    Route::post('/quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
    Route::post('/quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
    Route::resource('quotation-templates', \App\Http\Controllers\QuotationTemplateController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/quotations/export', [QuotationController::class, 'export'])->name('quotations.export');
    Route::resource('quotations', QuotationController::class);

    Route::get('/orders/export', [SalesOrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order}/pdf', [SalesOrderController::class, 'pdf'])->name('orders.pdf');
    Route::post('/orders/{order}/approve', [SalesOrderController::class, 'approve'])->name('orders.approve');
    Route::post('/orders/{order}/invoice', [SalesOrderController::class, 'createInvoice'])->name('orders.invoice');
    Route::post('/orders/{order}/return', [SalesOrderController::class, 'returnGoods'])->name('orders.return');
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
    Route::delete('/users/{user}/two-factor', [UserController::class, 'resetTwoFactor'])->name('users.two-factor.reset')->middleware('throttle:10,1');
    Route::resource('users', UserController::class);

    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/{log}', [ActivityLogController::class, 'show'])->name('activity-logs.show');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/settings/branding', [SettingController::class, 'branding'])->name('settings.branding');
    Route::get('/settings/custom-fields', [\App\Http\Controllers\CustomFieldController::class, 'index'])->name('settings.custom-fields');
    // Cấu hình pipeline & giai đoạn cơ hội
    Route::get('/settings/pipelines', [\App\Http\Controllers\PipelineSettingsController::class, 'index'])->name('settings.pipelines');
    Route::post('/settings/pipelines', [\App\Http\Controllers\PipelineSettingsController::class, 'storePipeline'])->name('settings.pipelines.store');
    Route::put('/settings/pipelines/{pipeline}', [\App\Http\Controllers\PipelineSettingsController::class, 'updatePipeline'])->name('settings.pipelines.update');
    Route::delete('/settings/pipelines/{pipeline}', [\App\Http\Controllers\PipelineSettingsController::class, 'destroyPipeline'])->name('settings.pipelines.destroy');
    Route::post('/settings/pipelines/{pipeline}/stages', [\App\Http\Controllers\PipelineSettingsController::class, 'storeStage'])->name('settings.pipelines.stages.store');
    Route::put('/settings/pipeline-stages/{pipelineStage}', [\App\Http\Controllers\PipelineSettingsController::class, 'updateStage'])->name('settings.pipelines.stages.update');
    Route::delete('/settings/pipeline-stages/{pipelineStage}', [\App\Http\Controllers\PipelineSettingsController::class, 'destroyStage'])->name('settings.pipelines.stages.destroy');
    // Cấu hình lead: giai đoạn, lý do thất bại, form website
    Route::get('/settings/leads', [\App\Http\Controllers\LeadSettingsController::class, 'index'])->name('settings.leads');
    Route::post('/settings/leads/stages', [\App\Http\Controllers\LeadSettingsController::class, 'storeStage'])->name('settings.leads.stages.store');
    Route::put('/settings/leads/stages/{stage}', [\App\Http\Controllers\LeadSettingsController::class, 'updateStage'])->name('settings.leads.stages.update');
    Route::delete('/settings/leads/stages/{stage}', [\App\Http\Controllers\LeadSettingsController::class, 'destroyStage'])->name('settings.leads.stages.destroy');
    Route::post('/settings/leads/reasons', [\App\Http\Controllers\LeadSettingsController::class, 'storeReason'])->name('settings.leads.reasons.store');
    Route::put('/settings/leads/reasons/{reason}', [\App\Http\Controllers\LeadSettingsController::class, 'updateReason'])->name('settings.leads.reasons.update');
    Route::delete('/settings/leads/reasons/{reason}', [\App\Http\Controllers\LeadSettingsController::class, 'destroyReason'])->name('settings.leads.reasons.destroy');
    Route::put('/settings/leads/form', [\App\Http\Controllers\LeadSettingsController::class, 'updateForm'])->name('settings.leads.form');
    Route::post('/settings/leads/form/token', [\App\Http\Controllers\LeadSettingsController::class, 'regenerateToken'])->name('settings.leads.form.token');
    Route::post('/settings/custom-fields', [\App\Http\Controllers\CustomFieldController::class, 'store'])->name('settings.custom-fields.store');
    Route::put('/settings/custom-fields/{field}', [\App\Http\Controllers\CustomFieldController::class, 'update'])->name('settings.custom-fields.update');
    Route::delete('/settings/custom-fields/{field}', [\App\Http\Controllers\CustomFieldController::class, 'destroy'])->name('settings.custom-fields.destroy');
    Route::get('/design-system', function () {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        return view('design-system');
    })->name('design-system');
    Route::put('/settings/branding', [SettingController::class, 'updateBranding'])->name('settings.branding.update');
});
