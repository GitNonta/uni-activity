<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // 1. Database
        try {
            DB::select('SELECT 1');
            $checks['database'] = ['status' => 'ok', 'driver' => config('database.default')];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
            $healthy = false;
        }

        // 2. Cache
        try {
            $key = 'health:check:' . time();
            Cache::put($key, 'ok', 10);
            $val = Cache::get($key);
            Cache::forget($key);
            $checks['cache'] = [
                'status' => $val === 'ok' ? 'ok' : 'error',
                'driver' => config('cache.default'),
            ];
            if ($val !== 'ok') {
                $healthy = false;
            }
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'error', 'message' => $e->getMessage()];
            $healthy = false;
        }

        // 3. Queue
        try {
            $queueDriver = config('queue.default');
            $checks['queue'] = ['status' => 'ok', 'driver' => $queueDriver];
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'error', 'message' => $e->getMessage()];
            $healthy = false;
        }

        // 4. Storage writable
        try {
            $testFile = storage_path('framework/health-check.txt');
            file_put_contents($testFile, (string) time());
            unlink($testFile);
            $checks['storage'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['storage'] = ['status' => 'error', 'message' => $e->getMessage()];
            $healthy = false;
        }

        // 5. PHP version
        $checks['php'] = ['version' => PHP_VERSION];

        // 6. Laravel version
        $checks['laravel'] = ['version' => app()->version()];

        // 7. Uptime
        $checks['uptime_seconds'] = (int) (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);

        return response()->json([
            'status'     => $healthy ? 'healthy' : 'degraded',
            'timestamp'  => now()->toIso8601String(),
            'hostname'   => gethostname() ?: php_uname('n'),
            'checks'     => $checks,
        ], $healthy ? 200 : 503);
    }
}
