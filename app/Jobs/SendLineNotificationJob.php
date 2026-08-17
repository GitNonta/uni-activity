<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLineNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, string>|null $targetLineUserIds
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?array $targetLineUserIds = null,
        public readonly ?string $lockKey = null
    ) {
        $this->onQueue('notifications');
    }

    public function handle(LineService $lineService): void
    {
        // 1. Idempotency Lock Check
        if ($this->lockKey !== null && !Cache::add($this->lockKey, true, 60)) {
            Log::info("SendLineNotificationJob: Duplicate notification prevented by lock: {$this->lockKey}");
            return;
        }

        if (empty($this->messages)) {
            Log::warning('SendLineNotificationJob: Empty messages array provided.');
            return;
        }

        // 2. Multicast or Broadcast to all linked users
        if ($this->targetLineUserIds !== null) {
            if (count($this->targetLineUserIds) === 1) {
                $lineService->pushMessage($this->targetLineUserIds[0], $this->messages);
            } else {
                $lineService->multicast($this->targetLineUserIds, $this->messages);
            }
        } else {
            $lineService->broadcastToLinkedUsers($this->messages);
        }

        Log::info('SendLineNotificationJob: LINE notification dispatched successfully.');
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendLineNotificationJob failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}
