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
        $response->assertSee('ข้อมูลสังกัดและข้อมูลติดต่อ');
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

    public function test_admin_profile_displays_male_svg_avatar_when_gender_is_male(): void
    {
        $admin = User::factory()->create([
            'role'          => 'admin',
            'full_name'     => 'สมชาย ใจดี',
            'email'         => 'somchai@test.ac.th',
            'gender'        => 'male',
            'profile_photo' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('aria-label="อวตารเพศชาย"', false);
        $response->assertSee('mBg_', false);
        $response->assertSee('เพศ: <strong style="color: #cbd5e1;">ชาย</strong>', false);
    }

    public function test_admin_profile_displays_female_svg_avatar_when_gender_is_female(): void
    {
        $admin = User::factory()->create([
            'role'          => 'admin',
            'full_name'     => 'สมหญิง สวยงาม',
            'email'         => 'somying@test.ac.th',
            'gender'        => 'female',
            'profile_photo' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('aria-label="อวตารเพศหญิง"', false);
        $response->assertSee('fBg_', false);
        $response->assertSee('เพศ: <strong style="color: #cbd5e1;">หญิง</strong>', false);
    }

    public function test_admin_profile_displays_neutral_svg_avatar_when_gender_is_unspecified(): void
    {
        $admin = User::factory()->create([
            'role'          => 'admin',
            'full_name'     => 'ผู้ดูแล ทั่วไป',
            'email'         => 'general@test.ac.th',
            'gender'        => null,
            'profile_photo' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('aria-label="อวตารทั่วไป"', false);
        $response->assertSee('nBg_', false);
        $response->assertSee('เพศ: <strong style="color: #cbd5e1;">ไม่ระบุ</strong>', false);
    }

    public function test_admin_can_update_gender_to_female_and_avatar_updates(): void
    {
        $admin = User::factory()->create([
            'role'          => 'admin',
            'full_name'     => 'ผู้ดูแลระบบ',
            'english_name'  => 'Admin User',
            'email'         => 'admin_gender@test.ac.th',
            'gender'        => 'male',
            'profile_photo' => null,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.profile.update'), [
            'full_name'    => 'ผู้ดูแลระบบ',
            'english_name' => 'Admin User',
            'email'        => 'admin_gender@test.ac.th',
            'gender'       => 'female',
        ]);

        $response->assertRedirect(route('admin.profile.edit'));
        $this->assertDatabaseHas('users', [
            'id'     => $admin->id,
            'gender' => 'female',
        ]);

        $viewResponse = $this->actingAs($admin->fresh())->get(route('admin.profile.edit'));
        $viewResponse->assertSee('aria-label="อวตารเพศหญิง"', false);
    }
}
