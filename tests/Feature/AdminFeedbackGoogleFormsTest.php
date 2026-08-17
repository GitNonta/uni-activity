<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeedbackGoogleFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_feedbacks_index_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = ActivityCategory::create([
            'name'           => 'วิชาการ',
            'required_hours' => 3,
            'icon'           => 'academic-cap',
            'color'          => '#ea580c',
        ]);

        $activity = Activity::factory()->create([
            'title'        => 'สัมมนาการเขียนโปรแกรมด้วย AI',
            'category_id'  => $category->id,
            'created_by'   => $admin->id,
        ]);

        ActivityFeedback::create([
            'activity_id'  => $activity->id,
            'user_id'      => $admin->id,
            'rating'       => 5,
            'comment'      => 'กิจกรรมยอดเยี่ยมมาก ได้รับความรู้เต็มที่',
            'ratings'      => [
                'content'      => 5,
                'speaker'      => 5,
                'location'     => 4,
                'organization' => 5,
            ],
            'is_anonymous' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.feedbacks.index'));

        $response->assertStatus(200);
        $response->assertSee('รายงานการประเมินความพึงพอใจกิจกรรม');
        $response->assertSee('กิจกรรมยอดเยี่ยมมาก');
    }

    public function test_admin_can_view_google_forms_style_feedback_summary(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $category = ActivityCategory::create([
            'name'           => 'วิชาการ',
            'required_hours' => 3,
            'icon'           => 'academic-cap',
            'color'          => '#ea580c',
        ]);

        $activity = Activity::factory()->create([
            'title'        => 'การอบรม AI & Laravel Cloud',
            'category_id'  => $category->id,
            'created_by'   => $admin->id,
        ]);

        ActivityFeedback::create([
            'activity_id'  => $activity->id,
            'user_id'      => $student->id,
            'rating'       => 5,
            'comment'      => 'วิทยากรอธิบายเข้าใจง่าย สถานที่สะดวกสบาย',
            'ratings'      => [
                'content'      => 5,
                'speaker'      => 5,
                'location'     => 4,
                'organization' => 5,
            ],
            'is_anonymous' => false,
        ]);

        ActivityFeedback::create([
            'activity_id'  => $activity->id,
            'user_id'      => $admin->id,
            'rating'       => 4,
            'comment'      => 'ข้อเสนอแนะ: อยากให้เพิ่มเวลา Workshop',
            'ratings'      => [
                'content'      => 4,
                'speaker'      => 4,
                'location'     => 4,
                'organization' => 4,
            ],
            'is_anonymous' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.feedbacks.show', $activity->id));

        $response->assertStatus(200);
        $response->assertSee('การอบรม AI & Laravel Cloud');
        $response->assertSee('ข้อมูลสรุป (Summary)');
        $response->assertSee('คำถาม (Questions)');
        $response->assertSee('แยกตามบุคคล (Individual)');
        $response->assertSee('วิทยากรอธิบายเข้าใจง่าย');
        $response->assertSee('ข้อเสนอแนะ: อยากให้เพิ่มเวลา Workshop');
    }
}
