<?php
/**
 * Add optional, verified personal-email support for staff accounts.
 *
 * Run from the command line. This migration is additive and preserves every
 * existing staff row and email address. Admin-entered addresses are backfilled
 * as verified by `migrate_account_email_verification.php`; only addresses a
 * staff member changes themselves require a confirmation code.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration script can only be run from the command line.\n");
}

ini_set('session.save_path', sys_get_temp_dir());
require __DIR__ . '/../bootstrap.php';

use Guinhawa\Database;

function staffEmailColumnExists(): bool
{
    $row = Database::fetchOne(
        'SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['employees', 'email_verified'],
    );
    return (int) ($row['total'] ?? 0) > 0;
}

echo "Guinhawa staff email verification migration\n";
echo "------------------------------------------\n";

if (!staffEmailColumnExists()) {
    Database::execute(
        'ALTER TABLE employees ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email',
    );
    echo "[ok] employees.email_verified added; existing addresses are backfilled by migrate_account_email_verification.php\n";
} else {
    echo "[skip] employees.email_verified already exists\n";
}

Database::execute(
    "CREATE TABLE IF NOT EXISTS staff_email_verifications (
        employee_id INT UNSIGNED NOT NULL,
        pending_email VARCHAR(190) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_sent_at DATETIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (employee_id),
        UNIQUE KEY uq_staff_email_verifications_email (pending_email),
        CONSTRAINT fk_staff_email_verifications_employee
            FOREIGN KEY (employee_id) REFERENCES employees (id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
);
echo "[ok] staff_email_verifications table is ready\n";
echo "Done. No existing staff records were deleted or rewritten.\n";
