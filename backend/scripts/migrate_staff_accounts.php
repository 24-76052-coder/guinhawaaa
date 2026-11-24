<?php
/**
 * Staff account management migration.
 *
 * Adds the Staff ID (STF-001, STF-002, ...) used by the Admin-managed staff
 * accounts, exposes the last password change date, and creates a read-only
 * `staff_accounts` view with the account-management field names.
 *
 * The script is additive and idempotent: existing staff rows, sales,
 * attendance and audit history are never deleted or rewritten. Existing
 * employee IDs are kept for backward compatibility; the new Staff ID is
 * generated alongside them.
 *
 * Run from the command line:
 *   C:\xampp2\php\php.exe backend\scripts\migrate_staff_accounts.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration script can only be run from the command line.\n");
}

ini_set('session.save_path', sys_get_temp_dir());
require __DIR__ . '/../bootstrap.php';

use Guinhawa\Database;

function staffAccountsColumnExists(string $table, string $column): bool
{
    $row = Database::fetchOne(
        'SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column],
    );
    return (int) ($row['total'] ?? 0) > 0;
}

function staffAccountsIndexExists(string $table, string $index): bool
{
    $row = Database::fetchOne(
        'SELECT COUNT(*) AS total
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [$table, $index],
    );
    return (int) ($row['total'] ?? 0) > 0;
}

echo "Guinhawa staff account migration\n";
echo "--------------------------------\n";

if (!staffAccountsColumnExists('employees', 'staff_id')) {
    Database::execute(
        'ALTER TABLE employees ADD COLUMN staff_id VARCHAR(20) NULL AFTER employee_id',
    );
    echo "[ok] employees.staff_id added\n";
} else {
    echo "[skip] employees.staff_id already exists\n";
}

// Backfill every existing staff record with a unique STF-### identifier.
$rows = Database::fetchAll('SELECT id, employee_id, staff_id FROM employees ORDER BY id ASC');
$highest = 0;
foreach ($rows as $row) {
    if (preg_match('/^STF-(\d+)$/i', (string) ($row['staff_id'] ?? ''), $match)) {
        $highest = max($highest, (int) $match[1]);
    }
}
foreach ($rows as $row) {
    $staffId = trim((string) ($row['staff_id'] ?? ''));
    if ($staffId !== '') {
        continue;
    }

    if (preg_match('/^STF-(\d+)$/i', (string) $row['employee_id'], $match)) {
        $staffId = 'STF-' . str_pad((string) (int) $match[1], 3, '0', STR_PAD_LEFT);
        $highest = max($highest, (int) $match[1]);
    } else {
        $highest += 1;
        $staffId = 'STF-' . str_pad((string) $highest, 3, '0', STR_PAD_LEFT);
    }

    Database::execute('UPDATE employees SET staff_id = ? WHERE id = ?', [$staffId, (int) $row['id']]);
    echo "[ok] employee #{$row['id']} ({$row['employee_id']}) -> {$staffId}\n";
}

if (!staffAccountsIndexExists('employees', 'uq_employees_staff_id')) {
    Database::execute('ALTER TABLE employees ADD UNIQUE KEY uq_employees_staff_id (staff_id)');
    echo "[ok] unique index on employees.staff_id added\n";
} else {
    echo "[skip] unique index on employees.staff_id already exists\n";
}

if (!staffAccountsColumnExists('employees', 'account_status')) {
    Database::execute(
        'ALTER TABLE employees
         ADD COLUMN account_status VARCHAR(20) GENERATED ALWAYS AS (status) STORED',
    );
    echo "[ok] employees.account_status generated column added\n";
} else {
    echo "[skip] employees.account_status already exists\n";
}

if (!staffAccountsColumnExists('employees', 'last_password_change')) {
    Database::execute(
        'ALTER TABLE employees
         ADD COLUMN last_password_change DATETIME GENERATED ALWAYS AS (password_changed_at) STORED',
    );
    echo "[ok] employees.last_password_change generated column added\n";
} else {
    echo "[skip] employees.last_password_change already exists\n";
}

Database::execute(
    'CREATE OR REPLACE VIEW staff_accounts AS
     SELECT
         id,
         COALESCE(NULLIF(staff_id, \'\'), employee_id) AS staff_id,
         full_name,
         email,
         contact_number,
         password_hash,
         role,
         status AS account_status,
         must_change_password,
         created_at,
         updated_at,
         password_changed_at AS last_password_change
     FROM employees',
);
echo "[ok] staff_accounts view is ready\n";

echo "Done. No existing staff records were deleted or rewritten.\n";
