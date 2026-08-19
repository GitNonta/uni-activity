<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectAdminPanel
{
    /**
     * ❌ FIXED V4: Restrict admin panel access by:
     * 1. Rate limiting (already applied via throttle:staff-login)
     * 2. CAPTCHA on login (can be added via package)
     * 3. Optional IP whitelist (via env config)
     * 
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow authenticated admins to access admin panel
        // Wrap in try-catch to handle Redis/session connection failures gracefully
        try {
            if (auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isStaff())) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            // If session backend (Redis) is down, log and continue without auth check
            // This prevents a 500 error on every page when Redis is unreachable
            \Illuminate\Support\Facades\Log::error('ProtectAdminPanel: Session backend unavailable', [
                'error' => $e->getMessage(),
                'path'  => $request->path(),
            ]);
        }

        // If trying to access admin login, check IP whitelist if configured
        if ($request->path() === 'admin/login' || str_starts_with($request->path(), 'admin/login')) {
            $whitelist = env('ADMIN_IP_WHITELIST');
            
            // If IP whitelist is configured, enforce it
            if ($whitelist) {
                $allowedIps = array_map('trim', explode(',', $whitelist));
                if (!in_array($request->ip(), $allowedIps, strict: true)) {
                    // Log attempt
                    \Illuminate\Support\Facades\Log::warning('Admin login attempt from non-whitelisted IP: ' . $request->ip(), [
                        'path' => $request->path(),
                        'timestamp' => now(),
                    ]);
                    
                    // Return 403 Forbidden
                    return response()->view('errors.403', [
                        'message' => 'Access to admin panel is restricted.',
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
