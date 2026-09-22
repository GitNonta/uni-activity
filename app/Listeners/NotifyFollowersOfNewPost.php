<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ActivityPublished;
use App\Events\AnnouncementPublished;
use App\Events\JobPublished;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * แจ้งเตือนผู้ติดตาม (Followers) เมื่อผู้ที่ตนติดตามโพสต์ของใหม่
 *
 * รองรับ 3 เหตุการณ์: กิจกรรมใหม่, ประกาศงานใหม่, ข่าวประกาศใหม่
 * ทำงานแบบ queued เพื่อไม่ให้กระทบ request หลัก
 * ข้อความแจ้งเตือนจะมี url ไปยังหน้าโปรไฟล์ของผู้โพสต์ เพื่อให้ผู้รับ
 * เห็นข่าวได้เร็วและตรงจุดทันที (deep-link)
 */
class NotifyFollowersOfNewPost implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $tries = 3;
    public int $backoff = 30;

    public function handle(ActivityPublished|JobPublished|AnnouncementPublished $event): void
    {
        $post = $event->activity ?? $event->job ?? $event->announcement;
        $authorId = (int) ($post->created_by ?? 0);

        if ($authorId === 0) {
            return;
        }

        $author = User::find($authorId);
        if ($author === null) {
            return;
        }

        [$title, $message, $url] = match (true) {
            $event instanceof ActivityPublished => [
                'กิจกรรมใหม่จากผู้ที่คุณติดตาม',
                "{$author->full_name} เผยแพร่กิจกรรม: {$post->title}",
                route('activities.show', $post->id),
            ],
            $event instanceof JobPublished => [
                'ประกาศงานใหม่จากผู้ที่คุณติดตาม',
                "{$author->full_name} เปิดรับสมัครงาน: {$post->title}",
                route('jobs.show', $post->id),
            ],
            default => [
                'ข่าวประกาศใหม่จากผู้ที่คุณติดตาม',
                "{$author->full_name} เผยแพร่ข่าวประกาศ: {$post->title}",
                route('announcements.show', $post->id),
            ],
        };

        // ผู้ติดตามทั้งหมดของผู้โพสต์
        $followerIds = $author->followers()->pluck('users.id');

        foreach ($followerIds as $followerId) {
            try {
                Notification::create([
                    'user_id' => $followerId,
                    'title'   => $title,
                    'message' => $message,
                    'type'    => 'new_post_from_following',
                    'url'     => $url,
                ]);
            } catch (Throwable) {
                // ไม่ให้การแจ้งเตือนรายการใดรายการหนึ่งล้มเหลว ทำให้รายการอื่นไม่ถูกสร้าง
                continue;
            }
        }
    }
}
