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
    }

    public function boot(): void
    {
        // {user} trên route chỉ tìm trong thành viên của tổ chức đang làm việc (ngoài tổ chức → 404)
        \Illuminate\Support\Facades\Route::bind('user', fn ($value) => \App\Models\User::inOrganization()->findOrFail($value));

        // Tên ngắn cho quan hệ đa hình (lưu trong DB thay vì tên class đầy đủ)
        Relation::morphMap([
            'user' => \App\Models\User::class,
            'customer' => \App\Models\Customer::class,
            'contact' => \App\Models\Contact::class,
            'lead' => \App\Models\Lead::class,
            'opportunity' => \App\Models\Opportunity::class,
            'task' => \App\Models\Task::class,
            'product' => \App\Models\Product::class,
            'quotation' => \App\Models\Quotation::class,
            'sales_order' => \App\Models\SalesOrder::class,
            'invoice' => \App\Models\Invoice::class,
            'payment' => \App\Models\Payment::class,
            'campaign' => \App\Models\Campaign::class,
            'ticket' => \App\Models\SupportTicket::class,
            'department' => \App\Models\Department::class,
        ]);

        // Super Admin có toàn quyền
        Gate::before(fn ($user) => $user->hasRole('Super Admin') ? true : null);

        Paginator::useBootstrapFive();
        Carbon::setLocale('vi');

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip());
        });
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('crmSettings', Setting::allCached());
            $view->with('unreadNotifications', $user ? $user->unreadNotifications()->count() : 0);
            $view->with('hasDemoData', \Illuminate\Support\Facades\Cache::remember('crm.has_demo', 600,
                fn () => \App\Models\Customer::withTrashed()->where('is_demo', true)->exists()));
        });
        View::composer(['layouts.auth', 'auth.*', 'pages.*', 'dashboard'], fn ($view) => $view->with('crmSettings', Setting::allCached()));
    }
}
