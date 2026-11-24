<?php

declare(strict_types=1);

/**
 * Compact-schema cutover / rollback.
 *
 * The staging build in migrate_compact_schema.php leaves the legacy tables
 * untouched and writes verified copies to *_new tables. This script performs
 * the final atomic table rename and flips Schema::t() back to canonical names.
 *
 * IMPORTANT: this script never drops a table. The legacy tables are kept under
 * their original names with a `_legacy` suffix so the pre-cutover state can be
 * restored. Removing those tables needs a separate, explicitly approved step.
 *
 * Examples (nothing is written in plan mode):
 *
 *   php backend/scripts/cutover_compact_schema.php
 *   php backend/scripts/cutover_compact_schema.php --execute \
 *       --backup=C:\Users\Lenovo\guinhawa_db_backups\guinhawa_db_backup_pre_compact_20261122.sql \
 *       --confirm=I-APPROVE-COMPACT-CUTOVER
 *
 *   php backend/scripts/cutover_compact_schema.php --rollback \
 *       --backup=C:\Users\Lenovo\guinhawa_db_backups\guinhawa_db_backup_pre_compact_20261122.sql \
 *       --confirm=I-APPROVE-COMPACT-ROLLBACK
 */

require __DIR__ . '/../bootstrap.php';

use Guinhawa\Database;

const BACKUP_DIR = 'C:/Users/Lenovo/guinhawa_db_backups';
const CONFIRM_CUTOVER = 'I-APPROVE-COMPACT-CUTOVER';
const CONFIRM_ROLLBACK = 'I-APPROVE-COMPACT-ROLLBACK';

/** @var array<string, mixed> $config */
$checks = [];

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function note(string $message): void
{
    echo $message . "\n";
}

function option(string $name): ?string
{
    global $argv;
    foreach ($argv as $argument) {
        if (str_starts_with($argument, "--{$name}=")) {
            return substr($argument, strlen($name) + 3);
        }
    }
    return null;
}

function hasFlag(string $name): bool
{
    global $argv;
    return in_array("--{$name}", $argv, true);
}

function q(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function one(string $sql, array $params = []): ?array
{
    return Database::fetchOne($sql, $params);
}

function tableExists(string $table): bool
{
    return one(
        'SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
        [$table],
    ) !== null;
}

function viewExists(string $view): bool
{
    return one(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = 'VIEW' LIMIT 1",
        [$view],
    ) !== null;
}

function columnExists(string $table, string $column): bool
{
    return one(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        [$table, $column],
    ) !== null;
}

/** Legacy tables preserved as *_legacy. */
function legacyMap(): array
{
    return [
        // These legacy names are reused by the compact schema, so the old
        // tables must be archived in the same atomic statement.
        'suppliers' => 'suppliers_legacy',
        'customers' => 'customers_legacy',
        'products' => 'products_legacy',
        'product_variants' => 'product_variants_legacy',
        'attendance' => 'attendance_legacy',
        'settings' => 'settings_legacy',

        'admin' => 'admin_legacy',
        'employees' => 'employees_legacy',
        'auth_sessions' => 'auth_sessions_legacy',
        'auth_remember_tokens' => 'auth_remember_tokens_legacy',
        'admin_recovery_codes' => 'admin_recovery_codes_legacy',
        'account_activation_tokens' => 'account_activation_tokens_legacy',
        'account_email_verifications' => 'account_email_verifications_legacy',
        'staff_email_verifications' => 'staff_email_verifications_legacy',
        'password_reset_requests' => 'password_reset_requests_legacy',
        'account_audit_logs' => 'account_audit_logs_legacy',
        'audit_logs' => 'audit_logs_legacy',
        'inventory' => 'inventory_legacy',
        'inventory_history' => 'inventory_history_legacy',
        'stock_in' => 'stock_in_legacy',
        'stock_out' => 'stock_out_legacy',
        'sales' => 'sales_legacy',
        'sale_items' => 'sale_items_legacy',
        'orders' => 'orders_legacy',
        'held_orders' => 'held_orders_legacy',
        'user_appearance_settings' => 'user_appearance_settings_legacy',
    ];
}

/** Staging tables promoted to canonical names. */
function stagingMap(): array
{
    return [
        'users_new' => 'users',
        'suppliers_new' => 'suppliers',
        'customers_new' => 'customers',
        'products_new' => 'products',
        'product_variants_new' => 'product_variants',
        'orders_new' => 'orders',
        'order_items_new' => 'order_items',
        'inventory_transactions_new' => 'inventory_transactions',
        'attendance_new' => 'attendance',
        'settings_new' => 'settings',
        'email_verification_tokens_new' => 'email_verification_tokens',
        'password_reset_tokens_new' => 'password_reset_tokens',
        'audit_logs_new' => 'audit_logs',
    ];
}

/** Cutover: legacy names are freed first, then staging names are promoted. */
function cutoverPairs(): array
{
    return array_merge(legacyMap(), stagingMap());
}

/** Rollback: compact names are moved back to *_new, then legacy names return. */
function rollbackPairs(): array
{
    $pairs = [];
    foreach (array_reverse(stagingMap(), true) as $new => $canonical) {
        $pairs[$canonical] = $new;
    }
    foreach (array_reverse(legacyMap(), true) as $legacy => $legacyArchive) {
        $pairs[$legacyArchive] = $legacy;
    }
    return $pairs;
}

function renderRenameSql(array $pairs): string
{
    $lines = [];
    foreach ($pairs as $from => $to) {
        $lines[] = '  ' . q($from) . ' TO ' . q($to);
    }
    return "RENAME TABLE\n" . implode(",\n", $lines);
}

function recordCheck(string $label, bool $ok, string $detail = ''): void
{
    global $checks;
    $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
}

function reportChecks(): void
{
    global $checks;
    $failed = 0;
    foreach ($checks as $check) {
        $status = $check['ok'] ? 'OK  ' : 'FAIL';
        if (!$check['ok']) {
            $failed += 1;
        }
        printf("[%s] %-52s %s\n", $status, $check['label'], $check['detail']);
    }
    if ($failed > 0) {
        fail($failed . ' preflight check(s) failed. No changes were made.');
    }
}

function verifyBackup(?string $path): void
{
    if ($path === null || $path === '') {
        fail('A verified SQL dump is required. Pass --backup=<path-to-dump.sql>.');
    }
    if (!is_file($path)) {
        fail("Backup file not found: {$path}");
    }
    if (filesize($path) < 10000) {
        fail("Backup file is unexpectedly small: {$path}");
    }
    $contents = (string) file_get_contents($path);
    if (!str_contains($contents, 'Dump completed')) {
        fail("Backup file does not contain a mysqldump completion marker: {$path}");
    }
    if (!str_contains($contents, 'CREATE TABLE')) {
        fail("Backup file does not contain CREATE TABLE statements: {$path}");
    }
    note("Verified backup: {$path} (" . filesize($path) . " bytes)");
}

/** Read-only reconciliation of the staging copies against the untouched source. */
function verifyStagingParity(): void
{
    $count = static fn (string $table): int => (int) (one('SELECT COUNT(*) AS n FROM ' . q($table))['n'] ?? 0);
    $sum = static fn (string $sql): float => (float) (one($sql)['n'] ?? 0);

    recordCheck('products copied', $count('products_new') === $count('products'), $count('products_new') . ' rows');
    recordCheck('variants copied', $count('product_variants_new') === $count('product_variants'), $count('product_variants_new') . ' rows');
    recordCheck('order items copied', $count('order_items_new') === $count('sale_items'), $count('order_items_new') . ' rows');
    recordCheck('attendance copied', $count('attendance_new') === $count('attendance'), $count('attendance_new') . ' rows');

    $variantUnits = $sum('SELECT COALESCE(SUM(quantity),0) AS n FROM product_variants_new');
    $legacyUnits = $sum('SELECT COALESCE(SUM(quantity),0) AS n FROM inventory');
    recordCheck('stock units match', abs($variantUnits - $legacyUnits) < 0.001, $variantUnits . ' = ' . $legacyUnits);

    $itemsTotal = $sum('SELECT COALESCE(SUM(total),0) AS n FROM order_items_new');
    $salesTotal = $sum('SELECT COALESCE(SUM(total),0) AS n FROM sales');
    $ordersTotal = $sum('SELECT COALESCE(SUM(total),0) AS n FROM orders_new');
    recordCheck(
        'sales totals match',
        abs($itemsTotal - $salesTotal) < 0.001 && abs($ordersTotal - $salesTotal) < 0.001,
        $itemsTotal . ' = ' . $ordersTotal . ' = ' . $salesTotal,
    );

    $newAudit = $count('audit_logs_new');
    $oldAudit = $count('account_audit_logs') + $count('audit_logs');
    recordCheck('audit logs merged', $newAudit === $oldAudit, $newAudit . ' = ' . $oldAudit);

    $adminSame = (bool) (one(
        'SELECT (SELECT password_hash FROM users_new WHERE id = 1)
             = (SELECT password_hash FROM admin WHERE id = 1) AS ok',
    )['ok'] ?? false);
    $staffSame = (bool) (one(
        'SELECT (SELECT password_hash FROM users_new WHERE id = 23)
             = (SELECT password_hash FROM employees WHERE id = 23) AS ok',
    )['ok'] ?? false);
    recordCheck('password hashes preserved', $adminSame && $staffSame, 'admin=' . (int) $adminSame . ', staff=' . (int) $staffSame);

    $archived = (int) (one('SELECT COALESCE(SUM(JSON_LENGTH(session_history)),0) AS n FROM users_new')['n'] ?? 0);
    $sessions = $count('auth_sessions');
    recordCheck('sessions archived', $archived === $sessions, $archived . ' = ' . $sessions);

    $orphans = one(
        'SELECT
           (SELECT COUNT(*) FROM product_variants_new v LEFT JOIN products_new p ON v.product_id = p.id WHERE p.id IS NULL) AS variants,
           (SELECT COUNT(*) FROM order_items_new i LEFT JOIN orders_new o ON i.order_id = o.id WHERE o.id IS NULL) AS items,
           (SELECT COUNT(*) FROM inventory_transactions_new t LEFT JOIN products_new p ON t.product_id = p.id WHERE t.product_id IS NOT NULL AND p.id IS NULL) AS txn_products,
           (SELECT COUNT(*) FROM audit_logs_new a LEFT JOIN users_new u ON a.actor_id = u.id WHERE a.actor_id IS NOT NULL AND u.id IS NULL) AS audit_actors',
    ) ?? [];
    $orphanTotal = (int) ($orphans['variants'] ?? 0) + (int) ($orphans['items'] ?? 0)
        + (int) ($orphans['txn_products'] ?? 0) + (int) ($orphans['audit_actors'] ?? 0);
    recordCheck('no staging orphans', $orphanTotal === 0, (string) $orphanTotal);

    $fkCount = (int) (one(
        "SELECT COUNT(*) AS n FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%\\_new'
           AND REFERENCED_TABLE_NAME IS NOT NULL",
    )['n'] ?? 0);
    recordCheck('staging foreign keys present', $fkCount === 13, $fkCount . ' of 13');
}

function preflight(array $config, string $mode): void
{
    $stagingActive = !empty($config['db']['compact_staging']);
    if ($mode === 'cutover' && !$stagingActive) {
        fail('compact_staging is already off. The tables may already be cut over.');
    }
    if ($mode === 'rollback' && $stagingActive) {
        fail('compact_staging is still on. Rollback is only valid after a completed cutover.');
    }

    $env = getenv('GUINHAWA_COMPACT_STAGING');
    if ($env !== false) {
        $envStaging = filter_var($env, FILTER_VALIDATE_BOOL);
        if ($mode === 'cutover' && $envStaging) {
            fail('Unset GUINHAWA_COMPACT_STAGING before cutover; it would keep the app on *_new tables.');
        }
        if ($mode === 'rollback' && !$envStaging) {
            fail('Set GUINHAWA_COMPACT_STAGING=1 before rollback.');
        }
    }

    if ($mode === 'cutover') {
        foreach (legacyMap() as $from => $to) {
            recordCheck("legacy source {$from}", tableExists($from));
            recordCheck("archive target {$to} is free", !tableExists($to));
        }
        foreach (stagingMap() as $from => $to) {
            recordCheck("staging source {$from}", tableExists($from));
            $targetFree = !tableExists($to) || array_key_exists($to, legacyMap());
            recordCheck("canonical target {$to} is available", $targetFree);
        }
        recordCheck('legacy view staff_accounts', viewExists('staff_accounts'));
        verifyStagingParity();
    } else {
        foreach (stagingMap() as $new => $canonical) {
            recordCheck("compact table {$canonical}", tableExists($canonical));
            recordCheck("staging target {$new} is free", !tableExists($new));
        }
        foreach (legacyMap() as $legacy => $archive) {
            recordCheck("archived table {$archive}", tableExists($archive));
            recordCheck("legacy target {$legacy} is free", !tableExists($legacy));
        }
        recordCheck('legacy archive view staff_accounts_legacy', viewExists('staff_accounts_legacy'));
        recordCheck(
            'canonical schema is compact',
            columnExists('users', 'role') && columnExists('audit_logs', 'category'),
        );
    }
}

function viewSelect(string $view): ?string
{
    $row = one('SHOW CREATE VIEW ' . q($view));
    if (!$row) {
        return null;
    }
    $definition = '';
    foreach (['Create View', 'View', 'create view'] as $key) {
        if (isset($row[$key])) {
            $definition = (string) $row[$key];
            break;
        }
    }
    if ($definition === '' || !preg_match('/\bAS\s+(select\b.*)$/is', $definition, $matches)) {
        return null;
    }
    return $matches[1];
}

function preserveViewAfterCutover(): void
{
    if (!viewExists('staff_accounts')) {
        return;
    }
    $select = viewSelect('staff_accounts');
    if ($select === null) {
        fail('Could not read the staff_accounts view definition. The rename is complete; fix the view manually.');
    }
    $legacySelect = str_replace('`employees`', '`employees_legacy`', $select);
    Database::execute('CREATE OR REPLACE VIEW `staff_accounts_legacy` AS ' . $legacySelect);
    Database::execute('DROP VIEW IF EXISTS `staff_accounts`');
    note('Preserved staff_accounts as staff_accounts_legacy (now backed by employees_legacy).');
}

function restoreViewAfterRollback(): void
{
    if (!viewExists('staff_accounts_legacy')) {
        return;
    }
    $select = viewSelect('staff_accounts_legacy');
    if ($select === null) {
        fail('Could not read the staff_accounts_legacy view definition. The rollback is complete; fix the view manually.');
    }
    $originalSelect = str_replace('`employees_legacy`', '`employees`', $select);
    Database::execute('CREATE OR REPLACE VIEW `staff_accounts` AS ' . $originalSelect);
    Database::execute('DROP VIEW IF EXISTS `staff_accounts_legacy`');
    note('Restored the original staff_accounts view.');
}

function verifyAfterRename(string $mode): void
{
    if ($mode === 'cutover') {
        foreach (stagingMap() as $new => $canonical) {
            if (!tableExists($canonical) || tableExists($new)) {
                fail("Cutover verification failed for {$canonical}.");
            }
        }
        if (!columnExists('users', 'role') || !columnExists('audit_logs', 'category')) {
            fail('The canonical tables do not look like the compact schema.');
        }
    } else {
        foreach (legacyMap() as $legacy => $archive) {
            if (!tableExists($legacy) || tableExists($archive)) {
                fail("Rollback verification failed for {$legacy}.");
            }
        }
        if (!tableExists('users_new') || !tableExists('admin')) {
            fail('The staging tables were not restored by rollback.');
        }
    }

    $compactTables = array_values(stagingMap());
    $placeholders = implode(',', array_fill(0, count($compactTables), '?'));
    $fks = Database::fetchAll(
        "SELECT kcu.TABLE_NAME AS child, kcu.REFERENCED_TABLE_NAME AS parent,
                kcu.CONSTRAINT_NAME AS name
         FROM information_schema.KEY_COLUMN_USAGE kcu
         WHERE kcu.TABLE_SCHEMA = DATABASE()
           AND kcu.TABLE_NAME IN ({$placeholders})
           AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME",
        $compactTables,
    );
    $allowed = array_flip($compactTables);
    $broken = [];
    foreach ($fks as $fk) {
        if (!isset($allowed[$fk['parent']])) {
            $broken[] = $fk['child'] . '.' . $fk['name'] . ' -> ' . $fk['parent'];
        }
    }
    if ($mode === 'cutover' && (count($fks) !== 13 || $broken !== [])) {
        fail('Compact foreign keys did not follow the rename: ' . implode(', ', $broken));
    }
    note('Post-rename table set and foreign keys verified.');
}

function switchConfig(bool $staging): void
{
    $path = __DIR__ . '/../config/config.php';
    $contents = (string) file_get_contents($path);
    $from = "getenv('GUINHAWA_COMPACT_STAGING') ?: '" . ($staging ? '0' : '1') . "'";
    $to = "getenv('GUINHAWA_COMPACT_STAGING') ?: '" . ($staging ? '1' : '0') . "'";
    $updated = str_replace($from, $to, $contents, $count);
    if ($count !== 1) {
        fail('Could not find the compact_staging default in config.php; switch it manually.');
    }

    if (!is_dir(BACKUP_DIR)) {
        fail('Backup directory is missing: ' . BACKUP_DIR);
    }
    $backup = BACKUP_DIR . '/config.php.before_compact_' . ($staging ? 'rollback' : 'cutover') . '_' . date('Ymd_His') . '.php';
    file_put_contents($backup, $contents);

    file_put_contents($path, $updated);
    note('Switched compact_staging default to ' . ($staging ? '1 (staging)' : '0 (canonical)') . '.');
    note('Config backup: ' . $backup);
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

try {
    $execute = hasFlag('execute');
    $rollback = hasFlag('rollback');
    if ($execute && $rollback) {
        fail('Choose either --execute or --rollback, not both.');
    }

    // In plan mode, infer the direction from the current config so the printed
    // SQL matches what would actually run.
    $direction = $rollback || (!$execute && empty($config['db']['compact_staging']))
        ? 'rollback'
        : 'cutover';
    $pairs = $direction === 'rollback' ? rollbackPairs() : cutoverPairs();

    if ($execute || $rollback) {
        $expected = $rollback ? CONFIRM_ROLLBACK : CONFIRM_CUTOVER;
        if (option('confirm') !== $expected) {
            fail('Explicit confirmation required: --confirm=' . $expected);
        }
        verifyBackup(option('backup'));
    }

    note('Guinhawa compact schema ' . ($execute || $rollback ? strtoupper($direction) : $direction . ' plan'));
    note('Database: ' . (string) ($config['db']['name'] ?? 'guinhawa_db'));
    note('Current compact_staging: ' . (!empty($config['db']['compact_staging']) ? '1 (staging)' : '0 (canonical)'));
    note('');
    note('Planned atomic rename:');
    note(renderRenameSql($pairs));
    note('');
    note($direction === 'rollback'
        ? 'After rename: restore staff_accounts; set compact_staging default back to 1.'
        : 'After rename: preserve staff_accounts_legacy; set compact_staging default to 0.');
    note('');

    preflight($config, $direction);
    reportChecks();

    if (!$execute && !$rollback) {
        note("\nPlan only. No tables, views, or configuration files were changed.");
        exit(0);
    }

    note("\nApplying the rename in a single atomic statement...");
    Database::execute(renderRenameSql($pairs));
    note('Atomic rename completed.');

    verifyAfterRename($direction);

    if ($direction === 'cutover') {
        preserveViewAfterCutover();
    } else {
        restoreViewAfterRollback();
    }

    switchConfig($direction === 'rollback');

    note("\nDone. Existing PHP pages should now use " . ($direction === 'cutover' ? 'the canonical 13 tables.' : 'the *_new staging tables again.'));
    note('Legacy tables are preserved with a _legacy suffix; nothing was dropped.');
} catch (Throwable $error) {
    fail($error->getMessage());
}
