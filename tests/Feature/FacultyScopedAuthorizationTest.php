<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacultyScopedAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_staff_can_view_and_edit_activities_in_their_own_faculty(): void
    {
        $staff1 = User::factory()->create([
            'role'    => 'staff',
            'faculty' => 'คณะวิทยาศาสตร์และเทคโนโลยี',
        ]);

        $staff2 = User::factory()->create([
            'role'    => 'staff',
            'faculty' => 'คณะวิทยาศาสตร์และเทคโนโลยี',
        ]);

        // Activity created by Staff 1 in Science faculty
        $scienceActivity = Activity::factory()->create([
            'created_by' => $staff1->id,
            'faculty'    => 'คณะวิทยาศาสตร์และเทคโนโลยี',
            'title'      => 'สัปดาห์วิทยาศาสตร์แห่งชาติ',
        ]);

        // Staff 2 (same faculty) CAN view and edit
        $response = $this->actingAs($staff2)->get(route('admin.activities.show', $scienceActivity->id));
        $response->assertOk();

        $resEdit = $this->actingAs($staff2)->get(route('admin.activities.edit', $scienceActivity->id));
        $resEdit->assertOk();
    }

    public function test_faculty_staff_cannot_edit_or_delete_activities_of_different_faculty(): void
    {
        $scienceStaff = User::factory()->create([
            'role'    => 'staff',
            'faculty' => 'คณะวิทยาศาสตร์และเทคโนโลยี',
        ]);

        $educationStaff = User::factory()->create([
            'role'    => 'staff',
            'faculty' => 'คณะครุศาสตร์',
        ]);

        $educationActivity = Activity::factory()->create([
            'created_by' => $educationStaff->id,
            'faculty'    => 'คณะครุศาสตร์',
            'title'      => 'อบรมสัมมนาครูมืออาชีพ',
        ]);

        // Science staff CANNOT edit Education faculty activity
        $response = $this->actingAs($scienceStaff)->get(route('admin.activities.edit', $educationActivity->id));
        $response->assertForbidden();

        $resDelete = $this->actingAs($scienceStaff)->delete(route('admin.activities.destroy', $educationActivity->id));
        $resDelete->assertForbidden();
    }

    public function test_admin_can_manage_activities_of_any_faculty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $activity = Activity::factory()->create([
            'faculty' => 'คณะวิทยาการจัดการ',
            'title'   => 'มหกรรมตลาดนัดวิชาการ',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.activities.edit', $activity->id));
        $response->assertOk();
    }
}
