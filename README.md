# Guinhawa Poultry Supply — Management System (PHP + MySQL)

The staff management system for Guinhawa Poultry Supply: a plain **HTML5 / CSS3 / Bootstrap 5 / vanilla JavaScript** front end backed by a self-contained **PHP 8 + MySQL** API. There is no React, no build step, and no CDN dependency.

The pages, styling, and Bootstrap build are the original dashboard design. The data layer was replaced: every read and write now goes through the authenticated PHP API and the `guinhawa_db` MySQL database. Nothing business related is stored in `localStorage`; only UI preferences (appearance, theme and the remember-me token) are cached in the browser.

This copy runs **in parallel** with the existing app at `/guinhawa`. The original folder (`C:\xampp2\htdocs\guinhawa`) is left untouched.

- New app: `http://localhost/guinhawa_system/`
- Existing app: `http://localhost/guinhawa/`
- Web root: `C:\xampp2\htdocs\guinhawa_system` (directory junction to this folder)
- Database: `guinhawa_db` on `127.0.0.1:3306`

---

## Requirements

- XAMPP with Apache, PHP 8.1+ (`pdo_mysql`, `mbstring`) and MySQL 8 / MariaDB 10.4+
- The `guinhawa_db` database with the schema in `backend/schema.sql`
- The junction or a copy of this folder under `htdocs/guinhawa_system`

## Setup

1. Create the junction (already created on this machine):

   ```powershell
   cmd /c mklink /J C:\xampp2\htdocs\guinhawa_system C:\Users\Lenovo\guinhawa_system
   ```

2. For a fresh database only, import the schema. For an existing database, skip this import and run the additive migrations below. `backend/schema.sql` is now the canonical 13-table reference/clean-install file with `CREATE TABLE IF NOT EXISTS`; it is **not** an upgrade script and must never be imported into the populated live `guinhawa_db`.

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS guinhawa_db"
   C:\xampp\mysql\bin\mysql.exe -u root guinhawa_db < backend\schema.sql
   ```

3. Run the additive migrations (safe to re-run; they never delete rows):

   ```powershell
   C:\xampp2\php\php.exe backend\scripts\migrate_auth.php
   C:\xampp2\php\php.exe backend\scripts\migrate_profile.php
   C:\xampp2\php\php.exe backend\scripts\migrate_order_type.php
   C:\xampp2\php\php.exe backend\scripts\migrate_quantities.php
   C:\xampp2\php\php.exe backend\scripts\migrate_sack_weight.php
   C:\xampp2\php\php.exe backend\scripts\migrate_design_port.php
   C:\xampp2\php\php.exe backend\scripts\migrate_account_email_verification.php
   C:\xampp2\php\php.exe backend\scripts\migrate_staff_email_verification.php
   C:\xampp2\php\php.exe backend\scripts\migrate_staff_accounts.php
   C:\xampp2\php\php.exe backend\scripts\migrate_database_cleanup.php
   ```

   `migrate_database_cleanup.php` is the additive Phase 1 cleanup: it revives no data and removes no tables or rows. It revokes sessions for accounts that no longer exist, adds a unique index on `admin.email`, and adds date indexes used by the report screens. Always take a full backup before running it.

4. Create the administrator if the database has none:

   ```powershell
   C:\xampp2\php\php.exe backend\scripts\setup_admin.php
   ```

   If an existing Admin or staff account is locked out, an administrator on the server can issue a one-time password with `backend/scripts/reset_password.php` (for example, `--user=STF-001`).

5. Open `http://localhost/guinhawa_system/`. The root sends signed-in users to their role dashboard and everyone else to the sign-in page.

Configuration lives in `backend/config/config.php`. Defaults are the standard XAMPP values (`127.0.0.1`, user `root`, empty password, database `guinhawa_db`, base path `/guinhawa_system`). Environment variables (`GUINHAWA_DB_*`, `GUINHAWA_SMTP_*`, `GUINHAWA_BASE_PATH`, …) or a protected `C:/xampp/guinhawa-config/config.local.php` override them without editing the web-served file.

---

## Compact schema migration (13 tables)

The original 26 base tables plus `staff_accounts` view have been rebuilt into 13 compact tables. The application code is already refactored and `Schema::t()` currently resolves to the verified `*_new` staging copies (`backend/config/config.php` default `compact_staging = 1`); the legacy tables are untouched. **Cutover has not been executed.**

Canonical tables: `users`, `suppliers`, `customers`, `products`, `product_variants`, `orders`, `order_items`, `inventory_transactions`, `attendance`, `settings`, `email_verification_tokens`, `password_reset_tokens`, `audit_logs`.

### Backups

- `C:\Users\Lenovo\guinhawa_db_backups\guinhawa_db_backup_20261122.sql` — original pre-cleanup dump.
- `C:\Users\Lenovo\guinhawa_db_backups\guinhawa_db_backup_pre_compact_20261122.sql` — verified pre-compact dump (26 base tables + view, all rows and foreign keys).
- Keep both outside the web root; they contain password and session hashes.

### Rebuild and verify staging

```powershell
C:\xampp2\php\php.exe backend\scripts\migrate_compact_schema.php
C:\xampp2\php\php.exe backend\scripts\cutover_compact_schema.php
```

The second command is plan-only and read-only: it checks table availability, row/total/hash/session/orphan/FK parity and prints the exact atomic `RENAME TABLE` statement. It does not change the database or configuration. Current verified staging result: 5 users, 202 products, 283 variants, 6 orders, 31 order items, 494 inventory transactions, 316 audit logs, 6,887 stock units and ₱3,110.00 in sales; 106 sessions archived in `users.session_history`; 13 foreign keys and zero orphans.

### Cutover (requires explicit approval)

Do not run this until the staging app has been exercised and a fresh verified dump exists. The command below archives every legacy table under a `_legacy` suffix, promotes each `*_new` table to its canonical name in one atomic `RENAME TABLE` statement, preserves `staff_accounts` as `staff_accounts_legacy`, and flips `compact_staging` to `0`. It never drops a table or row.

```powershell
C:\xampp2\php\php.exe backend\scripts\cutover_compact_schema.php --execute --backup=C:\Users\Lenovo\guinhawa_db_backups\guinhawa_db_backup_pre_compact_20261122.sql --confirm=I-APPROVE-COMPACT-CUTOVER
```

After cutover, repeat the smoke checks: sign in as Admin and Cashier, open `/admin`, `/inventory` and `/cashier`, call `/state`, `/auth/me`, `/appearance` and `/settings/mail`, and exercise customer/supplier CRUD shapes. Destructive checkout/stock-mutation and live-mail OTP tests still need a controlled test-data window.

### Rollback

Before the archived tables are removed, the reverse rename restores the original table names and switches `compact_staging` back to `1`. Any writes made to canonical tables after cutover stay in the `*_new` tables and are not discarded.

```powershell
C:\xampp2\php\php.exe backend\scripts\cutover_compact_schema.php --rollback --backup=C:\Users\Lenovo\guinhawa_db_backups\guinhawa_db_backup_pre_compact_20261122.sql --confirm=I-APPROVE-COMPACT-ROLLBACK
```

Removing the archived `*_legacy` tables is a separate destructive step and is **not** performed by this script.

---

## Accounts and security

- Passwords are hashed with `password_hash()` and checked with `password_verify()`; no plain-text passwords exist in the database.
- Only an Admin can create staff accounts. The Admin supplies full name, personal email, role and a default password; the Staff ID (`STF-001`, `STF-002`, …) is generated automatically. Passwords are stored only with `password_hash()`.
- Admin-entered personal emails are registered and verified immediately, so they work for sign-in and email password recovery. A staff member can later change their own personal email from Settings, but the new address requires a 6-digit verification code.
- New accounts start with the status chosen by the Admin (Active by default) and with `must_change_password = 1`. Staff sign in with their personal email, Staff ID, legacy employee ID or username and are taken to the dedicated Change Password page before any dashboard opens.
- The PHP session cookie is `guinhawa_session` (`HttpOnly`, `SameSite=Lax`, 8-hour lifetime, path `/`), so the API and the HTML pages share one login.
- Every dashboard page is a `.php` file under `admin/`, `inventory/` or `cashier/` and starts with `shared/guard.php`, which checks the session, the role and `must_change_password` before rendering. Direct access to a page outside the signed-in role's area returns `403`.
- All writes require a CSRF token (`X-CSRF-Token`, delivered by `GET /api/auth/me` and on sign-in).
- Role checks and the forced password change are enforced in the controllers and APIs, not just the UI.
- Email delivery (verification codes, reset links and OTPs) needs SMTP credentials. Staff can still sign in by Staff ID or username if mail is unavailable.
- `.sql`, `.md`, and `backend/` source are denied by `.htaccess`.

### Signing in

There are no demo accounts. Use the administrator account created by `setup_admin.php` and the temporary password it prints, then choose a new password when prompted. Admins create staff accounts with a personal email, role and default password; staff sign in with that email, their Staff ID or their username and must replace the default password before the dashboard opens.

---

## Roles and access

| Module | Admin | Inventory Staff | Cashier |
| --- | :---: | :---: | :---: |
| Admin Dashboard | ✓ | — | — |
| Inventory Dashboard | ✓ | ✓ | — |
| POS / Cashier Dashboard | — | — | ✓ |
| Products | ✓ | ✓ | ✓ (catalog view) |
| Inventory | ✓ | ✓ | — |
| Stock In / Stock Out (Inventory side panels) | ✓ | ✓ | — |
| Suppliers | ✓ | — | — |
| Transactions / Sales | ✓ (view, receipts, status, export) | — | ✓ |
| Attendance | ✓ | ✓ | ✓ |
| Inventory History (Inventory tab) | ✓ | ✓ | — |
| User Management | ✓ | — | — |
| Reports | ✓ | — | — |
| Settings | ✓ | ✓ | ✓ |

Navigation items are hidden for roles that cannot access them, and `shared/guard.php` returns `403` when a page is opened outside the role's area. Inventory Staff are limited to `inventory/`, and Cashier / Counter Staff to `cashier/`. Admins may open the Admin and Inventory areas and can view transactions and update sale statuses, but POS checkout and held orders are Cashier-only (`SalesController::checkout()` / `hold()` require `counter-staff`). The old `history.php`, `stock-in.php` and `stock-out.php` URLs redirect into the Inventory page's history tab or stock panels.

---

## Main features

### Authentication and sessions
- Glassmorphism sign-in page against the real `POST /api/auth/login` endpoint with show/hide password, Caps Lock hint, remember-me, loading state, and role-based redirect.
- Staff sign in with their personal email, Staff ID, legacy employee ID or username plus their password. When `must_change_password` is set, sign-in redirects to `login/change-password.php` before any dashboard.
- The dedicated `login/change-password.php` page validates the current/default password, strength, confirmation and "different from current", then redirects to the dashboard for the signed-in role (Admin, Inventory Staff or Cashier).
- Deactivated and still-pending accounts cannot sign in, and completing a password reset signs that account out everywhere.
- Logout destroys the PHP session and returns to the sign-in page.
- The intro animation plays once when the system is opened in a browser tab and is skipped after logout, on refresh, and when the session expires.

### Staff account creation and personal email
- Only an Admin can create, edit, activate/deactivate, reset or delete staff accounts. Public registration is disabled.
- Required fields are full name, personal email, role (Inventory Staff or Cashier) and Admin-supplied default password. Username is optional and generated from the email when left blank. Staff ID, Date Joined and Created date are generated automatically.
- Statuses shown are Active, Inactive and Must Change Password (Pending remains only for legacy invitations). Admin resets set a new temporary password, force a change at next sign-in and sign the staff member out everywhere.
- The Admin-entered personal email is treated as registered and verified. A staff member can change it themselves from Settings, but must confirm a 6-digit code (hashed, 10-minute expiry, 5 attempts, 60-second resend cooldown) before the new address is saved.
- Password recovery sends both a secure one-hour reset link and a 5-minute OTP to the registered address. Reset link tokens are stored only as SHA-256 hashes. Existing Pending Activation invitations continue to support their legacy email activation links.

### Password recovery by email OTP (Admin, Inventory Staff, Cashier)
- `login/forgot-password.php` is a three-step wizard: registered email → 6-digit code → new password.
- `backend/src/RecoveryService.php` emails a 6-digit OTP through Gmail SMTP. The OTP is hashed, expires after **5 minutes**, allows **5 attempts**, and works once. Resends have a **60-second cooldown** and the form shows the countdown.
- Responses are deliberately generic so the page cannot be used to discover which emails have accounts. Inactive accounts (and legacy pending accounts) cannot request a code.
- After verification a one-time reset token is bound to the browser session. The new password is written with `password_hash()` and checked with `password_verify()`; choosing the current password again is rejected.
- `login/reset-password.php` is the secure emailed reset-link page: a one-hour, single-use token stored only as a SHA-256 hash. The forgot-password wizard also emails a 5-minute OTP as a second recovery option.

### Admin dashboard
- Sales, transaction, product, and stock summaries from the live database.
- Revenue and payment breakdown charts drawn as hand-built SVG (no chart library).
- Sales report with five ranges — Today, Specific date, Last 7 days, Specific month, and Yearly (monthly buckets). The payment breakdown follows the selected range.
- Low-stock alerts, top products, recent transactions, and a Reports quick action. Recording sales and stock movements is done from the Cashier POS and Inventory pages, so the dashboard hero no longer shows New sale / Stock in buttons.

### Inventory Staff dashboard
- Product, available stock, low stock, out-of-stock, and expiring-within-30-days counters.
- Recent stock activity, low-stock list, expiration watch, and category stock bars.
- Hero links to the Inventory page's Stock in / Stock out side panels and its Movement history tab; the separate Quick actions card was removed.

### Cashier / POS
- Searchable product grid with categories, images, prices, and live stock.
- Cart with quantity controls, discount, payment method, cash received, and change.
- Feeds are sold by kilogram; the weight modal takes a decimal weight and the exact weight is deducted.
- Checkout writes the sale transactionally, deducts stock, appends the inventory ledger, and opens a printable receipt.

### Inventory operations
- Stock in / stock out are right-hand side panels on the Inventory page: select product → quantity → review → confirm → inventory updated. Deep links `inventory.php?panel=in|out&product=…` open the panel with a product preselected.
- Live balance preview, decimal kilograms, whole-number piece units, and over-stock protection.
- Every movement is written to `inventory_history`; delivery receipts also create supplier delivery records.
- The **Movement history** tab inside Inventory holds the filterable ledger and CSV export (same filters as the old standalone page).
- Destructive actions (products, suppliers, account changes) use the shared warning modal.

### Products and inventory
- Product create/edit/archive/restore with one or two selling units (Price A / Price B), cost, minimum stock, supplier, image, and expiry date.
- Inventory table with stock bars, status badges, expiry state, quick stock actions, and CSV export.
- Product, Inventory, and Transactions tables use a fixed-height scroll viewport with a sticky header (`.table-scroll`) so long lists never stretch the page.
- The Products page uses one row of category chips (**All products**, Accessories, Cat Food, Dog Food, Feeds, Medicine, …) plus a search box, stock filter, and status filter. Chips, counts, and the category dropdown in the product form are generated from the categories stored in MySQL — there is no hardcoded category list — so the filters always match the live catalog.

### Appearance
- **Settings → Appearance** is shared by the Admin, Inventory Staff, and Cashier areas through `shared/partials/appearance-settings.php`.
- Three theme modes (Light, Dark, System) and a fixed library of **9 professionally paired colour combinations** grouped into two families — Green (8: Forest, Emerald, Sage, Olive, Mint, Jade, Deep, Tropical) and Blue (1: Ocean Blue). Each combination ships a coordinated Light and Dark palette covering the background, sidebar, cards, primary and secondary buttons, active navigation, text, borders, notifications, and hover states.
- A global readability layer covering all three areas lifts body copy, table headers, badges, form labels, placeholders, dates, pagination, dialogs, and POS text to a readable floor (roughly 16px body text with 1.55 line height). Muted and accent text colours are generated per combination as tokens (`--gq-muted`, `--gq-muted-2`, `--gq-accent-ink`, and the amber/rose/blue/violet ink variants) so small labels clear a 4.5:1 contrast ratio on the page, cards, and tinted chips in both Light and Dark.
- There is **no colour picker, HEX/RGB field, or per-colour customisation**. An administrator selects one palette card, only one combination is active at a time, and category filters (with counts) narrow the grid. Cards show the theme name, family, description, six colour swatches, and a Selected badge, and the grid repaints with the active Light/Dark variation when the mode changes.
- Seven display and interface preferences: font size (Small/Medium/Large), layout density (Compact/Comfortable/Spacious), card corners (Square/Rounded), animation (Off/Subtle/Smooth), sidebar (Expanded/Collapsed), card appearance (Flat/Bordered/Elevated), and background (Solid/Subtle gradient).
- The desktop sidebar has an icon-only toggle button above the Guinhawa logo (and the `Ctrl+B` / `Cmd+B` shortcut). Collapsed, it becomes an 84px rail that keeps the logo and navigation icons aligned without overlap and reveals each label as a tooltip. The toggle writes the same `sidebar` preference, so the layout survives navigation, refreshes, and sign-in on another device; on small screens the separate off-canvas drawer is unaffected.
- A search box and family filters narrow the 9 cards; the grid reports how many combinations match and shows a clear empty state. The live preview and the Save/Cancel/Reset bar stay in view while the options scroll, so a change never requires scrolling to the end of the card.
- The live preview mirrors a full dashboard — sidebar and navigation, sales summary, primary/secondary buttons, product cards, table, and notification strip — and updates the whole interface immediately on any selection. **Save changes** persists the preference object to `user_appearance_settings.preferences` and mirrors the legacy columns; **Cancel** reverts the draft; **Reset to default** deletes the account's row after confirmation.
- `shared/js/theme-init.js` is loaded synchronously in every page's `<head>` and turns the saved combination into CSS custom properties before first paint. `localStorage` paints instantly, then `Store.syncAppearance()` reconciles the account row from MySQL so the same appearance follows the account across pages and devices. The original one-column-per-option columns are still written for older rows, and earlier `preset`/`accent` values are mapped onto the nearest new combination.

### Other modules
- **Suppliers:** supplier records, contacts, and linked product counts.
- **User Management:** create staff with full name, personal email, role, Admin-supplied default password, optional username (generated from the email when blank), and account status. Staff ID and Date Joined are generated automatically; the directory shows personal-email verification state and supports account activation/deactivation. Legacy pending invitations can still be resent or cancelled. Every employee uses the fixed 8:00 AM to 5:00 PM schedule; no shift selection is needed. Only Admins can create accounts.
- **Deleting staff (kept as a backup):** the trash button on each staff row removes the account from the team directory and blocks sign-in, but nothing is erased. The `employees` row is soft-deleted (`deleted_at`, `deleted_by`) so personal details, sales, attendance and audit history stay linked to the same person, and outstanding activation links are cancelled. Deleted records appear in the **Archived staff** panel below the team directory, where an Admin can open a read-only **Saved staff record** (name, Employee ID, username, email, phone, role, work schedule, joined, last active, deleted on/by) or **Restore** the record. Restored accounts return as **Inactive** and must be activated before sign-in. Archived rows keep their unique username and email, so re-creating one returns a message to restore the archived record instead.
- **Attendance:** daily log with clock in/out, admin manual edit (Present, Late, On Leave, Absent, Not recorded), notes, and summary figures.
- **Inventory History:** the Movement history tab on the Inventory page — a filterable ledger of stock in, stock out, adjustments, and sale movements with CSV export. The old `history.php` URL redirects there.
- **Reports:** sales and inventory reporting with date ranges and printable/exportable summaries.
- **Settings:** signed-in profile with role badge, real password change, personal email verification for Admin and staff, business profile, GCash QR (stored in `settings.gcash_qr`), appearance, notification preferences, read-only JSON snapshot, and system information. The page body is shared by all three roles in `shared/partials/settings-body.php` and uses the full content width, with the Appearance card providing its own jump chips for quick section access.

---

## Project structure

```text
guinhawa_system/
├── .htaccess                     # API rewrite, legacy redirects, deny rules
├── index.php                     # Entry point: role-based redirect
├── admin/                        # Admin area (12 pages; 3 are redirect-only shims)
│   ├── index.php                 # Admin dashboard
│   ├── products.php, inventory.php
│   ├── stock-in.php, stock-out.php  # Legacy URLs → inventory.php side panels
│   ├── suppliers.php, transactions.php
│   ├── attendance.php, history.php  # history.php → inventory.php?tab=history
│   ├── accounts.php              # User Management
│   └── reports.php, settings.php
├── inventory/                    # Inventory Staff area (8 pages; 3 are redirect-only shims)
│   ├── index.php                 # Inventory dashboard
│   ├── products.php, inventory.php
│   ├── stock-in.php, stock-out.php  # Legacy URLs → inventory.php side panels
│   ├── history.php               # → inventory.php?tab=history
│   └── attendance.php, settings.php
├── cashier/                      # Cashier / Counter Staff area (5 pages)
│   ├── index.php                 # POS / Cashier dashboard
│   ├── products.php
│   └── transactions.php, attendance.php, settings.php
├── login/
│   ├── index.php                 # Sign-in page
│   ├── forgot-password.php       # Email → OTP → new password wizard
│   ├── activate.php              # Legacy 24-hour staff activation link
│   ├── reset-password.php        # Secure emailed reset-link page
│   ├── change-password.php
│   ├── css/style.css
│   └── js/script.js, change-password.js,
│       forgot-password.js, activate.js
├── shared/                       # Files every area reuses
│   ├── guard.php                 # Session + role guard for role pages
│   ├── css/style.css
│   ├── js/
│   │   ├── store.js              # API-backed data adapter (sync reads, API writes)
│   │   ├── app.js                # Shared shell, nav, guards, UI helpers
│   │   ├── charts.js, theme-init.js
│   │   ├── dashboard.js, inventory-dashboard.js, cashier.js
│   │   ├── products.js, inventory.js, suppliers.js
│   │   ├── transactions.js, attendance.js, history.js, accounts.js
│   │   └── reports.js, settings.js
│   ├── bootstrap5/               # Local Bootstrap 5.3.3
│   ├── bootstrap-icons/          # Local Bootstrap Icons 1.11.3
│   └── assets/products/          # Product illustration SVGs
├── backend/
│   ├── bootstrap.php             # Config, autoload, DB, error handling
│   ├── schema.sql                # Canonical 13-table reference (never import into live DB)
│   ├── config/
│   │   ├── config.php            # DB, session, mail, CORS, base path
│   │   └── config.local.example.php
│   ├── api/index.php             # JSON API router
│   ├── scripts/                  # Additive migrations, staging build, cutover/rollback, installers
│   ├── vendor/PHPMailer/src/     # PHPMailer (blocked from the web)
│   └── src/
│       ├── Auth.php, Users.php, AccountService.php, LoginService.php
│       ├── Database.php, Http.php, Validator.php, Inventory.php
│       ├── Mailer.php            # Gmail SMTP, PHP mail() or local file driver
│       ├── RecoveryService.php   # All-role email OTP recovery
│       ├── AccountEmailVerificationService.php  # Shared Admin/Staff email codes
│       ├── StaffActivationService.php  # Account-ready emails + legacy activation links
│       ├── StaffRecoveryService.php    # Admin-issued staff reset codes
│       ├── StateRepository.php   # Builds the full state snapshot
│       └── Controllers/          # Auth, Product, Stock, Sales, Partner,
│                                 # Employee, Attendance, Settings, State …
└── assets/                       # Logo and login background
```

### URLs

| Page | URL |
| --- | --- |
| Entry point | `/guinhawa_system/` |
| Sign in | `/guinhawa_system/login/index.php` |
| Forgot password (email OTP) | `/guinhawa_system/login/forgot-password.php` |
| Staff account activation | `/guinhawa_system/login/activate.php?token=…` |
| Change password | `/guinhawa_system/login/change-password.php` |
| Admin dashboard | `/guinhawa_system/admin/index.php` |
| Inventory dashboard | `/guinhawa_system/inventory/index.php` |
| Cashier / POS | `/guinhawa_system/cashier/index.php` |
| API | `/guinhawa_system/api/...` |

Old `guinhawa-admin/*.html` and `guinhawa-login/*.html` bookmarks redirect to the matching new page.

### How the front end reads data

`shared/js/store.js` exposes `window.GuinhawaStore`. On load it performs a synchronous `GET /api/state` and maps the snapshot into the same `Store.data` shape the original pages already used (`products`, `inventory`, `sales`, `attendance`, …). Writes call the matching API method and then refresh the snapshot, so page scripts could stay almost unchanged. All API calls use the shared session cookie and the CSRF token. Each role page includes `shared/guard.php` before any HTML, which enforces the folder and page permissions on the server.

---

## API overview

All routes are under `/guinhawa_system/api` and return JSON. Authentication is the session cookie; writes also need `X-CSRF-Token`.

| Method | Route | Purpose |
| --- | --- | --- |
| `POST` | `/auth/login`, `/auth/logout` | Sign in / sign out |
| `GET` | `/auth/me` | Current user + CSRF token |
| `POST` | `/auth/change-password` | Replace a temporary or normal password |
| `POST` | `/auth/forgot-password` | Email a 6-digit recovery OTP (all roles) |
| `POST` | `/auth/verify-otp` | Verify the OTP and start a reset session |
| `POST` | `/auth/reset-password` | Save the new password after OTP verification |
| `POST` | `/auth/activate` | Complete a staff invitation with a new password |
| `GET`/`PUT` | `/auth/profile` | Read/update own profile |
| `GET` | `/state` | Full dashboard snapshot |
| `GET` | `/employees/directory` | Staff names for attendance and pickers |
| `POST` | `/products`, `PUT /products/{id}`, `DELETE /products/{id}` | Product create / update / archive |
| `POST` | `/products/{id}/restore` | Restore an archived product |
| `POST` | `/stock/in`, `/stock/out` | Stock movements |
| `POST` | `/sales/checkout` | POS checkout (Cashier / `counter-staff` only) |
| `PATCH` | `/sales/{id}/status` | Mark a sale completed or refunded |
| `POST`/`PUT`/`DELETE` | `/suppliers…` | Supplier records |
| `GET`/`POST`/`PUT`/`DELETE` | `/admin/employees…` | Staff accounts and activation actions |
| `GET` | `/auth/email-verification` | Current personal-email verification state |
| `POST` | `/auth/email-verification/request` | Send a personal-email verification code |
| `POST` | `/auth/email-verification/verify` | Verify and save the pending personal email |
| `GET` | `/admin/employees/archived` | Deleted staff kept as read-only backups |
| `POST` | `/admin/employees/{id}/restore` | Restore an archived staff record (returns **Inactive**) |
| `DELETE` | `/admin/employees/{id}` | Delete a staff account (soft delete; record kept in the archive) |
| `GET` | `/admin/activations` | Legacy accounts in Pending Activation |
| `POST` | `/admin/employees/{id}/resend-activation` | Send a fresh legacy activation link |
| `DELETE` | `/admin/employees/{id}/activation` | Cancel a pending invitation |
| `POST` | `/attendance/clock-in`, `/attendance/clock-out` | Self service attendance |
| `PUT` | `/attendance/record` | Admin upsert for any day |
| `PUT` | `/settings` | Business profile, notifications, GCash QR |
| `GET`/`PUT`/`DELETE` | `/appearance` | Per-account appearance preferences (read, save, reset) |

---

## Email setup (Gmail SMTP)

Gmail verification codes, password-reset codes, and legacy activation links are sent with PHPMailer over Gmail SMTP. Credentials must never live inside `htdocs`. Staff account creation itself does not require or send email.

1. Enable **2-Step Verification** on the dedicated Guinhawa Google account, then create an **App password** (Google Account → Security → App passwords).
2. Copy `backend/config/config.local.example.php` to `C:/xampp/guinhawa-config/config.local.php` (outside the web root) and fill in:

   ```php
   'mail' => [
       'driver' => 'smtp',
       'host' => 'smtp.gmail.com',
       'port' => 587,
       'username' => 'your-guinhawa-address@gmail.com',
       'password' => 'the-16-character-app-password',
       'encryption' => 'tls',
       'from_email' => 'your-guinhawa-address@gmail.com',
       'from_name' => 'Guinhawa Agri-Poultry Supply',
   ],
   'app' => [
       'url' => 'http://localhost/guinhawa_system',
   ],
   ```

3. Set `app.url` to the address staff actually open so legacy activation links point to this installation.
4. PHPMailer is already vendored in `backend/vendor/PHPMailer/src`. To reinstall it, run:

   ```powershell
   C:\xampp2\php\php.exe backend\scripts\install_phpmailer.php
   ```

5. For offline testing without a mailbox, set `'driver' => 'file'` with a `spool_dir`; messages are written as `.eml` files outside the web root. Use `file` only for local testing.

The Gmail address and app password are read from the protected config file (or environment variables) and are never written to HTML, JavaScript, or logs.

---

## Data and backups

- Business data lives only in MySQL. The JSON export in **Settings → Data management** is a **read-only snapshot**; it is not an importable backup.
- Use a MySQL dump for backups:

  ```powershell
  C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction guinhawa_db > guinhawa_db.sql
  ```

- `guinhawa_db.sql` is intentionally ignored by git because it contains password and session hashes; the repository is public.
- The dashboard never clears or resets database tables. The old "reset demo data" action and the former `POST /api/state/restore` endpoint have been removed.
- Deleting staff never removes rows. Accounts are soft-deleted into **Archived staff**, so a normal MySQL dump captures every former staff member, their personal details and their linked sales/attendance history. A restore only clears `deleted_at` / `deleted_by`.
- Additive migrations live in `backend/scripts/` and are safe to run repeatedly. The Phase 1 cleanup migration is idempotent and never drops a table or deletes business data.
- `backend/scripts/migrate_compact_schema.php` builds the verified `*_new` staging copies without touching legacy tables. `backend/scripts/cutover_compact_schema.php` prints a read-only plan by default and only renames tables when given `--execute` plus an explicit confirmation and a verified dump.
- Verified backups: `C:\Users\Lenovo\guinhawa_db_backups\guinhawa_db_backup_20261122.sql` (original pre-cleanup dump) and `C:\Users\Lenovo\guinhawa_db_backups\guinhawa_db_backup_pre_compact_20261122.sql` (pre-compact dump, 26 base tables + 1 view, all rows and foreign keys). Keep both files outside the web root; they contain password hashes.

---

## Notes

- This app shares `guinhawa_db` with the existing `/guinhawa` app. Both can run at the same time; schema changes are additive.
- No CDNs, analytics, or third-party front-end assets are used. Bootstrap, Bootstrap Icons, SweetAlert2, and fonts are served locally. The only outbound network call is the configured Gmail SMTP connection that delivers verification codes, password-reset links and OTPs, and legacy activation emails.
- Product images are reused from the assets folder and are not overwritten by the application.
- UI preferences (colour combination, theme mode, font size, density, corners, animation, sidebar, card style, background, remember-me) are cached in browser storage for instant painting; the authoritative copy is the signed-in account's `preferences` JSON column in `users` (currently `users_new` while staging is active). Business data is always read from MySQL.
- Run `C:\xampp2\php\php.exe backend\scripts\migrate_appearance.php` once on an older *legacy* database to add the old `user_appearance_settings.preferences` JSON column. The compact migration maps those rows into `users.preferences`; the script is idempotent.
- Run `C:\xampp2\php\php.exe backend\scripts\migrate_staff_archive.php` once on an older database to add the `employees.deleted_at` / `employees.deleted_by` archive columns and their index. The script is idempotent and never deletes data.
- Run `C:\xampp2\php\php.exe backend\scripts\migrate_account_email_verification.php` once on an older database to add `email_verified` / `email_verified_at` and backfill Admin-entered addresses as verified. Only for older installs that used the staff-only verification table, run `C:\xampp2\php\php.exe backend\scripts\migrate_staff_email_verification.php` first so its pending codes are copied by the shared migration. Both are idempotent and never delete staff rows.
- Run `C:\xampp2\php\php.exe backend\scripts\migrate_fixed_schedule.php` once on an older database to drop the legacy `employees.work_shift` column. Every employee now follows the fixed **8:00 AM – 5:00 PM** schedule. The script is idempotent and keeps all staff rows.
