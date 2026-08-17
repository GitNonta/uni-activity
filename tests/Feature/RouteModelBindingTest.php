<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\AdminAuditLog;
use App\Models\Announcement;
use App\Models\JobComment;
use App\Models\JobListing;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteModelBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_show_resolves_route_model_binding(): void
    {
        $category = ActivityCategory::create([
            'name'           => 'Academic',
            'required_hours' => 10,
            'color'          => '#3B82F6',
        ]);

        $activity = Activity::create([
            'title'             => 'AI Workshop',
            'description'       => 'Test AI Workshop',
            'category_id'       => $category->id,
            'activity_date'     => now()->addDays(5)->toDateString(),
            'start_time'        => '09:00',
            'end_time'          => '12:00',
            'location'          => 'Building 1',
            'activity_hours'    => 3.0,
            'max_participants'  => 50,
            'status'            => 'open',
            'scope'             => 'university',
            'register_open_at'  => now()->subDays(1),
            'register_close_at' => now()->addDays(4),
            'checkin_open_at'   => now()->addDays(5)->setHour(8)->setMinute(30),
            'checkin_close_at'  => now()->addDays(5)->setHour(9)->setMinute(30),
        ]);

        $response = $this->get(route('activities.show', $activity));
        $response->assertStatus(200);
        $response->assertSee('AI Workshop');
    }

    public function test_registration_store_and_destroy_with_route_model_binding(): void
    {
        $student = User::factory()->create([
            'role'       => 'student',
            'student_id' => '65111111',
        ]);

        $category = ActivityCategory::create([
            'name'           => 'Academic',
            'required_hours' => 10,
            'color'          => '#3B82F6',
        ]);

        $activity = Activity::create([
            'title'             => 'Tech Seminar',
            'description'       => 'Seminar details',
            'category_id'       => $category->id,
            'activity_date'     => now()->addDays(10)->toDateString(),
            'start_time'        => '13:00',
            'end_time'          => '16:00',
            'location'          => 'Hall A',
            'activity_hours'    => 3.0,
            'max_participants'  => 100,
            'status'            => 'open',
            'scope'             => 'university',
            'register_open_at'  => now()->subDays(2),
            'register_close_at' => now()->addDays(8),
            'checkin_open_at'   => now()->addDays(10)->setHour(12)->setMinute(30),
            'checkin_close_at'  => now()->addDays(10)->setHour(13)->setMinute(30),
        ]);

        // Register using Route Model Binding
        $response = $this->actingAs($student)->post(route('activities.register', $activity));
        $response->assertRedirect();
        $this->assertDatabaseHas('registrations', [
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $registration = Registration::where('user_id', $student->id)->where('activity_id', $activity->id)->first();

        // Cancel registration using Route Model Binding
        $cancelResponse = $this->actingAs($student)->delete(route('registrations.destroy', $registration));
        $cancelResponse->assertRedirect();
        $this->assertDatabaseHas('registrations', [
            'id'     => $registration->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_announcement_and_job_show_route_model_binding(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $announcement = Announcement::create([
            'title'      => 'Campus Holiday',
            'content'    => 'Campus closed on Friday',
            'type'       => 'info',
            'is_active'  => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->get(route('announcements.show', $announcement));
        $response->assertStatus(200);
        $response->assertSee('Campus Holiday');

        $job = JobListing::create([
            'title'        => 'Library Assistant',
            'position'     => 'Assistant',
            'job_type'     => 'parttime',
            'quota'        => 2,
            'location'     => 'Central Library',
            'start_date'   => now()->addDays(3)->toDateString(),
            'gender'       => 'any',
            'status'       => 'open',
            'created_by'   => $admin->id,
        ]);

        $jobResponse = $this->get(route('jobs.show', $job));
        $jobResponse->assertStatus(200);
        $jobResponse->assertSee('Library Assistant');
    }

    public function test_admin_category_and_user_route_model_binding(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $category = ActivityCategory::create([
            'name'           => 'Sports',
            'required_hours' => 5,
            'color'          => '#10B981',
        ]);

        $updateResponse = $this->actingAs($admin)->patch(route('admin.categories.update', $category), [
            'name'           => 'Sports & Recreation',
            'required_hours' => 8,
            'color'          => '#10B981',
        ]);

        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('activity_categories', [
            'id'             => $category->id,
            'name'           => 'Sports & Recreation',
            'required_hours' => 8,
        ]);

        $student = User::factory()->create([
            'role'       => 'student',
            'student_id' => '65222222',
            'full_name'  => 'Somchai Dee',
        ]);

        $userUpdate = $this->actingAs($admin)->patch(route('admin.users.update', $student), [
            'full_name'  => 'Somchai Dee Updated',
            'student_id' => '65222222',
            'email'      => 's65222222@pkru.ac.th',
        ]);

        $userUpdate->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id'        => $student->id,
            'full_name' => 'Somchai Dee Updated',
        ]);
    }
}
