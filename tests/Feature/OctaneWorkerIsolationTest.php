<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OctaneWorkerIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_consecutive_requests_maintain_strict_user_memory_isolation(): void
    {
        $userA = User::factory()->create([
            'student_id' => '65099001',
            'full_name'  => 'Isolation Student A',
            'role'       => 'student',
            'is_active'  => true,
        ]);

        $userB = User::factory()->create([
            'student_id' => '65099002',
            'full_name'  => 'Isolation Student B',
            'role'       => 'student',
            'is_active'  => true,
        ]);

        for ($i = 0; $i < 10; $i++) {
            // User A request
            Sanctum::actingAs($userA);
            $responseA = $this->getJson('/api/user');
            $responseA->assertOk();
            $this->assertEquals('Isolation Student A', $responseA->json('full_name'));
            $this->assertEquals($userA->id, $responseA->json('id'));

            // User B request immediately following in same memory process
            Sanctum::actingAs($userB);
            $responseB = $this->getJson('/api/user');
            $responseB->assertOk();
            $this->assertEquals('Isolation Student B', $responseB->json('full_name'));
            $this->assertEquals($userB->id, $responseB->json('id'));
        }
    }
}
