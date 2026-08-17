<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'        => 'student',
            'student_id'  => '6512345678',
            'full_name'   => 'นาย สมชาย เรียนดี',
            'faculty'     => 'วิทยาศาสตร์และเทคโนโลยี',
            'department'  => 'วิทยาการคอมพิวเตอร์',
        ], $attributes));
    }

    private function createActivity(array $attributes = []): Activity
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'กิจกรรมมหาวิทยาลัย', 'color' => '#3b82f6', 'required_hours' => 10]
        );

        $creator = User::firstOrCreate(
            ['email' => 'admin@pkru.ac.th'],
            ['role' => 'admin', 'full_name' => 'Admin User', 'password' => bcrypt('password')]
        );

        return Activity::create(array_merge([
            'title'             => 'สัมมนาวิชาการ AI',
            'description'       => 'การใช้ AI ในยุคปัจจุบัน',
            'location'          => 'ห้องประชุม 1',
            'activity_date'     => now()->addDays(2)->format('Y-m-d'),
            'start_time'        => '09:00',
            'end_time'          => '12:00',
            'activity_hours'    => 3,
            'max_participants'  => 50,
            'register_open_at'  => now()->subDay(),
            'register_close_at' => now()->addDay(),
            'checkin_open_at'   => now()->addDays(2)->setTime(8, 30),
            'checkin_close_at'  => now()->addDays(2)->setTime(12, 30),
            'category_id'       => $category->id,
            'scope'             => 'university',
            'status'            => 'open',
            'is_mandatory'      => false,
            'created_by'        => $creator->id,
            'qr_token'          => Str::random(16),
        ], $attributes));
    }

    public function test_guest_cannot_access_student_routes(): void
    {
        $this->get(route('student.profile'))->assertRedirect(route('login'));
        $this->get(route('student.my'))->assertRedirect(route('login'));
        $this->get(route('student.history'))->assertRedirect(route('login'));
        $this->get(route('student.summary'))->assertRedirect(route('login'));
        $this->get(route('student.calendar'))->assertRedirect(route('login'));
        $this->get(route('student.calendar.events'))->assertRedirect(route('login'));
        $this->get(route('student.notifications'))->assertRedirect(route('login'));
        $this->get(route('student.scanner'))->assertRedirect(route('login'));
    }

    public function test_student_can_view_profile_and_summary_metrics(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivity();

        Attendance::create([
            'activity_id'   => $activity->id,
            'user_id'       => $student->id,
            'checked_in_at' => now()->subDay(),
            'status'        => 'approved',
            'method'        => 'qr',
        ]);

        $response = $this->actingAs($student)->get(route('student.profile'));

        $response->assertOk();
        $response->assertViewIs('student.profile');
        $response->assertViewHasAll([
            'user',
            'totalHours',
            'totalRequired',
            'byCategory',
            'recentAttendances',
            'totalActivities',
        ]);
    }

    public function test_student_can_update_english_name(): void
    {
        $student = $this->createStudent(['english_name' => null]);

        $response = $this->actingAs($student)->post(route('student.profile.english_name'), [
            'english_name' => 'Somchai Reandee',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id'           => $student->id,
            'english_name' => 'Somchai Reandee',
        ]);
    }

    public function test_student_can_view_my_activities_with_computed_todos(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivity([
            'checkin_open_at'  => now()->subHour(),
            'checkin_close_at' => now()->addHour(),
        ]);

        Registration::create([
            'activity_id'   => $activity->id,
            'user_id'       => $student->id,
            'status'        => 'approved',
            'registered_at' => now()->subDay(),
        ]);

        Notification::create([
            'user_id' => $student->id,
            'type'    => 'registration_approved',
            'title'   => 'อนุมัติการลงทะเบียน',
            'message' => 'คุณได้รับการอนุมัติ',
            'is_read' => false,
        ]);

        $response = $this->actingAs($student)->get(route('student.my'));

        $response->assertOk();
        $response->assertViewIs('student.my-activities');
        $response->assertViewHasAll([
            'registrations',
            'checkedInActivityIds',
            'attendanceMap',
            'feedbackDoneIds',
            'walkInAttendances',
            'todos',
        ]);

        // Unread notifications must be marked read
        $this->assertDatabaseHas(Notification::class, [
            'user_id' => $student->id,
            'is_read' => true,
        ]);
    }

    public function test_student_can_view_attendance_history(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivity();

        Attendance::create([
            'activity_id'   => $activity->id,
            'user_id'       => $student->id,
            'checked_in_at' => now()->subDays(2),
            'status'        => 'approved',
            'method'        => 'qr',
        ]);

        $response = $this->actingAs($student)->get(route('student.history'));

        $response->assertOk();
        $response->assertViewIs('student.history');
        $response->assertViewHas('attendances');
    }

    public function test_student_can_view_summary_hours(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student)->get(route('student.summary'));

        $response->assertOk();
        $response->assertViewIs('student.summary');
        $response->assertViewHasAll(['totalHours', 'totalRequired', 'byCategory']);
    }

    public function test_student_can_fetch_calendar_events_json(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivity();

        $response = $this->actingAs($student)->getJson(route('student.calendar.events'));

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => [
                'id',
                'title',
                'start',
                'end',
                'color',
                'url',
                'extendedProps' => [
                    'location',
                    'hours',
                    'category',
                    'status',
                    'is_registered',
                    'is_checked_in',
                    'needs_feedback',
                ],
            ],
        ]);
    }

    public function test_student_can_fetch_notifications_alerts_json(): void
    {
        $student = $this->createStudent();

        Notification::create([
            'user_id' => $student->id,
            'type'    => 'registration_approved',
            'title'   => 'ยินดีต้อนรับ',
            'message' => 'คุณได้รับการอนุมัติแล้ว',
            'is_read' => false,
        ]);

        $response = $this->actingAs($student)->getJson(route('student.notifications'));

        $response->assertOk();
        $response->assertJsonStructure([
            'alerts' => [
                '*' => [
                    'id',
                    'type',
                    'title',
                    'body',
                    'url',
                    'icon',
                    'db',
                ],
            ],
        ]);
    }

    public function test_student_can_download_activity_transcript_pdf(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivity();

        Attendance::create([
            'activity_id'   => $activity->id,
            'user_id'       => $student->id,
            'checked_in_at' => now()->subDay(),
            'status'        => 'approved',
            'method'        => 'qr',
        ]);

        $response = $this->actingAs($student)->get(route('student.summary.pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
