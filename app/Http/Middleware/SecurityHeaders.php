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
        $response = $next($request);

        // Make sure the response supports headers (e.g. not a BinaryFileResponse in some edge cases, though it usually does)
        if (method_exists($response, 'header')) {
            // 1. Prevent Clickjacking
            $response->header('X-Frame-Options', 'SAMEORIGIN');
            
            // 2. Prevent MIME-sniffing
            $response->header('X-Content-Type-Options', 'nosniff');
            
            // 3. HTTP Strict Transport Security (HSTS)
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            
            // 4. Content Security Policy (CSP)
            // Adjust the allowed sources based on your application's actual CDN and external resource needs.
            // Allowed: self, fonts.googleapis.com, Tailwind CDN (if used), WebSockets (ws/wss)
            $csp = "default-src 'self'; "
                 . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://unpkg.com; "
                 . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; "
                 . "font-src 'self' https://fonts.gstatic.com data:; "
                 . "img-src 'self' data: https: blob:; "
                 . "connect-src 'self' ws: wss: https:;";
            
            $response->header('Content-Security-Policy', $csp);
            
            // 5. Referrer Policy
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        return $response;
    }
}
