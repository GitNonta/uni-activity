<?php

namespace App\Services;

use Cassandra\Connection;
use Cassandra\Connection\StreamNodeConfig;
use Cassandra\Request\Options\ExecuteOptions;

class CassandraService
{
    protected ?Connection $connection = null;
    protected string $keyspace;
    protected string $host;

    public function __construct()
    {
        $this->host = config('database.cassandra.host', env('CASSANDRA_HOST', 'cassandra'));
        $this->keyspace = config('database.cassandra.keyspace', env('CASSANDRA_KEYSPACE', 'uni_chat'));
    }

    /**
     * Get or create Cassandra connection.
     */
    public function getConnection(): Connection
    {
        if ($this->connection === null) {
            $node = new StreamNodeConfig(
                host: $this->host,
                port: 9042
            );
            $this->connection = new Connection([$node], keyspace: $this->keyspace);
        }
        return $this->connection;
    }

    /**
     * Log a message to Cassandra.
     */
    public function logMessage(string $roomId, string $messageId, int $userId, string $body, string $type, \DateTime $createdAt): void
    {
        $conn = $this->getConnection();
        $conn->query("
            INSERT INTO messages (room_id, created_at, message_id, user_id, body, type)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [
            $roomId,
            $createdAt->format('Y-m-d H:i:s.uO'), // Cassandra timestamp format
            $messageId,
            $userId,
            $body,
            $type
        ]);
    }

    /**
     * Get message history from Cassandra.
     */
    public function getHistory(string $roomId, ?int $limit = 50, ?\DateTime $before = null): array
    {
        $conn = $this->getConnection();
        $query = "SELECT * FROM messages WHERE room_id = ?";
        $params = [$roomId];

        if ($before) {
            $query .= " AND created_at < ?";
            $params[] = $before->format('Y-m-d H:i:s.uO');
        }

        $query .= " LIMIT ?";
        $params[] = $limit;

        $result = $conn->query($query, $params)->asRowsResult();
        
        $messages = [];
        foreach ($result as $row) {
            $messages[] = $row;
        }
        return $messages;
    }
}
