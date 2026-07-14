<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestInspectorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('HEAD') || $request->is('up')) {
            return $next($request);
        }
        $request->attributes->set('inspector_start_time', microtime(true));
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('HEAD') || $request->is('up')) {
            return;
        }
        try {
            $startTime = $request->attributes->get('inspector_start_time');
            $duration = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : 0;

            // Get Request Body (limit to 10KB)
            $requestBody = $request->getContent();
            if (strlen($requestBody) > 10240) {
                $requestBody = substr($requestBody, 0, 10240) . '... (truncated)';
            }

            // Get Response Body (limit to 10KB)
            $responseBody = $response->getContent();
            if (is_string($responseBody) && strlen($responseBody) > 10240) {
                $responseBody = substr($responseBody, 0, 10240) . '... (truncated)';
            }

            $data = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'duration' => $duration,
                'status' => $response->getStatusCode(),
                'time' => now()->toIso8601String(),
                'request' => [
                    'headers' => $request->headers->all(),
                    'body' => $requestBody,
                ],
                'response' => [
                    'headers' => $response->headers->all(),
                    'body' => $responseBody,
                ]
            ];

            $payload = json_encode($data);

            // Send via UDP (fire-and-forget)
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket) {
                // non-blocking
                socket_set_nonblock($socket);
                socket_sendto($socket, $payload, strlen($payload), 0, '127.0.0.1', 9998);
                socket_close($socket);
            }
        } catch (\Throwable $e) {
            // Ignore errors to not affect the application
        }
    }
}
