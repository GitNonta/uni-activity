<?php
declare(strict_types=1);

/**
 * Import old chat data from PostgreSQL dump.
 * Usage: php sql/import_old_chats.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$dumpFile = __DIR__ . '/postgres_dump_utf8.sql';

// Read dump file
$contents = file_get_contents($dumpFile);
$lines = explode("\n", $contents);

function extractCopyData(array $lines, string $tableName): array
{
    $startLine = null;
    $data = [];
    
    foreach ($lines as $i => $line) {
        $line = rtrim($line, "\r\n");
        
        if (str_contains($line, "COPY public.{$tableName} (")) {
            $startLine = $i;
            continue;
        }
        
        if ($startLine !== null) {
            // Stop at next COPY or end of section
            if (str_starts_with(trim($line), 'COPY public.') || trim($line) === '.' || str_starts_with(trim($line), '-- ')) {
                break;
            }
            if ($line && !str_starts_with($line, '--')) {
                $data[] = $line;
            }
        }
    }
    
    return $data;
}

echo "=== Importing old chat data ===\n\n";

// 1. Import rooms
$roomLines = extractCopyData($lines, 'rooms');
echo "1. Rooms found: " . count($roomLines) . "\n";
$roomCount = 0;
foreach ($roomLines as $line) {
    $parts = explode("\t", $line);
    if (count($parts) < 7) continue;
    
    $id = $parts[0];
    $name = $parts[1];
    $type = $parts[2];
    $jobId = $parts[3] === '\\N' ? null : (int) $parts[3];
    $createdBy = (int) $parts[4];
    $createdAt = $parts[5] === '\\N' ? null : $parts[5];
    $updatedAt = $parts[6] === '\\N' ? null : $parts[6];
    
    DB::table('rooms')->updateOrInsert(
        ['id' => $id],
        [
            'name' => $name,
            'type' => $type,
            'job_id' => $jobId,
            'created_by' => $createdBy,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]
    );
    $roomCount++;
}
echo "   Rooms imported: {$roomCount}\n";

// 2. Import room_user
$roomUserLines = extractCopyData($lines, 'room_user');
echo "2. Room members found: " . count($roomUserLines) . "\n";
$memberCount = 0;
foreach ($roomUserLines as $line) {
    $parts = explode("\t", $line);
    if (count($parts) < 7) continue;
    
    DB::table('room_user')->updateOrInsert(
        ['room_id' => $parts[0], 'user_id' => (int) $parts[1]],
        [
            'role' => $parts[2],
            'last_read_at' => $parts[3] === '\\N' ? null : $parts[3],
            'joined_at' => $parts[4] === '\\N' ? null : $parts[4],
            'created_at' => $parts[5] === '\\N' ? null : $parts[5],
            'updated_at' => $parts[6] === '\\N' ? null : $parts[6],
        ]
    );
    $memberCount++;
}
echo "   Room members imported: {$memberCount}\n";

// 3. Import messages
$msgLines = extractCopyData($lines, 'messages');
echo "3. Messages found: " . count($msgLines) . "\n";
$msgCount = 0;
foreach ($msgLines as $line) {
    $parts = explode("\t", $line);
    if (count($parts) < 10) continue;
    
    $id = $parts[0];
    $roomId = $parts[1];
    $userId = $parts[2] === '\\N' ? null : (int) $parts[2];
    $body = $parts[3];
    $type = $parts[4];
    $readBy = $parts[5] === '\\N' ? null : $parts[5];
    $attachments = $parts[6] === '\\N' ? null : $parts[6];
    $createdAt = $parts[7] === '\\N' ? null : $parts[7];
    $updatedAt = $parts[8] === '\\N' ? null : $parts[8];
    $deletedAt = $parts[9] === '\\N' ? null : $parts[9];
    
    // Skip if room doesn't exist
    if (!DB::table('rooms')->where('id', $roomId)->exists()) continue;
    
    DB::table('messages')->updateOrInsert(
        ['id' => $id],
        [
            'room_id' => $roomId,
            'user_id' => $userId,
            'body' => $body,
            'type' => $type,
            'read_by' => $readBy,
            'attachments' => $attachments,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'deleted_at' => $deletedAt,
        ]
    );
    $msgCount++;
}
echo "   Messages imported: {$msgCount}\n";

// Verify
echo "\n=== Verification ===\n";
$tables = ['rooms', 'room_user', 'messages'];
foreach ($tables as $t) {
    $c = DB::table($t)->count();
    echo "  {$t}: {$c}\n";
}

echo "\nDone!\n";
