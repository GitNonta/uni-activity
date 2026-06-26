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
            
            // อัปเดตเฉพาะเมื่อผ่านไปแล้วอย่างน้อย 5 นาที เพื่อลดภาระฐานข้อมูล
            $lastSeen = $user->last_seen_at;
            if (!$lastSeen || $lastSeen->diffInMinutes(now()) >= 5) {
                DB::table('users')->where('id', $user->id)
                    ->update(['last_seen_at' => now()]);
            }
        }

        return $next($request);
    }
}
