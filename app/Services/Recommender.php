<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * (7) บริการแนะนำกิจกรรม — สร้างคะแนนจาก 2 สัญญาณตาม schema จริงของระบบ:
 *   1) กิจกรรมที่ผู้ใช้ "ที่เราติดตาม" เป็นผู้สร้าง (ตาราง follows คือ user-to-user)
 *   2) หมวดหมู่จากประวัติลงทะเบียนล่าสุด 10 รายการ
 *   เงื่อนไขกลาง: กิจกรรมสถานะ open + ไม่ใช่กิจกรรมที่ลงทะเบียนไปแล้ว
 *   ถ้าไม่มีสัญญาณใดเลย → กิจกรรมยอดนิยม (นับจำนวนลงทะเบียน)
 */
class Recommender
{
    private const LIMIT_HISTORY = 10;

    /** @return Activity[] */
    public function forUser(User $user, int $limit = 6): array
    {
        $registeredIds = DB::table('registrations')
            ->where('user_id', $user->id)
            ->pluck('activity_id')
            ->all();

        // สัญญาณ 1: กิจกรรมที่ผู้แต่งเป็นคนที่เราติดตาม
        $followingIds = Follow::query()
            ->where('follower_id', $user->id)
            ->pluck('following_id')
            ->all();

        // สัญญาณ 2: หมวดจากประวัติลงทะเบียนล่าสุด 10 รายการ
        $historyCategoryIds = DB::table('registrations as r')
            ->join('activities as a', 'a.id', '=', 'r.activity_id')
            ->where('r.user_id', $user->id)
            ->orderByDesc('r.id')
            ->limit(self::LIMIT_HISTORY)
            ->pluck('a.category_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $hasSignals = $followingIds !== [] || $historyCategoryIds !== [];

        if (! $hasSignals) {
            return $this->popular($limit, $registeredIds);
        }

        $activities = Activity::query()
            ->where('status', 'open')
            ->when($registeredIds !== [], fn ($q) => $q->whereNotIn('id', $registeredIds))
            ->where(function ($q) use ($followingIds, $historyCategoryIds): void {
                $q->whereIn('created_by', $followingIds)
                    ->orWhereIn('category_id', $historyCategoryIds);
            })
            ->orderByDesc('created_at')
            ->limit($limit * 3)
            ->get();

        // จัดอันดับ: กิจกรรมของคนที่เราติดตามมาก่อน รองลงมาคือหมวดจากประวัติ
        $ranked = $activities->sortByDesc(fn (Activity $a): int => match (true) {
            in_array($a->created_by, $followingIds, true) => 2,
            in_array($a->category_id, $historyCategoryIds, true) => 1,
            default => 0,
        })->values();

        $picked = $ranked->take($limit);

        // เผื่อไม่ครบ limit จึงเติมจากกิจกรรมยอดนิยม
        if ($picked->count() < $limit) {
            $excludeIds = array_merge($registeredIds, $picked->pluck('id')->all());
            $picked = $picked->merge(collect($this->popular($limit - $picked->count(), $excludeIds)));
        }

        return $picked->all();
    }

    /** กิจกรรมยอดนิยม: สถานะ open + นับจำนวนลงทะเบียนมากที่สุด */
    /** @return Activity[] */
    public function popular(int $limit = 6, array $excludeIds = []): array
    {
        return Activity::query()
            ->where('status', 'open')
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->withCount('registrations')
            ->orderByDesc('registrations_count')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}
