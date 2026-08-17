<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\User;
use App\Services\CheckInRiskScoringService;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckInRiskScoringTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'               => 'student',
            'student_id'         => 'RISK_' . Str::random(5),
            'face_descriptor'    => array_fill(0, 512, 0.05),
            'face_descriptor_js' => array_fill(0, 128, 0.05),
        ], $attributes));
    }

    private function createActivity(array $attributes = []): Activity
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $creator = User::firstOrCreate(
            ['email' => 'admin_risk@pkru.ac.th'],
            ['role' => 'admin', 'full_name' => 'Admin', 'password' => bcrypt('password')]
        );

        return Activity::create(array_merge([
            'title'                      => 'Risk Assessment Activity',
            'location'                   => 'Auditorium A',
            'activity_date'              => now()->format('Y-m-d'),
            'start_time'                 => '09:00',
            'end_time'                   => '12:00',
            'activity_hours'             => 3,
            'max_participants'           => 50,
            'register_open_at'           => now()->subDay(),
            'register_close_at'          => now()->addDays(2),
            'checkin_open_at'            => now()->subHour(),
            'checkin_close_at'           => now()->addHours(3),
            'category_id'                => $category->id,
            'scope'                      => 'university',
            'status'                     => 'open',
            'is_mandatory'               => false,
            'require_face_scan'          => false,
            'require_selfie_verification'=> false,
            'created_by'                 => $creator->id,
            'qr_token'                   => Str::random(10),
            'qr_checkout_token'          => Str::random(10),
        ], $attributes));
    }

    public function test_risk_scoring_service_evaluates_low_risk_for_clean_checkin(): void
    {
        $service = new CheckInRiskScoringService();

        $assessment = $service->evaluate([
            'require_face_scan'    => true,
            'face_match_passed'    => true,
            'face_match_score'     => 92.5,
            'liveness_passed'      => true,
            'has_geolocation'      => true,
            'distance_meters'      => 30.0,
            'radius_meters'        => 100.0,
            'is_shared_device'     => false,
            'other_accounts_count' => 0,
            'is_registered'        => true,
        ]);

        $this->assertEquals('low', $assessment['risk_level']);
        $this->assertFalse($assessment['is_suspicious']);
        $this->assertEquals('approved', $assessment['decision']);
        $this->assertLessThan(30, $assessment['risk_score']);
    }

    public function test_risk_scoring_service_evaluates_medium_risk_for_shared_device(): void
    {
        $service = new CheckInRiskScoringService();

        $assessment = $service->evaluate([
            'require_face_scan'    => true,
            'face_match_passed'    => true,
            'face_match_score'     => 88.0,
            'liveness_passed'      => true,
            'has_geolocation'      => true,
            'distance_meters'      => 40.0,
            'radius_meters'        => 100.0,
            'is_shared_device'     => true, // Device shared by 2 students
            'other_accounts_count' => 2,
            'is_registered'        => true,
        ]);

        // Device sharing is a probabilistic signal -> flagged for review, not outright hard-blocked
        $this->assertEquals('medium', $assessment['risk_level']);
        $this->assertTrue($assessment['is_suspicious']);
        $this->assertEquals('flagged_for_review', $assessment['decision']);
        $this->assertGreaterThanOrEqual(12, $assessment['risk_score']);
    }

    public function test_risk_scoring_service_evaluates_high_risk_for_multiple_critical_failures(): void
    {
        $service = new CheckInRiskScoringService();

        $assessment = $service->evaluate([
            'require_face_scan'    => true,
            'face_match_passed'    => false, // Mismatched face
            'face_match_score'     => 22.0,
            'liveness_passed'      => false, // Spoofed liveness
            'has_geolocation'      => true,
            'distance_meters'      => 500.0, // Out of bounds
            'radius_meters'        => 100.0,
            'is_shared_device'     => true,
            'other_accounts_count' => 5,
            'is_qr_replay'         => true,
        ]);

        $this->assertEquals('high', $assessment['risk_level']);
        $this->assertTrue($assessment['is_suspicious']);
        $this->assertEquals('rejected', $assessment['decision']);
        $this->assertGreaterThanOrEqual(70, $assessment['risk_score']);
    }

    public function test_checkin_service_stores_suspicious_flag_for_medium_risk_shared_device(): void
    {
        $activity = $this->createActivity();
        $student1 = $this->createStudent();
        $student2 = $this->createStudent();

        Registration::create([
            'user_id'     => $student1->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);
        Registration::create([
            'user_id'     => $student2->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $fp = app(\App\Services\DeviceFingerprintService::class)->generate(request());

        // Student 1 checks in first from this device
        Attendance::create([
            'user_id'            => $student1->id,
            'activity_id'        => $activity->id,
            'checked_in_at'      => now(),
            'method'             => 'qr_scan',
            'status'             => 'pending',
            'is_verified'        => true,
            'device_fingerprint' => $fp,
        ]);

        // Student 2 checks in from the same device / IP
        $checkInService = app(CheckInService::class);
        $result = $checkInService->processCheckIn(
            $activity->qr_token,
            $student2,
            'qr_scan',
        );

        $this->assertTrue($result['success']);
        
        $attendance2 = Attendance::where('user_id', $student2->id)
            ->where('activity_id', $activity->id)
            ->first();

        $this->assertNotNull($attendance2);
        // Flagged as suspicious for staff audit without blocking student
        $this->assertTrue($attendance2->is_suspicious);
    }
}
