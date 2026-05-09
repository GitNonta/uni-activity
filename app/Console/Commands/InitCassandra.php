<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Cassandra\Connection;
use Cassandra\Connection\StreamNodeConfig;

class InitCassandra extends Command
{
    protected $signature = 'chat:init-cassandra';
    protected $description = 'Initialize Cassandra keyspace and tables for chat history';

    public function handle()
    {
        $host = config('database.cassandra.host', env('CASSANDRA_HOST', 'cassandra'));
        $keyspace = config('database.cassandra.keyspace', env('CASSANDRA_KEYSPACE', 'uni_chat'));

        $this->info("Connecting to Cassandra at $host...");

        try {
            $node = new StreamNodeConfig(
                host: $host,
                port: 9042
            );

            // Connect without keyspace first to create it
            $conn = new Connection([$node]);

            $this->info("Creating keyspace $keyspace...");
            $conn->query("
                CREATE KEYSPACE IF NOT EXISTS $keyspace 
                WITH replication = {'class': 'SimpleStrategy', 'replication_factor': 1}
            ");

            // Reconnect with keyspace
            $conn = new Connection([$node], keyspace: $keyspace);
            $conn->query("USE $keyspace");

            $this->info("Creating table $keyspace.messages...");
            $conn->query("
                CREATE TABLE IF NOT EXISTS $keyspace.messages (
                    room_id text,
                    created_at timestamp,
                    message_id text,
                    user_id int,
                    body text,
                    type text,
                    PRIMARY KEY (room_id, created_at, message_id)
                ) WITH CLUSTERING ORDER BY (created_at DESC)
            ");

            $this->info("Cassandra initialized successfully!");
        } catch (\Exception $e) {
            $this->error("Failed to initialize Cassandra: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
