<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\GraduationCriteria;
use App\Models\Notification;
use App\Models\User;
use App\Services\GraduationAuditService;
use App\Services\LineService;
use App\Services\OfficialTranscriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GraduationAuditAndTranscriptTest extends TestCase
{
    use RefreshDatabase;

    private function createStaff(): User
    {
        return User::factory()->create([
            'role'  => 'staff',
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    private function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'       => 'student',
            'year'       => 4,
            'student_id' => '640100' . fake()->unique()->numberBetween(10, 99),
        ], $attributes));
    }

    private function setupCriteriaAndCategories(): array
    {
        $volunteerCat = ActivityCategory::create([
            'name'           => 'จิตอาสาและบำเพ็ญประโยชน์',
            'required_hours' => 20,
            'color'          => '#EF4444',
        ]);

        $academicCat = ActivityCategory::create([
            'name'           => 'วิชาการ',
            'required_hours' => 15,
            'color'          => '#3B82F6',
        ]);

        $criteria = GraduationCriteria::create([
            'name'                 => 'เกณฑ์กิจกรรมมาตรฐานปริญญาตรี',
            'code'                 => 'CRITERIA-TEST-DEFAULT',
            'min_total_hours'      => 100.0,
            'min_mandatory_hours'  => 0.0,
            'scope_requirements'   => [
                'university' => 40.0,
                'faculty'    => 40.0,
            ],
            'category_requirements' => [
                (string) $volunteerCat->id => 20.0,
            ],
            'is_default'           => true,
            'is_active'            => true,
        ]);

        return [$criteria, $volunteerCat, $academicCat];
    }

    private function createActivity(array $attributes = []): Activity
    {
        $creator = User::factory()->create(['role' => 'admin']);

        return Activity::create(array_merge([
            'title'            => 'กิจกรรมทดสอบ',
            'location'         => 'หอประชุม',
            'activity_date'    => now()->subDays(5)->format('Y-m-d'),
            'start_time'       => '09:00',
            'end_time'         => '12:00',
            'activity_hours'   => 10.0,
            'max_participants' => 100,
            'register_open_at' => now()->subDays(10),
            'register_close_at'=> now()->subDays(6),
            'checkin_open_at'  => now()->subDays(5)->setTime(8, 30),
            'checkin_close_at' => now()->subDays(5)->setTime(12, 30),
            'scope'            => 'university',
            'status'           => 'completed',
            'is_mandatory'     => false,
            'created_by'       => $creator->id,
        ], $attributes));
    }

    public function test_staff_can_view_graduation_audit_page(): void
    {
        $this->setupCriteriaAndCategories();
        $staff = $this->createStaff();
        $this->createStudent();

        $response = $this->actingAs($staff)->get(route('admin.graduation.audit.index'));

        $response->assertStatus(200);
        $response->assertSee('ตรวจสอบการสำเร็จการศึกษา');
        $response->assertSee('รายชื่อนักศึกษาและผลการตรวจสอบชั่วโมงกิจกรรม');
    }

    public function test_student_cannot_view_admin_graduation_audit_page(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student)->get(route('admin.graduation.audit.index'));

        $response->assertRedirect(route('activities.index'));
    }

    public function test_graduation_audit_evaluation_passed_when_all_criteria_met(): void
    {
        [$criteria, $volunteerCat, $academicCat] = $this->setupCriteriaAndCategories();
        $student = $this->createStudent();

        // 1. กิจกรรมระดับมหาวิทยาลัย: 40 ชม.
        $actUni = $this->createActivity([
            'title'          => 'ปฐมนิเทศมหาวิทยาลัย',
            'scope'          => 'university',
            'activity_hours' => 40.0,
            'category_id'    => $academicCat->id,
        ]);
        Attendance::create([
            'user_id'       => $student->id,
            'activity_id'   => $actUni->id,
            'status'        => 'approved',
            'checked_in_at' => now()->subDays(3),
        ]);

        // 2. กิจกรรมระดับคณะ: 40 ชม.
        $actFac = $this->createActivity([
            'title'          => 'ค่ายคณะวิทยาศาสตร์',
            'scope'          => 'faculty',
            'activity_hours' => 40.0,
            'category_id'    => $academicCat->id,
        ]);
        Attendance::create([
            'user_id'       => $student->id,
            'activity_id'   => $actFac->id,
            'status'        => 'approved',
            'checked_in_at' => now()->subDays(2),
        ]);

        // 3. กิจกรรมจิตอาสา: 20 ชม.
        $actVol = $this->createActivity([
            'title'          => 'ปลูกป่าชายเลนจิตอาสา',
            'scope'          => 'faculty',
            'activity_hours' => 20.0,
            'category_id'    => $volunteerCat->id,
        ]);
        Attendance::create([
            'user_id'       => $student->id,
            'activity_id'   => $actVol->id,
            'status'        => 'approved',
            'checked_in_at' => now()->subDay(),
        ]);

        /** @var GraduationAuditService $auditService */
        $auditService = app(GraduationAuditService::class);
        $result = $auditService->auditStudent($student, $criteria);

        $this->assertTrue($result['is_eligible']);
        $this->assertEquals(100.0, $result['total_hours']);
        $this->assertTrue($result['total_passed']);
        $this->assertTrue($result['scope_audit']['university']['passed']);
        $this->assertTrue($result['scope_audit']['faculty']['passed']);
        $this->assertEmpty($result['missing_requirements']);
    }

    public function test_graduation_audit_evaluation_failed_when_missing_category_or_scope(): void
    {
        [$criteria, $volunteerCat, $academicCat] = $this->setupCriteriaAndCategories();
        $student = $this->createStudent();

        // สะสมได้แค่ 40 ชม. มหาวิทยาลัย (ขาดคณะ 40 ชม. และจิตอาสา 20 ชม.)
        $actUni = $this->createActivity([
            'title'          => 'กิจกรรมมหาวิทยาลัย',
            'scope'          => 'university',
            'activity_hours' => 40.0,
            'category_id'    => $academicCat->id,
        ]);
        Attendance::create([
            'user_id'       => $student->id,
            'activity_id'   => $actUni->id,
            'status'        => 'approved',
            'checked_in_at' => now()->subDays(3),
        ]);

        /** @var GraduationAuditService $auditService */
        $auditService = app(GraduationAuditService::class);
        $result = $auditService->auditStudent($student, $criteria);

        $this->assertFalse($result['is_eligible']);
        $this->assertEquals(40.0, $result['total_hours']);
        $this->assertFalse($result['total_passed']);
        $this->assertNotEmpty($result['missing_requirements']);
    }

    public function test_staff_can_download_official_transcript_pdf(): void
    {
        $this->setupCriteriaAndCategories();
        $staff = $this->createStaff();
        $student = $this->createStudent();

        $response = $this->actingAs($staff)->get(route('admin.graduation.audit.transcript', $student));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_staff_can_send_deficit_alert_to_student(): void
    {
        $this->setupCriteriaAndCategories();
        $staff = $this->createStaff();
        $student = $this->createStudent([
            'line_user_id'        => 'U99999999999999999999999999999999',
            'line_notify_enabled' => true,
        ]);

        $mockLine = Mockery::mock(LineService::class);
        $mockLine->shouldReceive('buildGraduationDeficitMessage')->andReturn(['type' => 'flex']);
        $mockLine->shouldReceive('pushMessage')->once()->andReturn(true);
        $this->app->instance(LineService::class, $mockLine);

        $response = $this->actingAs($staff)->post(route('admin.graduation.audit.alert', $student));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // ตรวจสอบว่ามี Notification บันทึกในตาราง notifications_custom
        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $student->id,
            'type'    => 'graduation_deficit',
        ]);
    }

    public function test_student_can_view_own_graduation_status_and_download_transcript(): void
    {
        $this->setupCriteriaAndCategories();
        $student = $this->createStudent();

        // 1. หน้าตรวจสอบสถานะ
        $response = $this->actingAs($student)->get(route('student.graduation.status'));
        $response->assertStatus(200);
        $response->assertSee('สถานะกิจกรรมเพื่อการสำเร็จการศึกษา');

        // 2. ดาวน์โหลด Official Transcript PDF
        $pdfResponse = $this->actingAs($student)->get(route('student.graduation.transcript'));
        $pdfResponse->assertStatus(200);
        $this->assertEquals('application/pdf', $pdfResponse->headers->get('Content-Type'));
    }

    public function test_public_transcript_verification(): void
    {
        $this->setupCriteriaAndCategories();
        $student = $this->createStudent();

        $code = "TR-MDTU-{$student->student_id}-20260925-XYZ12";
        $response = $this->get(route('transcripts.verify', ['code' => $code]));

        $response->assertStatus(200);
        $response->assertSee('เอกสารใบรับรองกิจกรรมถูกต้องตามระเบียบ');
        $response->assertSee($student->full_name);
    }
}
