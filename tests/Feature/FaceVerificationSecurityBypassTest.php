<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FaceVerificationSecurityBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai_server.urls' => null,
            'services.ai_server.url'  => 'http://127.0.0.1:8082',
            'services.ai_server.key'  => 'test-secret-key-12345',
        ]);
        Cache::flush();
    }

    private function createTestActivity(array $attributes = []): Activity
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $creator = User::firstOrCreate(
            ['email' => 'admin@pkru.ac.th'],
            ['role' => 'admin', 'full_name' => 'Admin', 'password' => bcrypt('password')]
        );

        return Activity::create(array_merge([
            'title'             => 'Mandatory Face Verification Activity',
            'description'       => 'Test Description',
            'location'          => 'Main Hall',
            'activity_date'     => now()->toDateString(),
            'start_time'        => '08:00',
            'end_time'          => '17:00',
            'activity_hours'    => 3,
            'max_participants'  => 100,
            'register_open_at'  => now()->subDays(5),
            'register_close_at' => now()->addDays(2),
            'category_id'       => $category->id,
            'created_by'        => $creator->id,
            'status'            => 'open',
            'qr_token'          => 'sec-face-token-' . Str::random(10),
            'require_face_scan' => true,
            'checkin_open_at'   => now()->subHour(),
            'checkin_close_at'  => now()->addHours(2),
        ], $attributes));
    }

    public function test_attacker_cannot_bypass_face_verification_using_client_side_fake_scores(): void
    {
        // Mock Python AI Server returning a mismatch (e.g. attacker sends wrong face or random image)
        Http::fake([
            'http://127.0.0.1:8082/health' => Http::response(['status' => 'healthy', 'models' => ['retinaface', 'arcface']], 200),
            'http://127.0.0.1:8082/verify' => Http::response([
                'success'          => true,
                'is_match'         => false,
                'score_percentage' => 22.5,
                'similarity'       => 0.225,
                'distance'         => 0.775,
                'threshold'        => 0.60,
                'liveness_passed'  => true,
                'liveness_score'   => 0.95,
            ], 200),
        ]);

        $student = User::factory()->create([
            'student_id'      => '6511111111',
            'face_descriptor' => array_fill(0, 512, 0.05),
            'role'            => 'student',
            'is_active'       => true,
        ]);

        $activity = $this->createTestActivity();

        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        // Fake image base64
        $fakeSelfieBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-image-bytes');

        // Attacker sends spoofed client-side match scores in request payload
        $response = $this->actingAs($student)->post(route('checkin.store', ['token' => $activity->qr_token]), [
            'selfie'               => $fakeSelfieBase64,
            'js_face_match_score'  => 99.9,
            'js_face_match_passed' => true,
            'latitude'             => 7.890,
            'longitude'            => 98.390,
        ]);

        // Server MUST reject the request
        $response->assertSessionHas('error');

        // Verify that NO Attendance record was created
        $this->assertDatabaseMissing('attendances', [
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_attacker_cannot_checkin_when_liveness_detection_fails(): void
    {
        // Mock Python AI Server returning a photo spoof (liveness failed)
        Http::fake([
            'http://127.0.0.1:8082/health' => Http::response(['status' => 'healthy', 'models' => ['retinaface', 'arcface']], 200),
            'http://127.0.0.1:8082/verify' => Http::response([
                'success'          => true,
                'is_match'         => true,
                'score_percentage' => 88.0,
                'liveness_passed'  => false, // Spoof detected!
                'liveness_score'   => 0.12,
            ], 200),
        ]);

        $student = User::factory()->create([
            'student_id'      => '6522222222',
            'face_descriptor' => array_fill(0, 512, 0.05),
            'role'            => 'student',
            'is_active'       => true,
        ]);

        $activity = $this->createTestActivity();

        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $fakeSelfieBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-image-bytes');

        $response = $this->actingAs($student)->post(route('checkin.store', ['token' => $activity->qr_token]), [
            'selfie'    => $fakeSelfieBase64,
            'latitude'  => 7.890,
            'longitude' => 98.390,
        ]);

        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
        ]);
    }

    public function test_legitimate_student_with_matching_face_checks_in_successfully(): void
    {
        // Mock Python AI Server returning a valid match
        Http::fake([
            'http://127.0.0.1:8082/health' => Http::response(['status' => 'healthy', 'models' => ['retinaface', 'arcface']], 200),
            'http://127.0.0.1:8082/verify' => Http::response([
                'success'          => true,
                'is_match'         => true,
                'score_percentage' => 91.5,
                'similarity'       => 0.915,
                'distance'         => 0.085,
                'threshold'        => 0.60,
                'liveness_passed'  => true,
                'liveness_score'   => 0.98,
            ], 200),
        ]);

        $student = User::factory()->create([
            'student_id'      => '6533333333',
            'face_descriptor' => array_fill(0, 512, 0.05),
            'role'            => 'student',
            'is_active'       => true,
        ]);

        $activity = $this->createTestActivity();

        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $selfieBase64 = 'data:image/jpeg;base64,' . base64_encode('real-face-bytes');

        $response = $this->actingAs($student)->post(route('checkin.store', ['token' => $activity->qr_token]), [
            'selfie'    => $selfieBase64,
            'latitude'  => 7.890,
            'longitude' => 98.390,
        ]);

        $response->assertViewIs('checkin.success');

        // Verify that attendance is stored with the SERVER's calculated score (91.5)
        $this->assertDatabaseHas('attendances', [
            'user_id'           => $student->id,
            'activity_id'       => $activity->id,
            'face_match_passed' => true,
            'face_match_score'  => 91.5,
        ]);
    }

    public function test_student_without_registered_face_profile_cannot_checkin_to_face_required_activity(): void
    {
        $student = User::factory()->create([
            'student_id'      => '6599999999',
            'face_descriptor' => null, // No registered face descriptor
            'role'            => 'student',
            'is_active'       => true,
        ]);

        $activity = $this->createTestActivity();

        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $selfieBase64 = 'data:image/jpeg;base64,' . base64_encode('any-face-bytes');

        $response = $this->actingAs($student)->post(route('checkin.store', ['token' => $activity->qr_token]), [
            'selfie'    => $selfieBase64,
            'latitude'  => 7.890,
            'longitude' => 98.390,
        ]);

        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
        ]);
    }
}
