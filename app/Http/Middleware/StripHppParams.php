<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strips dangerous parameters to prevent HTTP Parameter Pollution (HPP)
 * and SSRF / Open Redirect probe attacks across all web & API routes.
 *
 * ตรวจจับและลบ parameter อันตราย รวมถึงตรวจจับ parameter ซ้ำซ้อน (HPP)
 */
class StripHppParams
{
    /**
     * Dangerous query parameters that must never reach controllers via GET query string.
     */
    private const BLOCKED_QUERY_PARAMS = [
        'config', 'debug', 'env', 'phpinfo', 'password',
        'secret', 'admin', 'root', 'bypass',
        'test', 'eval', 'exec', 'shell', 'cmd', 'command',
        'dump', 'log', 'error', 'stack', 'trace', 'setup',
        'install', 'delete', 'drop', 'truncate',
        '_method_override', 'override', 'auth', 'role', 'is_admin',
    ];

    /**
     * Dangerous parameters that must not be injected in request body.
     */
    private const BLOCKED_BODY_PARAMS = [
        '_method_override', 'override', 'role', 'is_admin',
        'eval', 'exec', 'shell', 'cmd', 'command',
    ];

    /**
     * Query parameter names commonly abused for SSRF or Open Redirect probes.
     */
    private const REDIRECT_PROBE_PARAMS = [
        'url', 'redirect', 'dest', 'destination', 'target', 'next', 'return', 'forward',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $detected = [];
        $rawQuery = (string) $request->server->get('QUERY_STRING', '');

        // 1. Detect Duplicate Query Parameters (HTTP Parameter Pollution, e.g. ?email=a&email=b)
        if ($rawQuery !== '') {
            $hppKeys = $this->detectDuplicateParams($rawQuery);
            if (!empty($hppKeys)) {
                $detected['hpp_duplicate_keys'] = $hppKeys;
                foreach ($hppKeys as $key) {
                    $request->query->remove($key);
                }
            }
        }

        // 2. Strip blocked parameters from GET query string
        $query = $request->query();
        $cleanQuery = array_diff_key($query, array_flip(self::BLOCKED_QUERY_PARAMS));
        if (count($cleanQuery) !== count($query)) {
            $stripped = array_keys(array_diff_key($query, $cleanQuery));
            $detected['blocked_query_params'] = $stripped;
            foreach ($stripped as $key) {
                $request->query->remove($key);
            }
        }

        // 3. Detect and strip SSRF / Open Redirect probes (e.g. ?url=http://169.254.169.254)
        foreach (self::REDIRECT_PROBE_PARAMS as $param) {
            if ($request->query->has($param)) {
                $val = (string) $request->query->get($param);
                if ($this->isSuspiciousRedirectTarget($val)) {
                    $detected['ssrf_redirect_probe'] = [$param => $val];
                    $request->query->remove($param);
                }
            }
        }

        // Update raw QUERY_STRING if query was sanitized
        if (!empty($detected)) {
            $request->server->set('QUERY_STRING', http_build_query($request->query->all()));
        }

        // 4. Strip blocked parameters from POST / PUT / PATCH body
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $body = $request->except(array_keys($request->allFiles()));
            $cleanBody = array_diff_key($body, array_flip(self::BLOCKED_BODY_PARAMS));
            if (count($cleanBody) !== count($body)) {
                $bodyStripped = array_keys(array_diff_key($body, $cleanBody));
                $detected['blocked_body_params'] = $bodyStripped;
                foreach ($bodyStripped as $key) {
                    $request->request->remove($key);
                }
            }
        }

        // 5. Security audit logging
        if (!empty($detected)) {
            Log::warning('[SECURITY_INTERCEPT] Blocked suspicious request parameters', [
                'ip'         => $request->ip(),
                'url'        => $request->fullUrl(),
                'method'     => $request->method(),
                'detected'   => $detected,
                'user_agent' => $request->userAgent(),
                'user_id'    => auth()->id(),
            ]);
        }

        return $next($request);
    }

    /**
     * Detects non-array parameters occurring multiple times in query string.
     *
     * @return array<string>
     */
    private function detectDuplicateParams(string $queryString): array
    {
        $pairs = explode('&', $queryString);
        $counts = [];
        $duplicates = [];

        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }
            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);

            // Skip legitimate array parameters like filter[] or tags[0]
            if (str_ends_with($key, ']') || str_contains($key, '[')) {
                continue;
            }

            $counts[$key] = ($counts[$key] ?? 0) + 1;
            if ($counts[$key] > 1) {
                $duplicates[] = $key;
            }
        }

        return array_values(array_unique($duplicates));
    }

    /**
     * Checks if a target contains internal IPs, metadata endpoints, or arbitrary remote schemes.
     */
    private function isSuspiciousRedirectTarget(string $target): bool
    {
        $decoded = strtolower(urldecode($target));

        // Detect AWS / Cloud metadata, localhost, or loopback IPs
        if (str_contains($decoded, '169.254.169.254')
            || str_contains($decoded, '127.0.0.1')
            || str_contains($decoded, 'localhost')
            || str_contains($decoded, '::1')
            || str_contains($decoded, '0.0.0.0')
        ) {
            return true;
        }

        // Detect external schemes in redirect/url params
        if (preg_match('#^https?://#i', $decoded) || str_starts_with($decoded, '//')) {
            return true;
        }

        return false;
    }
}
