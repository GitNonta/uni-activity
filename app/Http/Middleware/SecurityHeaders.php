<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->header('Origin', '');
        $isAllowedOrigin = $origin !== '' && (
            str_ends_with($origin, 'github.io')
            || str_ends_with($origin, '.trycloudflare.com')
            || str_contains($origin, 'localhost')
            || str_contains($origin, '127.0.0.1')
            || str_contains($origin, '192.168.1.')
        );

        if ($request->isMethod('OPTIONS')) {
            $preflight = response('', 204);
            if ($isAllowedOrigin) {
                $preflight->header('Access-Control-Allow-Origin', $origin);
                $preflight->header('Access-Control-Allow-Methods', 'GET, HEAD, POST, PUT, DELETE, OPTIONS');
                $preflight->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization, X-CSRF-TOKEN, Accept');
                $preflight->header('Access-Control-Allow-Credentials', 'true');
            }
            return $preflight;
        }

        $isDirectIpRequest = filter_var($request->getHost(), FILTER_VALIDATE_IP) !== false;
        $isHttps = !$isDirectIpRequest && ($request->isSecure()
            || $request->header('x-forwarded-proto') === 'https'
            || $request->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || $request->header('cf-visitor') !== null);
        \Illuminate\Support\Facades\URL::forceScheme($isHttps ? 'https' : 'http');

        $response = $next($request);

        if (method_exists($response, 'header')) {
            if ($isAllowedOrigin) {
                $response->header('Access-Control-Allow-Origin', $origin);
                $response->header('Access-Control-Allow-Methods', 'GET, HEAD, POST, PUT, DELETE, OPTIONS');
                $response->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization, X-CSRF-TOKEN, Accept');
                $response->header('Access-Control-Allow-Credentials', 'true');
            }

            $response->header('X-Frame-Options', 'SAMEORIGIN');
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->remove('X-Powered-By');
            header_remove('X-Powered-By');

            // Only add CSP to HTML responses (skip regex on non-HTML)
            $contentType = $response->headers->get('Content-Type', '');
            if (str_contains($contentType, 'text/html')) {
                $csp = "default-src 'self' https: data: blob:; "
                    . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net; "
                    . "script-src-attr 'unsafe-inline'; "
                    . "worker-src 'self' blob:; "
                    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com https://cdnjs.cloudflare.com; "
                    . "font-src 'self' https://fonts.gstatic.com data:; "
                    . "img-src 'self' data: https: blob:; "
                    . "connect-src 'self' ws: wss: https:; "
                    . "upgrade-insecure-requests;";
                $response->header('Content-Security-Policy', $csp);
            }
        }

        return $response;
    }
}
