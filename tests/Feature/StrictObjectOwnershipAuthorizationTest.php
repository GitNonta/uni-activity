<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\JobComment;
use App\Models\JobListing;
use App\Models\Message;
use App\Models\Registration;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrictObjectOwnershipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staffA;
    private User $staffB;
    private User $studentA;
    private User $studentB;
    private ActivityCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staffA = User::factory()->create(['role' => 'staff']);
        $this->staffB = User::factory()->create(['role' => 'staff']);
        $this->studentA = User::factory()->create(['role' => 'student', 'student_id' => '65000001']);
        $this->studentB = User::factory()->create(['role' => 'student', 'student_id' => '65000002']);

        $this->category = ActivityCategory::create([
            'name'           => 'Leadership',
            'required_hours' => 10,
            'color'          => '#3B82F6',
        ]);
    }

    public function test_staff_cannot_update_or_delete_other_staff_job(): void
    {
        $jobStaffA = JobListing::create([
            'title'        => 'Staff A Job',
            'position'     => 'Assistant',
            'job_type'     => 'parttime',
            'quota'        => 2,
            'location'     => 'Room 101',
            'start_date'   => now()->addDays(5)->toDateString(),
            'gender'       => 'any',
            'status'       => 'open',
            'created_by'   => $this->staffA->id,
        ]);

        // Staff B attempts to update Staff A's job -> 403 Forbidden
        $response = $this->actingAs($this->staffB)->put(route('admin.jobs.update', $jobStaffA), [
            'title'        => 'Hacked Job Title',
            'job_type'     => 'parttime',
            'position'     => 'Assistant',
            'quota'        => 2,
            'location'     => 'Room 101',
            'start_date'   => now()->addDays(5)->toDateString(),
            'gender'       => 'any',
        ]);
        $response->assertStatus(403);

        // Staff B attempts to delete Staff A's job -> 403 Forbidden
        $deleteResponse = $this->actingAs($this->staffB)->delete(route('admin.jobs.destroy', $jobStaffA));
        $deleteResponse->assertStatus(403);

        // Staff A can update own job -> 302 Redirect
        $ownUpdate = $this->actingAs($this->staffA)->put(route('admin.jobs.update', $jobStaffA), [
            'title'        => 'Updated Staff A Job',
            'job_type'     => 'parttime',
            'position'     => 'Assistant',
            'quota'        => 2,
            'location'     => 'Room 101',
            'start_date'   => now()->addDays(5)->toDateString(),
            'gender'       => 'any',
        ]);
        $ownUpdate->assertRedirect();
        $this->assertDatabaseHas('job_listings', ['id' => $jobStaffA->id, 'title' => 'Updated Staff A Job']);
    }

    public function test_staff_cannot_update_or_delete_other_staff_announcement(): void
    {
        $announcementA = Announcement::create([
            'title'      => 'Announcement by Staff A',
            'content'    => 'Content text',
            'type'       => 'info',
            'is_active'  => true,
            'created_by' => $this->staffA->id,
        ]);

        // Staff B attempts to update Staff A's announcement -> 403 Forbidden
        $response = $this->actingAs($this->staffB)->put(route('admin.announcements.update', $announcementA), [
            'title'   => 'Hacked Announcement',
            'content' => 'Hacked content',
            'type'    => 'warning',
        ]);
        $response->assertStatus(403);

        // Staff B attempts to toggle active -> 403 Forbidden
        $toggleResponse = $this->actingAs($this->staffB)->patch(route('admin.announcements.toggle-active', $announcementA));
        $toggleResponse->assertStatus(403);

        // Admin can update any announcement
        $adminUpdate = $this->actingAs($this->admin)->put(route('admin.announcements.update', $announcementA), [
            'title'   => 'Admin Modified Announcement',
            'content' => 'Approved content',
            'type'    => 'info',
        ]);
        $adminUpdate->assertRedirect();
    }

    public function test_staff_cannot_manage_attendances_for_other_staff_activities(): void
    {
        $activityA = Activity::create([
            'title'             => 'Activity Staff A',
            'description'       => 'Description',
            'category_id'       => $this->category->id,
            'created_by'        => $this->staffA->id,
            'activity_date'     => now()->addDays(2)->toDateString(),
            'start_time'        => '09:00',
            'end_time'          => '12:00',
            'location'          => 'Hall 1',
            'activity_hours'    => 3.0,
            'max_participants'  => 50,
            'status'            => 'open',
            'scope'             => 'university',
            'register_open_at'  => now()->subDays(1),
            'register_close_at' => now()->addDays(1),
            'checkin_open_at'   => now()->addDays(2)->setHour(8)->setMinute(30),
            'checkin_close_at'  => now()->addDays(2)->setHour(9)->setMinute(30),
        ]);

        $attendance = Attendance::create([
            'user_id'       => $this->studentA->id,
            'activity_id'   => $activityA->id,
            'status'        => 'pending',
            'checked_in_at' => now(),
        ]);

        // Staff B attempts to update attendance in Staff A's activity -> 403 Forbidden
        $response = $this->actingAs($this->staffB)->patch(route('admin.students.attendances.update', [
            'student' => $this->studentA,
            'aid'     => $attendance->id,
        ]), [
            'status'        => 'approved',
            'checked_in_at' => now()->toDateTimeString(),
        ]);
        $response->assertStatus(403);

        // Staff B attempts to delete attendance in Staff A's activity -> 403 Forbidden
        $delResponse = $this->actingAs($this->staffB)->delete(route('admin.students.attendances.delete', [
            'student' => $this->studentA,
            'aid'     => $attendance->id,
        ]));
        $delResponse->assertStatus(403);

        // Staff A can approve attendance in own activity
        $staffAResponse = $this->actingAs($this->staffA)->patch(route('admin.students.attendances.update', [
            'student' => $this->studentA,
            'aid'     => $attendance->id,
        ]), [
            'status'        => 'approved',
            'checked_in_at' => now()->toDateTimeString(),
        ]);
        $staffAResponse->assertRedirect();
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'status' => 'approved']);
    }

    public function test_student_cannot_delete_other_student_registration(): void
    {
        $activity = Activity::create([
            'title'             => 'Campus Workshop',
            'description'       => 'Workshop desc',
            'category_id'       => $this->category->id,
            'created_by'        => $this->staffA->id,
            'activity_date'     => now()->addDays(3)->toDateString(),
            'start_time'        => '09:00',
            'end_time'          => '12:00',
            'location'          => 'Auditorium',
            'activity_hours'    => 3.0,
            'max_participants'  => 50,
            'status'            => 'open',
            'scope'             => 'university',
            'register_open_at'  => now()->subDays(1),
            'register_close_at' => now()->addDays(2),
            'checkin_open_at'   => now()->addDays(3)->setHour(8)->setMinute(30),
            'checkin_close_at'  => now()->addDays(3)->setHour(9)->setMinute(30),
        ]);

        $regStudentA = Registration::create([
            'user_id'       => $this->studentA->id,
            'activity_id'   => $activity->id,
            'status'        => 'approved',
            'registered_at' => now(),
        ]);

        // Student B attempts to cancel Student A's registration -> 403 Forbidden
        $response = $this->actingAs($this->studentB)->delete(route('registrations.destroy', $regStudentA));
        $response->assertStatus(403);

        // Student A can cancel own registration
        $ownResponse = $this->actingAs($this->studentA)->delete(route('registrations.destroy', $regStudentA));
        $ownResponse->assertRedirect();
        $this->assertDatabaseHas('registrations', ['id' => $regStudentA->id, 'status' => 'cancelled']);
    }

    public function test_student_cannot_delete_other_student_job_comment(): void
    {
        $job = JobListing::create([
            'title'        => 'Library Job',
            'position'     => 'Assistant',
            'job_type'     => 'parttime',
            'quota'        => 2,
            'location'     => 'Library',
            'start_date'   => now()->addDays(5)->toDateString(),
            'gender'       => 'any',
            'status'       => 'open',
            'created_by'   => $this->staffA->id,
        ]);

        $commentA = JobComment::create([
            'job_listing_id' => $job->id,
            'user_id'        => $this->studentA->id,
            'body'           => 'Is this still open?',
        ]);

        // Student B attempts to delete Student A's comment -> 403 Forbidden
        $response = $this->actingAs($this->studentB)->delete(route('jobs.comment.delete', $commentA));
        $response->assertStatus(403);

        // Student A can delete own comment
        $ownResponse = $this->actingAs($this->studentA)->delete(route('jobs.comment.delete', $commentA));
        $ownResponse->assertRedirect();
        $this->assertDatabaseMissing('job_comments', ['id' => $commentA->id]);
    }
}
