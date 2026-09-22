<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\JobPublished;
use App\Models\Follow;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    private function createStaff(): User
    {
        return User::factory()->create([
            'role'       => 'staff',
            'email'      => 'staff@pkru.ac.th',
            'full_name'  => 'เจ้าหน้าที่ ทดสอบ',
            'position'   => 'ฝ่ายกิจการนักศึกษา',
        ]);
    }

    public function test_guest_can_view_public_user_profile(): void
    {
        $staff = $this->createStaff();

        $response = $this->get(route('users.show', $staff));

        $response->assertOk();
        $response->assertViewIs('users.show');
        $response->assertViewHas('profileUser');
        $response->assertSee($staff->full_name);
    }

    public function test_any_authenticated_user_can_view_other_user_profile(): void
    {
        $staff = $this->createStaff();
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get(route('users.show', $staff));

        $response->assertOk();
        $response->assertViewIs('users.show');
        $response->assertViewHas('viewerIsSelf', false);
        $response->assertViewHas('isFollowing', false);
    }

    public function test_user_can_follow_and_unfollow_another_user(): void
    {
        $staff = $this->createStaff();
        $student = User::factory()->create(['role' => 'student']);

        // ── Follow ──
        $response = $this->actingAs($student)->postJson(route('users.follow', $staff));
        $response->assertOk();
        $response->assertJson([
            'success'      => true,
            'is_following' => true,
        ]);
        $this->assertDatabaseHas('follows', [
            'follower_id'  => $student->id,
            'following_id' => $staff->id,
        ]);

        // Follow ซ้ำต้องไม่สร้างแถวใหม่ (idempotent)
        $this->actingAs($student)->postJson(route('users.follow', $staff))->assertOk();
        $this->assertSame(1, Follow::where('follower_id', $student->id)->where('following_id', $staff->id)->count());

        // ── Unfollow ──
        $response = $this->actingAs($student)->postJson(route('users.unfollow', $staff));
        $response->assertOk();
        $response->assertJson([
            'success'      => true,
            'is_following' => false,
        ]);
        $this->assertDatabaseMissing('follows', [
            'follower_id'  => $student->id,
            'following_id' => $staff->id,
        ]);
    }

    public function test_user_cannot_follow_self(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->postJson(route('users.follow', $student));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('follows', [
            'follower_id'  => $student->id,
            'following_id' => $student->id,
        ]);
    }

    public function test_guest_cannot_follow(): void
    {
        $staff = $this->createStaff();

        $this->postJson(route('users.follow', $staff))->assertUnauthorized();
        $this->postJson(route('users.unfollow', $staff))->assertUnauthorized();

        $this->assertSame(0, Follow::count());
    }

    public function test_following_creates_notification_for_target_user(): void
    {
        $staff = $this->createStaff();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->postJson(route('users.follow', $staff))->assertOk();

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $staff->id,
            'type'    => 'new_follower',
            'url'     => route('users.show', $student),
        ]);
    }

    public function test_new_job_post_notifies_all_followers(): void
    {
        $staff = $this->createStaff();
        $followerA = User::factory()->create(['role' => 'student']);
        $followerB = User::factory()->create(['role' => 'student']);
        $stranger = User::factory()->create(['role' => 'student']);

        Follow::create(['follower_id' => $followerA->id, 'following_id' => $staff->id]);
        Follow::create(['follower_id' => $followerB->id, 'following_id' => $staff->id]);

        $job = JobListing::create([
            'title'      => 'งานประชาสัมพันธ์',
            'job_type'   => 'parttime',
            'position'   => 'PR',
            'quota'      => 3,
            'location'   => 'อาคารเรียนรวม',
            'start_date' => now()->addDays(3)->toDateString(),
            'gender'     => 'any',
            'status'     => 'open',
            'created_by' => $staff->id,
        ]);

        // จำลอง flow เดียวกับ JobAdminController::store — event → listener
        event(new JobPublished($job));

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $followerA->id,
            'type'    => 'new_post_from_following',
            'url'     => route('jobs.show', $job),
        ]);
        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $followerB->id,
            'type'    => 'new_post_from_following',
        ]);
        // คนที่ไม่ได้ติดตามต้องไม่ได้รับแจ้งเตือน
        $this->assertDatabaseMissing('notifications_custom', [
            'user_id' => $stranger->id,
            'type'    => 'new_post_from_following',
        ]);
    }

    public function test_followers_and_following_endpoints_return_json(): void
    {
        $staff = $this->createStaff();
        $student = User::factory()->create(['role' => 'student']);

        Follow::create(['follower_id' => $student->id, 'following_id' => $staff->id]);

        $this->getJson(route('users.followers', $staff))
            ->assertOk()
            ->assertJsonStructure(['followers' => [['id', 'name', 'role', 'url']]]);

        $this->getJson(route('users.following', $student))
            ->assertOk()
            ->assertJsonStructure(['following' => [['id', 'name', 'role', 'url']]]);
    }

    public function test_profile_view_shows_follow_state_and_counts(): void
    {
        $staff = $this->createStaff();
        $student = User::factory()->create(['role' => 'student']);

        Follow::create(['follower_id' => $student->id, 'following_id' => $staff->id]);

        $response = $this->actingAs($student)->get(route('users.show', $staff));

        $response->assertOk();
        $response->assertViewHas('isFollowing', true);
        $response->assertViewHas('followersCount', 1);
    }
}
