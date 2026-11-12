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
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesTargetController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Trang công khai (ai cũng xem được)
Route::get('/dieu-khoan-su-dung', [PageController::class, 'show'])->defaults('slug', 'terms')->name('pages.terms');
Route::get('/chinh-sach-bao-mat', [PageController::class, 'show'])->defaults('slug', 'privacy')->name('pages.privacy');
Route::get('/ho-tro', [PageController::class, 'show'])->defaults('slug', 'support')->name('pages.support');

// Form thu lead công khai của từng tổ chức (không cần đăng nhập; chống spam: token, honeypot, thời gian điền, giới hạn tần suất)
Route::get('/manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
// Cron qua web (hosting không có cron): dịch vụ bên ngoài gọi mỗi phút; token bí mật 48 ký tự
Route::get('/cron/{token}', \App\Http\Controllers\WebCronController::class)->name('cron.run')
    ->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:10,1,web-cron');

// Webhook SePay (máy chủ SePay gọi, không có phiên / CSRF): xác thực bằng token URL + header Authorization: Apikey
Route::post('/webhooks/sepay/{token}', \App\Http\Controllers\SepayWebhookController::class)->name('webhooks.sepay')
    ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:120,1,sepay')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
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
    Route::post('/two-factor-challenge/resend', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'resend'])->name('two-factor.resend')->middleware('throttle:5,1');
});

// Tạo tài khoản (xin tham gia công ty, chờ duyệt) — chỉ khách chưa đăng nhập
Route::middleware('guest')->prefix('register')->group(function () {
    Route::get('/', [\App\Http\Controllers\Auth\RegisterController::class, 'show'])->name('register')->middleware('throttle:30,1,register-view');
    Route::post('/', [\App\Http\Controllers\Auth\RegisterController::class, 'submit'])->name('register.submit')->middleware('throttle:6,1,register-submit');
    Route::get('/verify', [\App\Http\Controllers\Auth\RegisterController::class, 'verifyForm'])->name('register.verify')->middleware('throttle:30,1,register-view');
    Route::post('/verify', [\App\Http\Controllers\Auth\RegisterController::class, 'verify'])->name('register.verify.store')->middleware('throttle:10,1,register-verify');
    Route::post('/resend', [\App\Http\Controllers\Auth\RegisterController::class, 'resend'])->name('register.resend')->middleware('throttle:5,1,register-resend');
    Route::get('/done', [\App\Http\Controllers\Auth\RegisterController::class, 'done'])->name('register.done');
});

// (prefix throttle riêng: các throttle không prefix dùng chung một bộ đếm theo IP)
// Đăng ký qua link mời (công khai; token 40 ký tự, chỉ lưu bản băm). Trang "show" tự chuyển người đã đăng nhập về Tổng quan.
Route::prefix('join/{token}')->where(['token' => '[A-Za-z0-9]{40}'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Auth\JoinController::class, 'show'])->name('join.show')->middleware('throttle:30,1,join-view');
    Route::middleware('guest')->group(function () {
        Route::post('/', [\App\Http\Controllers\Auth\JoinController::class, 'submit'])->name('join.submit')->middleware('throttle:6,1,join-submit');
        Route::get('/verify', [\App\Http\Controllers\Auth\JoinController::class, 'verifyForm'])->name('join.verify')->middleware('throttle:30,1,join-view');
        Route::post('/verify', [\App\Http\Controllers\Auth\JoinController::class, 'verify'])->name('join.verify.store')->middleware('throttle:10,1,join-verify');
        Route::post('/resend', [\App\Http\Controllers\Auth\JoinController::class, 'resend'])->name('join.resend')->middleware('throttle:5,1,join-resend');
    });
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
        Route::post('/profile/email-otp', [\App\Http\Controllers\SecurityController::class, 'emailOtpStart'])->name('email-otp.start')->middleware('throttle:5,1');
        Route::post('/profile/email-otp/confirm', [\App\Http\Controllers\SecurityController::class, 'emailOtpConfirm'])->name('email-otp.confirm')->middleware('throttle:10,1');
        Route::delete('/profile/email-otp', [\App\Http\Controllers\SecurityController::class, 'emailOtpDisable'])->name('email-otp.disable');
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
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::delete('/notifications/read', [NotificationController::class, 'clearRead'])->name('notifications.clear-read');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/{id}/toggle', [NotificationController::class, 'toggle'])->name('notifications.toggle');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Lịch sử tương tác / ghi chú
    Route::post('/interactions/{type}/{id}', [InteractionController::class, 'store'])->name('interactions.store')
        ->whereIn('type', ['customer', 'lead', 'contact', 'opportunity'])->whereNumber('id');
    Route::put('/interactions/{interaction}', [InteractionController::class, 'update'])->name('interactions.update');
    Route::patch('/interactions/{interaction}/complete', [InteractionController::class, 'complete'])->name('interactions.complete');
    Route::delete('/interactions/{interaction}', [InteractionController::class, 'destroy'])->name('interactions.destroy');
    Route::get('/calendar', [\App\Http\Controllers\CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/{interaction}/ics', [\App\Http\Controllers\CalendarController::class, 'ics'])->name('calendar.ics');
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
    Route::get('/tasks/board', [TaskController::class, 'board'])->name('tasks.board');
    Route::get('/tasks/workload', [TaskController::class, 'workload'])->name('tasks.workload');
    Route::post('/tasks/{task}/dependencies', [TaskController::class, 'addDependency'])->name('tasks.dependencies.store');
    Route::delete('/tasks/{task}/dependencies/{dependency}', [TaskController::class, 'removeDependency'])->whereNumber('dependency')->name('tasks.dependencies.destroy');
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
    Route::get('/projects/export', [ProjectController::class, 'export'])->name('projects.export');
    Route::resource('projects', ProjectController::class);
    Route::get('/documents/{document}/download/{version?}', [\App\Http\Controllers\DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview/{version?}', [\App\Http\Controllers\DocumentController::class, 'preview'])->name('documents.preview');
    Route::post('/documents/{document}/versions', [\App\Http\Controllers\DocumentController::class, 'addVersion'])->name('documents.versions.store')->middleware('throttle:30,1');
    Route::post('/documents', [\App\Http\Controllers\DocumentController::class, 'store'])->name('documents.store')->middleware('throttle:30,1');
    Route::resource('documents', \App\Http\Controllers\DocumentController::class)->except(['store']);

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
    Route::post('/invoices/{invoice}/refunds', [PaymentController::class, 'refund'])->name('payments.refund');
    Route::post('/invoices/{invoice}/credit-notes', [PaymentController::class, 'credit'])->name('payments.credit');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/reconcile', [PaymentController::class, 'reconcile'])->name('payments.reconcile');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
    Route::resource('invoices', InvoiceController::class);

    Route::get('/targets', [SalesTargetController::class, 'index'])->name('targets.index');
    Route::post('/targets', [SalesTargetController::class, 'store'])->name('targets.store');
    Route::delete('/targets/{target}', [SalesTargetController::class, 'destroy'])->name('targets.destroy');

    // Marketing
    Route::get('/campaigns/export', [CampaignController::class, 'export'])->name('campaigns.export');
    Route::post('/campaigns/{campaign}/leads', [CampaignController::class, 'attachLeads'])->name('campaigns.leads.attach');
    Route::post('/campaigns/{campaign}/expenses', [CampaignController::class, 'storeExpense'])->name('campaigns.expenses.store');
    Route::delete('/campaigns/{campaign}/expenses/{expense}', [CampaignController::class, 'destroyExpense'])->name('campaigns.expenses.destroy');
    Route::delete('/campaigns/{campaign}/leads/{lead}', [CampaignController::class, 'detachLead'])->name('campaigns.leads.detach');
    Route::resource('campaigns', CampaignController::class);

    // Hỗ trợ
    Route::get('/tickets/export', [SupportTicketController::class, 'export'])->name('tickets.export');
    Route::post('/tickets/{ticket}/replies', [SupportTicketController::class, 'reply'])->name('tickets.reply');
    Route::post('/tickets/{ticket}/satisfaction', [SupportTicketController::class, 'satisfaction'])->name('tickets.satisfaction');
    Route::get('/tickets/analytics', [SupportTicketController::class, 'analytics'])->name('tickets.analytics');
    Route::resource('ticket-categories', \App\Http\Controllers\TicketCategoryController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['ticket-categories' => 'category']);
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
    Route::resource('invitations', \App\Http\Controllers\InvitationController::class)->only(['index', 'store', 'destroy']);
    Route::get('/join-requests', [\App\Http\Controllers\JoinRequestController::class, 'index'])->name('join-requests.index');
    Route::post('/join-requests/{joinRequest}/approve', [\App\Http\Controllers\JoinRequestController::class, 'approve'])->name('join-requests.approve');
    Route::post('/join-requests/{joinRequest}/reject', [\App\Http\Controllers\JoinRequestController::class, 'reject'])->name('join-requests.reject');
    Route::resource('users', UserController::class);

    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees/accounts', [EmployeeController::class, 'storeAccount'])->name('employees.accounts.store')->middleware('throttle:20,1');
    Route::resource('pay-grades', \App\Http\Controllers\PayGradeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/attendance', [\App\Http\Controllers\AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/overtime', [\App\Http\Controllers\OvertimeController::class, 'index'])->name('overtime.index');
    Route::post('/overtime', [\App\Http\Controllers\OvertimeController::class, 'store'])->name('overtime.store')->middleware('throttle:20,1');
    Route::post('/overtime/{overtime}/approve', [\App\Http\Controllers\OvertimeController::class, 'approve'])->name('overtime.approve');
    Route::post('/overtime/{overtime}/reject', [\App\Http\Controllers\OvertimeController::class, 'reject'])->name('overtime.reject');
    Route::post('/overtime/{overtime}/cancel', [\App\Http\Controllers\OvertimeController::class, 'cancel'])->name('overtime.cancel');
    Route::get('/contact-qr/{type}/{id}', [\App\Http\Controllers\ContactQrController::class, 'show'])->name('contact-qr')->whereIn('type', ['lead', 'customer', 'contact'])->whereNumber('id')->middleware('throttle:60,1');
    Route::get('/leave', [\App\Http\Controllers\LeaveController::class, 'index'])->name('leave.index');
    Route::post('/leave', [\App\Http\Controllers\LeaveController::class, 'store'])->name('leave.store')->middleware('throttle:20,1');
    Route::post('/leave/{leave}/approve', [\App\Http\Controllers\LeaveController::class, 'approve'])->name('leave.approve');
    Route::post('/leave/{leave}/reject', [\App\Http\Controllers\LeaveController::class, 'reject'])->name('leave.reject');
    Route::post('/leave/{leave}/cancel', [\App\Http\Controllers\LeaveController::class, 'cancel'])->name('leave.cancel');
    Route::get('/holidays', [\App\Http\Controllers\HolidayController::class, 'index'])->name('holidays.index');
    Route::post('/holidays', [\App\Http\Controllers\HolidayController::class, 'store'])->name('holidays.store');
    Route::post('/holidays/fixed', [\App\Http\Controllers\HolidayController::class, 'addFixed'])->name('holidays.fixed');
    Route::put('/holidays/settings', [\App\Http\Controllers\HolidayController::class, 'settings'])->name('holidays.settings');
    Route::delete('/holidays/{holiday}', [\App\Http\Controllers\HolidayController::class, 'destroy'])->name('holidays.destroy');
    Route::get('/payroll', [\App\Http\Controllers\PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll', [\App\Http\Controllers\PayrollController::class, 'store'])->name('payroll.store');
    Route::put('/payroll/settings', [\App\Http\Controllers\PayrollController::class, 'settings'])->name('payroll.settings');
    Route::get('/payroll/{payroll}', [\App\Http\Controllers\PayrollController::class, 'show'])->name('payroll.show');
    Route::post('/payroll/{payroll}/recalculate', [\App\Http\Controllers\PayrollController::class, 'recalculate'])->name('payroll.recalculate');
    Route::post('/payroll/{payroll}/{action}', [\App\Http\Controllers\PayrollController::class, 'transition'])->name('payroll.transition')->whereIn('action', ['submit', 'reopen', 'approve', 'pay']);
    Route::get('/payroll/{payroll}/export', [\App\Http\Controllers\PayrollController::class, 'export'])->name('payroll.export');
    Route::delete('/payroll/{payroll}', [\App\Http\Controllers\PayrollController::class, 'destroy'])->name('payroll.destroy');
    Route::get('/payslips', [\App\Http\Controllers\PayslipController::class, 'mine'])->name('payslips.mine');
    Route::get('/assets/overview', [\App\Http\Controllers\AssetController::class, 'overview'])->name('assets.overview');
    Route::get('/assets/mine', [\App\Http\Controllers\AssetController::class, 'mine'])->name('assets.mine');
    Route::post('/assets/labels', [\App\Http\Controllers\AssetController::class, 'labels'])->name('assets.labels');
    Route::get('/assets/categories', [\App\Http\Controllers\AssetCategoryController::class, 'index'])->name('assets.categories.index');
    Route::post('/assets/categories', [\App\Http\Controllers\AssetCategoryController::class, 'store'])->name('assets.categories.store');
    Route::put('/assets/categories/{category}', [\App\Http\Controllers\AssetCategoryController::class, 'update'])->name('assets.categories.update');
    Route::delete('/assets/categories/{category}', [\App\Http\Controllers\AssetCategoryController::class, 'destroy'])->name('assets.categories.destroy');
    Route::get('/assets/audits', [\App\Http\Controllers\AssetAuditController::class, 'index'])->name('assets.audits.index');
    Route::post('/assets/audits', [\App\Http\Controllers\AssetAuditController::class, 'store'])->name('assets.audits.store');
    Route::get('/assets/audits/{audit}', [\App\Http\Controllers\AssetAuditController::class, 'show'])->name('assets.audits.show');
    Route::post('/assets/audits/{audit}/check', [\App\Http\Controllers\AssetAuditController::class, 'check'])->name('assets.audits.check');
    Route::post('/assets/audits/{audit}/close', [\App\Http\Controllers\AssetAuditController::class, 'close'])->name('assets.audits.close');
    Route::resource('assets', \App\Http\Controllers\AssetController::class);
    Route::get('/contracts/export', [\App\Http\Controllers\ContractController::class, 'export'])->name('contracts.export');
    Route::resource('contracts', \App\Http\Controllers\ContractController::class);
    Route::post('/contracts/{contract}/terminate', [\App\Http\Controllers\ContractController::class, 'terminate'])->name('contracts.terminate');
    Route::post('/contracts/{contract}/renew', [\App\Http\Controllers\ContractController::class, 'renew'])->name('contracts.renew');
    Route::resource('licenses', \App\Http\Controllers\SoftwareLicenseController::class)->parameters(['licenses' => 'license']);
    Route::post('/licenses/{license}/reveal-key', [\App\Http\Controllers\SoftwareLicenseController::class, 'revealKey'])->name('licenses.reveal')->middleware('throttle:10,1');
    Route::post('/licenses/{license}/assign', [\App\Http\Controllers\SoftwareLicenseController::class, 'assign'])->name('licenses.assign');
    Route::post('/licenses/{license}/assignments/{assignment}/release', [\App\Http\Controllers\SoftwareLicenseController::class, 'release'])->name('licenses.release');
    Route::post('/assets/{asset}/act/{action}', [\App\Http\Controllers\AssetController::class, 'act'])->name('assets.act')->whereIn('action', ['assign', 'recall', 'borrow', 'return', 'maintenance', 'repaired', 'status', 'dispose']);
    Route::get('/assets/{asset}/qr', [\App\Http\Controllers\AssetController::class, 'qr'])->name('assets.qr');
    Route::get('/a/{token}', [\App\Http\Controllers\AssetController::class, 'scan'])->name('assets.scan')->where('token', '[A-Za-z0-9]{32}');
    Route::get('/payslips/{payslip}', [\App\Http\Controllers\PayslipController::class, 'show'])->name('payslips.show');
    Route::get('/payslips/{payslip}/pdf', [\App\Http\Controllers\PayslipController::class, 'pdf'])->name('payslips.pdf');
    Route::put('/payslips/{payslip}', [\App\Http\Controllers\PayslipController::class, 'update'])->name('payslips.update');
    Route::post('/attendance/consent', [\App\Http\Controllers\AttendanceController::class, 'consent'])->name('attendance.consent');
    Route::post('/attendance/punch', [\App\Http\Controllers\AttendanceController::class, 'punch'])->name('attendance.punch')->middleware('throttle:10,1');
    Route::get('/attendance/board', [\App\Http\Controllers\AttendanceController::class, 'board'])->name('attendance.board');
    Route::post('/attendance/records', [\App\Http\Controllers\AttendanceController::class, 'storeManual'])->name('attendance.records.store');
    Route::get('/attendance/records/{record}', [\App\Http\Controllers\AttendanceController::class, 'show'])->name('attendance.records.show');
    Route::put('/attendance/records/{record}', [\App\Http\Controllers\AttendanceController::class, 'adjust'])->name('attendance.records.adjust');
    Route::get('/attendance/records/{record}/selfie/{type}', [\App\Http\Controllers\AttendanceController::class, 'selfie'])->name('attendance.records.selfie');
    Route::get('/attendance/settings', [\App\Http\Controllers\AttendanceSettingsController::class, 'edit'])->name('attendance.settings');
    Route::put('/attendance/settings', [\App\Http\Controllers\AttendanceSettingsController::class, 'update'])->name('attendance.settings.update');
    Route::post('/attendance/locations', [\App\Http\Controllers\AttendanceSettingsController::class, 'storeLocation'])->name('attendance.locations.store');
    Route::put('/attendance/locations/{location}', [\App\Http\Controllers\AttendanceSettingsController::class, 'updateLocation'])->name('attendance.locations.update');
    Route::delete('/attendance/locations/{location}', [\App\Http\Controllers\AttendanceSettingsController::class, 'destroyLocation'])->name('attendance.locations.destroy');
    Route::get('/employees/chart', [EmployeeController::class, 'chart'])->name('employees.chart');
    Route::get('/employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee')->name('employees.show');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->whereNumber('employee')->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->whereNumber('employee')->name('employees.update');
    Route::resource('teams', TeamController::class);
    Route::resource('branches', BranchController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/{log}', [ActivityLogController::class, 'show'])->name('activity-logs.show');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/settings/branding', [SettingController::class, 'branding'])->name('settings.branding');
    Route::get('/settings/maintenance', [\App\Http\Controllers\MaintenanceController::class, 'edit'])->name('settings.maintenance');
    Route::post('/settings/maintenance/backup', [\App\Http\Controllers\MaintenanceController::class, 'backup'])->name('settings.maintenance.backup')->middleware('throttle:3,1');
    Route::get('/settings/maintenance/backups/{file}', [\App\Http\Controllers\MaintenanceController::class, 'download'])->name('settings.maintenance.download')->where('file', '[A-Za-z0-9._-]+\.sql\.gz');
    Route::post('/settings/maintenance/regenerate', [\App\Http\Controllers\MaintenanceController::class, 'regenerate'])->name('settings.maintenance.regenerate');
    Route::get('/settings/sepay',[\App\Http\Controllers\SepaySettingsController::class, 'edit'])->name('settings.sepay');
    Route::put('/settings/sepay', [\App\Http\Controllers\SepaySettingsController::class, 'update'])->name('settings.sepay.update');
    Route::post('/settings/sepay/regenerate', [\App\Http\Controllers\SepaySettingsController::class, 'regenerate'])->name('settings.sepay.regenerate');
    Route::get('/settings/mail', [\App\Http\Controllers\MailSettingsController::class, 'edit'])->name('settings.mail');
    Route::put('/settings/mail', [\App\Http\Controllers\MailSettingsController::class, 'update'])->name('settings.mail.update');
    Route::post('/settings/mail/test', [\App\Http\Controllers\MailSettingsController::class, 'test'])->name('settings.mail.test')->middleware('throttle:5,1');
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
