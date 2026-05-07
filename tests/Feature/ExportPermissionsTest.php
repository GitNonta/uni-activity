<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportPermissionsTest extends TestCase
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

    public function test_staff_can_access_exports_page()
    {
        $staff = $this->createStaff();

        $response = $this->actingAs($staff)->get(route('admin.exports.index'));

        $response->assertOk();
    }

    public function test_student_cannot_access_exports_page()
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student)->get(route('admin.exports.index'));

        // Middleware 'role:staff' returns 403 Forbidden
        $response->assertForbidden();
    }

    public function test_guest_cannot_access_exports_page()
    {
        $response = $this->get(route('admin.exports.index'));

        // Middleware 'auth' redirects to login
        $response->assertRedirect(route('login'));
    }
}
