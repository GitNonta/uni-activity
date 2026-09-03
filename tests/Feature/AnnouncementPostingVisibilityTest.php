<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use App\Services\ListCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnnouncementPostingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_newly_posted_announcement_is_visible_immediately_despite_list_cache(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        // Warm the list cache (5-minute TTL would hide new posts without bump).
        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('ประกาศใหม่ล่าสุด');

        $this->actingAs($student)->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('ประกาศใหม่ล่าสุด');

        // Staff posts a new announcement (store() must bump the cache group).
        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title'     => 'ประกาศใหม่ล่าสุด',
            'content'   => 'เนื้อหาประกาศที่เพิ่งโพสต์',
            'type'      => 'info',
            'is_active' => '1',
        ])->assertRedirect(route('admin.announcements.index'));

        // Cache version must have changed → new post visible on next request.
        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('ประกาศใหม่ล่าสุด');
    }

    public function test_student_list_cache_is_scoped_by_faculty_not_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Announcement::create([
            'title'         => 'ประกาศเฉพาะคณะวิทย์',
            'content'       => 'เนื้อหา',
            'type'          => 'info',
            'is_active'     => true,
            'target_faculty' => 'วิทยาศาสตร์และเทคโนโลยี',
            'created_by'    => $admin->id,
        ]);

        $scienceStudent = User::factory()->create([
            'role'    => 'student',
            'faculty' => 'วิทยาศาสตร์และเทคโนโลยี',
        ]);
        $otherStudent = User::factory()->create([
            'role'    => 'student',
            'faculty' => 'มนุษยศาสตร์และสังคมศาสตร์',
        ]);

        // Warm cache for both audiences.
        $this->actingAs($scienceStudent)->get(route('announcements.index'))->assertOk();
        $this->actingAs($otherStudent)->get(route('announcements.index'))->assertOk();

        // Second visit (served from cache) must still be faculty-scoped.
        $this->actingAs($otherStudent)->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('ประกาศเฉพาะคณะวิทย์');
    }

    public function test_toggling_active_state_invalidates_cached_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Announcement::create([
            'title'      => 'ประกาศจะถูกปิด',
            'content'    => 'เนื้อหา',
            'type'       => 'info',
            'is_active'  => true,
            'created_by' => $admin->id,
        ]);

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('ประกาศจะถูกปิด');

        $announcement = Announcement::where('title', 'ประกาศจะถูกปิด')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.announcements.toggle-active', $announcement))
            ->assertRedirect();

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('ประกาศจะถูกปิด');
    }
}
