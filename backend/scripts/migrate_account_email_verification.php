<?php
/**
 * Add verified personal-email support for both Admin and Staff accounts.
 *
 * This migration is additive: it preserves accounts, emails, password hashes,
 * and existing pending verification codes. Personal emails entered by an Admin
 * are registered and trusted immediately, so existing addresses are backfilled
 * as verified. Staff can still change their own address from Settings, but a
 * new address stays pending until its 6-digit code is confirmed.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration script can only be run from the command line.\n");
}

ini_set('session.save_path', sys_get_temp_dir());
require __DIR__ . '/../bootstrap.php';

use Guinhawa\Database;

function accountEmailColumnExists(string $table, string $column): bool
{
    $row = Database::fetchOne(
        'SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column],
    );

    return (int) ($row['total'] ?? 0) > 0;
}

function accountEmailTableExists(string $table): bool
{
    $row = Database::fetchOne(
        'SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table],
    );

    return (int) ($row['total'] ?? 0) > 0;
}

echo "Guinhawa personal-email verification migration\n";
echo "-----------------------------------------------\n";

foreach (['admin', 'employees'] as $table) {
    if (!accountEmailColumnExists($table, 'email_verified')) {
        Database::execute(
            'ALTER TABLE ' . $table . ' ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email',
        );
        echo "[ok] " . $table . ".email_verified added; existing addresses are backfilled below\n";
    } else {
        echo "[skip] " . $table . ".email_verified already exists\n";
    }

    if (!accountEmailColumnExists($table, 'email_verified_at')) {
        Database::execute(
            'ALTER TABLE ' . $table . ' ADD COLUMN email_verified_at DATETIME NULL AFTER email_verified',
        );
        echo "[ok] " . $table . ".email_verified_at added\n";
    } else {
        echo "[skip] " . $table . ".email_verified_at already exists\n";
    }

    // Legacy accounts and current Admin-created accounts both use an
    // Admin-entered personal email, so treat those addresses as registered
    // and verified. This only fills email_verified / email_verified_at; it
    // never changes the address or any password hash.
    $notArchived = ($table === 'employees' && accountEmailColumnExists($table, 'deleted_at'))
        ? ' AND deleted_at IS NULL'
        : '';
    Database::execute(
        "UPDATE {$table}
         SET email_verified = 1,
             email_verified_at = COALESCE(email_verified_at, COALESCE(updated_at, NOW()))
         WHERE email IS NOT NULL AND email <> ''
           AND (email_verified = 0 OR email_verified_at IS NULL)
           {$notArchived}",
    );
    echo "[ok] existing {$table} personal emails marked as verified\n";
}

Database::execute(
    "CREATE TABLE IF NOT EXISTS account_email_verifications (
        user_type ENUM('admin', 'employee') NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        pending_email VARCHAR(190) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_sent_at DATETIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_type, user_id),
        UNIQUE KEY uq_account_email_verifications_pending_email (pending_email),
        KEY idx_account_email_verifications_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
);
echo "[ok] shared account_email_verifications table is ready\n";

if (accountEmailTableExists('staff_email_verifications')) {
    Database::execute(
        "INSERT IGNORE INTO account_email_verifications
            (user_type, user_id, pending_email, otp_hash, expires_at, attempts, last_sent_at, created_at)
         SELECT 'employee', employee_id, pending_email, otp_hash, expires_at, attempts, last_sent_at, created_at
         FROM staff_email_verifications",
    );
    echo "[ok] existing pending staff verification requests were preserved\n";
}

echo "Done. No accounts, emails, or passwords were deleted or changed.\n";
