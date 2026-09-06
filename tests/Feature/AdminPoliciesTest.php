<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(): ActivityCategory
    {
        return ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );
    }

    private function createActivity(User $creator, array $attributes = []): Activity
    {
        $category = $this->createCategory();

        return Activity::create(array_merge([
            'title'             => 'Policy Test Activity',
            'description'       => 'Test Description',
            'location'          => 'Auditorium',
            'activity_date'     => now()->addDays(2)->format('Y-m-d'),
            'start_time'        => '09:00',
            'end_time'          => '12:00',
            'activity_hours'    => 3,
            'max_participants'  => 25,
            'register_open_at'  => now()->subDay(),
            'register_close_at' => now()->addDays(1),
            'checkin_open_at'   => now()->addDays(2)->setTime(8, 30),
            'checkin_close_at'  => now()->addDays(2)->setTime(12, 30),
            'category_id'       => $category->id,
            'scope'             => 'university',
            'status'            => 'open',
            'is_mandatory'      => false,
            'created_by'        => $creator->id,
            'qr_token'          => Str::random(10),
            'qr_checkout_token' => Str::random(10),
        ], $attributes));
    }

    public function test_staff_can_manage_own_activity_but_cannot_manage_other_staff_activity(): void
    {
        $staffA = User::factory()->create(['role' => 'staff']);
        $staffB = User::factory()->create(['role' => 'staff']);

        $activityA = $this->createActivity($staffA);
        $activityB = $this->createActivity($staffB);

        // Staff A can view own activity
        $response = $this->actingAs($staffA)->get(route('admin.activities.show', $activityA->id));
        $response->assertOk();

        // Staff A cannot view Staff B's activity (403 Forbidden via ActivityPolicy)
        $response = $this->actingAs($staffA)->get(route('admin.activities.show', $activityB->id));
        $response->assertForbidden();

        // Staff A can view edit page of own activity
        $response = $this->actingAs($staffA)->get(route('admin.activities.edit', $activityA->id));
        $response->assertOk();

        // Staff A cannot edit Staff B's activity (403 Forbidden)
        $response = $this->actingAs($staffA)->get(route('admin.activities.edit', $activityB->id));
        $response->assertForbidden();

        // Staff A cannot delete Staff B's activity
        $response = $this->actingAs($staffA)->delete(route('admin.activities.destroy', $activityB->id));
        $response->assertForbidden();
    }

    public function test_admin_can_manage_any_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $activity = $this->createActivity($staff);

        // Admin can view any activity
        $response = $this->actingAs($admin)->get(route('admin.activities.show', $activity->id));
        $response->assertOk();

        // Admin can view edit form
        $response = $this->actingAs($admin)->get(route('admin.activities.edit', $activity->id));
        $response->assertOk();
    }

    public function test_staff_can_delete_owned_activity_with_attendance_records(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $activity = $this->createActivity($staff);

        $attendance = Attendance::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'method'      => 'manual',
            'status'      => 'pending',
        ]);

        $response = $this->actingAs($staff)->delete(route('admin.activities.destroy', $activity));

        $response->assertRedirect(route('admin.activities.index'));
        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    public function test_registration_policy_protects_approvals(): void
    {
        $staffA = User::factory()->create(['role' => 'staff']);
        $staffB = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);

        $activityA = $this->createActivity($staffA);
        $activityB = $this->createActivity($staffB);

        $regA = Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activityA->id,
            'status'      => 'pending',
        ]);

        $regB = Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activityB->id,
            'status'      => 'pending',
        ]);

        // Staff A can approve regA
        $response = $this->actingAs($staffA)->post(route('admin.registrations.approve', $regA->id));
        $response->assertRedirect();
        $this->assertDatabaseHas('registrations', ['id' => $regA->id, 'status' => 'approved']);

        // Staff A cannot approve regB (Activity created by Staff B) -> 403 Forbidden
        $response = $this->actingAs($staffA)->post(route('admin.registrations.approve', $regB->id));
        $response->assertForbidden();
        $this->assertDatabaseHas('registrations', ['id' => $regB->id, 'status' => 'pending']);
    }

    public function test_attendance_policy_protects_approvals(): void
    {
        $staffA = User::factory()->create(['role' => 'staff']);
        $staffB = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);

        $activityB = $this->createActivity($staffB);

        $attB = Attendance::create([
            'user_id'     => $student->id,
            'activity_id' => $activityB->id,
            'method'      => 'qr',
            'status'      => 'pending',
        ]);

        // Staff A cannot approve Attendance for Staff B's activity -> 403 Forbidden
        $response = $this->actingAs($staffA)->post(route('admin.attendances.approve', $attB->id));
        $response->assertForbidden();
        $this->assertDatabaseHas('attendances', ['id' => $attB->id, 'status' => 'pending']);

        // Staff B can approve own activity attendance
        $response = $this->actingAs($staffB)->post(route('admin.attendances.approve', $attB->id));
        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', ['id' => $attB->id, 'status' => 'approved']);
    }
}
