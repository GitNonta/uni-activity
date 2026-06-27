<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Test error pages routes (remove in production)
            if (config('app.env') === 'local') {
                Route::middleware('web')
                    ->group(base_path('routes/test-errors.php'));
            }
        },
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            \App\Http\Middleware\AuditLogMiddleware::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\UpdateLastSeen::class);
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle database connection errors gracefully
        $exceptions->renderable(function (\PDOException $e, $request) {
            if (str_contains($e->getMessage(), 'Access denied') || 
                str_contains($e->getMessage(), 'Connection refused')) {
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Database connection error',
                        'message' => 'Unable to connect to the database.',
                    ], 500);
                }

                return response()->view('errors.database', [
                    'message' => 'Database Connection Error',
                    'details' => config('app.debug') ? $e->getMessage() : 'Unable to connect to the database.',
                ], 500);
            }
        });

        $exceptions->renderable(function (\Illuminate\Database\QueryException $e, $request) {
            if (str_contains($e->getMessage(), 'Access denied') || 
                str_contains($e->getMessage(), 'Connection refused')) {
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Database connection error',
                        'message' => 'Unable to connect to the database.',
                    ], 500);
                }

                return response()->view('errors.database', [
                    'message' => 'Database Connection Error',
                    'details' => config('app.debug') ? $e->getMessage() : 'Unable to connect to the database.',
                ], 500);
            }
        });

        // Log 403 Authorization exceptions
        $exceptions->reportable(function (\Illuminate\Auth\Access\AuthorizationException $e) {
            \Illuminate\Support\Facades\Log::error('AuthorizationException: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        });

        // Handle CSRF Token Mismatch (419 Page Expired) gracefully

        $exceptions->renderable(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->is('logout') || $request->is('admin/logout')) {
                return redirect('/');
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'CSRF token mismatch.'], 419);
            }

            return redirect()->back()->withInput($request->except('_token'))->with('error', 'เซสชันของคุณหมดอายุแล้ว โปรดโหลดหน้าเว็บใหม่และลองอีกครั้ง');
        });
    })->create();
