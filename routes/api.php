<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ActivityController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API endpoints ที่เปิดให้ภายนอกดึงข้อมูลได้ (ต้องใช้ API Key)
Route::middleware(['auth:sanctum', 'throttle:api-general'])->group(function () {
    
    // ทดสอบดึงข้อมูล User ของ Token นั้น
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // API สำหรับกิจกรรม (ตัวอย่าง)
    Route::get('/v1/activities', [ActivityController::class, 'index']);

});

// Admin Dashboard & Monitoring (ต้องใช้ API Token)
Route::middleware(['auth:sanctum', 'throttle:api-general'])->group(function () {
    // Cluster Telemetry Metrics for Monitor UI & Dashboard
    Route::get('/cluster/metrics', [\App\Http\Controllers\Admin\ClusterMonitoringController::class, 'metrics']);

    // Failed Queue Jobs Management for Monitor UI & Dashboard
    Route::get('/failed-jobs', [\App\Http\Controllers\Admin\FailedJobsController::class, 'index']);
    Route::get('/failed-jobs/{uuid}', [\App\Http\Controllers\Admin\FailedJobsController::class, 'show']);
    Route::post('/failed-jobs/retry-all', [\App\Http\Controllers\Admin\FailedJobsController::class, 'retryAll']);
    Route::post('/failed-jobs/{id}/retry', [\App\Http\Controllers\Admin\FailedJobsController::class, 'retry']);
    Route::delete('/failed-jobs/flush', [\App\Http\Controllers\Admin\FailedJobsController::class, 'flush']);
    Route::delete('/failed-jobs/{id}', [\App\Http\Controllers\Admin\FailedJobsController::class, 'destroy']);
});
