<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_students_can_authenticate_using_their_student_id(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'student_id' => $user->student_id,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('activities.index', absolute: false));
    }

    public function test_students_can_not_authenticate_with_an_invalid_student_id(): void
    {
        $this->post('/login', [
            'student_id' => 'missing-student-id',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }
}
