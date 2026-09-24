<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\GeoSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GeoSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'               => 'student',
            'student_id'         => 'GEO_' . Str::random(5),
            'face_descriptor'    => array_fill(0, 512, 0.05),
            'face_descriptor_js' => array_fill(0, 128, 0.05),
        ], $attributes));
    }

    private function createActivityWithGeofence(array $attributes = []): Activity
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $creator = User::firstOrCreate(
            ['email' => 'admin_geo@pkru.ac.th'],
            ['role' => 'admin', 'full_name' => 'Admin', 'password' => bcrypt('password')]
        );

        return Activity::create(array_merge([
            'title'                      => 'Geofenced Campus Activity',
            'location'                   => 'Main Hall PKRU',
            'latitude'                   => 7.8943200,
            'longitude'                  => 98.3976500,
            'checkin_radius'             => 100, // 100 meters
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
            'qr_token'                   => 'GEO_TEST_TOKEN_' . Str::random(10),
            'created_by'                 => $creator->id,
        ], $attributes));
    }

    public function test_user_cannot_checkin_with_synthetic_accuracy_mock_gps(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivityWithGeofence();
        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $checkInService = app(CheckInService::class);

        // Fake GPS app reporting impossible 0.0m accuracy
        $mockTelemetry = json_encode([
            'accuracy'        => 0.0,
            'sample_count'    => 3,
            'jitter_variance' => 0.0,
        ]);

        $result = $checkInService->processCheckIn(
            token: $activity->qr_token,
            user: $student,
            method: 'qr_scan',
            latitude: (float) $activity->latitude,
            longitude: (float) $activity->longitude,
            metaData: ['geo_telemetry' => $mockTelemetry]
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ความแม่นยำสังเคราะห์', $result['message']);
        $this->assertDatabaseMissing('attendances', [
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_user_cannot_checkin_with_devtools_emulated_gps(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivityWithGeofence();
        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $checkInService = app(CheckInService::class);

        // DevTools / WebDriver automation emulation
        $devToolsTelemetry = json_encode([
            'accuracy'     => 15.0,
            'is_webdriver' => true,
        ]);

        $result = $checkInService->processCheckIn(
            token: $activity->qr_token,
            user: $student,
            method: 'qr_scan',
            latitude: (float) $activity->latitude,
            longitude: (float) $activity->longitude,
            metaData: ['geo_telemetry' => $devToolsTelemetry]
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Developer Tools', $result['message']);
    }

    public function test_user_cannot_checkin_with_zero_jitter_mock_gps(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivityWithGeofence();
        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $checkInService = app(CheckInService::class);

        // Stationary Mock app with zero satellite jitter
        $mockTelemetry = json_encode([
            'accuracy'           => 3.0,
            'sample_count'       => 3,
            'jitter_variance'    => 0.0,
            'is_stationary_mock' => true,
        ]);

        $result = $checkInService->processCheckIn(
            token: $activity->qr_token,
            user: $student,
            method: 'qr_scan',
            latitude: (float) $activity->latitude,
            longitude: (float) $activity->longitude,
            metaData: ['geo_telemetry' => $mockTelemetry]
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('พิกัดนิ่งสนิทผิดธรรมชาติ', $result['message']);
    }

    public function test_user_cannot_checkin_with_impossible_teleportation_velocity(): void
    {
        $student = $this->createStudent();
        $activity1 = $this->createActivityWithGeofence(['title' => 'Bangkok Activity', 'latitude' => 13.7563000, 'longitude' => 100.5018000]);
        $activity2 = $this->createActivityWithGeofence(['title' => 'Phuket Activity', 'latitude' => 7.8943200, 'longitude' => 98.3976500]);

        // Previous check-in 10 minutes ago in Bangkok
        $att = new Attendance([
            'user_id'           => $student->id,
            'activity_id'       => $activity1->id,
            'status'            => 'approved',
            'method'            => 'qr_scan',
            'checkin_latitude'  => 13.7563000,
            'checkin_longitude' => 100.5018000,
        ]);
        $att->created_at = now()->subMinutes(10);
        $att->updated_at = now()->subMinutes(10);
        $att->save();

        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity2->id,
            'status'      => 'approved',
        ]);

        $checkInService = app(CheckInService::class);

        // Attempting to check in 700km away 10 minutes later (~4200 km/h)
        $telemetry = json_encode([
            'accuracy'        => 12.0,
            'sample_count'    => 3,
            'jitter_variance' => 0.0000021,
        ]);

        $result = $checkInService->processCheckIn(
            token: $activity2->qr_token,
            user: $student,
            method: 'qr_scan',
            latitude: (float) $activity2->latitude,
            longitude: (float) $activity2->longitude,
            metaData: ['geo_telemetry' => $telemetry]
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ความเร็วเหนือจริง', $result['message']);
    }

    public function test_user_can_checkin_with_authentic_satellite_gps(): void
    {
        $student = $this->createStudent();
        $activity = $this->createActivityWithGeofence();
        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $checkInService = app(CheckInService::class);

        // Authentic smartphone GPS telemetry with normal jitter & 10m accuracy
        $authenticTelemetry = json_encode([
            'accuracy'           => 9.5,
            'altitude'           => 18.2,
            'sample_count'       => 3,
            'jitter_variance'    => 0.0000015,
            'is_stationary_mock' => false,
            'is_webdriver'       => false,
            'touch_mismatch'     => false,
        ]);

        $result = $checkInService->processCheckIn(
            token: $activity->qr_token,
            user: $student,
            method: 'qr_scan',
            latitude: (float) $activity->latitude,
            longitude: (float) $activity->longitude,
            metaData: ['geo_telemetry' => $authenticTelemetry]
        );

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('attendances', [
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'pending',
        ]);
    }
}
