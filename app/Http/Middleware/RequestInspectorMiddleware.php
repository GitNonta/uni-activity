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

        $startTime = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->sendTelemetry($request, $response, $duration);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $startTime = $request->attributes->get('inspector_start_time');
        $duration = $startTime ? round((microtime(true) - (float) $startTime) * 1000, 2) : 0;
        $this->sendTelemetry($request, $response, $duration);
    }

    protected function sendTelemetry(Request $request, Response $response, float $duration): void
    {
        if ($request->attributes->get('inspector_logged') === true) {
            return;
        }
        $request->attributes->set('inspector_logged', true);

        if ($request->isMethod('HEAD') || $request->is('up')) {
            return;
        }

        try {
            // Extract sanitized input payload (omitting sensitive keys)
            $inputs = $request->except([
                'password', 'password_confirmation', 'current_password', 
                'new_password', '_token', 'api_key', 'token'
            ]);

            $rawContent = (string) $request->getContent();
            $bodyStr = '';

            if ($request->isMethod('GET')) {
                // For GET requests, parameters belong in URL query, not body
                if (!empty($rawContent)) {
                    $bodyStr = strlen($rawContent) > 2048 ? substr($rawContent, 0, 2048) . '... (truncated)' : $rawContent;
                }
            } else {
                if (!empty($inputs)) {
                    $bodyStr = (string) json_encode($inputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                } elseif (!empty($rawContent)) {
                    $bodyStr = strlen($rawContent) > 2048 ? substr($rawContent, 0, 2048) . '... (truncated)' : $rawContent;
                }
            }

            // Extract relevant request headers
            $headers = [];
            $allowedHeaders = [
                'host', 'user-agent', 'accept', 'content-type', 'referer',
                'x-requested-with', 'x-forwarded-for', 'x-forwarded-proto',
                'accept-language', 'accept-encoding'
            ];
            foreach ($request->headers->all() as $k => $v) {
                if (in_array(strtolower($k), $allowedHeaders, true)) {
                    $headers[$k] = is_array($v) ? implode(', ', $v) : (string) $v;
                }
            }
            if ($request->hasHeader('Authorization')) {
                $headers['Authorization'] = 'Bearer [PROTECTED]';
            }

            // Response metadata
            $responseHeaders = [
                'Content-Type' => (string) $response->headers->get('content-type', 'text/html'),
                'Content-Length' => (string) $response->headers->get('content-length', (string) strlen((string) $response->getContent())),
            ];

            $responseBody = '';
            $contentType = strtolower((string) $response->headers->get('content-type', ''));
            if (str_contains($contentType, 'application/json')) {
                $content = (string) $response->getContent();
                $responseBody = strlen($content) > 2048 ? substr($content, 0, 2048) . '...' : $content;
            }

            $data = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'ip' => (string) $request->ip(),
                'duration' => $duration,
                'status' => $response->getStatusCode(),
                'time' => now()->toIso8601String(),
                'request' => [
                    'headers' => $headers,
                    'body' => $bodyStr ?: '',
                    'query' => $request->query(),
                ],
                'response' => [
                    'headers' => $responseHeaders,
                    'body' => $responseBody,
                ],
            ];

            $payload = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (strlen($payload) > 60000) {
                $data['request']['body'] = '(Payload exceeds UDP limit)';
                $data['response']['body'] = '';
                $payload = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket) {
                socket_set_nonblock($socket);
                socket_sendto($socket, $payload, strlen($payload), 0, '127.0.0.1', 9998);
                socket_close($socket);
            }
        } catch (\Throwable $e) {
            // Silence exceptions to avoid disrupting user response
        }
    }
}
