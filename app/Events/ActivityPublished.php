<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Activity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Activity $activity
    ) {}
}
