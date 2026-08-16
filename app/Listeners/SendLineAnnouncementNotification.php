<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AnnouncementPublished;
use App\Services\LineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendLineAnnouncementNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $tries    = 3;
    public int $backoff  = 30;

    public function __construct(
        private readonly LineService $lineService
    ) {}

    public function handle(AnnouncementPublished $event): void
    {
        // ป้องกันการส่งซ้ำ (Idempotency Lock 60 วินาที)
        $lockKey = "line_notify_announcement_{$event->announcement->id}";
        if (!Cache::add($lockKey, true, 60)) {
            Log::info('Duplicate LINE announcement notification prevented', ['announcement_id' => $event->announcement->id]);
            return;
        }

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
