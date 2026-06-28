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
Route::middleware('auth:sanctum')->group(function () {
    
    // ทดสอบดึงข้อมูล User ของ Token นั้น
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // API สำหรับกิจกรรม (ตัวอย่าง)
    Route::get('/v1/activities', [ActivityController::class, 'index']);

});
