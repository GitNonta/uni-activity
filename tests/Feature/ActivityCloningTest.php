<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityCloningTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_clone_existing_activity(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $category = ActivityCategory::factory()->create(['name' => 'กิจกรรมวิชาการ']);

        $originalActivity = Activity::factory()->create([
            'title'            => 'อบรมการเขียนโปรแกรม Python ประจำเดือน',
            'description'      => 'หลักสูตรปูพื้นฐานการเขียนโปรแกรม',
            'category_id'      => $category->id,
            'location'         => 'ห้องปฏิบัติการคอมพิวเตอร์ 1',
            'activity_hours'   => 3.0,
            'max_participants' => 50,
            'created_by'       => $staff->id,
            'status'           => 'published',
        ]);

        // Attach registrations and attendances to original activity
        Registration::create([
            'activity_id' => $originalActivity->id,
            'user_id'     => $student->id,
            'status'      => 'approved',
        ]);

        Attendance::create([
            'activity_id' => $originalActivity->id,
            'user_id'     => $student->id,
            'method'      => 'qr_scan',
            'status'      => 'approved',
            'check_in_at' => now(),
        ]);

        // Action: Clone activity
        $response = $this->actingAs($staff)->post(route('admin.activities.clone', $originalActivity->id));

        $clonedActivity = Activity::where('title', 'like', '%[สำเนา]%')->first();

        $this->assertNotNull($clonedActivity);
        $response->assertRedirect(route('admin.activities.edit', $clonedActivity));

        // Assert metadata copied correctly
        $this->assertEquals('[สำเนา] อบรมการเขียนโปรแกรม Python ประจำเดือน', $clonedActivity->title);
        $this->assertEquals($originalActivity->description, $clonedActivity->description);
        $this->assertEquals($originalActivity->category_id, $clonedActivity->category_id);
        $this->assertEquals($originalActivity->location, $clonedActivity->location);
        $this->assertEquals(3.0, (float) $clonedActivity->activity_hours);
        $this->assertEquals('draft', $clonedActivity->status);

        // Assert fresh tokens and NO old participants/attendances copied
        $this->assertNotEquals($originalActivity->qr_token, $clonedActivity->qr_token);
        $this->assertNotEquals($originalActivity->qr_checkout_token, $clonedActivity->qr_checkout_token);
        $this->assertEquals(0, Registration::where('activity_id', $clonedActivity->id)->count());
        $this->assertEquals(0, Attendance::where('activity_id', $clonedActivity->id)->count());
    }
}
