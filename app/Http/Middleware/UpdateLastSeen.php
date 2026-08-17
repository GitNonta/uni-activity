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
            $user = Auth::user();
            
            // อัปเดตเฉพาะเมื่อผ่านไปแล้วอย่างน้อย 1 นาที เพื่อความแม่นยำของสถานะออนไลน์
            $lastSeen = $user->last_seen_at;
            if (!$lastSeen || $lastSeen->diffInSeconds(now()) >= 60) {
                DB::table('users')->where('id', $user->id)
                    ->update(['last_seen_at' => now()]);
            }
        }

        return $next($request);
    }
}
