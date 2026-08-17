<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiLoadBalancerService;
use App\Services\FaceVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiLoadBalancerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'services.ai_server.urls'                      => [
                'http://192.168.1.222:8001',
                'http://192.168.1.223:8001',
            ],
            'services.ai_server.key'                       => 'test-ai-key',
            'services.ai_server.timeout'                   => 4,
            'services.ai_server.retry'                     => 2,
            'services.ai_server.circuit_breaker_threshold' => 3,
            'services.ai_server.circuit_breaker_cooldown'  => 30,
        ]);
    }

    private function createStudent(): User
    {
        return User::factory()->create([
            'role'               => 'student',
            'student_id'         => 'LB_' . Str::random(5),
            'face_descriptor'    => array_fill(0, 512, 0.05),
            'face_descriptor_js' => array_fill(0, 128, 0.05),
        ]);
    }

    public function test_ai_load_balancer_resolves_configured_node_pool(): void
    {
        $lb = new AiLoadBalancerService();

        $nodes = $lb->getNodes();
        $this->assertCount(2, $nodes);
        $this->assertEquals('http://192.168.1.222:8001', $nodes[0]);
        $this->assertEquals('http://192.168.1.223:8001', $nodes[1]);
    }

    public function test_ai_load_balancer_distributes_requests_via_round_robin(): void
    {
        $lb = new AiLoadBalancerService();

        $node1 = $lb->getNextNode();
        $node2 = $lb->getNextNode();
        $node3 = $lb->getNextNode();

        $this->assertNotEquals($node1, $node2);
        $this->assertEquals($node1, $node3);
    }

    public function test_ai_load_balancer_automatically_fails_over_when_primary_node_fails(): void
    {
        Http::fake([
            'http://192.168.1.222:8001/verify' => Http::response(['error' => 'Server Overloaded'], 503),
            'http://192.168.1.223:8001/verify' => Http::response([
                'status'           => 'success',
                'is_match'         => true,
                'score_percentage' => 91.5,
                'liveness_passed'  => true,
            ], 200),
        ]);

        $lb = new AiLoadBalancerService();

        $response = $lb->executeWithFailover(function (string $nodeUrl, string $apiKey, int $timeout) {
            $res = Http::timeout($timeout)->post(rtrim($nodeUrl, '/') . '/verify');
            if (!$res->successful()) {
                throw new \RuntimeException("HTTP {$res->status()}");
            }
            return $res->json();
        });

        $this->assertTrue($response['result']['is_match']);
        $this->assertEquals('http://192.168.1.223:8001', $response['node_used']);
        $this->assertEquals(1, $response['failovers']);
    }

    public function test_ai_circuit_breaker_trips_after_consecutive_failures(): void
    {
        $node1 = 'http://192.168.1.222:8001';
        $node2 = 'http://192.168.1.223:8001';

        $lb = new AiLoadBalancerService();

        $this->assertFalse($lb->isCircuitOpen($node1));

        // Record 3 failures
        $lb->recordFailure($node1, 'Timeout');
        $lb->recordFailure($node1, 'Timeout');
        $lb->recordFailure($node1, 'Timeout');

        $this->assertTrue($lb->isCircuitOpen($node1));

        // Healthy nodes pool should now only contain Node 2
        $healthy = $lb->getHealthyNodes();
        $this->assertCount(1, $healthy);
        $this->assertEquals($node2, $healthy[0]);

        // Success on node 1 resets circuit
        $lb->recordSuccess($node1);
        $this->assertFalse($lb->isCircuitOpen($node1));
        $this->assertCount(2, $lb->getHealthyNodes());
    }

    public function test_ai_load_balancer_checks_health_across_all_cluster_nodes(): void
    {
        Http::fake([
            'http://192.168.1.222:8001/health' => Http::response([
                'status' => 'healthy',
                'models' => ['insightface' => true],
            ], 200),
            'http://192.168.1.223:8001/health' => Http::response([
                'status' => 'healthy',
                'models' => ['insightface' => true, 'yolov8' => true],
            ], 200),
        ]);

        $lb = new AiLoadBalancerService();
        $health = $lb->checkAllNodesHealth();

        $this->assertCount(2, $health);
        $this->assertTrue($health[0]['available']);
        $this->assertTrue($health[1]['available']);
        $this->assertArrayHasKey('latency_ms', $health[0]);
    }

    public function test_face_verification_service_integrates_seamlessly_with_load_balancer(): void
    {
        Http::fake([
            'http://192.168.1.222:8001/verify' => Http::response([
                'status'           => 'success',
                'is_match'         => true,
                'face_match'       => true,
                'similarity'       => 0.89,
                'score_percentage' => 89.0,
                'liveness_passed'  => true,
                'processing_ms'    => 120,
            ], 200),
            'http://192.168.1.223:8001/verify' => Http::response([
                'status'           => 'success',
                'is_match'         => true,
                'face_match'       => true,
                'similarity'       => 0.89,
                'score_percentage' => 89.0,
                'liveness_passed'  => true,
                'processing_ms'    => 120,
            ], 200),
            '*/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        $student = $this->createStudent();
        $service = new FaceVerificationService();

        $fakeBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-camera-frame-data');
        $result = $service->verifyFace($student, $fakeBase64);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_match']);
        $this->assertArrayHasKey('node_used', $result);
        $this->assertContains($result['node_used'], [
            'http://192.168.1.222:8001',
            'http://192.168.1.223:8001',
        ]);
    }
}
