<?php
/**
 * Fix admin_audit_logs JSON values.
 * The MySQL backup stores JSON as: '{"key":"val"}' with MySQL escaping.
 * We need to convert to valid JSON: {"key":"val"}
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$backupFile = __DIR__ . '/backup_utf8.sql';
$content = file_get_contents($backupFile);

if (!preg_match('/INSERT INTO `admin_audit_logs` VALUES (.+?);\r?\n/s', $content, $matches)) {
    die("Could not find admin_audit_logs INSERT data\n");
}

$valuesStr = $matches[1];

// Parse rows
$rows = [];
$start = -1;
$inString = false;
$escapeNext = false;

for ($i = 0; $i < strlen($valuesStr); $i++) {
    $ch = $valuesStr[$i];
    if ($escapeNext) { $escapeNext = false; continue; }
    if ($ch === '\\') { $escapeNext = true; continue; }
    if ($ch === "'") { $inString = !$inString; continue; }
    if ($inString) { continue; }
    if ($ch === '(' && $start === -1) { $start = $i; continue; }
    if ($ch === ')' && $start !== -1) {
        $rows[] = substr($valuesStr, $start, $i - $start + 1);
        $start = -1;
    }
}

echo "Found " . count($rows) . " audit log rows\n\n";

$fixed = 0;

foreach ($rows as $row) {
    $inner = substr($row, 1, -1);

    // Parse fields with proper depth tracking
    $fields = [];
    $field = '';
    $inStr = false;
    $esc = false;
    $jsonDepth = 0;

    for ($i = 0; $i < strlen($inner); $i++) {
        $ch = $inner[$i];
        if ($esc) { $field .= $ch; $esc = false; continue; }
        if ($ch === '\\') { $esc = true; $field .= $ch; continue; }
        if ($ch === "'" && $jsonDepth === 0) { $inStr = !$inStr; $field .= $ch; continue; }
        if (!$inStr && $ch === '{') { $jsonDepth++; $field .= $ch; continue; }
        if (!$inStr && $ch === '}') { $jsonDepth--; $field .= $ch; continue; }
        if ($ch === ',' && !$inStr && $jsonDepth === 0) { $fields[] = $field; $field = ''; continue; }
        $field .= $ch;
    }
    $fields[] = $field;

    if (count($fields) < 12) {
        echo "  ⚠️  Skipping row (only " . count($fields) . " fields)\n";
        continue;
    }

    $id = (int) $fields[0];

    // Fix old_values (field 6) and new_values (field 7)
    foreach ([6 => 'old_values', 7 => 'new_values'] as $idx => $col) {
        $raw = trim($fields[$idx]);

        // Skip NULL
        if ($raw === 'NULL' || $raw === '') {
            continue;
        }

        // Remove surrounding single quotes (the crucial step!)
        if (strlen($raw) >= 2 && $raw[0] === "'" && substr($raw, -1) === "'") {
            $raw = substr($raw, 1, -1);
        }

        if ($raw === '' || $raw === 'NULL') {
            continue;
        }

        // The raw bytes are: {\"key\":\"val\"}
        // In PHP memory: backslash + double-quote at each quote position
        // We need to replace every backslash+double-quote with just double-quote
        // Use chr() to avoid any escaping ambiguity
        $bs = chr(0x5C); // backslash
        $dq = chr(0x22); // double-quote
        $raw = str_replace($bs . $dq, $dq, $raw);

        // Fix double-escaped unicode: \\u0e1c -> \u0e1c
        // After the above, we might still have \\u from MySQL's \\\\u
        $raw = str_replace($bs . $bs . 'u', $bs . 'u', $raw);

        // Validate JSON
        $decoded = json_decode($raw);
        if (json_last_error() === JSON_ERROR_NONE) {
            $json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            DB::table('admin_audit_logs')->where('id', $id)->update([$col => $json]);
            $fixed++;
            echo "  ✅ Audit #{$id}: fixed {$col}\n";
        } else {
            echo "  ⚠️  Audit #{$id}: {$col} invalid - " . json_last_error_msg() . " (first 80: " . substr($raw, 0, 80) . ")\n";
        }
    }
}

echo "\n=== Fixed {$fixed} JSON fields ===\n";

$countWithJson = DB::table('admin_audit_logs')
    ->whereNotNull('old_values')
    ->orWhereNotNull('new_values')
    ->count();
echo "Audit logs with JSON data: {$countWithJson}/15\n";

$sample = DB::table('admin_audit_logs')->where('id', 2)->first();
if ($sample && $sample->old_values) {
    echo "\nSample Audit #2 old_values:\n";
    echo substr($sample->old_values, 0, 300) . "\n";
}
