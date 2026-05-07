<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\User;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent()
    {
        return User::factory()->create(['role' => 'student']);
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
            'description' => 'Description',
            'location' => 'Building 1',
            'activity_date' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'activity_hours' => 2,
            'max_participants' => 10,
            'register_open_at' => now()->subDay(),
            'register_close_at' => now()->addDays(4),
            'checkin_open_at' => now()->addDays(5)->setTime(9, 30),
            'checkin_close_at' => now()->addDays(5)->setTime(12, 30),
            'category_id' => $category->id,
            'scope' => 'university',
            'status' => 'upcoming',
            'is_mandatory' => false,
            'created_by' => $creator->id,
            'qr_token' => Str::random(10),
        ], $attributes));
    }

    public function test_student_can_register_for_an_open_activity()
    {
        $student = $this->createStudent();
        $activity = $this->createActivity(['status' => 'open']);

        $response = $this->actingAs($student)->post(route('activities.register', $activity->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('registrations', [
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'status' => 'approved',
        ]);
    }

    public function test_student_placed_on_waitlist_if_activity_is_full()
    {
        $student = $this->createStudent();
        $activity = $this->createActivity(['status' => 'open', 'max_participants' => 1]);

        // Fill the activity
        $otherStudent = $this->createStudent();
        Registration::create([
            'user_id' => $otherStudent->id,
            'activity_id' => $activity->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($student)->post(route('activities.register', $activity->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('registrations', [
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'status' => 'waitlisted',
        ]);
    }

    public function test_student_cannot_register_if_time_overlaps()
    {
        $student = $this->createStudent();
        
        $activity1 = $this->createActivity([
            'status' => 'open',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'activity_date' => now()->addDays(5)->format('Y-m-d')
        ]);

        $activity2 = $this->createActivity([
            'status' => 'open',
            'start_time' => '11:00',
            'end_time' => '13:00',
            'activity_date' => now()->addDays(5)->format('Y-m-d')
        ]);

        // Register for first activity
        $this->actingAs($student)->post(route('activities.register', $activity1->id));

        // Attempt to register for second overlapping activity
        $response = $this->actingAs($student)->post(route('activities.register', $activity2->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('registrations', [
            'user_id' => $student->id,
            'activity_id' => $activity2->id,
        ]);
    }
}
