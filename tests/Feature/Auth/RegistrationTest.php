<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'student_id' => '6612345678',
            'full_name' => 'Test Student',
            'faculty' => 'วิทยาศาสตร์และเทคโนโลยี',
            'department' => 'วิทยาการคอมพิวเตอร์',
            'year' => 2,
            'program' => 'ปกติ',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('activities.index', absolute: false));
        $this->assertDatabaseHas('users', [
            'student_id' => '6612345678',
            'full_name' => 'Test Student',
            'role' => 'student',
        ]);
    }
}
