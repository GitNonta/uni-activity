<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strips dangerous query parameters from admin routes to prevent
 * HTTP Parameter Pollution (HPP) attacks.
 */
class StripHppParams
{
    /**
     * Parameters that must never reach admin controllers via query string.
     */
    private const BLOCKED_PARAMS = [
        'config', 'debug', 'env', 'phpinfo', 'password',
        'secret', 'token', 'key', 'admin', 'root', 'bypass',
        'test', 'eval', 'exec', 'shell', 'cmd', 'command',
        'dump', 'log', 'error', 'stack', 'trace',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $query = $request->query();

        $filtered = array_diff_key(
            $query,
            array_flip(self::BLOCKED_PARAMS)
        );

        if (count($filtered) !== count($query)) {
            $request->merge($filtered);
        }

        return $next($request);
    }
}
