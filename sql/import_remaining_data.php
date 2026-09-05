<?php
/**
 * Import remaining data from MySQL backup into PostgreSQL
 * - notifications_custom (5 records)
 * - admin_audit_logs (15 records) with JSON fix
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Importing Remaining Data from MySQL Backup ===\n\n";

// ============================================================
// 1. Import notifications_custom (is_read is boolean in PG)
//    User #16 in MySQL became #15 in PG — map accordingly
// ============================================================
echo "--- Notifications Custom ---\n";

// Check which user IDs actually exist
$existingUserIds = DB::table('users')->pluck('id')->toArray();
echo "  Existing user IDs: " . implode(', ', $existingUserIds) . "\n\n";

// MySQL had: 1,12,13,16 for notification user_ids
// PG has:   1,12,13,15 (user 16 "พพพพ" got id 15 in PG)
$notifications = [
    [1, 12, 'ลงทะเบียนสำเร็จ', 'คุณลงทะเบียนกิจกรรม "วิ่งการกุศล Uni Run 2025" เรียบร้อยแล้ว', 'registration', false, '2026-03-17 20:32:56', '2026-03-17 20:32:56'],
    [2, 12, 'ลงทะเบียนสำเร็จ', 'คุณลงทะเบียนกิจกรรม "ลอยกระทง" เรียบร้อยแล้ว', 'registration', false, '2026-03-18 11:58:58', '2026-03-18 11:58:58'],
    [3, 13, 'ลงทะเบียนสำเร็จ', 'คุณลงทะเบียนกิจกรรม "งานบายเนียร์ Bye \'nior" เรียบร้อยแล้ว', 'registration', false, '2026-03-18 14:30:51', '2026-03-18 14:30:51'],
    [4, 15, 'ลงทะเบียนสำเร็จ', 'คุณลงทะเบียนกิจกรรม "บริจาคโลหิต" เรียบร้อยแล้ว', 'registration', false, '2026-03-20 07:08:53', '2026-03-20 07:08:53'],  // user 16→15
    [5, 12, 'ลงทะเบียนสำเร็จ', 'คุณลงทะเบียนกิจกรรม "บริจาคโลหิต" เรียบร้อยแล้ว', 'registration', false, '2026-03-20 08:33:26', '2026-03-20 08:33:26'],
];

foreach ($notifications as $n) {
    try {
        DB::table('notifications_custom')->updateOrInsert(
            ['id' => $n[0]],
            [
                'user_id' => $n[1],
                'title' => $n[2],
                'message' => $n[3],
                'type' => $n[4],
                'is_read' => $n[5],
                'created_at' => $n[6],
                'updated_at' => $n[7],
            ]
        );
        echo "  ✅ Notification #{$n[0]}: {$n[2]}\n";
    } catch (\Exception $e) {
        echo "  ❌ Notification #{$n[0]}: {$e->getMessage()}\n";
    }
}

// ============================================================
// 2. Import admin_audit_logs with proper JSON handling
// ============================================================
echo "\n--- Admin Audit Logs ---\n";

$backupFile = __DIR__ . '/backup_utf8.sql';
$content = file_get_contents($backupFile);

// Extract the audit logs INSERT block
if (preg_match('/INSERT INTO `admin_audit_logs` VALUES (.+?);\r?\n/s', $content, $matches)) {
    $valuesStr = $matches[1];

    // Parse rows
    $rows = [];
    $start = -1;
    $inString = false;
    $escapeNext = false;

    for ($i = 0; $i < strlen($valuesStr); $i++) {
        $ch = $valuesStr[$i];

        if ($escapeNext) {
            $escapeNext = false;
            continue;
        }
        if ($ch === '\\') {
            $escapeNext = true;
            continue;
        }
        if ($ch === "'") {
            $inString = !$inString;
            continue;
        }
        if ($inString) {
            continue;
        }
        if ($ch === '(' && $start === -1) {
            $start = $i;
            continue;
        }
        if ($ch === ')' && $start !== -1) {
            $rows[] = substr($valuesStr, $start, $i - $start + 1);
            $start = -1;
        }
    }

    echo "  Found " . count($rows) . " audit log rows\n";

    $inserted = 0;
    $skipped = 0;

    foreach ($rows as $row) {
        // Strip outer parentheses
        $inner = substr($row, 1, -1);

        // Parse fields
        $fields = [];
        $field = '';
        $inStr = false;
        $esc = false;
        $jsonDepth = 0;

        for ($i = 0; $i < strlen($inner); $i++) {
            $ch = $inner[$i];

            if ($esc) {
                $field .= $ch;
                $esc = false;
                continue;
            }
            if ($ch === '\\') {
                $esc = true;
                $field .= $ch;
                continue;
            }
            if ($ch === "'" && $jsonDepth === 0) {
                $inStr = !$inStr;
                $field .= $ch;
                continue;
            }
            if (!$inStr && $ch === '{') {
                $jsonDepth++;
                $field .= $ch;
                continue;
            }
            if (!$inStr && $ch === '}') {
                $jsonDepth--;
                $field .= $ch;
                continue;
            }
            if ($ch === ',' && !$inStr && $jsonDepth === 0) {
                $fields[] = $field;
                $field = '';
                continue;
            }
            $field .= $ch;
        }
        if (trim($field) !== '') {
            $fields[] = $field;
        }

        if (count($fields) < 12) {
            echo "  ⚠️  Skipping row (only " . count($fields) . " fields)\n";
            $skipped++;
            continue;
        }

        $id = (int) $fields[0];
        $userId = (int) $fields[1];
        $action = unquote($fields[2]);
        $modelType = unquote($fields[3]);
        $modelId = trim($fields[4]) === 'NULL' ? null : (int) $fields[4];
        $description = unquote($fields[5]);

        // Fix JSON: MySQL stores as '{\"key\":\"val\"}' inside single quotes
        $oldValues = fixMySqlJson($fields[6]);
        $newValues = fixMySqlJson($fields[7]);

        $ipAddress = unquote($fields[8]);
        $userAgent = unquote($fields[9]);
        $createdAt = unquote($fields[10]);
        $updatedAt = unquote($fields[11]);

        // Validate JSON before inserting
        if ($oldValues !== null) {
            $decoded = json_decode($oldValues);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "  ⚠️  Audit #{$id}: Invalid old_values JSON, setting NULL\n";
                $oldValues = null;
            }
        }
        if ($newValues !== null) {
            $decoded = json_decode($newValues);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "  ⚠️  Audit #{$id}: Invalid new_values JSON, setting NULL\n";
                $newValues = null;
            }
        }

        try {
            DB::table('admin_audit_logs')->updateOrInsert(
                ['id' => $id],
                [
                    'user_id' => $userId,
                    'action' => $action,
                    'model_type' => $modelType,
                    'model_id' => $modelId,
                    'description' => $description,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]
            );
            $inserted++;
        } catch (\Exception $e) {
            echo "  ❌ Audit #{$id}: " . substr($e->getMessage(), 0, 120) . "\n";
            $skipped++;
        }
    }

    echo "  ✅ Inserted: {$inserted}, Skipped: {$skipped}\n";
} else {
    echo "  ❌ Could not find admin_audit_logs INSERT data\n";
}

// ============================================================
// Final Summary
// ============================================================
echo "\n=== Final Database Summary ===\n";
$tables = [
    'users', 'activities', 'activity_categories', 'registrations',
    'attendances', 'announcements', 'messages', 'rooms', 'room_user',
    'job_listings', 'activity_feedbacks', 'settings',
    'notifications_custom', 'admin_audit_logs',
];

foreach ($tables as $table) {
    $count = DB::table($table)->count();
    echo sprintf("  %-25s %d\n", $table, $count);
}

echo "\n=== Recovery Complete ===\n";

// ============================================================
// Helper Functions
// ============================================================

/**
 * Remove surrounding single quotes and unescape MySQL string
 */
function unquote(string $val): ?string
{
    $val = trim($val);
    if ($val === 'NULL') {
        return null;
    }
    // Remove surrounding quotes
    if (strlen($val) >= 2 && $val[0] === "'" && substr($val, -1) === "'") {
        $val = substr($val, 1, -1);
    }
    // Fix MySQL string escaping: \\" → "
    $val = str_replace('\\"', '"', $val);
    // Fix MySQL string escaping: \\' → '
    $val = str_replace("\\'", "'", $val);
    // Fix MySQL string escaping: \\\\ → \\
    $val = str_replace('\\\\', '\\', $val);
    return $val;
}

/**
 * Convert MySQL-escaped JSON string to valid JSON
 * MySQL stores: '{\"key\":\"val\"}' -> need: {"key":"val"}
 */
function fixMySqlJson(string $val): ?string
{
    $val = trim($val);
    if ($val === 'NULL') {
        return null;
    }
    // Remove surrounding quotes
    if (strlen($val) >= 2 && $val[0] === "'" && substr($val, -1) === "'") {
        $val = substr($val, 1, -1);
    }
    // Empty or null
    if ($val === '' || $val === 'NULL') {
        return null;
    }
    // MySQL escaping inside SQL strings:
    // The file has: {\\\"id\\\":20}
    // PHP reads it as: {\\"id\\":20} (literal backslash-backslash-quote)
    // We need: {"id":20}
    // Step 1: Replace \\\" with " (MySQL double-quote escape inside SQL string)
    $val = str_replace('\\\"', '"', $val);
    // Step 2: Replace remaining \\\\ with \\
    $val = str_replace('\\\\', '\\', $val);
    // Step 3: Clean up any remaining single backslash-escape sequences that aren't valid JSON
    // Validate
    $decoded = json_decode($val);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
    return $val;
}
