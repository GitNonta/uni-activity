<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cspNonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $cspNonce);

        $isDirectIpRequest = filter_var($request->getHost(), FILTER_VALIDATE_IP) !== false;
        $isHttps = !$isDirectIpRequest && ($request->isSecure()
            || $request->header('x-forwarded-proto') === 'https'
            || $request->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || $request->header('cf-visitor') !== null);
        \Illuminate\Support\Facades\URL::forceScheme($isHttps ? 'https' : 'http');

        $response = $next($request);

        // Make sure the response supports headers (e.g. not a BinaryFileResponse in some edge cases, though it usually does)
        if (method_exists($response, 'header')) {
            if (str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
                $response->header('Cache-Control', 'no-store, private');
            }

            // 1. Prevent Clickjacking
            $response->header('X-Frame-Options', 'SAMEORIGIN');
            
            // 2. Prevent MIME-sniffing
            $response->header('X-Content-Type-Options', 'nosniff');
            
            // 3. HTTP Strict Transport Security (HSTS)
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            
            // 4. Content Security Policy (CSP) ❌ FIXED V6: Remove unsafe-inline and unsafe-eval
            // Updated policy restricts inline scripts and eval() to prevent XSS attacks
            // Note: Tailwind CDN may need to be replaced with local/build-time CSS to fully comply
            $csp = "default-src 'self' https: data: blob:; "
                . "script-src 'self' 'nonce-{$cspNonce}' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net; "
                  . "worker-src 'self' blob:; "
                . "style-src 'self' 'nonce-{$cspNonce}' https://fonts.googleapis.com https://unpkg.com https://cdnjs.cloudflare.com; "
                  . "style-src-attr 'unsafe-inline'; "
                  . "font-src 'self' https://fonts.gstatic.com data:; "
                  . "img-src 'self' data: https: blob:; "
                  . "connect-src 'self' ws: wss: https:; "
                  . "upgrade-insecure-requests;";
            
            $response->header('Content-Security-Policy', $csp);
            
            // 5. Referrer Policy
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        return $response;
    }
}
