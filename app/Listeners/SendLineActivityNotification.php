<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ActivityPublished;
use App\Services\LineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendLineActivityNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'line-notifications';
    public int $tries    = 3;
    public int $backoff  = 30;

    public function __construct(
        private readonly LineService $lineService
    ) {}

    public function handle(ActivityPublished $event): void
    {
        try {
            $message = $this->lineService->buildActivityMessage($event->activity);
            $this->lineService->broadcastToLinkedUsers([$message]);

            Log::info('LINE activity notification sent', ['activity_id' => $event->activity->id]);
        } catch (\Throwable $e) {
            Log::error('LINE activity notification failed', [
                'activity_id' => $event->activity->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
