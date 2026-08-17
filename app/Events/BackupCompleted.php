<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $filename,
        public readonly string $type,
        public readonly int $sizeBytes,
        public readonly string $formattedSize,
        public readonly bool $isSuccess,
        public readonly ?string $errorMessage = null
    ) {}
}
