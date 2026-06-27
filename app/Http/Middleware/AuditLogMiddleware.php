<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log only authenticated user actions
        if ($request->user()) {
            $action = strtolower($request->method()) . ' ' . $request->path();
            $description = "User performed {$action}";
            log_action($action, null, null, $description);
        }

        return $response;
    }
}
