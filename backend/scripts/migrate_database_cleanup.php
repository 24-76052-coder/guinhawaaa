<?php
/**
 * Guinhawa database cleanup — Phase 1 (preservation-first).
 *
 * This migration is additive and idempotent. It never drops or truncates a
 * table, never changes an ID, and never deletes business data. Run it as often
 * as needed; each step checks its current state before acting.
 *
 *   C:\xampp2\php\php.exe backend\scripts\migrate_database_cleanup.php
 *
 * Steps:
 *   1. Revoke sessions belonging to accounts that no longer exist.
 *   2. Add a UNIQUE index on admin.email (after checking for duplicates).
 *   3. Add date indexes used by the report/ledger screens.
 *
 * A complete backup must be taken before running this script.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration can only be run from the command line.\n");
}

ini_set('session.save_path', sys_get_temp_dir());
require __DIR__ . '/../bootstrap.php';

use Guinhawa\Database;

/** Return the current database name so we never touch the wrong schema. */
function currentDatabase(): string
{
    $row = Database::fetchOne('SELECT DATABASE() AS db_name');
    return (string) ($row['db_name'] ?? '');
}

function indexExists(string $table, string $index): bool
{
    $row = Database::fetchOne(
        'SELECT COUNT(*) AS total
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [$table, $index],
    );
    return (int) ($row['total'] ?? 0) > 0;
}

function revokeOrphanSessions(): void
{
    $orphans = Database::fetchOne(
        "SELECT COUNT(*) AS total
         FROM auth_sessions s
         LEFT JOIN admin a ON s.user_type = 'admin' AND s.user_id = a.id
         LEFT JOIN employees e ON s.user_type = 'employee' AND s.user_id = e.id
         WHERE s.revoked_at IS NULL
           AND ((s.user_type = 'admin' AND a.id IS NULL)
             OR (s.user_type = 'employee' AND e.id IS NULL))",
    );

    if ((int) ($orphans['total'] ?? 0) === 0) {
        echo "[skip] no active sessions for removed accounts\n";
        return;
    }

    $affected = Database::transaction(static function (): int {
        return Database::execute(
            "UPDATE auth_sessions s
             LEFT JOIN admin a ON s.user_type = 'admin' AND s.user_id = a.id
             LEFT JOIN employees e ON s.user_type = 'employee' AND s.user_id = e.id
             SET s.revoked_at = NOW()
             WHERE s.revoked_at IS NULL
               AND ((s.user_type = 'admin' AND a.id IS NULL)
                 OR (s.user_type = 'employee' AND e.id IS NULL))",
        );
    });

    echo "[ok] revoked {$affected} session(s) belonging to removed accounts\n";
}

function addAdminEmailUnique(): void
{
    if (indexExists('admin', 'uq_admin_email')) {
        echo "[skip] admin.uq_admin_email already exists\n";
        return;
    }

    $duplicates = Database::fetchOne(
        'SELECT COUNT(*) AS total FROM (
            SELECT email FROM admin
            WHERE email IS NOT NULL AND email <> \'\'
            GROUP BY email HAVING COUNT(*) > 1
        ) AS duplicates',
    );
    if ((int) ($duplicates['total'] ?? 0) > 0) {
        echo "[warn] duplicate admin emails found; skipping uq_admin_email\n";
        return;
    }

    Database::execute('ALTER TABLE admin ADD UNIQUE KEY uq_admin_email (email)');
    echo "[ok] admin.uq_admin_email added\n";
}

function addIndex(string $table, string $index, string $columns): void
{
    if (indexExists($table, $index)) {
        echo "[skip] {$table}.{$index} already exists\n";
        return;
    }

    Database::execute("ALTER TABLE {$table} ADD INDEX {$index} ({$columns})");
    echo "[ok] {$table}.{$index} added on ({$columns})\n";
}

// ---------------------------------------------------------------------------

$database = currentDatabase();
if ($database !== 'guinhawa_db') {
    exit("Refusing to run: current database is '{$database}', expected 'guinhawa_db'.\n");
}

echo "Guinhawa database cleanup (Phase 1)\n";
echo "Database: {$database}\n";

revokeOrphanSessions();
addAdminEmailUnique();
addIndex('inventory_history', 'idx_history_date', 'movement_date');
addIndex('stock_in', 'idx_stock_in_date', 'received_date');
addIndex('stock_out', 'idx_stock_out_date', 'issued_date');

echo "Cleanup migration finished. No tables or rows were removed.\n";
