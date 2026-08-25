<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QR code system must be closed once the activity is complete:
 * - check-in / check-out / walk-in via QR are rejected server-side
 * - admin cannot regenerate QR codes
 * - admin show page displays a "closed" banner instead of QR panel
 */
class QrClosedWhenActivityCompletedTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent()
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function createStaff()
    {
        return User::factory()->create(['role' => 'staff', 'email' => 'staff@pkru.ac.th']);
    }

    private function createActivity($attributes = [])
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $creator = User::firstOrCreate(
            ['email' => 'admin@pkru.ac.th'],
            ['role' => 'admin', 'full_name' => 'Admin', 'password' => bcrypt('password')]
        );

        return Activity::create(array_merge([
            'title' => 'Test Activity',
            'location' => 'Building 1',
            'activity_date' => now()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'activity_hours' => 2,
            'max_participants' => 10,
            'register_open_at' => now()->subDays(2),
            'register_close_at' => now()->subDay(),
            'checkin_open_at' => now()->subHours(3),
            'checkin_close_at' => now()->subHour(),   // เลยเวลาเช็คอินแล้ว = completed
            'category_id' => $category->id,
            'scope' => 'university',
            'status' => 'open',
            'is_mandatory' => false,
            'require_face_scan' => false,
            'created_by' => $creator->id,
            'qr_token' => Str::random(10),
        ], $attributes));
    }

    public function test_completed_status_closes_checkin_qr()
    {
        $activity = $this->createActivity(['status' => 'done']);

        $this->assertTrue($activity->isCompleted());
        $this->assertTrue($activity->isCheckInQrClosed());
        $this->assertTrue($activity->isCheckoutQrClosed());
    }

    public function test_past_checkin_close_time_marks_activity_completed()
    {
        // status ยังเป็น open แต่เลยเวลาปิดเช็คอิน → ถือว่าจบ (fallback กัน cron ไม่ update)
        $activity = $this->createActivity();

        $this->assertTrue($activity->isCompleted());
        $this->assertTrue($activity->isCheckInQrClosed());
    }

    public function test_active_activity_keeps_qr_open()
    {
        $activity = $this->createActivity([
            'status' => 'ongoing',
            'checkin_open_at' => now()->subHour(),
            'checkin_close_at' => now()->addHours(2),
            'checkout_open_at' => now()->addHour(),
            'checkout_close_at' => now()->addHours(3),
        ]);

        $this->assertFalse($activity->isCompleted());
        $this->assertFalse($activity->isCheckInQrClosed());
        $this->assertFalse($activity->isCheckoutQrClosed());
    }

    public function test_completed_activity_rejects_qr_check_in_page()
    {
        $student = $this->createStudent();
        $activity = $this->createActivity(['status' => 'done']);

        $this->actingAs($student)
            ->get(route('checkin.show', $activity->qr_token))
            ->assertForbidden();
    }

    public function test_completed_activity_rejects_qr_check_in_submission()
    {
        $student = $this->createStudent();
        $activity = $this->createActivity(['status' => 'done']);

        Registration::create([
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'status' => 'approved'
        ]);

        $response = $this->actingAs($student)
            ->post(route('checkin.store', $activity->qr_token));

        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $student->id,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_completed_activity_rejects_walk_in_check_in()
    {
        $staff = $this->createStaff();
        $activity = $this->createActivity([
            'allow_walkin' => true,
            'checkin_close_at' => now()->addHour(),   // active ยังไม่ done
        ]);
        $activity->update(['status' => 'done']);      // แต่ปิดกิจกรรมแล้ว

        $response = $this->actingAs($staff)->post(
            route('checkin.walkin.store', $activity->qr_token),
            ['student_id' => '65012345']
        );

        $response->assertSessionHas('error');
    }

    public function test_admin_cannot_regenerate_qr_for_completed_activity()
    {
        $staff = $this->createStaff();
        $activity = $this->createActivity(['status' => 'done', 'created_by' => $staff->id]);
        $oldToken = $activity->qr_token;

        $response = $this->actingAs($staff)
            ->post(route('admin.activities.regenerate-qr', $activity->id), []);

        $response->assertSessionHas('error');

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'qr_token' => $oldToken,   // token ไม่ถูกเปลี่ยน
        ]);
    }

    public function test_admin_sees_closed_banner_on_completed_activity_page()
    {
        $staff = $this->createStaff();
        $activity = $this->createActivity(['status' => 'done', 'created_by' => $staff->id]);

        $response = $this->actingAs($staff)
            ->get(route('admin.activities.show', $activity->id));

        $response->assertOk();
        $response->assertSee('ระบบ QR Code ทั้งหมด');
        $response->assertSee('ถูกปิดใช้งาน');
        $response->assertDontSee('สร้าง QR ใหม่');
    }

    public function test_admin_still_sees_qr_panel_for_active_activity()
    {
        $staff = $this->createStaff();
        $activity = $this->createActivity([
            'created_by' => $staff->id,
            'status' => 'ongoing',
            'checkin_open_at' => now()->subHour(),
            'checkin_close_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($staff)
            ->get(route('admin.activities.show', $activity->id));

        $response->assertOk();
        $response->assertSee('สร้าง QR ใหม่');
        $response->assertDontSee('ถูกปิดใช้งาน');
    }
}
