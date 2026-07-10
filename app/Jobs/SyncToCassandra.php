<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\CassandraService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncToCassandra implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Message $message)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(CassandraService $cassandra): void
    {
        try {
            $cassandra->logMessage(
                $this->message->room_id,
                $this->message->id,
                $this->message->user_id,
                $this->message->body,
                $this->message->type,
                $this->message->created_at
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Cassandra sync failed in Job: " . $e->getMessage());
        }
    }
}
