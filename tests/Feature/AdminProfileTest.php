<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_profile_page(): void
    {
        $admin = User::factory()->create([
            'role'      => 'admin',
            'full_name' => 'Admin User',
            'email'     => 'admin@test.ac.th',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('Admin User');
        $response->assertSee('ข้อมูลส่วนตัวและสังกัดหน่วยงาน');
    }

    public function test_admin_can_update_profile(): void
    {
        $admin = User::factory()->create([
            'role'         => 'admin',
            'full_name'    => 'Old Name',
            'english_name' => 'Old Name EN',
            'email'        => 'admin_update@test.ac.th',
            'phone'        => '0811111111',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.profile.update'), [
            'full_name'    => 'New Name Thai',
            'english_name' => 'New Name English',
            'email'        => 'admin_update@test.ac.th',
            'phone'        => '0899999999',
            'position'     => 'Director of IT',
            'organization' => 'Faculty of Engineering',
        ]);

        $response->assertRedirect(route('admin.profile.edit'));
        $this->assertDatabaseHas('users', [
            'id'           => $admin->id,
            'full_name'    => 'New Name Thai',
            'english_name' => 'New Name English',
            'phone'        => '0899999999',
            'position'     => 'Director of IT',
        ]);
    }
}
