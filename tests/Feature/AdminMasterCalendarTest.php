<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminMasterCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function createStaff(): User
    {
        return User::factory()->create([
            'role'  => 'staff',
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    private function createStudent(): User
    {
        return User::factory()->create([
            'role' => 'student',
        ]);
    }

    private function createActivity(array $attributes = []): Activity
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $creator = User::factory()->create([
            'role' => 'admin',
        ]);

        return Activity::create(array_merge([
            'title'            => 'Test Activity',
            'location'         => 'หอประชุมใหญ่',
            'activity_date'    => now()->addDays(5)->format('Y-m-d'),
            'start_time'       => '09:00',
            'end_time'         => '12:00',
            'activity_hours'   => 3,
            'max_participants' => 100,
            'register_open_at' => now()->subDay(),
            'register_close_at'=> now()->addDays(4),
            'checkin_open_at'  => now()->addDays(5)->setTime(8, 30),
            'checkin_close_at' => now()->addDays(5)->setTime(9, 30),
            'category_id'      => $category->id,
            'scope'            => 'university',
            'status'           => 'open',
            'is_mandatory'     => false,
            'created_by'       => $creator->id,
            'qr_token'         => Str::random(10),
        ], $attributes));
    }

    public function test_guest_cannot_access_calendar(): void
    {
        $response = $this->get(route('admin.calendar.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_calendar(): void
    {
        $student = $this->createStudent();
        $response = $this->actingAs($student)->get(route('admin.calendar.index'));
        $response->assertRedirect(route('activities.index'));

        $jsonResponse = $this->actingAs($student)->getJson(route('admin.calendar.events'));
        $jsonResponse->assertForbidden();
    }

    public function test_staff_can_view_calendar(): void
    {
        $staff = $this->createStaff();
        $this->createActivity(['title' => 'สัมมนาวิชาการ']);

        $response = $this->actingAs($staff)->get(route('admin.calendar.index'));
        $response->assertOk();
        $response->assertSee('ปฏิทินกิจกรรมอัจฉริยะ');
        $response->assertSee('หอประชุมใหญ่');
    }

    public function test_calendar_events_returns_proper_json(): void
    {
        $staff = $this->createStaff();
        $act = $this->createActivity([
            'title'         => 'ปฐมนิเทศนักศึกษา',
            'activity_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time'    => '09:00',
            'end_time'      => '12:00',
            'location'      => 'หอประชุม 1',
        ]);

        $response = $this->actingAs($staff)->getJson(route('admin.calendar.events', [
            'start' => now()->startOfMonth()->toDateString(),
            'end'   => now()->endOfMonth()->addMonths(1)->toDateString(),
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $first = collect($data)->firstWhere('id', (string) $act->id);
        $this->assertNotNull($first);
        $this->assertEquals('ปฐมนิเทศนักศึกษา', $first['title']);
        $this->assertEquals('หอประชุม 1', $first['extendedProps']['location']);
        $this->assertFalse($first['extendedProps']['is_conflict']);
    }

    public function test_calendar_detects_conflicts_between_activities_with_same_location_and_time(): void
    {
        $staff = $this->createStaff();
        $conflictDate = now()->addDays(7)->format('Y-m-d');

        // กิจกรรม A: 09:00 - 12:00 ที่ หอประชุมใหญ่
        $actA = $this->createActivity([
            'title'         => 'กิจกรรม A แข่งขันกีฬา',
            'activity_date' => $conflictDate,
            'start_time'    => '09:00',
            'end_time'      => '12:00',
            'location'      => 'หอประชุมใหญ่',
        ]);

        // กิจกรรม B: 10:00 - 13:00 ที่ หอประชุมใหญ่ (ชนช่วงเวลา 10:00 - 12:00)
        $actB = $this->createActivity([
            'title'         => 'กิจกรรม B คอนเสิร์ตรับน้อง',
            'activity_date' => $conflictDate,
            'start_time'    => '10:00',
            'end_time'      => '13:00',
            'location'      => 'หอประชุมใหญ่',
        ]);

        $response = $this->actingAs($staff)->getJson(route('admin.calendar.events', [
            'start' => now()->startOfMonth()->toDateString(),
            'end'   => now()->endOfMonth()->addMonths(1)->toDateString(),
        ]));

        $response->assertOk();
        $data = $response->json();

        $eventA = collect($data)->firstWhere('id', (string) $actA->id);
        $eventB = collect($data)->firstWhere('id', (string) $actB->id);

        $this->assertNotNull($eventA);
        $this->assertNotNull($eventB);

        $this->assertTrue($eventA['extendedProps']['is_conflict'], 'Event A should be marked as conflict');
        $this->assertTrue($eventB['extendedProps']['is_conflict'], 'Event B should be marked as conflict');
        $this->assertEquals('#dc2626', $eventA['backgroundColor'], 'Conflicting event should have red background');
    }

    public function test_calendar_ignores_cancelled_activity_conflicts(): void
    {
        $staff = $this->createStaff();
        $date = now()->addDays(10)->format('Y-m-d');

        $actActive = $this->createActivity([
            'title'         => 'กิจกรรมใช้งานจริง',
            'activity_date' => $date,
            'start_time'    => '09:00',
            'end_time'      => '12:00',
            'location'      => 'ห้อง 501',
            'status'        => 'open',
        ]);

        $this->createActivity([
            'title'         => 'กิจกรรมที่ยกเลิกไปแล้ว',
            'activity_date' => $date,
            'start_time'    => '09:00',
            'end_time'      => '12:00',
            'location'      => 'ห้อง 501',
            'status'        => 'cancelled',
        ]);

        $response = $this->actingAs($staff)->getJson(route('admin.calendar.events', [
            'start' => now()->startOfMonth()->toDateString(),
            'end'   => now()->endOfMonth()->addMonths(1)->toDateString(),
        ]));

        $response->assertOk();
        $event = collect($response->json())->firstWhere('id', (string) $actActive->id);

        $this->assertNotNull($event);
        $this->assertFalse($event['extendedProps']['is_conflict'], 'Active event should not conflict with cancelled activity');
    }

    public function test_check_conflict_endpoint_detects_collision(): void
    {
        $staff = $this->createStaff();
        $targetDate = now()->addDays(15)->format('Y-m-d');

        $this->createActivity([
            'title'         => 'การประชุมสภามหาวิทยาลัย',
            'activity_date' => $targetDate,
            'start_time'    => '13:00',
            'end_time'      => '16:00',
            'location'      => 'ห้องประชุมสภา 1',
        ]);

        $response = $this->actingAs($staff)->postJson(route('admin.calendar.check-conflict'), [
            'location'      => 'ห้องประชุมสภา 1',
            'activity_date' => $targetDate,
            'start_time'    => '14:00',
            'end_time'      => '17:00',
        ]);

        $response->assertOk();
        $response->assertJson([
            'has_conflict'   => true,
            'conflict_count' => 1,
        ]);
        $this->assertCount(1, $response->json('conflicts'));
        $this->assertEquals('การประชุมสภามหาวิทยาลัย', $response->json('conflicts.0.title'));
    }

    public function test_check_conflict_endpoint_returns_no_conflict_when_available(): void
    {
        $staff = $this->createStaff();
        $targetDate = now()->addDays(20)->format('Y-m-d');

        $response = $this->actingAs($staff)->postJson(route('admin.calendar.check-conflict'), [
            'location'      => 'ห้องประชุมใหม่ 99',
            'activity_date' => $targetDate,
            'start_time'    => '09:00',
            'end_time'      => '12:00',
        ]);

        $response->assertOk();
        $response->assertJson([
            'has_conflict'   => false,
            'conflict_count' => 0,
        ]);
    }

    public function test_check_conflict_endpoint_excludes_self_activity_id(): void
    {
        $staff = $this->createStaff();
        $targetDate = now()->addDays(25)->format('Y-m-d');

        $existingAct = $this->createActivity([
            'title'         => 'อบรมการใช้งานระบบ',
            'activity_date' => $targetDate,
            'start_time'    => '09:00',
            'end_time'      => '12:00',
            'location'      => 'ห้องแล็บคอม 1',
        ]);

        // เมื่อส่ง exclude_id ตรงกับ existingAct ต้องไม่มองว่าชนกับตัวเอง
        $response = $this->actingAs($staff)->postJson(route('admin.calendar.check-conflict'), [
            'location'      => 'ห้องแล็บคอม 1',
            'activity_date' => $targetDate,
            'start_time'    => '09:00',
            'end_time'      => '12:00',
            'exclude_id'    => $existingAct->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'has_conflict'   => false,
            'conflict_count' => 0,
        ]);
    }
}
