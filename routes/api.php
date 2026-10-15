<?php

use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\LeadApiController;
use App\Http\Controllers\Api\OpportunityApiController;
use App\Http\Controllers\Api\StatsApiController;
use Illuminate\Support\Facades\Route;

/*
| REST API nội bộ (JSON) — xác thực bằng phiên đăng nhập web + CSRF, giới hạn 120 yêu cầu/phút.
| Tiền tố: /api/v1
*/
Route::prefix('v1')->middleware(['web', 'auth', 'throttle:api'])->name('api.')->group(function () {
    Route::get('/stats', StatsApiController::class)->name('stats');
    Route::apiResource('customers', CustomerApiController::class);
    Route::apiResource('leads', LeadApiController::class)->only(['index', 'show', 'store', 'update']);
    Route::apiResource('opportunities', OpportunityApiController::class)->only(['index', 'show']);
});
