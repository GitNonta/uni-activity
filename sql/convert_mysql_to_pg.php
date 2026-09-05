<?php
declare(strict_types=1);

/**
 * Convert MySQL INSERT statements to PostgreSQL and import.
 * Usage: php sql/convert_mysql_to_pg.php
 */

$host = '192.168.1.222';
$port = '5432';
$db   = 'uni_activity';
$user = 'admin';
$pass = 'Admin234';

$backupFile = __DIR__ . '/backup_utf8.sql';
if (!file_exists($backupFile)) {
    fwrite(STDERR, "Backup file not found: {$backupFile}\n");
    exit(1);
}

$pgsql = pg_connect("host={$host} port={$port} dbname={$db} user={$user} password={$pass}");
if (!$pgsql) {
    fwrite(STDERR, "Failed to connect to PostgreSQL\n");
    exit(1);
}

echo "Connected to PostgreSQL ({$host}:{$port}/{$db})\n";

$contents = file_get_contents($backupFile);

// Remove MySQL-specific directives
$contents = preg_replace('/\/\*!.*?\*\//s', '', $contents);
$contents = preg_replace('/--.*$/m', '', $contents);

// Extract INSERT statements
preg_match_all('/INSERT INTO `(\w+)` VALUES\s*(.+?);/s', $contents, $matches, PREG_SET_ORDER);

$converted = 0;
$failed    = 0;

foreach ($matches as $match) {
    $table  = $match[1];
    $values = $match[2];

    // Convert backtick-quoted column names — not needed for INSERT (no column list)
    // Convert MySQL INSERT INTO `table` VALUES (row1),(row2) → PostgreSQL format

    // Split multi-row INSERTs into individual rows
    $rows = preg_split('/\)\s*,\s*\(/', $values);

    foreach ($rows as &$row) {
        $row = trim($row, '()');

        // Convert MySQL NULL → PostgreSQL NULL
        $row = preg_replace('/\bNULL\b/i', 'NULL', $row);

        // Convert MySQL boolean (0/1) — keep as-is, PostgreSQL handles this

        // Convert MySQL datetime format — keep as-is, PostgreSQL handles this

        // Fix escaped single quotes
        $row = str_replace("\\'", "''", $row);

        // Convert \\r\\n to actual newlines
        $row = str_replace('\\r\\n', "\n", $row);
        $row = str_replace('\\n', "\n", $row);
    }
    unset($row);

    // Rebuild as individual INSERT statements for better error handling
    foreach ($rows as $row) {
        $sql = "INSERT INTO \"{$table}\" VALUES ({$row});";
        $result = pg_query($pgsql, $sql);
        if ($result) {
            $converted++;
        } else {
            $err = pg_last_error($pgsql);
            // Skip duplicate key errors (idempotent)
            if (str_contains($err, 'duplicate key') || str_contains($err, 'already exists')) {
                $converted++;
                continue;
            }
            fwrite(STDERR, "FAILED on {$table}: {$err}\n");
            fwrite(STDERR, "  SQL: " . substr($sql, 0, 200) . "...\n");
            $failed++;
        }
    }
}

echo "\n=== Import Summary ===\n";
echo "Converted/Imported: {$converted} rows\n";
echo "Failed: {$failed} rows\n";

// Verify counts
$tables = ['users', 'activities', 'activity_categories', 'registrations', 'attendances'];
echo "\n=== Table Counts ===\n";
foreach ($tables as $t) {
    $res = pg_query($pgsql, "SELECT COUNT(*) AS cnt FROM \"{$t}\"");
    $row = pg_fetch_assoc($res);
    echo "{$t}: {$row['cnt']}\n";
}

pg_close($pgsql);
echo "\nDone.\n";
