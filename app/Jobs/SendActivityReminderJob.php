<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Activity;
use App\Models\User;
use App\Services\LineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendActivityReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'notifications';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $userId,
        public readonly int $activityId
    ) {}

    public function handle(LineService $lineService): void
    {
        $user = User::find($this->userId);
        $activity = Activity::find($this->activityId);

        if (!$user || !$activity) {
            Log::warning("SendActivityReminderJob: User #{$this->userId} or Activity #{$this->activityId} not found.");
            return;
        }

        if (empty($user->line_user_id) || !$user->line_notify_enabled) {
            return;
        }

        $message = $lineService->buildReminderMessage($activity, $user->full_name ?? 'นักศึกษา');
        $success = $lineService->pushMessage($user->line_user_id, [$message]);

        if ($success) {
            Log::info("SendActivityReminderJob: Sent reminder to User #{$user->id} for Activity #{$activity->id}");
        } else {
            Log::warning("SendActivityReminderJob: Failed to send reminder to User #{$user->id}");
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("SendActivityReminderJob failed permanently for User #{$this->userId}", [
            'activity_id' => $this->activityId,
            'error'       => $exception->getMessage(),
        ]);
    }
}
