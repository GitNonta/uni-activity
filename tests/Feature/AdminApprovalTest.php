<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent()
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function createStaff()
    {
        return User::factory()->create(['role' => 'staff', 'email' => fake()->unique()->safeEmail()]);
    }

    private function createActivity()
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $creator = User::firstOrCreate(
            ['email' => 'admin@pkru.ac.th'],
            ['role' => 'admin', 'full_name' => 'Admin', 'password' => bcrypt('password')]
        );

        return Activity::create([
            'title' => 'Test Activity',
            'location' => 'Building 1',
            'activity_date' => now()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'activity_hours' => 2,
            'max_participants' => 10,
            'register_open_at' => now()->subDay(),
            'register_close_at' => now()->addDays(4),
            'checkin_open_at' => now()->subHour(),
            'checkin_close_at' => now()->addHours(2),
            'category_id' => $category->id,
            'scope' => 'university',
            'status' => 'open',
            'is_mandatory' => false,
            'created_by' => $creator->id,
            'qr_token' => Str::random(10),
        ]);
    }

    public function test_staff_can_quick_approve_attendance()
    {
        $staff = $this->createStaff();
        $student = $this->createStudent();
        $activity = $this->createActivity();

        $attendance = Attendance::create([
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'method' => 'manual',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($staff)->postJson(route('admin.quick.approve'), [
            'type' => 'attendance',
            'id' => $attendance->id
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'approved',
            'is_verified' => 1,
            'verified_by' => $staff->id
        ]);
    }

    public function test_student_cannot_access_quick_approve()
    {
        $student = $this->createStudent();
        $activity = $this->createActivity();

        $attendance = Attendance::create([
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'method' => 'manual',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($student)->postJson(route('admin.quick.approve'), [
            'type' => 'attendance',
            'id' => $attendance->id
        ]);

        // Returns 403 Forbidden because 'role:staff' middleware restricts access
        $response->assertForbidden();
        
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'pending'
        ]);
    }
}
