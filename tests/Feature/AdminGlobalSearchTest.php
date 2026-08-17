<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Announcement;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_perform_omnisearch_across_all_modules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $student = User::factory()->create([
            'role'       => 'student',
            'full_name'  => 'นายสมชาย วิทยาการ',
            'student_id' => '6710889999',
        ]);

        $activity = Activity::factory()->create([
            'title' => 'กิจกรรมศึกษาดูงานเทคโนโลยีสารสนเทศ',
        ]);

        $job = JobListing::create([
            'title'        => 'ผู้ช่วยนักพัฒนาระบบสารสนเทศ',
            'company_name' => 'Tech Corp',
            'position'     => 'Programmer Assistant',
            'description'  => 'รายละเอียดงานพัฒนาระบบ',
            'location'     => 'ภูเก็ต',
            'type'         => 'part_time',
            'compensation' => '500 บาท/วัน',
            'status'       => 'open',
            'start_date'   => now()->format('Y-m-d'),
            'created_by'   => $admin->id,
        ]);

        $announcement = Announcement::create([
            'title'      => 'ประกาศรับสมัครนักศึกษาช่วยงานสารสนเทศ',
            'content'    => 'รายละเอียดการรับสมัคร',
            'type'       => 'info',
            'created_by' => $admin->id,
        ]);

        // Search query "สารสนเทศ" matches activity, job, and announcement
        $response = $this->actingAs($admin)->getJson(route('admin.global.search', ['q' => 'สารสนเทศ']));

        $response->assertOk();
        $response->assertJsonStructure([
            'query',
            'count',
            'results' => [
                '*' => ['type', 'type_label', 'title', 'subtitle', 'url', 'badge_color']
            ]
        ]);

        $data = $response->json();
        $this->assertGreaterThanOrEqual(3, $data['count']);

        // Search query by student ID
        $resStudent = $this->actingAs($admin)->getJson(route('admin.global.search', ['q' => '6710889999']));
        $resStudent->assertOk();
        $resStudent->assertJsonFragment([
            'type'  => 'student',
            'title' => "นายสมชาย วิทยาการ (6710889999)",
        ]);
    }

    public function test_guest_and_student_cannot_access_admin_global_search(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $resStudent = $this->actingAs($student)->getJson(route('admin.global.search', ['q' => 'test']));
        $this->assertTrue(in_array($resStudent->status(), [403, 302], true));

        auth()->logout();
        $resGuest = $this->getJson(route('admin.global.search', ['q' => 'test']));
        $resGuest->assertUnauthorized();
    }
}
