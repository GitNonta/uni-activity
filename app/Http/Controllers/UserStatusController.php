<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SocketService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserStatusController extends Controller
{
    /** Student pings to keep last_seen_at fresh (called every 60s) */
    public function ping()
    {
        DB::table('users')->where('id', Auth::id())
            ->update(['last_seen_at' => now()]);

        SocketService::emit('user:online:' . Auth::id(), 'user:online', [
            'user_id' => Auth::id(),
        ]);

        return response()->json(['ok' => true]);
    }

    /** Return online/last-seen status for a given user */
    public function status(int $userId)
    {
        $viewer = Auth::user();
        if (!$viewer || ($viewer->id !== $userId && !$viewer->isStaffOrAdmin())) {
            abort(403, 'คุณไม่มีสิทธิ์ดูสถานะผู้ใช้นี้');
        }

        $user = User::select('id', 'full_name', 'last_seen_at')->findOrFail($userId);

        $lastSeen   = $user->last_seen_at;
        $isOnline   = $lastSeen && $lastSeen->diffInMinutes(now()) < 2;
        $humanSeen  = $lastSeen ? $lastSeen->diffForHumans() : null;

        return response()->json([
            'user_id'    => $user->id,
            'is_online'  => $isOnline,
            'last_seen'  => $lastSeen?->toISOString(),
            'human_seen' => $humanSeen,
        ]);
    }
}
