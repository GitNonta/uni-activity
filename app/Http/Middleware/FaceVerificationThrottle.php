<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

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
        
        // Define rate limits per mode (requests per minute)
        $limits = [
            'python' => 10,   // Heavy processing
            'js' => 30,       // Light processing  
            'hybrid' => 20    // Balanced
        ];
        
        $maxAttempts = $limits[$mode] ?? $limits['hybrid'];
        $decayMinutes = 1;
        
        // Create unique key for this user and mode
        $key = sprintf('face_verify:%s:%s', $user->id, $mode);
        
        // Check rate limit
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);
            
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
        
        return $response;
    }
}