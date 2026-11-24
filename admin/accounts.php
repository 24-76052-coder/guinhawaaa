<?php require __DIR__ . '/../shared/guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00291c">
  <meta name="description" content="Guinhawa Poultry Supply user management — create and manage staff accounts.">
  <title>User Management &middot; Guinhawa Admin</title>

  <script src="/guinhawa_system/shared/js/theme-init.js?v=cleanup-20261122-2"></script>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap5/css/bootstrap.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/sweetalert2/sweetalert2.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/css/style.css">
</head>
<body data-page="accounts">
  <a class="skip-link" href="#mainContent">Skip to content</a>

  <div id="sidebarRoot"></div>
  <div id="offcanvasRoot"></div>

  <div class="app-main">
    <div id="topbarRoot"></div>

    <main class="page-content" id="mainContent">
      <div class="page-head">
        <div>
          <div class="page-eyebrow"><i class="bi bi-people" aria-hidden="true"></i> Administration</div>
          <h1 class="page-title">Staff Account Management</h1>
          <p class="page-sub">
            Create and manage Inventory Staff and Cashier accounts. The Admin sets each staff member's
            default password; staff sign in with their registered email or Staff ID and must change that
            password on first sign-in. Only Admins can add staff, assign roles and change access.
          </p>
        </div>
        <div class="page-actions">
          <button class="btn btn-outline-app" type="button" id="exportAccounts">
            <i class="bi bi-download" aria-hidden="true"></i> Export CSV
          </button>
          <button class="btn btn-brand" type="button" id="addAccountBtn">
            <i class="bi bi-person-plus" aria-hidden="true"></i> Add new staff
          </button>
        </div>
      </div>

      <div class="role-guard">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        <div>
          <strong>Administrator access.</strong> Only Admin accounts can create Inventory Staff and Cashier accounts.
          Public registration is disabled. The signed-in Admin account is protected from deactivation and role changes.
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-people" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Total accounts</span><span class="inventory-stat__value" id="statAccounts">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Admins</span><span class="inventory-stat__value" id="statAdmins">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Inventory staff</span><span class="inventory-stat__value" id="statInventory">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-receipt" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Cashiers</span><span class="inventory-stat__value" id="statCashiers">&mdash;</span></div>
          </div>
        </div>
      </div>

      <section class="panel mb-3" id="activationPanel">
        <div class="panel__header">
          <div>
            <h2 class="panel__title">
              <i class="bi bi-envelope-paper" aria-hidden="true"></i> Pending activations
              <span class="pill-count ms-2" id="activationCount">0</span>
            </h2>
            <p class="panel__sub">
              Legacy invitations created before the account-ready email flow wait here until
              their emailed activation link is used. Links expire after 24 hours and work only once.
            </p>
          </div>
        </div>
        <div class="panel__body panel__body--flush">
          <div class="table-responsive">
            <table class="table table-app" id="activationTable">
              <thead>
                <tr>
                  <th>Staff member</th>
                  <th>Personal email</th>
                  <th>Role</th>
                  <th>Link sent</th>
                  <th>Link expires</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="activationBody"></tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="panel mb-3">
        <div class="panel__body panel__body--filters">
          <div class="filter-bar">
            <div class="search-field">
              <i class="bi bi-search" aria-hidden="true"></i>
              <input class="form-control" type="search" id="accountSearch" placeholder="Search name, Staff ID, email, username, or phone" aria-label="Search accounts">
            </div>
            <select class="form-select" id="accountRole" aria-label="Filter by role" style="max-width:180px">
              <option value="">All roles</option>
              <option value="Admin">Admin</option>
              <option value="Inventory Staff">Inventory Staff</option>
              <option value="Cashier">Cashier</option>
            </select>
            <select class="form-select" id="accountStatus" aria-label="Filter by status" style="max-width:210px">
              <option value="">All statuses</option>
              <option value="Active">Active</option>
              <option value="Must Change Password">Must Change Password</option>
              <option value="Inactive">Inactive</option>
              <option value="Pending">Pending (legacy)</option>
            </select>
            <button class="btn btn-outline-app" type="button" id="clearAccountFilters">
              <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Clear
            </button>
            <span class="pill-count ms-auto" id="accountCount">0 accounts</span>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel__header">
          <div>
            <h2 class="panel__title"><i class="bi bi-person-badge" aria-hidden="true"></i> Team directory</h2>
            <p class="panel__sub">Roles control what each person can access in the console</p>
          </div>
          <span class="demo-watermark"><span class="dot"></span> Staff accounts</span>
        </div>
        <div class="panel__body panel__body--flush">
          <div class="table-responsive">
            <table class="table table-app" id="accountsTable">
              <thead>
                <tr>
                  <th class="sortable">Staff member <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Staff ID <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Role <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th>Contact</th>
                  <th class="sortable">Created <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Last password change <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Last active <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Status <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="accountsBody"></tbody>
            </table>
          </div>
          <div class="table-foot">
            <span id="accountsFoot">Showing 0 of 0 accounts</span>
            <span class="text-app-muted">Admin passwords can never be viewed &middot; use Reset password to issue a temporary one</span>
          </div>
        </div>
      </section>

      <section class="panel mt-3 d-none" id="archivedPanel">
        <div class="panel__header">
          <div>
            <h2 class="panel__title">
              <i class="bi bi-archive" aria-hidden="true"></i> Archived staff
              <span class="pill-count ms-2" id="archivedCount">0</span>
            </h2>
            <p class="panel__sub">
              Deleted staff are kept as read-only backups. Their details, sales, attendance and
              audit history stay linked, and an Admin can restore the record at any time.
            </p>
          </div>
        </div>
        <div class="panel__body panel__body--flush">
          <div class="table-responsive">
            <table class="table table-app" id="archivedTable">
              <thead>
                <tr>
                  <th>Staff member</th>
                  <th>Staff ID</th>
                  <th>Role</th>
                  <th>Joined</th>
                  <th>Deleted on</th>
                  <th>Deleted by</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="archivedBody"></tbody>
            </table>
          </div>
        </div>
      </section>
    </main>

    <div id="footerRoot"></div>
  </div>

  <!-- Add / edit staff account modal -->
  <div class="modal fade staff-form-modal" id="accountModal" tabindex="-1" aria-labelledby="accountModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-3">
            <div class="modal-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></div>
            <div>
              <h2 class="modal-title" id="accountModalTitle">Add new staff</h2>
              <div class="text-app-muted" style="font-size:.8rem" id="accountModalSub">Inventory Staff and Cashier accounts only</div>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-app" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="accountForm" novalidate>
          <div class="modal-body">
            <div class="alert-app staff-form-note" id="activationNote">
              <i class="bi bi-shield-check" aria-hidden="true"></i>
              <div>
                The account is created <strong>Active</strong> with the default password you set below.
                The staff member signs in with their email or Staff ID and must choose a new password
                before any dashboard opens. You will never be able to view that password again.
              </div>
            </div>

            <!-- Personal information -->
            <section class="form-section" aria-labelledby="personalSectionTitle">
              <div class="form-section__head">
                <span class="form-section__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                <div>
                  <h3 id="personalSectionTitle">Personal information</h3>
                  <p>Who the account belongs to and their contact details.</p>
                </div>
              </div>
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label class="form-label" for="accountName">Full name <span class="req">*</span></label>
                  <input class="form-control" id="accountName" type="text" placeholder="e.g. Maria Santos" autocomplete="name" required>
                  <div class="invalid-feedback">Enter the staff member's full name.</div>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="accountPhone">Phone number <span class="opt">Optional</span></label>
                  <input class="form-control" id="accountPhone" type="tel" placeholder="e.g. 0917 000 0000" autocomplete="tel" pattern="[0-9+() -]{7,40}" title="Use 7 to 40 digits, spaces, dashes, parentheses or a leading plus sign">
                  <div class="invalid-feedback">Enter a valid phone number.</div>
                  <div class="form-text">Contact number shown in the team directory.</div>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="accountEmail">Staff personal email <span class="req">*</span></label>
                  <input class="form-control" id="accountEmail" type="email" placeholder="name@example.com" autocomplete="email" required>
                  <div class="invalid-feedback">Enter a valid email address.</div>
                  <div class="form-text">Used to sign in and to receive password-reset links.</div>
                </div>
              </div>
            </section>

            <!-- Employee information -->
            <section class="form-section" aria-labelledby="employeeSectionTitle">
              <div class="form-section__head">
                <span class="form-section__icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
                <div>
                  <h3 id="employeeSectionTitle">Employee information</h3>
                  <p>Identity and role inside the store. All staff follow the fixed 8:00 AM &ndash; 5:00 PM schedule.</p>
                </div>
              </div>
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label class="form-label" for="accountStaffId">Staff ID</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-magic" aria-hidden="true"></i></span>
                    <input class="form-control" id="accountStaffId" type="text" readonly aria-readonly="true" tabindex="-1">
                  </div>
                  <div class="form-text">Generated automatically in STF-001, STF-002 order.</div>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="accountUsername">Username <span class="opt">Optional</span></label>
                  <input class="form-control" id="accountUsername" type="text" placeholder="Auto-generated from email" pattern="[A-Za-z0-9._-]+" title="Use letters, numbers, dots, dashes or underscores" autocomplete="off">
                  <div class="invalid-feedback">Enter a unique username (letters, numbers, dots, dashes, underscores).</div>
                  <div class="form-text">Leave blank to generate one from the staff member's email.</div>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="accountRoleInput">Role <span class="req">*</span></label>
                  <select class="form-select" id="accountRoleInput" required>
                    <option value="Inventory Staff">Inventory Staff</option>
                    <option value="Cashier">Cashier</option>
                    <option value="Admin" hidden>Admin</option>
                  </select>
                  <div class="form-text" id="roleHelp">Inventory staff can receive and release stock.</div>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label" for="accountStatusInput">Account status <span class="req">*</span></label>
                  <select class="form-select" id="accountStatusInput" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="pending" hidden>Pending (legacy)</option>
                  </select>
                  <div class="form-text">Inactive accounts cannot sign in until reactivated.</div>
                </div>
              </div>
            </section>

            <!-- Account security -->
            <section class="form-section" id="passwordSection" aria-labelledby="securitySectionTitle">
              <div class="form-section__head">
                <span class="form-section__icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                <div>
                  <h3 id="securitySectionTitle">Default password</h3>
                  <p>The staff member must replace this password the first time they sign in.</p>
                </div>
              </div>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="accountPassword">Default password <span class="req">*</span></label>
                  <div class="input-group">
                    <input class="form-control" id="accountPassword" type="password" placeholder="At least 10 characters" autocomplete="new-password" minlength="10" required>
                    <button class="btn btn-outline-app" type="button" id="toggleAccountPassword" aria-label="Show default password" aria-pressed="false">
                      <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                    <button class="btn btn-outline-app" type="button" id="generateAccountPassword">
                      <i class="bi bi-stars" aria-hidden="true"></i> Generate
                    </button>
                  </div>
                  <div class="invalid-feedback">Use at least 10 characters with an uppercase letter, a lowercase letter and a number.</div>
                  <div class="form-text">Share it with the staff member securely. It is never shown again after the account is created.</div>
                </div>
              </div>
            </section>

          </div>

          <div class="modal-footer staff-form-footer">
            <button type="button" class="btn btn-outline-app" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-brand" id="saveAccountBtn">
              <i class="bi bi-send" aria-hidden="true"></i> <span id="saveAccountLabel">Create staff account</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reset password modal -->
  <div class="modal fade staff-form-modal" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-3">
            <div class="modal-icon"><i class="bi bi-key" aria-hidden="true"></i></div>
            <div>
              <h2 class="modal-title" id="resetPasswordTitle">Reset password</h2>
              <div class="text-app-muted" style="font-size:.8rem" id="resetPasswordSub">Issue a temporary password</div>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-app" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="resetPasswordForm" novalidate>
          <div class="modal-body">
            <div class="alert-app staff-form-note">
              <i class="bi bi-shield-lock" aria-hidden="true"></i>
              <div>
                The staff member must choose a new password the next time they sign in. Any active
                sessions are signed out immediately. You can never view an existing password.
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label" for="resetPasswordInput">Temporary password <span class="opt">Optional</span></label>
              <div class="input-group">
                <input class="form-control" id="resetPasswordInput" type="password" placeholder="Leave blank to generate one" autocomplete="new-password" minlength="10">
                <button class="btn btn-outline-app" type="button" id="toggleResetPassword" aria-label="Show temporary password" aria-pressed="false">
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
                <button class="btn btn-outline-app" type="button" id="generateResetPassword">
                  <i class="bi bi-stars" aria-hidden="true"></i> Generate
                </button>
              </div>
              <div class="invalid-feedback">Use at least 10 characters with an uppercase letter, a lowercase letter and a number.</div>
              <div class="form-text">If left blank, a secure password is generated and shown to you once.</div>
            </div>
          </div>

          <div class="modal-footer staff-form-footer">
            <button type="button" class="btn btn-outline-app" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-brand" id="resetPasswordBtn">
              <i class="bi bi-key" aria-hidden="true"></i> Reset password
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="/guinhawa_system/shared/bootstrap5/js/bootstrap.bundle.min.js" defer></script>
  <script src="/guinhawa_system/shared/sweetalert2/sweetalert2.all.min.js" defer></script>
  <script src="/guinhawa_system/shared/js/store.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/charts.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/app.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/accounts.js?v=cleanup-20261122-2" defer></script>
</body>
</html>
