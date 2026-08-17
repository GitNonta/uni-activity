<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DragonflyPubSubService;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DragonflyPubSubBackboneTest extends TestCase
{
    public function test_dragonfly_pubsub_service_publishes_event_successfully(): void
    {
        $service = app(DragonflyPubSubService::class);

        $result = $service->publish('test.channel', [
            'type'    => 'ping',
            'message' => 'Dragonfly PubSub Backbone Alive',
        ]);

        $this->assertIsInt($result);
    }

    public function test_dragonfly_pubsub_publishes_chat_event(): void
    {
        $service = app(DragonflyPubSubService::class);

        $result = $service->publishChatEvent('room_101', 'MessageSent', [
            'id'      => 'msg_test_1',
            'message' => 'Real-time via Dragonfly PubSub',
            'user_id' => 1,
        ]);

        $this->assertIsInt($result);
    }

    public function test_dragonfly_pubsub_publishes_presence_and_telemetry(): void
    {
        $service = app(DragonflyPubSubService::class);

        $presenceResult = $service->publishPresence(1, 'online', ['role' => 'admin']);
        $this->assertIsInt($presenceResult);

        $telemetryResult = $service->publishCheckinTelemetry([
            'activity_id' => 1,
            'attendee_id' => 5,
            'mode'        => 'qr',
        ]);
        $this->assertIsInt($telemetryResult);
    }
}
