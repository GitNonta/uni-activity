<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * คอนโทรลเลอร์โปรไฟล์สาธารณะของผู้ใช้ + ระบบติดตาม (Follow)
 */
class UserProfileController extends Controller
{
    public function __construct(
        private readonly UserProfileService $profileService
    ) {}

    /**
     * แสดงหน้าโปรไฟล์สาธารณะของผู้ใช้ (ผู้โพสต์/เจ้าหน้าที่/นักศึกษา)
     */
    public function show(Request $request, User $user): View
    {
        $data = $this->profileService->getProfileData($user, $request->user());

        return view('users.show', $data);
    }

    /**
     * กดติดตามผู้ใช้ (JSON API สำหรับปุ่ม Follow แบบ realtime)
     */
    public function follow(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();

        if ($viewer->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถติดตามตัวเองได้',
            ], 422);
        }

        $this->profileService->follow($viewer, $user);

        return response()->json([
            'success'        => true,
            'is_following'   => true,
            'followers_count' => $user->followersCount(),
        ]);
    }

    /**
     * เลิกติดตามผู้ใช้ (JSON API)
     */
    public function unfollow(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();

        $this->profileService->unfollow($viewer, $user);

        return response()->json([
            'success'        => true,
            'is_following'   => false,
            'followers_count' => $user->followersCount(),
        ]);
    }

    /**
     * รายชื่อผู้ติดตามของผู้ใช้ (JSON สำหรับ modal / หน้ารายชื่อ)
     */
    public function followers(User $user): JsonResponse
    {
        $followers = $user->followers()
            ->orderByDesc('follows.created_at')
            ->limit(50)
            ->get(['users.id', 'users.full_name', 'users.profile_photo', 'users.role']);

        return response()->json([
            'followers' => $followers->map(fn (User $u): array => [
                'id'    => $u->id,
                'name'  => $u->full_name,
                'photo' => $u->profile_photo ? asset('storage/' . $u->profile_photo) : null,
                'role'  => $u->role,
                'url'   => route('users.show', $u),
            ]),
        ]);
    }

    /**
     * รายชื่อผู้ที่ผู้ใช้กำลังติดตาม (JSON)
     */
    public function following(User $user): JsonResponse
    {
        $followings = $user->followings()
            ->orderByDesc('follows.created_at')
            ->limit(50)
            ->get(['users.id', 'users.full_name', 'users.profile_photo', 'users.role']);

        return response()->json([
            'following' => $followings->map(fn (User $u): array => [
                'id'    => $u->id,
                'name'  => $u->full_name,
                'photo' => $u->profile_photo ? asset('storage/' . $u->profile_photo) : null,
                'role'  => $u->role,
                'url'   => route('users.show', $u),
            ]),
        ]);
    }
}
