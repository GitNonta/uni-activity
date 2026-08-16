<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\FaceVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_face_verification_service_attaches_x_api_key_header(): void
    {
        config([
            'services.ai_server.url' => 'http://127.0.0.1:8001',
            'services.ai_server.key' => 'test-secure-api-key-999',
        ]);

        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response([
                'status' => 'ok',
                'models' => ['insightface' => true, 'yolov8' => true, 'liveness' => true],
            ], 200),
            'http://127.0.0.1:8001/verify' => Http::response([
                'status' => 'success',
                'is_match' => true,
                'score_percentage' => 95.5,
                'liveness_passed' => true,
            ], 200),
        ]);

        $user = User::factory()->create([
            'student_id' => 'SECURE_TEST_01',
            'face_descriptor' => array_fill(0, 512, 0.05),
        ]);

        $service = new FaceVerificationService();
        $fakeBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-jpg-binary-data');

        $result = $service->verifyFace($user, $fakeBase64, ['mode' => 'python']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_match']);

        // Verify that Http request sent the X-API-Key header
        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8001/verify'
                && $request->hasHeader('X-API-Key', 'test-secure-api-key-999');
        });
    }

    public function test_cors_configuration_contains_allowed_origins(): void
    {
        $corsOrigins = config('cors.allowed_origins');
        $this->assertIsArray($corsOrigins);
        $this->assertNotEmpty($corsOrigins);

        $allowedHeaders = config('cors.allowed_headers');
        $this->assertIsArray($allowedHeaders);
        $this->assertTrue(in_array('X-API-Key', $allowedHeaders) || in_array('*', $allowedHeaders));
    }
}
