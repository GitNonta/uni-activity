<?php

use Illuminate\Support\Facades\Route;

/**
 * Test Error Pages Routes
 * 
 * These routes are for testing error pages during development.
 * Remove or comment out in production!
 */

Route::prefix('test-errors')->group(function () {
    
    // Test 404 - Page Not Found
    Route::get('/404', function () {
        abort(404);
    })->name('test.404');

    // Test 403 - Forbidden
    Route::get('/403', function () {
        abort(403, 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    })->name('test.403');

    // Test 419 - Page Expired
    Route::get('/419', function () {
        abort(419);
    })->name('test.419');

    // Test 500 - Internal Server Error
    Route::get('/500', function () {
        abort(500);
    })->name('test.500');

    // Test 503 - Service Unavailable
    Route::get('/503', function () {
        abort(503);
    })->name('test.503');

    // Test Database Error
    Route::get('/database', function () {
        // Simulate database error
        throw new \PDOException('SQLSTATE[HY000] [1698] Access denied for user \'root\'@\'localhost\'', 1698);
    })->name('test.database');

    // Error Pages Index
    Route::get('/', function () {
        return view('test-errors-index');
    })->name('test.errors.index');
});
