<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strips dangerous query parameters from admin routes to prevent
 * HTTP Parameter Pollution (HPP) attacks.
 *
 * ตรวจจับและลบ parameter อันตรายออกจาก query string และ POST body
 */
class StripHppParams
{
    /**
     * Parameters that must never reach admin controllers via query string or POST body.
     */
    private const BLOCKED_PARAMS = [
        'config', 'debug', 'env', 'phpinfo', 'password',
        'secret', 'token', 'key', 'admin', 'root', 'bypass',
        'test', 'eval', 'exec', 'shell', 'cmd', 'command',
        'dump', 'log', 'error', 'stack', 'trace', 'setup',
        'install', 'update', 'delete', 'drop', 'truncate',
        '_method_override', 'override', 'auth', 'role', 'is_admin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $detected = [];

        // 1. Strip from GET query string
        $query = $request->query();
        $cleanQuery = array_diff_key($query, array_flip(self::BLOCKED_PARAMS));
        if (count($cleanQuery) !== count($query)) {
            $detected = array_merge($detected, array_keys(array_diff_key($query, $cleanQuery)));
            // Remove from actual query string (Symfony ParameterBag)
            foreach (array_diff_key($query, $cleanQuery) as $key => $_) {
                $request->query->remove($key);
                $request->server->set('QUERY_STRING', http_build_query($cleanQuery));
            }
        }

        // 2. Strip from POST body (non-file inputs only)
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $body = $request->except(array_keys($request->allFiles()));
            $cleanBody = array_diff_key($body, array_flip(self::BLOCKED_PARAMS));
            if (count($cleanBody) !== count($body)) {
                $detected = array_merge($detected, array_keys(array_diff_key($body, $cleanBody)));
                foreach (array_diff_key($body, $cleanBody) as $key => $_) {
                    $request->request->remove($key);
                }
            }
        }

        // 3. Log ถ้าตรวจพบ HPP attempt
        if (!empty($detected)) {
            Log::warning('[HPP] Blocked dangerous parameters on admin route', [
                'ip'         => $request->ip(),
                'url'        => $request->fullUrl(),
                'method'     => $request->method(),
                'params'     => $detected,
                'user_agent' => $request->userAgent(),
                'user_id'    => auth()->id(),
            ]);
        }

        return $next($request);
    }
}
