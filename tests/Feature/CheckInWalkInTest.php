<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\User;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckInWalkInTest extends TestCase
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
        ], $attributes));
    }

    public function test_student_can_self_check_in_with_valid_qr()
    {
        $student = $this->createStudent();
        $activity = $this->createActivity();

        // Must be registered first
        Registration::create([
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'status' => 'approved'
        ]);

        $response = $this->actingAs($student)->post(route('checkin.store', $activity->qr_token));

        $response->assertOk();
        $response->assertViewIs('checkin.success');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $student->id,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_expired_qr_token_prevents_check_in()
    {
        $student = $this->createStudent();
        $activity = $this->createActivity([
            'qr_expires_at' => now()->subHour() // Expired 1 hour ago
        ]);

        $response = $this->actingAs($student)->post(route('checkin.store', $activity->qr_token));

        // Returns back with error
        $response->assertSessionHas('error');
        
        // Ensure not checked in
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $student->id,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_expired_qr_token_prevents_walk_in()
    {
        $activity = $this->createActivity([
            'qr_expires_at' => now()->subHour()
        ]);

        $response = $this->post(route('checkin.walkin.store', $activity->qr_token), [
            'student_id' => '65012345'
        ]);

        $response->assertSessionHas('error', 'QR Code หมดอายุแล้ว');
    }

    public function test_staff_can_manually_check_in_student()
    {
        $staff = $this->createStaff();
        $student = $this->createStudent();
        $activity = $this->createActivity();

        Registration::create([
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'status' => 'approved'
        ]);

        $response = $this->actingAs($staff)->post(route('admin.activities.manual-checkin', $activity->id), [
            'student_id' => $student->student_id
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'method' => 'manual',
            'verified_by' => $staff->id
        ]);
    }
}
