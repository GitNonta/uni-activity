<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AnnouncementPublished;
use App\Services\LineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendLineAnnouncementNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'line-notifications';
    public int $tries    = 3;
    public int $backoff  = 30;

    public function __construct(
        private readonly LineService $lineService
    ) {}

    public function handle(AnnouncementPublished $event): void
    {
        try {
            $message = $this->lineService->buildAnnouncementMessage($event->announcement);
            $this->lineService->broadcastToLinkedUsers([$message]);

            Log::info('LINE announcement notification sent', ['announcement_id' => $event->announcement->id]);
        } catch (\Throwable $e) {
            Log::error('LINE announcement notification failed', [
                'announcement_id' => $event->announcement->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
