<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;

/**
 * Smart rate limiting for face verification requests
 * Adapts limits based on processing mode and user behavior
 */
class FaceVerificationThrottle
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $mode = $request->input('mode', 'hybrid');
        
        // Rate limits per mode (requests per minute). Must match the scan
        // page's cadence: the live loop fires ~1 frame/second, so a 10/min
        // limit caused a 429 storm — ~10 frames, then throttled retries
        // masked as a frozen UI with only ~1 real frame/min reaching the
        // AI node.
        $limits = [
            'python' => 90,   // Heavy processing, 1 Hz scan + headroom
            'js' => 120,      // Light processing
            'hybrid' => 90    // Balanced
        ];
        
        $maxAttempts = $limits[$mode] ?? $limits['hybrid'];
        $decayMinutes = 1;
        
        // Create unique key for this user and mode
        $key = sprintf('face_verify:%s:%s', $user->id, $mode);
        
        // Check rate limit
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);
            // Diagnostic: one line per rejected attempt — proves whether the
            // browser loop is alive when the AI node sees no frames.
            Log::info(sprintf('[face-verify] user=%s mode=%s throttled retry_in=%ds', $user->id ?? 0, $mode, $retryAfter));

            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'retry_after' => $retryAfter,
                'mode' => $mode,
                'limit' => $maxAttempts . ' per minute'
            ], 429)->header('Retry-After', $retryAfter);
        }
        
        // Execute request
        $response = $next($request);
        
        // Only count against limit if request was processed (not a validation error)
        if ($response->getStatusCode() < 400) {
            $this->limiter->hit($key, $decayMinutes * 60);
        }

        // Diagnostic: status per request — 419/401/500 here means frames are
        // dying at Laravel and never reach the AI node.
        Log::info(sprintf('[face-verify] user=%s mode=%s status=%d', $user->id ?? 0, $mode, $response->getStatusCode()));

        return $response;
    }
}