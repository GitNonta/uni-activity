<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Registration;
use App\Models\User;
use App\Services\FaceVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai_server.url' => 'http://127.0.0.1:8001',
            'services.ai_server.key' => 'test-ai-key-integration',
        ]);
    }

    private function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'               => 'student',
            'student_id'         => 'AI_TEST_' . Str::random(5),
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
            ['email' => 'admin@pkru.ac.th'],
            ['role' => 'admin', 'full_name' => 'Admin', 'password' => bcrypt('password')]
        );

        return Activity::create(array_merge([
            'title'                      => 'AI Face Verification Activity',
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
            'require_face_scan'          => true,
            'require_selfie_verification'=> true,
            'face_scan_method'           => 'python',
            'created_by'                 => $creator->id,
            'qr_token'                   => Str::random(10),
            'qr_checkout_token'          => Str::random(10),
        ], $attributes));
    }

    public function test_face_verification_service_verifies_valid_face_successfully(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response([
                'status' => 'ok',
                'models' => ['insightface' => true, 'yolov8' => true, 'liveness' => true],
            ], 200),
            'http://127.0.0.1:8001/verify' => Http::response([
                'status'           => 'success',
                'is_match'         => true,
                'face_match'       => true,
                'similarity'       => 0.885,
                'score_percentage' => 88.5,
                'liveness_passed'  => true,
                'liveness_score'   => 0.94,
                'message'          => 'Face verified ✓ (88.5%) — Liveness confirmed',
            ], 200),
        ]);

        $user = $this->createStudent();
        $service = new FaceVerificationService();
        $fakeBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-valid-face-data');

        $result = $service->verifyFace($user, $fakeBase64, ['mode' => 'python']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_match']);
        $this->assertEquals(88.5, $result['score_percentage']);
        $this->assertTrue($result['liveness_passed']);
    }

    public function test_face_verification_service_detects_mismatch_face(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response([
                'status' => 'ok',
                'models' => ['insightface' => true, 'yolov8' => true, 'liveness' => true],
            ], 200),
            'http://127.0.0.1:8001/verify' => Http::response([
                'status'           => 'success',
                'is_match'         => false,
                'face_match'       => false,
                'similarity'       => 0.32,
                'score_percentage' => 32.0,
                'liveness_passed'  => true,
                'liveness_score'   => 0.90,
                'message'          => 'Face does not match (32.0%)',
            ], 200),
        ]);

        $user = $this->createStudent();
        $service = new FaceVerificationService();
        $fakeBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-mismatch-face');

        $result = $service->verifyFace($user, $fakeBase64, ['mode' => 'python']);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_match']);
        $this->assertEquals(32.0, $result['score_percentage']);
    }

    public function test_face_verification_service_detects_liveness_failure_photo_spoof(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response([
                'status' => 'ok',
                'models' => ['insightface' => true, 'yolov8' => true, 'liveness' => true],
            ], 200),
            'http://127.0.0.1:8001/verify' => Http::response([
                'status'           => 'success',
                'is_match'         => false,
                'face_match'       => true,
                'similarity'       => 0.85,
                'score_percentage' => 85.0,
                'liveness_passed'  => false,
                'liveness_score'   => 0.35,
                'message'          => 'Face matches (85.0%) but liveness check failed',
            ], 200),
        ]);

        $user = $this->createStudent();
        $service = new FaceVerificationService();
        $fakeBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-photo-spoof');

        $result = $service->verifyFace($user, $fakeBase64, ['mode' => 'python']);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_match']);
        $this->assertFalse($result['liveness_passed']);
    }

    public function test_face_verification_service_handles_server_down_gracefully(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response(null, 500),
        ]);

        $user = $this->createStudent();
        $service = new FaceVerificationService();
        $fakeBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-face-data');

        $result = $service->verifyFace($user, $fakeBase64, ['mode' => 'python']);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertTrue($result['fallback_recommended']);
    }

    public function test_face_verification_service_client_js_mode(): void
    {
        $user = $this->createStudent();
        $service = new FaceVerificationService();
        $fakeBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-face-data');

        $result = $service->verifyFace($user, $fakeBase64, [
            'mode' => 'js',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('js', $result['mode']);
        $this->assertIsArray($result['descriptor_128d']);
        $this->assertCount(128, $result['descriptor_128d']);
        $this->assertArrayHasKey('thresholds', $result);
    }

    public function test_checkin_realtime_verify_frame_endpoint(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/verify' => Http::response([
                'status'           => 'success',
                'is_match'         => true,
                'face_match'       => true,
                'score_percentage' => 92.4,
                'liveness_passed'  => true,
                'liveness_score'   => 0.95,
                'detector_used'    => 'scrfd+arcface+liveness',
            ], 200),
        ]);

        $student = $this->createStudent();
        $activity = $this->createActivity();

        $response = $this->actingAs($student)->postJson(route('checkin.verify_frame', $activity->qr_token), [
            'image' => 'data:image/jpeg;base64,' . base64_encode('test-webcam-frame'),
        ]);

        $response->assertOk();
        $response->assertJson([
            'status'           => 'success',
            'is_match'         => true,
            'score_percentage' => 92.4,
            'liveness_passed'  => true,
        ]);
    }

    public function test_profile_photo_upload_extracts_and_encrypts_both_512d_and_128d_descriptors(): void
    {
        $fake512D = array_fill(0, 512, 0.035);
        $fake128D = array_fill(0, 128, 0.075);

        Http::fake([
            'http://127.0.0.1:8001/extract' => Http::response([
                'status'         => 'success',
                'embedding_512d' => $fake512D,
                'embedding_128d' => $fake128D,
                'detector_used'  => 'scrfd+arcface',
            ], 200),
        ]);

        $student = $this->createStudent([
            'face_descriptor'    => null,
            'face_descriptor_js' => null,
            'profile_photo'      => null,
        ]);

        $file = UploadedFile::fake()->image('my_profile.jpg', 400, 400);

        $response = $this->actingAs($student)->post(route('profile.photo.upload'), [
            'profile_photo' => $file,
        ]);

        $response->assertRedirect();

        $student->refresh();
        $this->assertNotNull($student->profile_photo);
        $this->assertIsArray($student->face_descriptor);
        $this->assertCount(512, $student->face_descriptor);
        $this->assertIsArray($student->face_descriptor_js);
        $this->assertCount(128, $student->face_descriptor_js);
    }

    public function test_extract_missing_face_encodings_artisan_command(): void
    {
        $photoRelPath = 'profile-photos/test_avatar_' . time() . '.jpg';
        $fullPath = storage_path('app/public/' . $photoRelPath);
        @mkdir(dirname($fullPath), 0777, true);
        file_put_contents($fullPath, 'fake-avatar-jpg-content');

        $user = $this->createStudent([
            'face_descriptor'    => null,
            'face_descriptor_js' => null,
            'profile_photo'      => $photoRelPath,
        ]);

        Http::fake([
            'http://127.0.0.1:8001/extract' => Http::response([
                'status'         => 'success',
                'embedding_512d' => array_fill(0, 512, 0.06),
                'embedding_128d' => array_fill(0, 128, 0.06),
            ], 200),
        ]);

        $this->artisan('face:extract-missing')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->face_descriptor);
        $this->assertNotNull($user->face_descriptor_js);

        @unlink($fullPath);
    }
}
