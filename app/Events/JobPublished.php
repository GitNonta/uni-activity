<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\JobListing;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly JobListing $job
    ) {}
}
