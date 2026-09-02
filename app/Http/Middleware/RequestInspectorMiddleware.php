<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestInspectorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('HEAD') || $request->is('up')) {
            return $next($request);
        }
        $request->attributes->set('inspector_start_time', microtime(true));
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('HEAD') || $request->is('up')) {
            return;
        }
        try {
            $startTime = $request->attributes->get('inspector_start_time');
            $duration = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : 0;

            // Lightweight: only send metadata, no body capture
            $data = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'duration' => $duration,
                'status' => $response->getStatusCode(),
                'time' => now()->toIso8601String(),
            ];

            $payload = json_encode($data);
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket) {
                socket_set_nonblock($socket);
                socket_sendto($socket, $payload, strlen($payload), 0, '127.0.0.1', 9998);
                socket_close($socket);
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }
    }
}
