<?php
/**
 * Guinhawa 13-table compact schema — additive build and data migration.
 *
 * This script ONLY creates and populates tables whose names end in `_new`.
 * It never touches the existing live tables. Dropping the `_new` staging
 * tables on a rerun is safe because they contain no production data.
 *
 *   C:\xampp2\php\php.exe backend\scripts\migrate_compact_schema.php
 *
 * After verification and separate approval, a cutover script will rename the
 * live tables to `*_legacy` and these tables to their canonical names.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration can only be run from the command line.\n");
}

ini_set('session.save_path', sys_get_temp_dir());
require __DIR__ . '/../bootstrap.php';

use Guinhawa\Database;

const PLACEHOLDER_START = 9000;

function one(string $sql, array $params = []): array
{
    return Database::fetchOne($sql, $params);
}

function all(string $sql, array $params = []): array
{
    return Database::fetchAll($sql, $params);
}

function execSql(string $sql, array $params = []): int
{
    return Database::execute($sql, $params);
}

function insertRow(string $table, array $row): void
{
    $cols = array_keys($row);
    $quoted = array_map(static fn (string $c): string => '`' . $c . '`', $cols);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $quoted) . ') VALUES ('
        . implode(', ', array_fill(0, count($cols), '?')) . ')';
    execSql($sql, array_values($row));
}

function mapUserId(?string $type, $id): ?int
{
    $id = (int) $id;
    if ($type === 'admin' && $id === 1) {
        return 1;
    }
    if ($type === 'employee' && $id === 23) {
        return 23;
    }
    if ($type === 'employee' && in_array($id, [1, 2, 5], true)) {
        return PLACEHOLDER_START + $id;
    }
    return null;
}

function appearanceJson(array $row): ?string
{
    if (!empty($row['preferences'])) {
        return (string) $row['preferences'];
    }
    return json_encode([
        'mode' => $row['theme_mode'],
        'theme' => $row['accent_color'],
        'fontSize' => $row['font_size'],
        'density' => $row['interface_density'],
        'corners' => 'rounded',
        'animations' => (int) $row['reduce_animations'] === 1 ? 'none' : 'subtle',
        'sidebar' => $row['sidebar_state'],
        'cardStyle' => 'bordered',
        'background' => 'solid',
    ], JSON_UNESCAPED_SLASHES);
}

// ---------------------------------------------------------------------------
// DDL — staging tables (created in FK-safe order)
// ---------------------------------------------------------------------------
$ddl = [];

$ddl['users_new'] = <<<'SQL'
CREATE TABLE users_new (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role ENUM('admin','inventory-staff','cashier','staff') NOT NULL DEFAULT 'staff',
  status ENUM('active','inactive','pending','archived') NOT NULL DEFAULT 'active',
  username VARCHAR(60) NOT NULL,
  employee_code VARCHAR(40) NULL,
  staff_code VARCHAR(20) NULL,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NULL,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  email_verified_at DATETIME NULL,
  contact_number VARCHAR(40) NULL,
  avatar LONGTEXT NULL,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  temp_password_expires_at DATETIME NULL,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  deleted_at DATETIME NULL,
  deleted_by VARCHAR(160) NULL,
  created_by VARCHAR(60) NULL,
  work_shift ENUM('Morning','Afternoon','Night') NULL,
  date_joined DATE NULL,
  preferences JSON NULL,
  session_history JSON NULL,
  remember_selector CHAR(24) NULL,
  remember_token_hash VARCHAR(255) NULL,
  remember_session_version INT UNSIGNED NULL,
  remember_expires_at DATETIME NULL,
  legacy_source ENUM('admin','employees','appearance','auth_sessions') NULL,
  legacy_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_employee_code (employee_code),
  UNIQUE KEY uq_users_staff_code (staff_code),
  UNIQUE KEY uq_users_remember_selector (remember_selector),
  KEY idx_users_role_status (role, status),
  KEY idx_users_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['suppliers_new'] = <<<'SQL'
CREATE TABLE suppliers_new (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(160) NOT NULL,
  contact VARCHAR(60) NOT NULL DEFAULT '',
  email VARCHAR(190) NULL,
  address VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_suppliers_code (code),
  UNIQUE KEY uq_suppliers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['customers_new'] = <<<'SQL'
CREATE TABLE customers_new (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(160) NOT NULL,
  contact VARCHAR(60) NOT NULL DEFAULT '',
  email VARCHAR(190) NULL,
  address VARCHAR(255) NOT NULL DEFAULT '',
  orders_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  last_order VARCHAR(40) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['products_new'] = <<<'SQL'
CREATE TABLE products_new (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(40) NOT NULL,
  barcode VARCHAR(80) NULL,
  name VARCHAR(190) NOT NULL,
  category VARCHAR(60) NOT NULL,
  subcategory VARCHAR(120) NOT NULL DEFAULT '',
  brand VARCHAR(120) NOT NULL DEFAULT '',
  description TEXT NULL,
  minimum DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  sack_weight_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  expiration_date DATE NULL,
  supplier VARCHAR(160) NOT NULL DEFAULT '',
  supplier_id INT UNSIGNED NULL,
  image VARCHAR(600) NOT NULL DEFAULT '',
  review ENUM('Listed','Verify') NOT NULL DEFAULT 'Listed',
  notes TEXT NULL,
  source VARCHAR(160) NOT NULL DEFAULT '',
  sheet_row INT NULL,
  awaiting_stock TINYINT(1) NOT NULL DEFAULT 1,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  archived_at DATETIME NULL,
  archived_reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_sku (sku),
  UNIQUE KEY uq_products_barcode (barcode),
  KEY idx_products_archived (archived),
  KEY idx_products_category (category, subcategory),
  KEY idx_products_supplier (supplier_id),
  CONSTRAINT compact_fk_products_supplier FOREIGN KEY (supplier_id)
    REFERENCES suppliers_new(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['product_variants_new'] = <<<'SQL'
CREATE TABLE product_variants_new (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  variant_key ENUM('A','B') NOT NULL,
  label VARCHAR(40) NOT NULL DEFAULT 'Price A',
  unit VARCHAR(40) NOT NULL DEFAULT 'Units',
  unit_raw VARCHAR(80) NOT NULL DEFAULT '',
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  minimum DECIMAL(12,2) NOT NULL DEFAULT 0,
  assumed TINYINT(1) NOT NULL DEFAULT 0,
  unit_verified TINYINT(1) NOT NULL DEFAULT 1,
  quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
  stock_updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_variants_product_key (product_id, variant_key),
  UNIQUE KEY uq_variants_product_unit (product_id, unit),
  CONSTRAINT compact_chk_variant_quantity CHECK (quantity >= 0),
  CONSTRAINT compact_fk_variants_product FOREIGN KEY (product_id)
    REFERENCES products_new(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['orders_new'] = <<<'SQL'
CREATE TABLE orders_new (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_no VARCHAR(30) NOT NULL,
  order_type ENUM('pos','online','held') NOT NULL DEFAULT 'pos',
  status ENUM('pending','confirmed','processing','completed','cancelled','held','paid','refunded','void') NOT NULL DEFAULT 'pending',
  source VARCHAR(20) NOT NULL DEFAULT 'pos',
  legacy_id INT UNSIGNED NULL,
  legacy_order_type VARCHAR(20) NULL,
  legacy_status VARCHAR(30) NULL,
  customer VARCHAR(160) NOT NULL DEFAULT 'Walk-in Customer',
  customer_id VARCHAR(20) NULL,
  customer_ref INT UNSIGNED NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method ENUM('Cash','GCash') NULL,
  amount_tendered DECIMAL(12,2) NOT NULL DEFAULT 0,
  change_due DECIMAL(12,2) NOT NULL DEFAULT 0,
  cashier VARCHAR(160) NOT NULL DEFAULT '',
  user_id INT UNSIGNED NULL,
  staff_code VARCHAR(40) NULL,
  transaction_id VARCHAR(80) NULL,
  message TEXT NULL,
  order_date DATE NOT NULL,
  hold_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_no (order_no),
  UNIQUE KEY uq_orders_transaction (transaction_id),
  UNIQUE KEY uq_orders_legacy (source, legacy_id),
  KEY idx_orders_date (order_date),
  KEY idx_orders_type_status (order_type, status),
  KEY idx_orders_user (user_id),
  KEY idx_orders_customer (customer_ref),
  CONSTRAINT compact_fk_orders_user FOREIGN KEY (user_id)
    REFERENCES users_new(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT compact_fk_orders_customer FOREIGN KEY (customer_ref)
    REFERENCES customers_new(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['order_items_new'] = <<<'SQL'
CREATE TABLE order_items_new (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  variant_key ENUM('A','B') NOT NULL DEFAULT 'A',
  product_name VARCHAR(190) NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT '',
  unit VARCHAR(40) NOT NULL DEFAULT 'Units',
  quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id),
  KEY idx_order_items_product (product_id),
  CONSTRAINT compact_fk_order_items_order FOREIGN KEY (order_id)
    REFERENCES orders_new(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT compact_fk_order_items_product FOREIGN KEY (product_id)
    REFERENCES products_new(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['inventory_transactions_new'] = <<<'SQL'
CREATE TABLE inventory_transactions_new (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  legacy_source ENUM('inventory_history','stock_in','stock_out','application') NOT NULL,
  legacy_id BIGINT UNSIGNED NULL,
  transaction_type ENUM('stock_in','stock_out','adjustment','damaged','expired','sale') NOT NULL,
  direction ENUM('in','out') NOT NULL,
  reference VARCHAR(40) NOT NULL,
  product_id INT UNSIGNED NULL,
  variant_key ENUM('A','B') NOT NULL DEFAULT 'A',
  product_name VARCHAR(190) NOT NULL DEFAULT '',
  unit VARCHAR(40) NOT NULL DEFAULT 'Units',
  quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
  previous_stock DECIMAL(12,3) NOT NULL DEFAULT 0,
  new_stock DECIMAL(12,3) NOT NULL DEFAULT 0,
  supplier_id INT UNSIGNED NULL,
  supplier_name VARCHAR(160) NOT NULL DEFAULT '',
  is_delivery TINYINT(1) NOT NULL DEFAULT 0,
  customer VARCHAR(160) NOT NULL DEFAULT '',
  reason VARCHAR(60) NOT NULL DEFAULT '',
  remarks VARCHAR(500) NOT NULL DEFAULT '',
  user_label VARCHAR(160) NOT NULL DEFAULT '',
  actor_id INT UNSIGNED NULL,
  actor_type ENUM('admin','employee') NULL,
  movement_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inv_txn_legacy (legacy_source, legacy_id),
  KEY idx_inv_txn_date (movement_date),
  KEY idx_inv_txn_type_date (transaction_type, movement_date),
  KEY idx_inv_txn_product (product_id),
  KEY idx_inv_txn_reference (reference),
  KEY idx_inv_txn_supplier (supplier_id),
  KEY idx_inv_txn_actor (actor_id),
  CONSTRAINT compact_fk_inv_txn_product FOREIGN KEY (product_id)
    REFERENCES products_new(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT compact_fk_inv_txn_supplier FOREIGN KEY (supplier_id)
    REFERENCES suppliers_new(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT compact_fk_inv_txn_actor FOREIGN KEY (actor_id)
    REFERENCES users_new(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['attendance_new'] = <<<'SQL'
CREATE TABLE attendance_new (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  employee_code VARCHAR(40) NOT NULL,
  employee_name VARCHAR(160) NOT NULL,
  work_date DATE NOT NULL,
  clock_in TIME NULL,
  clock_out TIME NULL,
  hours DECIMAL(6,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT '',
  note VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attendance_day (employee_code, work_date),
  KEY idx_attendance_date (work_date),
  KEY idx_attendance_user (user_id),
  CONSTRAINT compact_fk_attendance_user FOREIGN KEY (user_id)
    REFERENCES users_new(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['settings_new'] = <<<'SQL'
CREATE TABLE settings_new (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  store_name VARCHAR(160) NOT NULL DEFAULT 'Guinhawa Poultry Supply',
  owner VARCHAR(160) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(60) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  business_hours VARCHAR(160) NOT NULL DEFAULT '',
  description VARCHAR(500) NOT NULL DEFAULT '',
  receipt_footer VARCHAR(255) NOT NULL DEFAULT '',
  low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 10,
  currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
  date_format VARCHAR(30) NOT NULL DEFAULT 'MMM DD, YYYY',
  email_notifications TINYINT(1) NOT NULL DEFAULT 1,
  inventory_alerts TINYINT(1) NOT NULL DEFAULT 1,
  order_notifications TINYINT(1) NOT NULL DEFAULT 1,
  gcash_qr LONGTEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['email_verification_tokens_new'] = <<<'SQL'
CREATE TABLE email_verification_tokens_new (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_type ENUM('email_change','activation') NOT NULL DEFAULT 'email_change',
  pending_email VARCHAR(190) NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
  last_sent_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  target_status ENUM('active','inactive') NULL,
  requested_ip VARCHAR(45) NOT NULL DEFAULT '',
  legacy_source VARCHAR(40) NULL,
  legacy_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email_token_hash (token_hash),
  UNIQUE KEY uq_email_token_pending_email (pending_email),
  KEY idx_email_token_user (user_id, token_type),
  KEY idx_email_token_expiry (expires_at),
  CONSTRAINT compact_fk_email_token_user FOREIGN KEY (user_id)
    REFERENCES users_new(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['password_reset_tokens_new'] = <<<'SQL'
CREATE TABLE password_reset_tokens_new (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  channel ENUM('email','link','recovery_code') NOT NULL DEFAULT 'email',
  otp_hash VARCHAR(255) NULL,
  reset_token_hash CHAR(64) NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
  requested_ip VARCHAR(45) NOT NULL DEFAULT '',
  expires_at DATETIME NOT NULL,
  token_expires_at DATETIME NULL,
  consumed_at DATETIME NULL,
  reset_completed_at DATETIME NULL,
  legacy_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_prt_user (user_id, created_at),
  KEY idx_prt_email (email, created_at),
  KEY idx_prt_token (reset_token_hash),
  KEY idx_prt_expiry (expires_at),
  CONSTRAINT compact_fk_prt_user FOREIGN KEY (user_id)
    REFERENCES users_new(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$ddl['audit_logs_new'] = <<<'SQL'
CREATE TABLE audit_logs_new (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category ENUM('account','business') NOT NULL DEFAULT 'business',
  legacy_source VARCHAR(40) NULL,
  legacy_id BIGINT UNSIGNED NULL,
  actor VARCHAR(160) NOT NULL DEFAULT '',
  actor_type ENUM('admin','employee') NULL,
  actor_id INT UNSIGNED NULL,
  actor_name VARCHAR(160) NOT NULL DEFAULT '',
  action VARCHAR(120) NOT NULL,
  target_employee_id VARCHAR(40) NULL,
  details VARCHAR(500) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_audit_legacy (legacy_source, legacy_id),
  KEY idx_audit_created (created_at),
  KEY idx_audit_category_created (category, created_at),
  KEY idx_audit_action (action),
  KEY idx_audit_actor (actor_id),
  CONSTRAINT compact_fk_audit_actor FOREIGN KEY (actor_id)
    REFERENCES users_new(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

// ---------------------------------------------------------------------------
// Safety checks
// ---------------------------------------------------------------------------
$database = one('SELECT DATABASE() AS db')['db'];
if ($database !== 'guinhawa_db') {
    exit("Refusing to run: current database is '{$database}', expected 'guinhawa_db'.\n");
}

foreach (['admin', 'employees', 'products', 'inventory_history', 'sales'] as $required) {
    $exists = one('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$required]);
    if ((int) $exists['n'] !== 1) {
        exit("Refusing to run: required source table '{$required}' is missing.\n");
    }
}

echo "Guinhawa compact schema build\nDatabase: {$database}\n";
echo "Source tables are untouched; only *_new staging tables are written.\n\n";

// Drop only our own staging tables, in FK-safe reverse order.
foreach (array_reverse(array_keys($ddl)) as $table) {
    execSql("DROP TABLE IF EXISTS `{$table}`");
}
foreach ($ddl as $table => $sql) {
    execSql($sql);
    echo "[ddl] created {$table}\n";
}

// ---------------------------------------------------------------------------
// Data migration
// ---------------------------------------------------------------------------
$appearance = [];
foreach (all('SELECT * FROM user_appearance_settings ORDER BY id') as $row) {
    $key = $row['user_type'] . ':' . (int) $row['user_id'];
    $appearance[$key] = appearanceJson($row);
}

$sessionsByUser = [];
foreach (all('SELECT * FROM auth_sessions ORDER BY id') as $s) {
    $uid = mapUserId($s['user_type'], $s['user_id']);
    if ($uid === null) {
        continue;
    }
    $sessionsByUser[$uid][] = [
        'legacy_id' => (int) $s['id'],
        'legacy_user_type' => $s['user_type'],
        'legacy_user_id' => (int) $s['user_id'],
        'session_hash' => $s['session_hash'],
        'session_version' => (int) $s['session_version'],
        'ip_address' => $s['ip_address'],
        'user_agent' => $s['user_agent'],
        'last_seen_at' => $s['last_seen_at'],
        'revoked_at' => $s['revoked_at'],
        'created_at' => $s['created_at'],
    ];
}

$admin = one('SELECT * FROM admin WHERE id = 1');
insertRow('users_new', [
    'id' => 1,
    'role' => 'admin',
    'status' => 'active',
    'username' => $admin['username'],
    'employee_code' => 'ADMIN-01',
    'full_name' => $admin['full_name'],
    'email' => $admin['email'],
    'email_verified' => (int) $admin['email_verified'],
    'email_verified_at' => $admin['email_verified_at'],
    'contact_number' => $admin['contact_number'],
    'avatar' => $admin['avatar'],
    'password_hash' => $admin['password_hash'],
    'must_change_password' => (int) $admin['must_change_password'],
    'last_login_at' => $admin['last_login_at'],
    'password_changed_at' => $admin['password_changed_at'],
    'temp_password_expires_at' => $admin['temp_password_expires_at'],
    'session_version' => (int) $admin['session_version'],
    'failed_login_count' => (int) $admin['failed_login_count'],
    'locked_until' => $admin['locked_until'],
    'preferences' => $appearance['admin:1'] ?? null,
    'session_history' => json_encode($sessionsByUser[1] ?? [], JSON_UNESCAPED_SLASHES),
    'legacy_source' => 'admin',
    'legacy_id' => 1,
    'created_at' => $admin['created_at'],
    'updated_at' => $admin['updated_at'],
]);

$employee = one('SELECT * FROM employees WHERE id = 23');
insertRow('users_new', [
    'id' => 23,
    'role' => $employee['role'],
    'status' => $employee['status'],
    'username' => $employee['username'],
    'employee_code' => $employee['employee_id'],
    'staff_code' => $employee['staff_id'],
    'full_name' => $employee['full_name'],
    'email' => $employee['email'],
    'email_verified' => (int) $employee['email_verified'],
    'email_verified_at' => $employee['email_verified_at'],
    'contact_number' => $employee['contact_number'],
    'avatar' => $employee['avatar'],
    'password_hash' => $employee['password_hash'],
    'must_change_password' => (int) $employee['must_change_password'],
    'last_login_at' => $employee['last_login_at'],
    'password_changed_at' => $employee['password_changed_at'],
    'temp_password_expires_at' => $employee['temp_password_expires_at'],
    'session_version' => (int) $employee['session_version'],
    'failed_login_count' => (int) $employee['failed_login_count'],
    'locked_until' => $employee['locked_until'],
    'deleted_at' => $employee['deleted_at'],
    'deleted_by' => $employee['deleted_by'],
    'created_by' => $employee['created_by'],
    'work_shift' => $employee['work_shift'],
    'date_joined' => $employee['date_joined'],
    'preferences' => $appearance['employee:23'] ?? null,
    'session_history' => json_encode($sessionsByUser[23] ?? [], JSON_UNESCAPED_SLASHES),
    'legacy_source' => 'employees',
    'legacy_id' => 23,
    'created_at' => $employee['created_at'],
    'updated_at' => $employee['updated_at'],
]);

// Archived placeholders keep orphan appearance/session data without a login path.
foreach ([1, 2, 5] as $legacyEmployeeId) {
    $placeholderId = PLACEHOLDER_START + $legacyEmployeeId;
    $source = isset($appearance['employee:' . $legacyEmployeeId]) ? 'appearance' : 'auth_sessions';
    insertRow('users_new', [
        'id' => $placeholderId,
        'role' => 'staff',
        'status' => 'archived',
        'username' => '__legacy_employee_' . $legacyEmployeeId . '__',
        'employee_code' => null,
        'full_name' => 'Legacy Employee ' . $legacyEmployeeId . ' (archived)',
        'email' => null,
        'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
        'must_change_password' => 0,
        'preferences' => $appearance['employee:' . $legacyEmployeeId] ?? null,
        'session_history' => json_encode($sessionsByUser[$placeholderId] ?? [], JSON_UNESCAPED_SLASHES),
        'legacy_source' => $source,
        'legacy_id' => $legacyEmployeeId,
    ]);
}
echo '[data] users_new: ' . one('SELECT COUNT(*) AS n FROM users_new')['n'] . " rows\n";

foreach (all('SELECT * FROM suppliers ORDER BY id') as $r) {
    insertRow('suppliers_new', array_intersect_key($r, array_flip(['id', 'code', 'name', 'contact', 'email', 'address', 'created_at', 'updated_at'])));
}
foreach (all('SELECT * FROM customers ORDER BY id') as $r) {
    insertRow('customers_new', array_intersect_key($r, array_flip(['id', 'code', 'name', 'contact', 'email', 'address', 'orders_count', 'status', 'last_order', 'created_at', 'updated_at'])));
}

$productCols = ['id', 'sku', 'barcode', 'name', 'category', 'subcategory', 'brand', 'description', 'minimum', 'cost', 'sack_weight_kg', 'expiration_date', 'supplier', 'image', 'review', 'notes', 'source', 'sheet_row', 'awaiting_stock', 'archived', 'archived_at', 'archived_reason', 'created_at', 'updated_at'];
foreach (all('SELECT * FROM products ORDER BY id') as $r) {
    $row = array_intersect_key($r, array_flip($productCols));
    $row['supplier_id'] = null;
    insertRow('products_new', $row);
}
echo '[data] products_new: ' . one('SELECT COUNT(*) AS n FROM products_new')['n'] . " rows\n";

$variantCols = ['id', 'product_id', 'variant_key', 'label', 'unit', 'unit_raw', 'price', 'minimum', 'assumed', 'unit_verified'];
foreach (all("SELECT v.*, COALESCE(i.quantity,0) AS stock, i.updated_at AS stock_updated_at FROM product_variants v LEFT JOIN inventory i ON i.product_id=v.product_id AND i.variant_key=v.variant_key ORDER BY v.id") as $r) {
    $row = array_intersect_key($r, array_flip($variantCols));
    $row['quantity'] = $r['stock'];
    $row['stock_updated_at'] = $r['stock_updated_at'];
    insertRow('product_variants_new', $row);
}
echo '[data] product_variants_new: ' . one('SELECT COUNT(*) AS n FROM product_variants_new')['n'] . " rows\n";

function orderStatus(string $status): string
{
    $map = [
        'Paid' => 'completed', 'Completed' => 'completed', 'Pending' => 'pending',
        'Refunded' => 'refunded', 'Void' => 'void', 'Confirmed' => 'confirmed',
        'Processing' => 'processing', 'Cancelled' => 'cancelled',
    ];
    return $map[$status] ?? 'pending';
}

$saleCount = 0;
foreach (all('SELECT * FROM sales ORDER BY id') as $s) {
    $staffId = (string) $s['staff_id'];
    $userId = $staffId === 'ADMIN-01' ? 1 : ($staffId === 'CST-001' ? 23 : null);
    insertRow('orders_new', [
        'id' => (int) $s['id'],
        'order_no' => $s['receipt_no'],
        'order_type' => $s['order_type'] === 'Online' ? 'online' : 'pos',
        'status' => orderStatus((string) $s['status']),
        'source' => 'sales',
        'legacy_id' => (int) $s['id'],
        'legacy_order_type' => $s['order_type'],
        'legacy_status' => $s['status'],
        'customer' => $s['customer'],
        'customer_id' => $s['customer_id'],
        'subtotal' => $s['subtotal'],
        'discount' => $s['discount'],
        'total' => $s['total'],
        'payment_method' => $s['payment_method'],
        'amount_tendered' => $s['amount_tendered'],
        'change_due' => $s['change_due'],
        'cashier' => $s['cashier'],
        'user_id' => $userId,
        'staff_code' => $staffId,
        'transaction_id' => $s['transaction_id'],
        'order_date' => $s['sale_date'],
        'created_at' => $s['created_at'],
    ]);
    $saleCount++;
}

foreach (all('SELECT * FROM orders ORDER BY id') as $o) {
    insertRow('orders_new', [
        'order_no' => $o['order_no'],
        'order_type' => 'online',
        'status' => orderStatus((string) $o['status']),
        'source' => 'orders',
        'legacy_id' => (int) $o['id'],
        'legacy_status' => $o['status'],
        'customer' => $o['customer'],
        'customer_id' => $o['customer_id'],
        'subtotal' => $o['total'],
        'total' => $o['total'],
        'message' => $o['message'],
        'order_date' => $o['order_date'],
        'created_at' => $o['created_at'],
        'updated_at' => $o['updated_at'],
    ]);
}

foreach (all('SELECT * FROM held_orders ORDER BY id') as $h) {
    insertRow('orders_new', [
        'order_no' => $h['hold_no'],
        'order_type' => $h['order_type'] === 'Online' ? 'online' : 'pos',
        'status' => 'held',
        'source' => 'held_orders',
        'legacy_id' => (int) $h['id'],
        'legacy_order_type' => $h['order_type'],
        'customer' => 'Walk-in Customer',
        'discount' => $h['discount'],
        'payment_method' => $h['payment_method'],
        'amount_tendered' => $h['amount_tendered'],
        'cashier' => $h['cashier'],
        'order_date' => $h['hold_date'],
        'hold_date' => $h['hold_date'],
        'created_at' => $h['created_at'],
    ]);
}
echo "[data] orders_new: {$saleCount} sales + " . one('SELECT COUNT(*) AS n FROM orders_new')['n'] . " total rows\n";

foreach (all('SELECT * FROM sale_items ORDER BY id') as $i) {
    insertRow('order_items_new', [
        'id' => (int) $i['id'],
        'order_id' => (int) $i['sale_id'],
        'product_id' => $i['product_id'] === null ? null : (int) $i['product_id'],
        'variant_key' => $i['variant_key'],
        'product_name' => $i['product_name'],
        'category' => $i['category'],
        'unit' => $i['unit'],
        'quantity' => $i['quantity'],
        'price' => $i['price'],
        'total' => $i['total'],
    ]);
}
foreach (all('SELECT * FROM held_orders ORDER BY id') as $h) {
    $items = json_decode((string) $h['items_json'], true);
    if (!is_array($items)) {
        continue;
    }
    $orderId = (int) one('SELECT id FROM orders_new WHERE source = ? AND legacy_id = ?', ['held_orders', (int) $h['id']])['id'];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        insertRow('order_items_new', [
            'order_id' => $orderId,
            'product_id' => isset($item['productId']) ? (int) $item['productId'] : null,
            'variant_key' => $item['variant'] ?? 'A',
            'product_name' => (string) ($item['name'] ?? ''),
            'category' => (string) ($item['category'] ?? ''),
            'unit' => (string) ($item['unit'] ?? 'Units'),
            'quantity' => $item['qty'] ?? 0,
            'price' => $item['price'] ?? 0,
            'total' => ((float) ($item['qty'] ?? 0)) * ((float) ($item['price'] ?? 0)),
        ]);
    }
}
echo '[data] order_items_new: ' . one('SELECT COUNT(*) AS n FROM order_items_new')['n'] . " rows\n";

function transactionType(string $action, string $reference): string
{
    if ($action === 'Stock In') {
        return 'stock_in';
    }
    if (str_starts_with($reference, 'RCP-')) {
        return 'sale';
    }
    return match ($action) {
        'Damaged' => 'damaged',
        'Expired' => 'expired',
        'Adjustment' => 'adjustment',
        default => 'stock_out',
    };
}

$stockInByHistory = [];
foreach (all('SELECT * FROM stock_in') as $r) {
    if ($r['history_id'] !== null) {
        $stockInByHistory[(int) $r['history_id']] = $r;
    }
}

foreach (all('SELECT * FROM inventory_history ORDER BY id') as $h) {
    $hid = (int) $h['id'];
    $si = $stockInByHistory[$hid] ?? null;
    $type = transactionType((string) $h['action'], (string) $h['reference']);
    insertRow('inventory_transactions_new', [
        'id' => $hid,
        'legacy_source' => 'inventory_history',
        'legacy_id' => $hid,
        'transaction_type' => $type,
        'direction' => $h['action'] === 'Stock In' ? 'in' : 'out',
        'reference' => $h['reference'],
        'product_id' => $h['product_id'] === null ? null : (int) $h['product_id'],
        'variant_key' => $h['variant_key'],
        'product_name' => $h['product_name'],
        'unit' => $h['unit'],
        'quantity' => $h['quantity'],
        'previous_stock' => $h['previous_stock'],
        'new_stock' => $h['next_stock'],
        'supplier_id' => $si !== null && $si['supplier_id'] !== null ? (int) $si['supplier_id'] : null,
        'supplier_name' => $si['supplier_name'] ?? '',
        'is_delivery' => $si !== null ? (int) $si['is_delivery'] : 0,
        'remarks' => $h['remarks'],
        'user_label' => $h['user_label'],
        'actor_id' => mapUserId($h['actor_type'] !== null ? (string) $h['actor_type'] : null, $h['actor_id']),
        'actor_type' => $h['actor_type'],
        'movement_date' => $h['movement_date'],
        'created_at' => $h['created_at'],
    ]);
}

foreach (all("SELECT * FROM stock_out WHERE history_id IS NULL OR history_id NOT IN (SELECT id FROM inventory_history) ORDER BY id") as $so) {
    insertRow('inventory_transactions_new', [
        'legacy_source' => 'stock_out',
        'legacy_id' => (int) $so['id'],
        'transaction_type' => in_array($so['reason'], ['Damaged', 'Expired'], true)
            ? strtolower((string) $so['reason']) : 'stock_out',
        'direction' => 'out',
        'reference' => $so['reference'],
        'product_id' => $so['product_id'] === null ? null : (int) $so['product_id'],
        'variant_key' => $so['variant_key'],
        'product_name' => $so['product_name'],
        'unit' => $so['unit'],
        'quantity' => $so['quantity'],
        'previous_stock' => $so['previous_stock'],
        'new_stock' => $so['new_stock'],
        'customer' => $so['customer'],
        'reason' => $so['reason'],
        'remarks' => $so['remarks'],
        'user_label' => $so['user_label'],
        'movement_date' => $so['issued_date'],
        'created_at' => $so['created_at'],
    ]);
}
echo '[data] inventory_transactions_new: ' . one('SELECT COUNT(*) AS n FROM inventory_transactions_new')['n'] . " rows\n";

foreach (all('SELECT * FROM attendance ORDER BY id') as $a) {
    $code = (string) $a['employee_code'];
    insertRow('attendance_new', [
        'id' => (int) $a['id'],
        'user_id' => $code === 'CST-001' ? 23 : null,
        'employee_code' => $a['employee_code'],
        'employee_name' => $a['employee_name'],
        'work_date' => $a['work_date'],
        'clock_in' => $a['clock_in'],
        'clock_out' => $a['clock_out'],
        'hours' => $a['hours'],
        'status' => $a['status'],
        'note' => $a['note'],
        'created_at' => $a['created_at'],
        'updated_at' => $a['updated_at'],
    ]);
}

foreach (all('SELECT * FROM settings ORDER BY id') as $s) {
    insertRow('settings_new', array_intersect_key($s, array_flip([
        'id', 'store_name', 'owner', 'email', 'phone', 'address', 'business_hours',
        'description', 'receipt_footer', 'low_stock_threshold', 'currency', 'date_format',
        'email_notifications', 'inventory_alerts', 'order_notifications', 'gcash_qr', 'updated_at',
    ])));
}

// All three legacy email-verification tables are empty, but copy any rows if present.
foreach (all('SELECT * FROM account_email_verifications') as $r) {
    insertRow('email_verification_tokens_new', [
        'user_id' => mapUserId($r['user_type'], $r['user_id']),
        'token_type' => 'email_change',
        'pending_email' => $r['pending_email'],
        'token_hash' => $r['otp_hash'],
        'expires_at' => $r['expires_at'],
        'attempts' => (int) $r['attempts'],
        'max_attempts' => 5,
        'last_sent_at' => $r['last_sent_at'],
        'legacy_source' => 'account_email_verifications',
    ]);
}
foreach (all('SELECT * FROM staff_email_verifications') as $r) {
    insertRow('email_verification_tokens_new', [
        'user_id' => mapUserId('employee', $r['employee_id']),
        'token_type' => 'email_change',
        'pending_email' => $r['pending_email'],
        'token_hash' => $r['otp_hash'],
        'expires_at' => $r['expires_at'],
        'attempts' => (int) $r['attempts'],
        'max_attempts' => 5,
        'last_sent_at' => $r['last_sent_at'],
        'legacy_source' => 'staff_email_verifications',
    ]);
}
foreach (all('SELECT * FROM account_activation_tokens') as $r) {
    $activationUserId = mapUserId('employee', $r['employee_id']);
    insertRow('email_verification_tokens_new', [
        'user_id' => $activationUserId,
        'token_type' => 'activation',
        // Unique marker keeps activation rows from colliding with the
        // globally unique pending_email key used by email-change tokens.
        'pending_email' => $activationUserId !== null
            ? 'activation:' . $activationUserId . ':' . (int) $r['id']
            : 'activation:legacy:' . (int) $r['id'],
        'token_hash' => $r['token_hash'],
        'expires_at' => $r['expires_at'],
        'attempts' => 0,
        'max_attempts' => 5,
        'last_sent_at' => $r['created_at'],
        'used_at' => $r['used_at'],
        'target_status' => $r['target_status'],
        'requested_ip' => $r['requested_ip'],
        'legacy_source' => 'account_activation_tokens',
        'legacy_id' => (int) $r['id'],
    ]);
}

foreach (all('SELECT * FROM password_reset_requests ORDER BY id') as $r) {
    insertRow('password_reset_tokens_new', [
        'user_id' => mapUserId($r['user_type'], $r['user_id']),
        'email' => $r['email'],
        'channel' => in_array($r['channel'], ['email', 'recovery_code'], true) ? $r['channel'] : 'email',
        'otp_hash' => $r['otp_hash'],
        'reset_token_hash' => $r['reset_token_hash'],
        'attempts' => (int) $r['attempts'],
        'max_attempts' => (int) $r['max_attempts'],
        'requested_ip' => $r['requested_ip'],
        'expires_at' => $r['expires_at'],
        'token_expires_at' => $r['token_expires_at'],
        'consumed_at' => $r['consumed_at'],
        'reset_completed_at' => $r['reset_completed_at'],
        'legacy_id' => (int) $r['id'],
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'],
    ]);
}

// Admin backup recovery codes become single-use recovery_code tokens.
$adminEmail = (string) ($admin['email'] ?? '');
foreach (all('SELECT * FROM admin_recovery_codes ORDER BY id') as $r) {
    insertRow('password_reset_tokens_new', [
        'user_id' => mapUserId('admin', $r['admin_id']),
        'email' => $adminEmail,
        'channel' => 'recovery_code',
        'reset_token_hash' => $r['code_hash'],
        'requested_ip' => $r['created_ip'] ?? '',
        'expires_at' => date('Y-m-d H:i:s', time() + 10 * 365 * 86400),
        'consumed_at' => $r['used_at'],
        'legacy_id' => (int) $r['id'],
        'created_at' => $r['created_at'],
    ]);
}

foreach (all('SELECT * FROM account_audit_logs ORDER BY id') as $r) {
    insertRow('audit_logs_new', [
        'category' => 'account',
        'legacy_source' => 'account_audit_logs',
        'legacy_id' => (int) $r['id'],
        'actor' => $r['actor_name'],
        'actor_type' => $r['actor_type'],
        'actor_id' => mapUserId($r['actor_type'] !== null ? (string) $r['actor_type'] : null, $r['actor_id']),
        'actor_name' => $r['actor_name'],
        'action' => $r['action'],
        'target_employee_id' => $r['target_employee_id'],
        'details' => $r['details'],
        'created_at' => $r['created_at'],
    ]);
}
foreach (all('SELECT * FROM audit_logs ORDER BY id') as $r) {
    insertRow('audit_logs_new', [
        'category' => 'business',
        'legacy_source' => 'audit_logs',
        'legacy_id' => (int) $r['id'],
        'actor' => $r['actor'],
        'actor_type' => $r['actor_type'],
        'actor_id' => mapUserId($r['actor_type'] !== null ? (string) $r['actor_type'] : null, $r['actor_id']),
        'actor_name' => $r['actor'],
        'action' => $r['action'],
        'details' => $r['details'],
        'created_at' => $r['created_at'],
    ]);
}

echo '[data] attendance_new: ' . one('SELECT COUNT(*) AS n FROM attendance_new')['n'] . " rows\n";
echo '[data] settings_new: ' . one('SELECT COUNT(*) AS n FROM settings_new')['n'] . " rows\n";
echo '[data] email_verification_tokens_new: ' . one('SELECT COUNT(*) AS n FROM email_verification_tokens_new')['n'] . " rows\n";
echo '[data] password_reset_tokens_new: ' . one('SELECT COUNT(*) AS n FROM password_reset_tokens_new')['n'] . " rows\n";
echo '[data] audit_logs_new: ' . one('SELECT COUNT(*) AS n FROM audit_logs_new')['n'] . " rows\n";

echo "\nStaging build finished. Live tables were not modified.\n";
