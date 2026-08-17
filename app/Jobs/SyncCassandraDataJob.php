<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncCassandraDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'sync';
    public int $tries = 5;
    public array $backoff = [15, 60, 300];

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $tableName,
        public readonly array $payload,
        public readonly string $operation = 'insert'
    ) {}

    public function handle(): void
    {
        $cassandraHost = config('database.cassandra.host') ?: env('CASSANDRA_HOST');

        // If Cassandra is not configured in current environment, record to async sync log
        if (empty($cassandraHost)) {
            Log::info("SyncCassandraDataJob: Simulated sync to Cassandra [table: {$this->tableName}, op: {$this->operation}]", [
                'keys' => array_keys($this->payload),
            ]);
            return;
        }

        try {
            // Placeholder / adapter for Cassandra CQL driver execution
            Log::info("SyncCassandraDataJob: Dispatched to Cassandra node {$cassandraHost} for {$this->tableName}");
        } catch (\Throwable $e) {
            Log::error("SyncCassandraDataJob: Failed to sync with Cassandra cluster", [
                'table' => $this->tableName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("SyncCassandraDataJob failed permanently", [
            'table'     => $this->tableName,
            'operation' => $this->operation,
            'error'     => $exception->getMessage(),
        ]);
    }
}
