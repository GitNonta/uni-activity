<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuditLogController;

Route::middleware(['auth:sanctum', 'role:admin|super-admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'filter'])
            ->name('api.audit-logs.index');
        Route::get('audit-logs/{id}', [AuditLogController::class, 'show'])
            ->name('api.audit-logs.show');
    });
