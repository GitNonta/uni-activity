<?php

namespace App\Console\Commands;

use App\Models\ChatMessage;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Repositories\ChatRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateFromMongo extends Command
{
    protected $signature = 'chat:migrate-from-mongo';
    protected $description = 'Migrate chat messages from MongoDB to PostgreSQL and Cassandra';

    public function handle(ChatRepository $repository)
    {
        $this->info("Starting migration from MongoDB...");

        // Check if ChatMessage model works (MongoDB connection)
        try {
            $total = ChatMessage::count();
        } catch (\Exception $e) {
            $this->error("Failed to connect to MongoDB: " . $e->getMessage());
            return 1;
        }

        if ($total === 0) {
            $this->warn("No messages found in MongoDB.");
            return 0;
        }

        $this->info("Found $total messages. Processing...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        ChatMessage::chunk(100, function ($mongoMessages) use ($repository, $bar) {
            foreach ($mongoMessages as $mongoMsg) {
                // 1. Identify Room
                $roomId = $this->getOrCreateRoom($mongoMsg, $repository);

                // 2. Create Message in PostgreSQL
                $message = Message::create([
                    'id' => (string) $mongoMsg->_id,
                    'room_id' => $roomId,
                    'user_id' => $mongoMsg->sender_id,
                    'body' => $mongoMsg->message ?? '',
                    'type' => $mongoMsg->sender_role === 'system' ? 'system' : 'text',
                    'created_at' => $mongoMsg->created_at,
                    'updated_at' => $mongoMsg->updated_at,
                ]);

                // 3. Mark as read if needed
                if ($mongoMsg->read_at) {
                    $message->update(['read_at' => $mongoMsg->read_at]);
                }

                // 4. Sync to Cassandra
                $repository->syncToCassandra($message);

                $bar->advance();
            }
        });

        $bar->finish();
        $this->info("\nMigration completed successfully!");

        return 0;
    }

    private function getOrCreateRoom(ChatMessage $mongoMsg, ChatRepository $repository): string
    {
        $jobId = $mongoMsg->job_id;
        $studentId = $mongoMsg->student_id;

        if (!$studentId && $mongoMsg->sender_role === 'student') {
            $studentId = $mongoMsg->sender_id;
        }

        if (!$studentId) {
             // Fallback: search for other messages in the same job thread to find student_id
             $fallback = ChatMessage::where('job_id', $jobId)->whereNotNull('student_id')->first();
             $studentId = $fallback ? $fallback->student_id : null;
        }

        if (!$studentId) {
            return 'system_room'; // Should not happen with good data
        }

        // Search for existing room in PostgreSQL
        $room = Room::where('job_id', $jobId)
            ->whereHas('users', function ($q) use ($studentId) {
                $q->where('users.id', $studentId);
            })
            ->first();

        if ($room) {
            return $room->id;
        }

        // Create new room
        $userIds = [$studentId];
        
        // Add participating admins
        $admins = ChatMessage::where('job_id', $jobId)
            ->where('student_id', $studentId)
            ->where('sender_role', 'admin')
            ->distinct()
            ->pluck('sender_id')
            ->toArray();
            
        $userIds = array_merge($userIds, $admins);
        $userIds = array_unique(array_filter($userIds));

        $newRoom = $repository->createRoom($userIds, 'group', "Chat for Job #$jobId", $jobId);

        return $newRoom->id;
    }
}
