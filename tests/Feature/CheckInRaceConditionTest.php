<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckInRaceConditionTest extends TestCase
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
            'title'             => 'Race Condition Test Activity',
            'description'       => 'Test Description',
            'location'          => 'Main Hall',
            'activity_date'     => now()->toDateString(),
            'start_time'        => '08:00',
            'end_time'          => '17:00',
            'activity_hours'    => 2,
            'max_participants'  => 50,
            'register_open_at'  => now()->subDays(2),
            'register_close_at' => now()->addDays(2),
            'checkin_open_at'   => now()->subHour(),
            'checkin_close_at'  => now()->addHours(2),
            'category_id'       => $category->id,
            'created_by'        => $creator->id,
            'scope'             => 'university',
            'status'            => 'open',
            'require_face_scan' => false,
            'qr_token'          => 'race-qr-' . Str::random(10),
        ], $attributes));
    }

    public function test_concurrent_checkin_requests_only_create_single_attendance_record(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivity();

        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        // Request 1: Initial check-in
        $response1 = $this->actingAs($student)->post(route('checkin.store', $activity->qr_token));
        $response1->assertOk();
        $response1->assertViewIs('checkin.success');

        // Request 2: Immediate duplicate check-in (simulating parallel request / double click)
        $response2 = $this->actingAs($student)->post(route('checkin.store', $activity->qr_token));
        $response2->assertSessionHas('error');

        // Assert that exactly 1 attendance record exists in the database
        $this->assertEquals(1, Attendance::where('user_id', $student->id)->where('activity_id', $activity->id)->count());
    }

    public function test_concurrent_walkin_requests_only_create_single_attendance_record(): void
    {
        $staff = $this->createStaff();
        $student = $this->createStudent();
        $activity = $this->createActivity();

        // Walk-in Request 1
        $response1 = $this->actingAs($staff)->post(route('checkin.walkin.store', $activity->qr_token), [
            'student_id' => $student->student_id,
        ]);
        $response1->assertSessionHas('success');

        // Walk-in Request 2: Immediate second submit
        $response2 = $this->actingAs($staff)->post(route('checkin.walkin.store', $activity->qr_token), [
            'student_id' => $student->student_id,
        ]);
        $response2->assertSessionHas('error');

        // Assert that exactly 1 attendance record was created
        $this->assertEquals(1, Attendance::where('user_id', $student->id)->where('activity_id', $activity->id)->count());
    }

    public function test_checkin_service_handles_parallel_calls_safely(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivity();

        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        /** @var CheckInService $service */
        $service = app(CheckInService::class);

        // Call 1
        $result1 = $service->processCheckIn($activity->qr_token, $student, 'qr_scan');
        $this->assertTrue($result1['success']);
        $this->assertEquals('checked_in', $result1['status']);

        // Call 2
        $result2 = $service->processCheckIn($activity->qr_token, $student, 'qr_scan');
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('คุณเช็คอินไปแล้ว', $result2['message']);

        // Verify total attendance records
        $this->assertEquals(1, Attendance::where('user_id', $student->id)->where('activity_id', $activity->id)->count());
    }
}
