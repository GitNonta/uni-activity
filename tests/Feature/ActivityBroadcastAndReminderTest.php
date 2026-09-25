<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ActivityBroadcastSent;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityReminderLog;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\User;
use App\Services\LineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class ActivityBroadcastAndReminderTest extends TestCase
{
    use RefreshDatabase;

    private function createStaff(): User
    {
        return User::factory()->create([
            'role'  => 'staff',
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    private function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'student',
        ], $attributes));
    }

    private function createActivity(array $attributes = []): Activity
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $creator = User::factory()->create([
            'role' => 'admin',
        ]);

        return Activity::create(array_merge([
            'title'            => 'กิจกรรมทดสอบบรอดแคสต์',
            'location'         => 'อาคาร 5 ห้อง 501',
            'activity_date'    => now()->addDays(2)->format('Y-m-d'),
            'start_time'       => '09:00',
            'end_time'         => '12:00',
            'activity_hours'   => 3,
            'max_participants' => 100,
            'register_open_at' => now()->subDay(),
            'register_close_at'=> now()->addDay(),
            'checkin_open_at'  => now()->setTime(8, 30),
            'checkin_close_at' => now()->setTime(12, 30),
            'category_id'      => $category->id,
            'scope'            => 'university',
            'status'           => 'open',
            'is_mandatory'     => false,
            'created_by'       => $creator->id,
        ], $attributes));
    }

    public function test_staff_can_view_activity_broadcast_page(): void
    {
        $staff = $this->createStaff();
        $activity = $this->createActivity();

        $response = $this->actingAs($staff)->get(route('admin.activities.broadcast', $activity));

        $response->assertStatus(200);
        $response->assertSee('ระบบบรอดแคสต์และแจ้งเตือนอัตโนมัติ');
        $response->assertSee($activity->title);
    }

    public function test_student_cannot_view_activity_broadcast_page(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivity();

        $response = $this->actingAs($student)->get(route('admin.activities.broadcast', $activity));

        $response->assertRedirect(route('activities.index'));
    }

    public function test_staff_can_broadcast_to_registered_students_with_event_and_notifications(): void
    {
        Event::fake([ActivityBroadcastSent::class]);

        $mockLine = Mockery::mock(LineService::class);
        $mockLine->shouldReceive('buildActivityBroadcastMessage')->andReturn(['type' => 'flex']);
        $mockLine->shouldReceive('multicast')->once()->andReturn(true);
        $this->app->instance(LineService::class, $mockLine);

        $staff = $this->createStaff();
        $activity = $this->createActivity();

        $studentApproved = $this->createStudent([
            'line_user_id'        => 'U11111111111111111111111111111111',
            'line_notify_enabled' => true,
        ]);
        $studentWaitlisted = $this->createStudent();

        Registration::create([
            'activity_id' => $activity->id,
            'user_id'     => $studentApproved->id,
            'status'      => 'approved',
        ]);

        Registration::create([
            'activity_id' => $activity->id,
            'user_id'     => $studentWaitlisted->id,
            'status'      => 'waitlisted',
        ]);

        $response = $this->actingAs($staff)->post(route('admin.activities.broadcast.send', $activity), [
            'title'           => 'แจ้งย้ายห้องด่วน',
            'message'         => 'ย้ายจากห้อง 501 เป็นหอประชุมใหญ่',
            'type'            => 'venue_change',
            'target_audience' => 'approved',
            'channels'        => ['in_app', 'line'],
        ]);

        $response->assertRedirect(route('admin.activities.broadcast', $activity));
        $response->assertSessionHas('success');

        // Check broadcast record in DB
        $this->assertDatabaseHas('activity_broadcasts', [
            'activity_id'      => $activity->id,
            'sender_id'        => $staff->id,
            'title'            => 'แจ้งย้ายห้องด่วน',
            'type'             => 'venue_change',
            'target_audience'  => 'approved',
            'recipients_count' => 1,
            'line_sent_count'  => 1,
        ]);

        // Approved student receives notification
        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $studentApproved->id,
            'title'   => '[ประกาศด่วน] แจ้งย้ายห้องด่วน',
            'type'    => 'activity_broadcast',
        ]);

        // Waitlisted student should NOT receive notification when target is 'approved'
        $this->assertDatabaseMissing('notifications_custom', [
            'user_id' => $studentWaitlisted->id,
            'title'   => '[ประกาศด่วน] แจ้งย้ายห้องด่วน',
        ]);

        // WebSocket Event broadcasted
        Event::assertDispatched(ActivityBroadcastSent::class, function ($event) use ($activity) {
            return $event->activityId === $activity->id
                && $event->payload['title'] === 'แจ้งย้ายห้องด่วน'
                && $event->payload['type'] === 'venue_change';
        });
    }

    public function test_broadcast_to_all_audience_reaches_both_approved_and_waitlisted(): void
    {
        Event::fake([ActivityBroadcastSent::class]);

        $staff = $this->createStaff();
        $activity = $this->createActivity();

        $student1 = $this->createStudent();
        $student2 = $this->createStudent();

        Registration::create([
            'activity_id' => $activity->id,
            'user_id'     => $student1->id,
            'status'      => 'approved',
        ]);

        Registration::create([
            'activity_id' => $activity->id,
            'user_id'     => $student2->id,
            'status'      => 'waitlisted',
        ]);

        $this->actingAs($staff)->post(route('admin.activities.broadcast.send', $activity), [
            'title'           => 'ประกาศทั่วไปถึงทุกคน',
            'message'         => 'กรุณาตรวจสอบกำหนดการ',
            'type'            => 'general',
            'target_audience' => 'all',
            'channels'        => ['in_app'],
        ]);

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $student1->id,
            'title'   => '[ประกาศด่วน] ประกาศทั่วไปถึงทุกคน',
        ]);

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $student2->id,
            'title'   => '[ประกาศด่วน] ประกาศทั่วไปถึงทุกคน',
        ]);
    }

    public function test_scheduled_reminders_command_sends_24h_and_2h_and_deduplicates(): void
    {
        $mockLine = Mockery::mock(LineService::class);
        $mockLine->shouldReceive('buildReminderMessage')->andReturn(['type' => 'flex']);
        $mockLine->shouldReceive('multicast')->andReturn(true);
        $this->app->instance(LineService::class, $mockLine);

        // 1. กิจกรรมเริ่มในอีก 24 ชั่วโมง
        $tomorrow = Carbon::now()->addHours(24);
        $act24h = $this->createActivity([
            'activity_date' => $tomorrow->toDateString(),
            'start_time'    => $tomorrow->format('H:i:s'),
        ]);

        // 2. กิจกรรมเริ่มในอีก 2 ชั่วโมง
        $inTwoHours = Carbon::now()->addMinutes(110); // ~1.8 ชั่วโมง
        $act2h = $this->createActivity([
            'activity_date' => $inTwoHours->toDateString(),
            'start_time'    => $inTwoHours->format('H:i:s'),
        ]);

        $student = $this->createStudent();
        Registration::create([
            'activity_id' => $act24h->id,
            'user_id'     => $student->id,
            'status'      => 'approved',
        ]);
        Registration::create([
            'activity_id' => $act2h->id,
            'user_id'     => $student->id,
            'status'      => 'approved',
        ]);

        // รันคำสั่ง reminders:send ครั้งที่ 1
        $exitCode = Artisan::call('reminders:send');
        $this->assertEquals(0, $exitCode);

        // ตรวจสอบว่ามีการบันทึก log สำหรับ 24h และ 2h
        $this->assertDatabaseHas('activity_reminder_logs', [
            'activity_id'   => $act24h->id,
            'reminder_type' => '24h',
        ]);

        $this->assertDatabaseHas('activity_reminder_logs', [
            'activity_id'   => $act2h->id,
            'reminder_type' => '2h',
        ]);

        // ตรวจสอบ In-App Notification ถูกส่ง
        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $student->id,
            'type'    => 'reminder',
        ]);

        $logCountBefore = ActivityReminderLog::count();

        // รันคำสั่งซ้ำครั้งที่ 2 -> ต้องไม่ส่งซ้ำ (Deduplication)
        Artisan::call('reminders:send');
        $this->assertEquals($logCountBefore, ActivityReminderLog::count());
    }

    public function test_staff_can_manually_trigger_reminder(): void
    {
        $mockLine = Mockery::mock(LineService::class);
        $mockLine->shouldReceive('buildReminderMessage')->andReturn(['type' => 'flex']);
        $mockLine->shouldReceive('multicast')->andReturn(true);
        $this->app->instance(LineService::class, $mockLine);

        $staff = $this->createStaff();
        $activity = $this->createActivity();
        $student = $this->createStudent();

        Registration::create([
            'activity_id' => $activity->id,
            'user_id'     => $student->id,
            'status'      => 'approved',
        ]);

        $response = $this->actingAs($staff)->post(route('admin.activities.reminders.trigger', $activity), [
            'window' => '24h',
        ]);

        $response->assertRedirect(route('admin.activities.broadcast', $activity));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('activity_reminder_logs', [
            'activity_id'   => $activity->id,
            'reminder_type' => '24h',
        ]);

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $student->id,
            'type'    => 'reminder',
        ]);
    }
}
