<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\UserLocationUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeLocationTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_broadcast_realtime_location(): void
    {
        Event::fake([UserLocationUpdated::class]);

        $user = User::factory()->create([
            'full_name' => 'Somchai Jaidee',
            'role' => 'student',
        ]);

        $payload = [
            'latitude' => 16.4745,
            'longitude' => 102.8235,
            'heading' => 180.5,
            'speed' => 3.2,
            'accuracy' => 10.0,
        ];

        $response = $this->actingAs($user)
            ->postJson(route('api.map.update_location'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Location updated and broadcasted successfully',
            ]);

        Event::assertDispatched(UserLocationUpdated::class, function (UserLocationUpdated $event) use ($user, $payload): bool {
            return $event->userId === $user->id
                && $event->latitude === $payload['latitude']
                && $event->longitude === $payload['longitude']
                && $event->heading === $payload['heading']
                && $event->broadcastOn()[0]->name === 'presence-map.tracking'
                && $event->broadcastAs() === 'UserLocationUpdated';
        });
    }

    public function test_unauthenticated_user_cannot_broadcast_location(): void
    {
        Event::fake();

        $payload = [
            'latitude' => 16.4745,
            'longitude' => 102.8235,
        ];

        $response = $this->postJson(route('api.map.update_location'), $payload);

        $response->assertUnauthorized();
        Event::assertNotDispatched(UserLocationUpdated::class);
    }

    public function test_invalid_coordinates_fail_validation(): void
    {
        $user = User::factory()->create();

        $payload = [
            'latitude' => 999.0, // Invalid latitude
            'longitude' => 102.8235,
        ];

        $response = $this->actingAs($user)
            ->postJson(route('api.map.update_location'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);
    }
}
