<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_only_sees_published_announcements_and_not_future_scheduled_ones(): void
    {
        $student = User::factory()->create([
            'role'    => 'student',
            'faculty' => 'คณะวิทยาศาสตร์และเทคโนโลยี',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        // 1. เผยแพร่ทันที (published_at is null)
        $ann1 = Announcement::create([
            'title'        => 'ประกาศรับสมัครทุนการศึกษา',
            'content'      => 'รายละเอียดทุนการศึกษาประจำปี',
            'type'         => 'info',
            'is_active'    => true,
            'published_at' => null,
            'created_by'   => $admin->id,
        ]);

        // 2. ตั้งเวลาในอดีต (published_at <= now())
        $ann2 = Announcement::create([
            'title'        => 'ประกาศแจ้งปิดปรับปรุงระบบ',
            'content'      => 'ระบบจะปิดปรับปรุงเวลา 22:00',
            'type'         => 'warning',
            'is_active'    => true,
            'published_at' => now()->subHour(),
            'created_by'   => $admin->id,
        ]);

        // 3. ตั้งเวลาในอนาคต (published_at > now()) -> นักศึกษาต้องยังไม่เห็น
        $ann3 = Announcement::create([
            'title'        => 'ประกาศลับกิจกรรมเปิดภาคเรียนใหม่',
            'content'      => 'กิจกรรมต้อนรับน้องใหม่',
            'type'         => 'success',
            'is_active'    => true,
            'published_at' => now()->addDays(2),
            'created_by'   => $admin->id,
        ]);

        $visibleAnnouncements = Announcement::forAudience($student)->get();

        $this->assertTrue($visibleAnnouncements->contains('id', $ann1->id));
        $this->assertTrue($visibleAnnouncements->contains('id', $ann2->id));
        $this->assertFalse($visibleAnnouncements->contains('id', $ann3->id));
    }

    public function test_admin_can_schedule_announcement_with_future_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $futureDate = now()->addDays(3)->format('Y-m-d H:i:s');

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title'        => 'ประกาศล่วงหน้า 3 วัน',
            'content'      => 'เนื้อหาประกาศล่วงหน้า',
            'type'         => 'info',
            'is_active'    => '1',
            'published_at' => $futureDate,
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $this->assertDatabaseHas('announcements', [
            'title' => 'ประกาศล่วงหน้า 3 วัน',
        ]);

        $ann = Announcement::where('title', 'ประกาศล่วงหน้า 3 วัน')->first();
        $this->assertNotNull($ann->published_at);
        $this->assertStringContainsString('ตั้งเวลา', $ann->publish_status);
    }
}
