<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;
    public function test_guest_cannot_access_horizon_dashboard(): void
    {
        $response = $this->get('/horizon');
        $response->assertStatus(403);
    }

    public function test_student_cannot_access_horizon_dashboard(): void
    {
        $student = User::create([
            'student_id' => '65019999',
            'full_name' => 'Test Student',
            'email' => 'student@test.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->actingAs($student);

        $response = $this->get('/horizon');
        $response->assertStatus(403);
    }

    public function test_admin_is_authorized_for_horizon(): void
    {
        $admin = User::create([
            'student_id' => 'admin_test_1',
            'full_name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/horizon');
        $response->assertOk();
    }
}
