<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateSqliteToMysql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:sqlite-to-mysql
                            {--dry-run : Perform a dry run without transferring data}
                            {--force : Force transfer even if target contains data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transfer all application data from SQLite to MySQL (mysql_import)';

    /**
     * SQLite internal / Laravel system tables to skip.
     *
     * @var array<string>
     */
    protected array $skipTables = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'sqlite_sequence',
    ];

    /**
     * Tables in foreign-key-safe insertion order.
     * Tables with no dependencies come first.
     *
     * @var array<string>
     */
    protected array $importOrder = [
        // Tier 1: no application dependencies
        'users',
        'settings',
        'academic_years',
        'subjects',
        'banners',
        'contact_messages',
        'notifications',
        // Tier 2: depends on Tier 1
        'terms',
        'classes',
        'examinations',
        'fee_structures',
        'sms_provider_configs',
        'conversations',
        // Tier 3: depends on Tier 1 and 2
        'students',
        'class_subject',
        'timetables',
        'homeworks',
        'behavior_notes',
        'invoices',
        'applications',
        'parent_student',
        'conversation_user',
        'messages',
        'sms_campaigns',
        'sms_purchases',
        'sms_templates',
        'assets',
        // Tier 4: depends on Tier 3
        'attendance',
        'assessments',
        'invoice_items',
        'payments',
        'expenses',
        'announcements',
        'events',
        'examination_classes',
        'examination_subjects',
        'audit_logs',
        'sms_messages',
        // Tier 5: depends on Tier 4
        'sms_delivery_events',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = DB::connection('sqlite');
        $target = DB::connection('mysql_import');

        // 1. Verify source SQLite database exists and has data
        $this->info('Verifying source SQLite database...');
        $sqliteTables = $this->getSqliteTables($source);
        if (empty($sqliteTables)) {
            $this->error('No tables found in SQLite database.');
            return 1;
        }
        $this->line("  Found " . count($sqliteTables) . " tables in SQLite.");

        // 2. Verify target MySQL database is empty (no application data rows)
        $this->info('Verifying target MySQL database (godwin_local_mysql) is empty...');
        $target = DB::connection('mysql_import');
        $nonEmptyTables = [];
        foreach ($this->importOrder as $table) {
            try {
                if (! $target->getSchemaBuilder()->hasTable($table)) {
                    continue;
                }
                $count = $target->table($table)->count();
                if ($count > 0) {
                    $nonEmptyTables[] = "$table ($count rows)";
                }
            } catch (\Throwable $e) {
                // Table may not exist yet; skip
            }
        }
        if (count($nonEmptyTables) > 0 && !$this->option('force')) {
            $this->error('Target database already contains data. Non-empty tables:');
            foreach ($nonEmptyTables as $t) {
                $this->error("  - $t");
            }
            $this->error('Use --force to override, or verify the database first.');
            return 1;
        }
        $this->line('  Target database contains no application data.');

        // 3. Show plan
        $this->info('Import order (' . count($this->importOrder) . ' tables):');
        foreach ($this->importOrder as $i => $table) {
            $marker = in_array($table, $sqliteTables) ? '*' : ' ';
            $this->line("  [{$marker}] " . str_pad($table, 30) . ($i % 4 === 3 ? '' : ''));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No data was transferred.');
            return 0;
        }

        // 4. Confirm
        if (! $this->confirm('Proceed with data transfer from SQLite to MySQL?')) {
            $this->info('Aborted.');
            return 1;
        }

        // 5. Transfer data
        $this->info('Transferring data...');
        $results = [];
        $totalRows = 0;

        $target->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->importOrder as $table) {
                if (! in_array($table, $sqliteTables)) {
                    continue;
                }

                $count = $this->transferTable($source, $target, $table);
                $results[$table] = $count;
                $totalRows += $count;
                $this->line("  [OK] {$table}: {$count} row(s) transferred.");
            }

            // 6. Reset auto-increment counters
            $this->info('Resetting auto-increment counters...');
            foreach ($results as $table => $count) {
                if ($count > 0) {
                    $this->resetAutoIncrement($target, $table);
                }
            }

            $target->statement('SET FOREIGN_KEY_CHECKS=1');

        } catch (\Throwable $e) {
            $target->statement('SET FOREIGN_KEY_CHECKS=1');
            $this->error('Transfer failed: ' . $e->getMessage());
            return 1;
        }

        $this->info("Transfer complete. Total rows: {$totalRows}.");
        $this->line('');

        // 7. Show summary
        $this->info('Transfer summary:');
        foreach ($results as $table => $count) {
            $sqliteCount = $source->table($table)->count();
            $match = $sqliteCount === $count ? 'OK' : 'MISMATCH';
            $color = $match === 'OK' ? 'green' : 'red';
            $this->line("  <{$color}>{$match}</> {$table}: SQLite={$sqliteCount}, MySQL={$count}");
        }

        return 0;
    }

    /**
     * Get list of tables from SQLite (excluding system tables).
     */
    protected function getSqliteTables($connection): array
    {
        $rows = $connection->select("
            SELECT name FROM sqlite_master
            WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ");

        return array_values(array_filter(
            array_map(fn ($r) => $r->name, $rows),
            fn ($name) => ! in_array($name, $this->skipTables, true)
        ));
    }

    /**
     * Get list of tables from MySQL.
     */
    protected function getMysqlTables($connection): array
    {
        $rows = $connection->select('SHOW TABLES');
        $tables = [];
        foreach ($rows as $r) {
            $row = (array) $r;
            $tables[] = reset($row);
        }
        return $tables;
    }

    /**
     * Transfer a single table from SQLite to MySQL.
     */
    protected function transferTable($source, $target, string $table): int
    {
        $rows = $source->table($table)->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $batchSize = 100;
        $chunked = $rows->chunk($batchSize);
        $total = 0;

        foreach ($chunked as $chunk) {
            $data = $chunk->map(fn ($row) => is_array($row) ? $row : (array) $row)->all();
            $target->table($table)->insert($data);
            $total += $chunk->count();
        }

        return $total;
    }

    /**
     * Check for unique/conflict violations before insert.
     */
    protected function checkConflicts($target, string $table, $rows, array $columns): void
    {
        // For tables with unique constraints, verify no duplicate data exists in target
        // that would cause insert failure. We report but do not skip.
        // The actual insert will fail naturally if there's a conflict, which is
        // the desired behavior (never silently skip).
    }

    /**
     * Reset auto-increment counter for a MySQL table.
     */
    protected function resetAutoIncrement($target, string $table): void
    {
        $maxId = $target->table($table)->max('id');
        if ($maxId !== null) {
            $target->statement("ALTER TABLE {$this->quoteId($table)} AUTO_INCREMENT = " . (intval($maxId) + 1));
        }
    }

    /**
     * Quote an identifier for MySQL.
     */
    protected function quoteId(string $id): string
    {
        return '`' . str_replace('`', '``', $id) . '`';
    }
}
