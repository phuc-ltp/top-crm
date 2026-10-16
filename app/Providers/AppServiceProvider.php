<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tổ chức đang làm việc — một thể hiện cho mỗi yêu cầu / mỗi job
        $this->app->scoped(\App\Support\Tenancy\CurrentOrganization::class);
        $this->app->scoped(\App\Support\LeadStages::class);
    }

    public function boot(): void
    {
        // Quy định mật khẩu chung cho mọi nơi đặt mật khẩu (cài đặt, đổi, đặt lại, tạo tài khoản)
        \Illuminate\Validation\Rules\Password::defaults(fn () => \App\Support\Security\PasswordPolicy::rule());

        // {user} trên route chỉ tìm trong thành viên của tổ chức đang làm việc (ngoài tổ chức → 404)
        \Illuminate\Support\Facades\Route::bind('user', fn ($value) => \App\Models\User::inOrganization(null, false)->findOrFail($value));
        // {field} = trường tùy biến của tổ chức đang làm việc (global scope tổ chức → khác tổ chức = 404)
        \Illuminate\Support\Facades\Route::model('field', \App\Models\CustomFieldDefinition::class);
        \Illuminate\Support\Facades\Route::model('stage', \App\Models\LeadStage::class);
        \Illuminate\Support\Facades\Route::model('pipeline', \App\Models\Pipeline::class);
        \Illuminate\Support\Facades\Route::model('pipelineStage', \App\Models\PipelineStage::class);
        \Illuminate\Support\Facades\Route::model('reason', \App\Models\LostReason::class);

        // Tên ngắn cho quan hệ đa hình (lưu trong DB thay vì tên class đầy đủ)
        Relation::morphMap([
            'user' => \App\Models\User::class,
            'customer' => \App\Models\Customer::class,
            'contact' => \App\Models\Contact::class,
            'lead' => \App\Models\Lead::class,
            'opportunity' => \App\Models\Opportunity::class,
            'task' => \App\Models\Task::class,
            'product' => \App\Models\Product::class,
            'supplier' => \App\Models\Supplier::class,
            'quotation' => \App\Models\Quotation::class,
            'sales_order' => \App\Models\SalesOrder::class,
            'invoice' => \App\Models\Invoice::class,
            'payment' => \App\Models\Payment::class,
            'campaign' => \App\Models\Campaign::class,
            'ticket' => \App\Models\SupportTicket::class,
            'department' => \App\Models\Department::class,
            'custom_field' => \App\Models\CustomFieldDefinition::class,
            'lead_stage' => \App\Models\LeadStage::class,
            'pipeline' => \App\Models\Pipeline::class,
            'pipeline_stage' => \App\Models\PipelineStage::class,
            'lost_reason' => \App\Models\LostReason::class,
        ]);

        // Khu vực quản trị nền tảng (danh sách tổ chức...) — chỉ quản trị nền tảng
        Gate::define('platform', fn ($user) => $user->isPlatformAdmin());
        // Thùng rác: có quyền xóa ít nhất một module (chỉ thấy loại mình được xóa)
        Gate::define('trash', fn ($user) => \App\Http\Controllers\TrashController::allowedTypes($user) !== []);

        // Quản trị nền tảng có toàn quyền trên mọi tổ chức
        Gate::before(fn ($user) => $user->isPlatformAdmin() ? true : null);

        // {role} trên route chỉ tìm trong vai trò của tổ chức đang làm việc
        \Illuminate\Support\Facades\Route::bind('role', fn ($value) => \Spatie\Permission\Models\Role::where('organization_id', app(\App\Support\Tenancy\CurrentOrganization::class)->id())->findOrFail($value));

        Paginator::useBootstrapFive();
        Carbon::setLocale('vi');

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip());
        });
        // Giới hạn theo khóa API (mỗi khóa một hạn mức riêng), phiên web theo người dùng, khách theo IP
        RateLimiter::for('api', function (Request $request) {
            $token = $request->user()?->currentAccessToken();
            $key = $token instanceof \App\Models\ApiToken ? 'token:'.$token->getKey() : ($request->user() ? 'user:'.$request->user()->id : 'ip:'.$request->ip());

            return Limit::perMinute(120)->by($key);
        });
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\ApiToken::class);

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('crmSettings', Setting::allCached());
            $view->with('unreadNotifications', $user ? $user->unreadNotifications()->count() : 0);
            $orgId = app(\App\Support\Tenancy\CurrentOrganization::class)->id();
            // Cache theo từng tổ chức (cờ dữ liệu demo của tổ chức này không lộ sang tổ chức khác)
            $view->with('hasDemoData', \Illuminate\Support\Facades\Cache::remember('crm.has_demo.'.$orgId, 600,
                fn () => \App\Models\Customer::withTrashed()->where('is_demo', true)->exists()));
            $view->with('currentOrganization', $orgId ? \App\Models\Organization::find($orgId) : null);
            $view->with('workspaces', $user ? \App\Http\Controllers\WorkspaceController::availableFor($user) : collect());
        });
        View::composer(['layouts.auth', 'auth.*', 'pages.*', 'dashboard'], fn ($view) => $view->with('crmSettings', Setting::allCached()));
    }
}
