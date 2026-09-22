<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Follow;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * เซอร์วิสจัดการโปรไฟล์สาธารณะและระบบติดตาม (Follow)
 * - ดึงข้อมูลโปรไฟล์ของผู้ใช้ (นักศึกษา/เจ้าหน้าที่) เพื่อแสดงบนหน้าโปรไฟล์สาธารณะ
 * - จัดการการกดติดตาม / เลิกติดตาม ระหว่างผู้ใช้
 * - แจ้งเตือนผู้ติดตามเมื่อมีการโพสต์ใหม่ (กิจกรรม / ประกาศงาน / ข่าวประกาศ)
 */
class UserProfileService
{
    /** จำนวนผลงานล่าสุดสูงสุดที่แสดงบนโปรไฟล์ */
    private const RECENT_POSTS_LIMIT = 6;

    /**
     * ดึงข้อมูลสำหรับหน้าโปรไฟล์สาธารณะของผู้ใช้
     *
     * @return array<string, mixed>
     */
    public function getProfileData(User $profileUser, ?User $viewer): array
    {
        $viewerIsSelf = $viewer !== null && $viewer->id === $profileUser->id;

        return [
            'profileUser'    => $profileUser,
            'viewer'         => $viewer,
            'viewerIsSelf'   => $viewerIsSelf,
            'isFollowing'    => $viewer !== null && !$viewerIsSelf && $viewer->isFollowing($profileUser),
            'followersCount' => $profileUser->followersCount(),
            'followingCount' => (int) $profileUser->followings()->count(),
            'stats'          => $this->getPostStats($profileUser),
            'posts'          => $this->getRecentPosts($profileUser),
        ];
    }

    /**
     * กดติดตามผู้ใช้อื่น (idempotent — กดซ้ำไม่สร้างแถวใหม่)
     * พร้อมสร้างการแจ้งเตือนให้เจ้าของโปรไฟล์ว่ามีคนกดติดตาม
     */
    public function follow(User $follower, User $target): void
    {
        if ($follower->id === $target->id) {
            return;
        }

        $wasNew = Follow::firstOrCreate([
            'follower_id'  => $follower->id,
            'following_id' => $target->id,
        ])->wasRecentlyCreated;

        if ($wasNew) {
            Notification::create([
                'user_id' => $target->id,
                'title'   => 'มีผู้ติดตามใหม่',
                'message' => "{$follower->full_name} เริ่มติดตามคุณแล้ว",
                'type'    => 'new_follower',
                'url'     => route('users.show', $follower),
            ]);
        }
    }

    /** เลิกติดตามผู้ใช้อื่น (idempotent) */
    public function unfollow(User $follower, User $target): void
    {
        Follow::where('follower_id', $follower->id)
            ->where('following_id', $target->id)
            ->delete();
    }

    /**
     * สถิติการโพสต์ของผู้ใช้: จำนวนกิจกรรม / ประกาศงาน / ข่าวประกาศ ที่สร้าง
     *
     * @return array{activities: int, jobs: int, announcements: int, total: int}
     */
    private function getPostStats(User $user): array
    {
        $activities = (int) DB::table('activities')->where('created_by', $user->id)->count();
        $jobs = (int) DB::table('job_listings')->where('created_by', $user->id)->count();
        $announcements = (int) DB::table('announcements')->where('created_by', $user->id)->count();

        return [
            'activities'    => $activities,
            'jobs'          => $jobs,
            'announcements' => $announcements,
            'total'         => $activities + $jobs + $announcements,
        ];
    }

    /**
     * ผลงานล่าสุดของผู้ใช้ (กิจกรรม / ประกาศงาน / ข่าวประกาศ) รวมกันเรียงตามเวลา
     * นักศึกษาทั่วไปมักไม่มีโพสต์ — หน้าโปรไฟล์จะแสดงสถิติกิจกรรมที่เข้าร่วมแทน
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function getRecentPosts(User $user): Collection
    {
        // ใช้ Eloquent (ไม่ใช่ DB::table) เพื่อให้ route() ได้ URL แบบ slug จาก getRouteKey()
        $activities = \App\Models\Activity::query()
            ->where('created_by', $user->id)
            ->orderByDesc('created_at')
            ->limit(self::RECENT_POSTS_LIMIT)
            ->get(['id', 'slug', 'title', 'activity_date', 'created_at'])
            ->map(fn (\App\Models\Activity $a): array => [
                'type'      => 'activity',
                'typeLabel' => 'กิจกรรม',
                'title'     => $a->title,
                'date'      => $a->activity_date ?? $a->created_at,
                'url'       => route('activities.show', $a),
                'color'     => '#2563eb',
            ]);

        $jobs = \App\Models\JobListing::query()
            ->where('created_by', $user->id)
            ->orderByDesc('created_at')
            ->limit(self::RECENT_POSTS_LIMIT)
            ->get(['id', 'slug', 'title', 'start_date', 'created_at'])
            ->map(fn (\App\Models\JobListing $j): array => [
                'type'      => 'job',
                'typeLabel' => 'ประกาศงาน',
                'title'     => $j->title,
                'date'      => $j->start_date ?? $j->created_at,
                'url'       => route('jobs.show', $j),
                'color'     => '#ea580c',
            ]);

        $announcements = \App\Models\Announcement::query()
            ->where('created_by', $user->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->limit(self::RECENT_POSTS_LIMIT)
            ->get(['id', 'slug', 'title', 'created_at'])
            ->map(fn (\App\Models\Announcement $n): array => [
                'type'      => 'announcement',
                'typeLabel' => 'ข่าวประกาศ',
                'title'     => $n->title,
                'date'      => $n->created_at,
                'url'       => route('announcements.show', $n),
                'color'     => '#7c3aed',
            ]);

        return $activities
            ->concat($jobs)
            ->concat($announcements)
            ->sortByDesc(fn (array $p): int => $p['date'] ? Carbon::parse($p['date'])->getTimestamp() : 0)
            ->take(self::RECENT_POSTS_LIMIT)
            ->values();
    }
}
