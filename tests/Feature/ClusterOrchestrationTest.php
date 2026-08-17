<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClusterOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/health' => Http::response([
                'status' => 'healthy',
                'models' => ['retinaface', 'arcface'],
            ], 200),
            '*' => Http::response(['status' => 'ok'], 200),
        ]);
    }

    public function test_cluster_status_artisan_command_executes_successfully(): void
    {
        $this->artisan('cluster:status')
            ->assertSuccessful();

        $this->artisan('cluster:status --json')
            ->assertSuccessful();
    }

    public function test_cluster_health_artisan_command_probe_returns_success(): void
    {
        $this->artisan('cluster:health')
            ->assertSuccessful();

        $this->artisan('cluster:health --json')
            ->assertSuccessful();
    }

    public function test_admin_can_access_cluster_control_center_and_live_metrics_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $responseView = $this->actingAs($admin)->get(route('admin.system.cluster'));
        $responseView->assertOk();
        $responseView->assertSee('Cluster Control Center');
        $responseView->assertSee('Distributed AI Face Recognition Cluster');

        $responseApi = $this->actingAs($admin)->get(route('admin.api.cluster.metrics'));
        $responseApi->assertOk();
        $responseApi->assertJsonStructure([
            'status',
            'data' => [
                'timestamp',
                'app' => ['name', 'env', 'debug', 'php_version', 'octane'],
                'database' => ['status', 'connection', 'database', 'latency_ms'],
                'redis' => ['status', 'client', 'latency_ms'],
                'queues' => ['driver', 'channels', 'total_pending', 'failed_jobs'],
                'broadcasting' => ['driver', 'host', 'port'],
                'ai_cluster' => ['cluster_state', 'total_nodes', 'healthy_nodes', 'nodes'],
                'security' => ['score', 'grade', 'app_debug_safe'],
            ],
        ]);
    }

    public function test_student_and_guest_are_denied_access_to_cluster_control_center(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        // Student is denied
        $responseStudent = $this->actingAs($student)->get(route('admin.system.cluster'));
        $this->assertTrue(
            in_array($responseStudent->status(), [403, 302], true),
            'Student should not be able to access Cluster Control Center'
        );

        $responseStudentApi = $this->actingAs($student)->get(route('admin.api.cluster.metrics'));
        $this->assertTrue(
            in_array($responseStudentApi->status(), [403, 302], true),
            'Student should not be able to access Cluster live metrics'
        );

        // Guest is redirected to login
        auth()->logout();
        $responseGuest = $this->get(route('admin.system.cluster'));
        $responseGuest->assertRedirect(route('login'));
    }
}
