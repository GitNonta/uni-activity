<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            DB::table('users')->where('id', Auth::id())
                ->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }
}
