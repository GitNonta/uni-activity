<?php

declare(strict_types=1);

$mysql = new PDO(
    'mysql:host=mysql;port=3306;dbname=uni_activity_restore_probe;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$pgsql = new PDO(
    'pgsql:host=postgres;port=5432;dbname=uni_activity',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$tables = [
    'users',
    'activity_categories',
    'settings',
    'activities',
    'registrations',
    'attendances',
    'activity_feedbacks',
    'announcements',
    'admin_audit_logs',
    'job_listings',
    'job_applications',
    'job_comments',
    'job_inquiries',
    'notifications_custom',
    'password_reset_tokens',
];

$pgsql->beginTransaction();
$pgsql->exec('SET session_replication_role = replica');

try {
    foreach (array_reverse($tables) as $table) {
        if (pgsqlTableExists($pgsql, $table)) {
            $pgsql->exec('TRUNCATE TABLE ' . quoteIdent($table) . ' CASCADE');
        }
    }

    foreach ($tables as $table) {
        if (!mysqlTableExists($mysql, $table) || !pgsqlTableExists($pgsql, $table)) {
            continue;
        }

        $mysqlColumns = tableColumns($mysql, 'mysql', $table);
        $pgsqlColumns = tableColumns($pgsql, 'pgsql', $table);
        $columns = array_values(array_intersect($pgsqlColumns, $mysqlColumns));

        if ($columns === []) {
            continue;
        }

        $selectColumns = implode(', ', array_map(static fn(string $column): string => quoteMysqlIdent($column), $columns));
        $rows = $mysql->query('SELECT ' . $selectColumns . ' FROM ' . quoteMysqlIdent($table))->fetchAll();

        if ($rows === []) {
            echo "{$table}: 0\n";
            continue;
        }

        $insertColumns = implode(', ', array_map(static fn(string $column): string => quoteIdent($column), $columns));
        $placeholders = implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns));
        $statement = $pgsql->prepare('INSERT INTO ' . quoteIdent($table) . " ({$insertColumns}) VALUES ({$placeholders})");

        foreach ($rows as $row) {
            foreach ($row as $column => $value) {
                if (is_boolColumn($table, $column)) {
                    $row[$column] = $value === null ? null : ((bool) $value ? 'true' : 'false');
                }
            }

            $statement->execute($row);
        }

        resetSequence($pgsql, $table);
        echo "{$table}: " . count($rows) . "\n";
    }

    $pgsql->exec('SET session_replication_role = DEFAULT');
    $pgsql->commit();
} catch (Throwable $exception) {
    $pgsql->rollBack();
    throw $exception;
}

function mysqlTableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $statement->execute([$table]);

    return (int) $statement->fetchColumn() > 0;
}

function pgsqlTableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?");
    $statement->execute([$table]);

    return (int) $statement->fetchColumn() > 0;
}

function tableColumns(PDO $pdo, string $driver, string $table): array
{
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position');
    } else {
        $statement = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? ORDER BY ordinal_position");
    }

    $statement->execute([$table]);

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function quoteIdent(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function quoteMysqlIdent(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function resetSequence(PDO $pdo, string $table): void
{
    $statement = $pdo->prepare("SELECT column_default FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = 'id'");
    $statement->execute([$table]);
    $default = (string) $statement->fetchColumn();

    if (!str_contains($default, 'nextval')) {
        return;
    }

    $pdo->exec(
        "SELECT setval(pg_get_serial_sequence('public." . str_replace("'", "''", $table) . "', 'id'), COALESCE((SELECT MAX(id) FROM " . quoteIdent($table) . '), 1), true)'
    );
}

function is_boolColumn(string $table, string $column): bool
{
    return in_array($column, [
        'is_active',
        'is_mandatory',
        'allow_early_checkin',
        'is_verified',
        'is_read',
        'is_active',
        'is_archived',
    ], true);
}
