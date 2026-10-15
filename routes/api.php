<?php

use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\LeadApiController;
use App\Http\Controllers\Api\OpportunityApiController;
use App\Http\Controllers\Api\StatsApiController;
use App\Http\Middleware\ApiContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

/*
| REST API (JSON), tiền tố /api/v1. Hai cách xác thực:
|  - Khóa API: header "Authorization: Bearer topcrm_..." (tạo tại Hồ sơ → Khóa API).
|  - Phiên đăng nhập web cùng tên miền (kèm CSRF) cho giao diện của chính hệ thống.
| Quyền thực tế = quyền của người dùng trong tổ chức ∩ phạm vi của khóa (ability:...).
| Giới hạn 120 yêu cầu/phút cho mỗi khóa.
*/
Route::prefix('v1')->middleware(['auth:sanctum', ApiContext::class, 'throttle:api', SecurityHeaders::class])->name('api.')->group(function () {
    Route::get('/stats', StatsApiController::class)->name('stats')->middleware('ability:stats:read');

    Route::middleware('ability:customers:read')->group(function () {
        Route::get('/customers', [CustomerApiController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerApiController::class, 'show'])->name('customers.show');
    });
    Route::middleware('ability:customers:write')->group(function () {
        Route::post('/customers', [CustomerApiController::class, 'store'])->name('customers.store');
        Route::match(['put', 'patch'], '/customers/{customer}', [CustomerApiController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerApiController::class, 'destroy'])->name('customers.destroy');
    });

    Route::middleware('ability:leads:read')->group(function () {
        Route::get('/leads', [LeadApiController::class, 'index'])->name('leads.index');
        Route::get('/leads/{lead}', [LeadApiController::class, 'show'])->name('leads.show');
    });
    Route::middleware('ability:leads:write')->group(function () {
        Route::post('/leads', [LeadApiController::class, 'store'])->name('leads.store');
        Route::match(['put', 'patch'], '/leads/{lead}', [LeadApiController::class, 'update'])->name('leads.update');
    });

    Route::middleware('ability:opportunities:read')->group(function () {
        Route::get('/opportunities', [OpportunityApiController::class, 'index'])->name('opportunities.index');
        Route::get('/opportunities/{opportunity}', [OpportunityApiController::class, 'show'])->name('opportunities.show');
    });
});
