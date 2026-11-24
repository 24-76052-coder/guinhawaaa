-- =====================================================================
-- Guinhawa Poultry Supply Management System
-- Canonical compact schema (13 tables) - reference / clean install only.
--
-- DO NOT import this file into the existing populated `guinhawa_db`.
-- The live database is being migrated with
-- backend/scripts/migrate_compact_schema.php into *_new staging tables and
-- will be cut over with a separate, explicitly approved rename script.
--
-- CREATE TABLE IF NOT EXISTS is used so an accidental import cannot drop or
-- overwrite existing data. For a genuinely clean install, create/select the
-- database first:
--   CREATE DATABASE IF NOT EXISTS guinhawa_db
--     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE guinhawa_db;
-- =====================================================================
-- users
CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- suppliers
CREATE TABLE IF NOT EXISTS suppliers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- customers
CREATE TABLE IF NOT EXISTS customers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- products
CREATE TABLE IF NOT EXISTS products (
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
    REFERENCES suppliers(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- product_variants
CREATE TABLE IF NOT EXISTS product_variants (
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
    REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- orders
CREATE TABLE IF NOT EXISTS orders (
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
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT compact_fk_orders_customer FOREIGN KEY (customer_ref)
    REFERENCES customers(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- order_items
CREATE TABLE IF NOT EXISTS order_items (
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
    REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT compact_fk_order_items_product FOREIGN KEY (product_id)
    REFERENCES products(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- inventory_transactions
CREATE TABLE IF NOT EXISTS inventory_transactions (
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
    REFERENCES products(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT compact_fk_inv_txn_supplier FOREIGN KEY (supplier_id)
    REFERENCES suppliers(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT compact_fk_inv_txn_actor FOREIGN KEY (actor_id)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- attendance
CREATE TABLE IF NOT EXISTS attendance (
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
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- settings
CREATE TABLE IF NOT EXISTS settings (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- email_verification_tokens
CREATE TABLE IF NOT EXISTS email_verification_tokens (
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
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- password_reset_tokens
CREATE TABLE IF NOT EXISTS password_reset_tokens (
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
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- audit_logs
CREATE TABLE IF NOT EXISTS audit_logs (
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
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

