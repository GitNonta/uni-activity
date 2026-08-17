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

    public function test_student_cannot_see_future_scheduled_announcement(): void
    {
        $student = User::factory()->create(['role' => 'student', 'faculty' => 'Science']);

        // 1. Published immediately
        $immediate = Announcement::factory()->create([
            'title'        => 'Immediate News',
            'is_active'    => true,
            'published_at' => null,
        ]);

        // 2. Published in past
        $past = Announcement::factory()->create([
            'title'        => 'Past News',
            'is_active'    => true,
            'published_at' => now()->subHour(),
        ]);

        // 3. Scheduled in future
        $future = Announcement::factory()->create([
            'title'        => 'Future Secret News',
            'is_active'    => true,
            'published_at' => now()->addDays(2),
        ]);

        $visibleAnnouncements = Announcement::forAudience($student)->get();

        $this->assertTrue($visibleAnnouncements->contains('id', $immediate->id));
        $this->assertTrue($visibleAnnouncements->contains('id', $past->id));
        $this->assertFalse($visibleAnnouncements->contains('id', $future->id));
    }

    public function test_admin_can_schedule_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $futureDate = now()->addDays(3)->format('Y-m-d H:i:s');

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title'          => 'Orientation Next Week',
            'content'        => 'Detailed orientation announcement...',
            'type'           => 'info',
            'is_active'      => '1',
            'published_at'   => $futureDate,
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $this->assertDatabaseHas('announcements', [
            'title' => 'Orientation Next Week',
        ]);

        $announcement = Announcement::where('title', 'Orientation Next Week')->first();
        $this->assertNotNull($announcement->published_at);
        $this->assertStringContainsString('ตั้งเวลา', $announcement->publish_status);
    }
}
