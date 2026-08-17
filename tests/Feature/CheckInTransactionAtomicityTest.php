<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckInTransactionAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(): User
    {
        return User::factory()->create([
            'role'      => 'student',
            'is_active' => true,
        ]);
    }

    private function createStaff(): User
    {
        return User::factory()->create([
            'role'      => 'staff',
            'email'     => 'staff_' . Str::random(6) . '@pkru.ac.th',
            'is_active' => true,
        ]);
    }

    private function createActivity(array $attributes = []): Activity
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
            'title'                      => 'Transaction Test Activity',
            'description'                => 'Test Description',
            'location'                   => 'Auditorium',
            'activity_date'              => now()->toDateString(),
            'start_time'                 => '08:00',
            'end_time'                   => '17:00',
            'activity_hours'             => 3,
            'max_participants'           => 100,
            'register_open_at'           => now()->subDays(2),
            'register_close_at'          => now()->addDays(2),
            'checkin_open_at'            => now()->subHour(),
            'checkin_close_at'           => now()->addHours(2),
            'category_id'                => $category->id,
            'created_by'                 => $creator->id,
            'scope'                      => 'university',
            'status'                     => 'open',
            'require_face_scan'          => false,
            'require_attendance_approval'=> false, // Auto-approve on exit
            'qr_token'                   => 'tx-qr-' . Str::random(10),
            'qr_checkout_token'          => 'tx-chk-' . Str::random(10),
        ], $attributes));
    }

    public function test_checkin_and_checkout_commit_atomically_with_all_related_records(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivity();

        $registration = Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        /** @var CheckInService $service */
        $service = app(CheckInService::class);

        // 1. Process Check-in (Entry) atomically with selfie metadata
        $entryResult = $service->processCheckIn(
            $activity->qr_token,
            $student,
            'qr_scan',
            7.8901,
            98.3901,
            [
                'selfie_photo_path' => 'selfies/test.jpg',
                'face_match_score'  => 95.5,
                'face_match_passed' => true,
                'liveness_passed'   => true,
            ]
        );

        $this->assertTrue($entryResult['success']);
        $this->assertEquals('checked_in', $entryResult['status']);

        // Assert database state after check-in
        $attendance = Attendance::where('user_id', $student->id)->where('activity_id', $activity->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals('pending', $attendance->status);
        $this->assertEquals('selfies/test.jpg', $attendance->selfie_photo_path);
        $this->assertEquals(95.5, (float) $attendance->face_match_score);
        $this->assertTrue((bool) $attendance->face_match_passed);

        // 2. Process Checkout (Exit) atomically
        $exitResult = $service->processCheckIn(
            $activity->qr_checkout_token,
            $student,
            'qr_scan',
            7.8901,
            98.3901,
        );

        $this->assertTrue($exitResult['success']);
        $this->assertEquals('approved', $exitResult['status']);

        // Assert that Attendance is approved and Registration marked completed atomically
        $attendance->refresh();
        $this->assertEquals('approved', $attendance->status);
        $this->assertNotNull($attendance->checked_out_at);

        $registration->refresh();
        $this->assertEquals('completed', $registration->status);
    }

    public function test_admin_attendance_approval_commits_atomically_with_registration_completion_and_notification(): void
    {
        $staff = $this->createStaff();
        $student = $this->createStudent();
        $activity = $this->createActivity(['created_by' => $staff->id]);

        $registration = Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $attendance = Attendance::create([
            'user_id'       => $student->id,
            'activity_id'   => $activity->id,
            'status'        => 'pending',
            'checked_in_at' => now(),
            'method'        => 'qr_scan',
        ]);

        $response = $this->actingAs($staff)->post(route('admin.attendances.approve', $attendance->id));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify Attendance updated
        $attendance->refresh();
        $this->assertEquals('approved', $attendance->status);
        $this->assertTrue((bool) $attendance->is_verified);
        $this->assertEquals($staff->id, $attendance->verified_by);

        // Verify Registration marked completed
        $registration->refresh();
        $this->assertEquals('completed', $registration->status);

        // Verify Notification created
        $this->assertTrue(Notification::where('user_id', $student->id)->where('type', 'attendance_approved')->exists());
    }
}
