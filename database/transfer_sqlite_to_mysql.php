<?php

declare(strict_types=1);

$sqlitePath = __DIR__ . "/../database/database.sqlite";

if (!file_exists($sqlitePath)) {
    throw new RuntimeException("SQLite database not found: {$sqlitePath}");
}

echo "=== SQLITE -> MYSQL DATA TRANSFER ===" . PHP_EOL;
echo "SQLite: {$sqlitePath}" . PHP_EOL;
echo "MySQL:  127.0.0.1:3306 / godwin" . PHP_EOL . PHP_EOL;

$sqlite = new PDO('sqlite:' . $sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$mysql = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=godwin;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

/*
 * Application tables only.
 * Laravel runtime/support tables are intentionally excluded.
 */
$tables = [
    'users',
    'academic_years',
    'terms',
    'classes',
    'subjects',
    'class_subject',
    'students',
    'parent_student',
    'attendance',
    'assessments',
    'homeworks',
    'behavior_notes',
    'timetables',
    'fee_structures',
    'invoices',
    'invoice_items',
    'payments',
    'expenses',
    'announcements',
    'events',
    'conversations',
    'conversation_user',
    'messages',
    'applications',
    'audit_logs',
    'banners',
    'contact_messages',
    'notifications',
    'settings',
];

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

function sqliteTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sqlite_master
        WHERE type = 'table'
          AND name = ?
    ");
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

function getColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("PRAGMA table_info(" . str_replace('"', '""', $table) . ")");

    $columns = [];

    foreach ($stmt->fetchAll() as $row) {
        $columns[] = $row['name'];
    }

    return $columns;
}

echo "Checking databases..." . PHP_EOL;

foreach ($tables as $table) {
    if (!sqliteTableExists($sqlite, $table)) {
        throw new RuntimeException("SQLite table missing: {$table}");
    }

    if (!tableExists($mysql, $table)) {
        throw new RuntimeException("MySQL table missing: {$table}");
    }
}

echo "All required tables exist." . PHP_EOL . PHP_EOL;

$mysql->beginTransaction();

try {
    /*
     * Disable FK checks during the controlled import.
     * Original IDs are preserved, so relationships remain intact.
     */
    $mysql->exec("SET FOREIGN_KEY_CHECKS=0");

    /*
     * Clear only the application tables in reverse dependency order.
     * They are currently expected to be empty, but this makes the script
     * deterministic if it is ever rerun.
     */
    $clearOrder = array_reverse($tables);

    foreach ($clearOrder as $table) {
        $mysql->exec("DELETE FROM `{$table}`");
    }

    $totalRows = 0;

    foreach ($tables as $table) {
        echo "Importing {$table}..." . PHP_EOL;

        $sqliteColumns = getColumns($sqlite, $table);

        if (empty($sqliteColumns)) {
            echo "  Skipped: no columns found." . PHP_EOL;
            continue;
        }

        $columnList = implode(
            ', ',
            array_map(
                fn ($column) => '`' . str_replace('`', '``', $column) . '`',
                $sqliteColumns
            )
        );

        $placeholders = implode(', ', array_fill(0, count($sqliteColumns), '?'));

        $select = $sqlite->query("SELECT * FROM \"{$table}\"");
        $rows = $select->fetchAll();

        if (count($rows) === 0) {
            echo "  0 rows" . PHP_EOL;
            continue;
        }

        $insert = $mysql->prepare(
            "INSERT INTO `{$table}` ({$columnList}) VALUES ({$placeholders})"
        );

        $count = 0;

        foreach ($rows as $row) {
            $values = [];

            foreach ($sqliteColumns as $column) {
                $values[] = $row[$column];
            }

            $insert->execute($values);
            $count++;
            $totalRows++;
        }

        echo "  {$count} rows imported." . PHP_EOL;
    }

    $mysql->exec("SET FOREIGN_KEY_CHECKS=1");

    $mysql->commit();

    echo PHP_EOL;
    echo "============================================" . PHP_EOL;
    echo "TRANSFER COMPLETED SUCCESSFULLY" . PHP_EOL;
    echo "Total rows imported: {$totalRows}" . PHP_EOL;
    echo "============================================" . PHP_EOL;

} catch (Throwable $e) {

    if ($mysql->inTransaction()) {
        $mysql->rollBack();
    }

    try {
        $mysql->exec("SET FOREIGN_KEY_CHECKS=1");
    } catch (Throwable $ignored) {
    }

    echo PHP_EOL;
    echo "TRANSFER FAILED." . PHP_EOL;
    echo "MySQL transaction was rolled back." . PHP_EOL;
    echo "SQLite was NOT modified." . PHP_EOL;
    echo PHP_EOL;
    echo "Error: " . $e->getMessage() . PHP_EOL;

    exit(1);
}
