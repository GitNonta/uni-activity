<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAutomatedBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public readonly string $type = 'full',
        public readonly bool $notify = true
    ) {}

    public function handle(BackupService $backupService): void
    {
        $backupService->runBackup($this->type, $this->notify);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ProcessAutomatedBackupJob failed: " . $exception->getMessage());
    }
}
