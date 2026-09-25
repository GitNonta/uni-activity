<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\User;
use App\Services\AutoApprovalService;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutoApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(): User
    {
        return User::factory()->create([
            'role' => 'student',
            'student_id' => '67' . fake()->numerify('########'),
        ]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email' => 'admin_' . Str::random(5) . '@pkru.ac.th',
        ]);
    }

    private function createActivity(array $attributes = []): Activity
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $creator = $this->createAdmin();

        return Activity::create(array_merge([
            'slug'                        => 'test-activity-' . Str::random(8),
            'title'                       => 'กิจกรรมทดสอบระบบ Smart Auto-Approval',
            'location'                    => 'หอประชุมใหญ่',
            'latitude'                    => 7.8938120,
            'longitude'                   => 98.3976540,
            'checkin_radius'              => 100,
            'activity_date'               => now()->format('Y-m-d'),
            'start_time'                  => '09:00',
            'end_time'                    => '12:00',
            'activity_hours'              => 3.0,
            'max_participants'            => 500,
            'register_open_at'            => now()->subDays(2),
            'register_close_at'           => now()->addDays(2),
            'checkin_open_at'             => now()->subHour(),
            'checkin_close_at'            => now()->addHours(3),
            'checkout_open_at'            => null,
            'checkout_close_at'           => null,
            'category_id'                 => $category->id,
            'scope'                       => 'university',
            'status'                      => 'open',
            'is_mandatory'                => false,
            'allow_walkin'                => true,
            'require_attendance_approval' => true,
            'require_face_scan'           => true,
            'created_by'                  => $creator->id,
            'qr_token'                    => Str::random(32),
        ], $attributes));
    }

    /**
     * ทดสอบ: Service สามารถประเมินผ่านเกณฑ์ เมื่อคะแนนใบหน้า >= 80% และ GPS <= 50m
     */
    public function test_auto_approval_service_can_evaluate_eligible_checkin(): void
    {
        $activity = $this->createActivity();
        $service = app(AutoApprovalService::class);

        $result = $service->evaluate($activity, [
            'face_match_passed' => true,
            'face_match_score'  => 85.5,
            'liveness_passed'   => true,
            'distance_meters'   => 25.0,
            'is_mock_location'  => false,
            'is_suspicious'     => false,
            'is_shared_device'  => false,
        ]);

        $this->assertTrue($result['approved']);
        $this->assertEmpty($result['reasons']);
    }

    /**
     * ทดสอบ: Service ไม่อนุมัติอัตโนมัติเมื่อคะแนนใบหน้าต่ำกว่าเกณฑ์ (< 80%)
     */
    public function test_auto_approval_service_cannot_approve_when_face_score_below_threshold(): void
    {
        $activity = $this->createActivity();
        $service = app(AutoApprovalService::class);

        $result = $service->evaluate($activity, [
            'face_match_passed' => true,
            'face_match_score'  => 65.0, // ต่ำกว่า 80%
            'liveness_passed'   => true,
            'distance_meters'   => 20.0,
            'is_mock_location'  => false,
            'is_suspicious'     => false,
        ]);

        $this->assertFalse($result['approved']);
        $this->assertNotEmpty($result['reasons']);
        $this->assertStringContainsString('คะแนนความคล้ายคลึงใบหน้าต่ำกว่าเกณฑ์', $result['reasons'][0]);
    }

    /**
     * ทดสอบ: Service ไม่อนุมัติอัตโนมัติเมื่อระยะทาง GPS ไกลเกินเกณฑ์ (> 50m)
     */
    public function test_auto_approval_service_cannot_approve_when_gps_distance_exceeds_threshold(): void
    {
        $activity = $this->createActivity();
        $service = app(AutoApprovalService::class);

        $result = $service->evaluate($activity, [
            'face_match_passed' => true,
            'face_match_score'  => 92.0,
            'liveness_passed'   => true,
            'distance_meters'   => 85.0, // เกิน 50m
            'is_mock_location'  => false,
            'is_suspicious'     => false,
        ]);

        $this->assertFalse($result['approved']);
        $this->assertNotEmpty($result['reasons']);
        $this->assertStringContainsString('ระยะห่าง GPS ไกลกว่าเกณฑ์', $result['reasons'][0]);
    }

    /**
     * ทดสอบ: Service ไม่อนุมัติอัตโนมัติเมื่อตรวจไม่ผ่านบุคคลจริง (Liveness failed)
     */
    public function test_auto_approval_service_cannot_approve_when_liveness_fails(): void
    {
        $activity = $this->createActivity();
        $service = app(AutoApprovalService::class);

        $result = $service->evaluate($activity, [
            'face_match_passed' => true,
            'face_match_score'  => 95.0,
            'liveness_passed'   => false, // หลอกภาพถ่าย
            'distance_meters'   => 15.0,
            'is_mock_location'  => false,
            'is_suspicious'     => false,
        ]);

        $this->assertFalse($result['approved']);
        $this->assertNotEmpty($result['reasons']);
        $this->assertStringContainsString('Liveness', $result['reasons'][0]);
    }

    /**
     * ทดสอบ: Service สามารถบันทึกอนุมัติ มอบชั่วโมง และส่ง Notification ได้สมบูรณ์
     */
    public function test_auto_approval_service_can_execute_approval_atomically(): void
    {
        Event::fake();

        $student = $this->createStudent();
        $activity = $this->createActivity(['activity_hours' => 4.0]);

        $registration = Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $attendance = Attendance::create([
            'user_id'          => $student->id,
            'activity_id'      => $activity->id,
            'status'           => 'pending',
            'method'           => 'qr_scan',
            'face_match_score' => 89.0,
            'distance_meters'  => 22.0,
        ]);

        $service = app(AutoApprovalService::class);
        $service->executeApproval($attendance, $registration);

        $this->assertDatabaseHas('attendances', [
            'id'          => $attendance->id,
            'status'      => 'approved',
            'is_verified' => 1,
        ]);

        $this->assertDatabaseHas('registrations', [
            'id'     => $registration->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $student->id,
            'type'    => 'attendance_approved',
        ]);
    }

    /**
     * ทดสอบ: แอดมินสามารถปรับเปลี่ยนเกณฑ์การอนุมัติอัตโนมัติผ่านหน้า Admin Settings ได้
     */
    public function test_admin_can_update_auto_approval_settings(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'settings_section'                   => 'auto_approval',
            'auto_approve_enabled'               => '1',
            'auto_approve_min_face_score'        => '85',
            'auto_approve_max_distance'          => '35',
            'auto_approve_require_liveness'      => '1',
            'auto_approve_prevent_shared_device' => '1',
        ]);

        $response->assertSessionHas('success');

        $this->assertSame('85', Setting::get('auto_approve_min_face_score'));
        $this->assertSame('35', Setting::get('auto_approve_max_distance'));
        $this->assertSame('1', Setting::get('auto_approve_enabled'));
    }

    /**
     * ทดสอบ: CheckInService อนุมัติชั่วโมงกิจกรรมทันที (End-to-End) สำหรับกิจกรรมไม่มี Checkout เมื่อผ่านเกณฑ์ครบ
     */
    public function test_checkin_service_can_auto_approve_single_step_activity_end_to_end(): void
    {
        Event::fake();

        $student = $this->createStudent();
        // กิจกรรมไม่มี qr_checkout_token (Single step)
        $activity = $this->createActivity([
            'qr_checkout_token'           => null,
            'require_attendance_approval' => true,
            'activity_hours'              => 2.5,
        ]);

        $registration = Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        /** @var CheckInService $service */
        $service = app(CheckInService::class);

        $result = $service->processCheckIn(
            $activity->qr_token,
            $student,
            'qr_scan',
            7.8938120, // พิกัดเดียวกับ activity (ระยะ 0 ม.)
            98.3976540,
            [
                'face_match_passed' => true,
                'face_match_score'  => 88.0,
                'liveness_passed'   => true,
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['auto_approved']);
        $this->assertEquals('approved', $result['status']);

        // ตรวจสอบฐานข้อมูลว่า attendance เป็น approved และ registration เป็น completed
        $this->assertDatabaseHas('attendances', [
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
            'is_verified' => 1,
        ]);

        $this->assertDatabaseHas('registrations', [
            'id'     => $registration->id,
            'status' => 'completed',
        ]);
    }

    /**
     * ทดสอบ: CheckInService ส่งเข้าคิว pending เมื่อคะแนนใบหน้าต่ำกว่าเกณฑ์ สำหรับกิจกรรมไม่มี Checkout
     */
    public function test_checkin_service_holds_pending_when_face_score_below_threshold_end_to_end(): void
    {
        Event::fake();

        $student = $this->createStudent();
        $activity = $this->createActivity([
            'qr_checkout_token'           => null,
            'require_attendance_approval' => true,
            'activity_hours'              => 2.5,
        ]);

        $registration = Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        /** @var CheckInService $service */
        $service = app(CheckInService::class);

        $result = $service->processCheckIn(
            $activity->qr_token,
            $student,
            'qr_scan',
            7.8938120,
            98.3976540,
            [
                'face_match_passed' => true,
                'face_match_score'  => 62.0, // ต่ำกว่า 80%
                'liveness_passed'   => true,
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertFalse($result['auto_approved']);
        $this->assertEquals('checked_in', $result['status']);

        // ตรวจสอบฐานข้อมูลว่า attendance ค้างที่ pending และ registration ยังไม่ completed
        $this->assertDatabaseHas('attendances', [
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'pending',
        ]);

        $this->assertDatabaseHas('registrations', [
            'id'     => $registration->id,
            'status' => 'approved',
        ]);
    }
}

