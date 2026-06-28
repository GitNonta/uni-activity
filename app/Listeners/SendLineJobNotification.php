<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\JobPublished;
use App\Services\LineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendLineJobNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'line-notifications';
    public int $tries    = 3;
    public int $backoff  = 30;

    public function __construct(
        private readonly LineService $lineService
    ) {}

    public function handle(JobPublished $event): void
    {
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
