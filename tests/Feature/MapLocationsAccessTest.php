<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapLocationsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_can_view_map_locations_without_login(): void
    {
        Activity::factory()->create([
            'latitude'  => 16.4745,
            'longitude' => 102.8235,
            'status'    => 'open',
        ]);
        JobListing::create([
            'title'      => 'Library Assistant',
            'position'   => 'Assistant',
            'job_type'   => 'parttime',
            'quota'      => 2,
            'location'   => 'Central Library',
            'latitude'   => 16.4750,
            'longitude'  => 102.8240,
            'start_date' => now()->addDays(3)->toDateString(),
            'gender'     => 'any',
            'status'     => 'open',
            'created_by' => 1,
        ]);

        $response = $this->getJson(route('api.map.locations'));

        $response->assertOk()
            ->assertJsonStructure(['success', 'locations', 'activities', 'jobs', 'landmarks'])
            ->assertJsonCount(1, 'activities')
            ->assertJsonCount(1, 'jobs')
            ->assertJsonCount(5, 'landmarks');
    }

    public function test_live_location_update_requires_login(): void
    {
        $this->postJson(route('api.map.update_location'), [
            'latitude'  => 16.4745,
            'longitude' => 102.8235,
        ])->assertUnauthorized();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.map.update_location'), [
                'latitude'  => 16.4745,
                'longitude' => 102.8235,
            ])
            ->assertOk();
    }

    public function test_map_locations_endpoint_is_rate_limited_for_public_use(): void
    {
        // api-general limiter: 60/min per identity, 300/min per IP.
        // Simulate a heavy scraper hitting the endpoint from one IP.
        // Use a unique source IP so the limiter state never leaks across
        // test processes (cache store may not be isolated on all machines).
        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.' . random_int(1, 254),
        ]);

        $response = null;
        for ($i = 0; $i < 305; $i++) {
            $response = $this->getJson(route('api.map.locations'));
            if ($response->status() === 429) {
                break;
            }
        }

        $response->assertStatus(429);
    }
}
