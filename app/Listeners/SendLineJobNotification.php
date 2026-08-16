<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\JobPublished;
use App\Services\LineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendLineJobNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $tries    = 3;
    public int $backoff  = 30;

    public function __construct(
        private readonly LineService $lineService
    ) {}

    public function handle(JobPublished $event): void
    {
        // ป้องกันการส่งซ้ำ (Idempotency Lock 60 วินาที)
        $lockKey = "line_notify_job_{$event->job->id}";
        if (!Cache::add($lockKey, true, 60)) {
            Log::info('Duplicate LINE job notification prevented', ['job_id' => $event->job->id]);
            return;
        }

        try {
            $message = $this->lineService->buildJobMessage($event->job);
            $this->lineService->broadcastToLinkedUsers([$message]);

            Log::info('LINE job notification sent', ['job_id' => $event->job->id]);
        } catch (\Throwable $e) {
            Log::error('LINE job notification failed', [
                'job_id' => $event->job->id,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
