<?php
/**
 * Shared settings body.
 *
 * Included by admin/settings.php, inventory/settings.php and
 * cashier/settings.php so every role sees the same sections, the same
 * navigation and the same wording. Admin-only sections are hidden by
 * shared/js/settings.js after the signed-in account is known.
 */

declare(strict_types=1);
?>
      <div class="page-head">
        <div>
          <div class="page-eyebrow"><i class="bi bi-sliders2" aria-hidden="true"></i> Workspace</div>
          <h1 class="page-title">Settings</h1>
          <p class="page-sub">
            Manage your account, the store details and the way Guinhawa looks for you.
            Store details are saved to the shared MySQL database.
          </p>
        </div>
        <div class="page-actions">
          <span class="badge-soft"><i class="bi bi-database"></i> MySQL connected</span>
        </div>
      </div>

      <div class="settings-sections">
        <!-- Signed-in profile -->
          <section class="settings-card mb-4" id="profile">
            <header class="settings-card__head">
              <button class="profile-avatar-button" id="profileAvatarPreview" type="button" aria-label="Change profile avatar" title="Change profile avatar" style="display:grid;place-items:center;width:48px;height:48px;min-width:48px;max-width:48px;flex:0 0 48px;overflow:hidden;padding:0;border-radius:50%"></button>
              <div class="settings-card__headtext">
                <h2 class="settings-card__title">My profile</h2>
                <p class="settings-card__sub">Details for the account signed in to this browser session.</p>
              </div>
              <span class="settings-card__badge" id="profileHeadRole">&mdash;</span>
            </header>

            <div class="detail-grid mb-3">
              <div class="detail-item"><span class="detail-item__label">Username</span><span class="detail-item__value" id="profileUsername" style="font-family:Consolas,monospace">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Personal email</span><span class="detail-item__value" id="profileEmailDisplay">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Employee ID</span><span class="detail-item__value" id="profileEmployeeId">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Role</span><span class="detail-item__value" id="profileRole">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Account status</span><span class="detail-item__value" id="profileStatus">&mdash;</span></div>
            </div>

            <form id="profileForm" novalidate>
              <input class="d-none" id="profilePhotoInput" type="file" accept="image/png,image/jpeg,image/webp">
              <div class="avatar-crop-modal" id="avatarCropModal" role="dialog" aria-modal="true" aria-labelledby="avatarCropTitle" hidden>
                <div class="avatar-crop-dialog">
                  <h2 id="avatarCropTitle">Crop Your Image</h2>
                  <div class="avatar-crop-content">
                    <div>
                      <div class="avatar-crop-stage" id="avatarCropStage">
                        <img id="avatarCropImage" alt="Image crop workspace" draggable="false">
                        <div class="avatar-crop-frame" id="avatarCropFrame">
                          <button class="avatar-crop-handle" type="button" data-handle="nw" aria-label="Resize crop from top left"></button>
                          <button class="avatar-crop-handle" type="button" data-handle="ne" aria-label="Resize crop from top right"></button>
                          <button class="avatar-crop-handle" type="button" data-handle="sw" aria-label="Resize crop from bottom left"></button>
                          <button class="avatar-crop-handle" type="button" data-handle="se" aria-label="Resize crop from bottom right"></button>
                        </div>
                      </div>
                      <div class="avatar-crop-zoom">
                        <label for="avatarCropZoom">Zoom</label>
                        <input id="avatarCropZoom" type="range" min="1" max="4" step="0.01" value="1">
                      </div>
                      <p class="avatar-crop-hint">Drag the image to position it. Drag a corner to resize the square crop.</p>
                    </div>
                    <aside class="avatar-crop-preview-wrap">
                      <span>Preview</span>
                      <canvas id="avatarCropPreview" width="96" height="96" aria-label="Live cropped profile picture preview"></canvas>
                    </aside>
                  </div>
                  <div class="avatar-crop-actions">
                    <button class="avatar-crop-reset" id="resetAvatarCrop" type="button">Reset</button>
                    <div>
                      <button class="avatar-crop-cancel" id="cancelAvatarCrop" type="button">Cancel</button>
                      <button class="avatar-crop-save" id="saveAvatarCrop" type="button">Save Changes</button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="profileName">Full name</label>
                  <input class="form-control" id="profileName" type="text" required>
                  <div class="invalid-feedback">Enter your full name.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="profilePhone">Phone</label>
                  <input class="form-control" id="profilePhone" type="tel" placeholder="0917 000 0000">
                </div>
              </div>
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                <span class="text-app-muted" style="font-size:.8rem">
                  Username, employee ID, and role are managed from User Management.
                </span>
                <button class="btn btn-brand" type="submit" id="saveProfileBtn">
                  <i class="bi bi-check2" aria-hidden="true"></i> Save profile
                </button>
              </div>
            </form>
          </section>

          <section class="settings-card mb-4 d-none" id="accountEmailSection">
            <header class="settings-card__head">
              <span class="settings-card__icon" aria-hidden="true"><i class="bi bi-envelope-check"></i></span>
              <div class="settings-card__headtext">
                <h2 class="settings-card__title">Personal email</h2>
                <p class="settings-card__sub">Your personal email is used to sign in and recover your password. To change it, enter a new address and confirm it with a code.</p>
              </div>
              <span class="settings-card__badge" id="accountEmailBadge">Not verified</span>
            </header>

            <div class="detail-item mb-3">
              <span class="detail-item__label">Current email status</span>
              <span class="detail-item__value" id="accountEmailCurrent">No email added</span>
            </div>
            <div class="row g-3 align-items-end">
              <div class="col-md-7">
                <label class="form-label" for="accountEmailAddress">Personal email address</label>
                <input class="form-control" id="accountEmailAddress" type="email" placeholder="name@example.com" autocomplete="email" required>
                <div class="invalid-feedback">Enter a valid email address.</div>
              </div>
              <div class="col-md-5">
                <button class="btn btn-outline-app" type="button" id="requestAccountEmailCode">
                  <i class="bi bi-send" aria-hidden="true"></i> Send verification code
                </button>
              </div>
              <div class="col-md-7" id="accountEmailCodePanel">
                <label class="form-label" for="accountEmailCode">6-digit verification code</label>
                <input class="form-control" id="accountEmailCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required>
                <div class="form-text" id="accountEmailCodeHelp">Enter your email above and send a code, then type the 6-digit code here.</div>
                <div class="invalid-feedback">Enter the 6-digit code sent to your email.</div>
              </div>
              <div class="col-md-5" id="accountEmailVerifyPanel">
                <button class="btn btn-brand" type="button" id="verifyAccountEmailCode">
                  <i class="bi bi-shield-check" aria-hidden="true"></i> Verify code
                </button>
              </div>
            </div>
            <div class="alert-app mt-3" role="status" aria-live="polite" id="accountEmailStatus">
              <i class="bi bi-info-circle" aria-hidden="true"></i>
              <div>You can sign in without verifying your email. Verify it to use email password recovery.</div>
            </div>
          </section>

          <!-- Business profile -->
          <section class="settings-card mb-4" id="business">
            <header class="settings-card__head">
              <span class="settings-card__icon" aria-hidden="true"><i class="bi bi-shop"></i></span>
              <div class="settings-card__headtext">
                <h2 class="settings-card__title">Business profile</h2>
                <p class="settings-card__sub">Shown on receipts and used across the console.</p>
              </div>
              <span class="settings-card__badge">Store-wide</span>
            </header>

            <form id="businessForm" novalidate>
              <div class="row g-3">
                <div class="col-md-7">
                  <label class="form-label" for="settingsBusinessName">Business name <span class="req">*</span></label>
                  <input class="form-control" id="settingsBusinessName" type="text" required>
                  <div class="invalid-feedback">Enter the business name.</div>
                </div>
                <div class="col-md-5">
                  <label class="form-label" for="settingsOwner">Owner / manager</label>
                  <input class="form-control" id="settingsOwner" type="text">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="settingsEmail">Email <span class="req">*</span></label>
                  <input class="form-control" id="settingsEmail" type="email" required>
                  <div class="invalid-feedback">Enter a valid email address.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="settingsPhone">Phone <span class="req">*</span></label>
                  <input class="form-control" id="settingsPhone" type="tel" required>
                  <div class="invalid-feedback">Enter a contact number.</div>
                </div>
                <div class="col-12">
                  <label class="form-label" for="settingsAddress">Address <span class="req">*</span></label>
                  <input class="form-control" id="settingsAddress" type="text" required>
                  <div class="invalid-feedback">Enter the business address.</div>
                </div>
                <div class="col-md-5">
                  <label class="form-label" for="settingsCurrency">Currency</label>
                  <input class="form-control" id="settingsCurrency" type="text" value="Philippine peso (&#8369;)" disabled>
                  <div class="form-text">Fixed to the Philippine peso (PHP) for this store.</div>
                </div>
                <div class="col-md-7">
                  <label class="form-label" for="settingsReceiptFooter">Receipt footer</label>
                  <input class="form-control" id="settingsReceiptFooter" type="text" placeholder="Thank you message">
                </div>
              </div>
              <div class="d-flex justify-content-end mt-3">
                <button class="btn btn-brand" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Save profile</button>
              </div>
            </form>
          </section>

          <!-- GCash QR -->
          <section class="settings-card mb-4" id="gcash">
            <header class="settings-card__head">
              <span class="settings-card__icon" aria-hidden="true"><i class="bi bi-qr-code"></i></span>
              <div class="settings-card__headtext">
                <h2 class="settings-card__title">GCash QR code</h2>
                <p class="settings-card__sub">Upload the GCash QR image customers scan when paying. Just choose an image &mdash; nothing else to configure.</p>
              </div>
              <span class="settings-card__badge">Store-wide</span>
            </header>

            <div class="gcash-qr">
              <div class="gcash-qr__preview" id="gcashQrPreview">
                <div class="gcash-qr__empty" id="gcashQrEmpty">
                  <i class="bi bi-qr-code" aria-hidden="true"></i>
                  <strong>No QR image yet</strong>
                  <span>PNG, JPG, or WebP up to 2&nbsp;MB.</span>
                </div>
                <img id="gcashQrImage" alt="GCash payment QR code" hidden>
              </div>

              <div class="gcash-qr__side">
                <div class="gcash-qr__title">Payment QR</div>
                <p class="gcash-qr__text">
                  Use a clear screenshot of your GCash QR. The uploaded image is shown here
                  exactly as provided, so keep the QR area uncropped and unobstructed.
                </p>
                <div class="d-flex flex-wrap gap-2">
                  <label class="btn btn-brand mb-0" for="gcashQrInput">
                    <i class="bi bi-upload" aria-hidden="true"></i> Upload QR image
                  </label>
                  <input type="file" id="gcashQrInput" accept="image/png,image/jpeg,image/webp" hidden>
                  <button class="btn btn-outline-app" type="button" id="gcashQrRemove" hidden>
                    <i class="bi bi-trash3" aria-hidden="true"></i> Remove
                  </button>
                </div>
                <p class="gcash-qr__note">
                  <i class="bi bi-shield-check" aria-hidden="true"></i>
                  The image is saved to the shared store settings and shown on every device.
                </p>
              </div>
            </div>
          </section>

          <!-- Appearance -->
          <?php require __DIR__ . '/appearance-settings.php'; ?>

          <!-- Email delivery -->
          <section class="settings-card mb-4 d-none" id="mailDelivery">
            <header class="settings-card__head">
              <span class="settings-card__icon" aria-hidden="true"><i class="bi bi-envelope-check"></i></span>
              <div class="settings-card__headtext">
                <h2 class="settings-card__title">Email for staff codes</h2>
                <p class="settings-card__sub">Set this up once so staff receive verification and password reset codes at their personal email.</p>
              </div>
              <span class="settings-card__badge" id="mailDeliveryBadge">Admin only</span>
            </header>

            <form id="mailSettingsForm" novalidate>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="mailSenderEmail">Sender Gmail address <span class="req">*</span></label>
                  <input class="form-control" id="mailSenderEmail" type="email" autocomplete="email" placeholder="name@gmail.com" required>
                  <div class="invalid-feedback">Enter the Gmail account Guinhawa will send from.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="mailAppPassword">Google App Password</label>
                  <input class="form-control" id="mailAppPassword" type="password" autocomplete="new-password" placeholder="Enter to set or replace the password">
                  <div class="form-text" id="mailPasswordHelp">Use a Google App Password, not your regular Gmail password. It is saved securely and never shown again.</div>
                </div>
              </div>
              <div class="alert-app mt-3" role="status" aria-live="polite" id="mailSettingsStatus">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <div>Loading email delivery status&hellip;</div>
              </div>
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                <span class="text-app-muted" style="font-size:.8rem">Test messages go to the sender Gmail. Staff codes go to each staff member's personal email.</span>
                <div class="d-flex flex-wrap gap-2">
                  <button class="btn btn-brand" type="submit" id="saveMailSettingsButton">
                    <i class="bi bi-check2" aria-hidden="true"></i> Save settings
                  </button>
                  <button class="btn btn-outline-app" type="button" id="testMailButton">
                    <i class="bi bi-send" aria-hidden="true"></i> Send test to sender Gmail
                  </button>
                </div>
              </div>
            </form>
          </section>

          <!-- Notifications -->
          <section class="settings-card mb-4" id="notifications">
            <header class="settings-card__head">
              <span class="settings-card__icon" aria-hidden="true"><i class="bi bi-bell"></i></span>
              <div class="settings-card__headtext">
                <h2 class="settings-card__title">Notifications</h2>
                <p class="settings-card__sub">Choose which alerts appear inside the console. No email or SMS is sent yet.</p>
              </div>
              <span class="settings-card__badge">Store-wide</span>
            </header>

            <form id="notificationsForm">
              <div class="setting-row">
                <div>
                  <div class="setting-row__title">Low stock alerts</div>
                  <p class="setting-row__text">Warn when a product reaches its minimum stock level.</p>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="notifyLowStock">
                </div>
              </div>
              <div class="setting-row">
                <div>
                  <div class="setting-row__title">Daily sales summary</div>
                  <p class="setting-row__text">Show a morning summary of yesterday&rsquo;s sales performance.</p>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="notifyDailySales">
                </div>
              </div>
              <div class="setting-row">
                <div>
                  <div class="setting-row__title">New order notifications</div>
                  <p class="setting-row__text">Flag new pending sales that need settlement.</p>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="notifyNewOrders">
                </div>
              </div>
              <div class="setting-row">
                <div>
                  <div class="setting-row__title">Attendance reminders</div>
                  <p class="setting-row__text">Remind staff to time in and time out for their shift.</p>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="notifyAttendance">
                </div>
              </div>
              <div class="d-flex justify-content-end mt-3">
                <button class="btn btn-brand" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Save notifications</button>
              </div>
            </form>
          </section>

          <!-- Data -->
          <section class="settings-card mb-4" id="data">
            <header class="settings-card__head">
              <span class="settings-card__icon" aria-hidden="true"><i class="bi bi-database"></i></span>
              <div class="settings-card__headtext">
                <h2 class="settings-card__title">Data management</h2>
                <p class="settings-card__sub">Review record counts, export a snapshot, or work with SQL backups.</p>
              </div>
              <span class="settings-card__badge">Read-only</span>
            </header>

            <div class="detail-grid mb-3">
              <div class="detail-item"><span class="detail-item__label">Products</span><span class="detail-item__value" id="dataProducts">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Suppliers</span><span class="detail-item__value" id="dataSuppliers">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Accounts</span><span class="detail-item__value" id="dataAccounts">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Transactions</span><span class="detail-item__value" id="dataTransactions">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Attendance records</span><span class="detail-item__value" id="dataAttendance">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Stock movements</span><span class="detail-item__value" id="dataHistory">&mdash;</span></div>
            </div>

            <div class="detail-item mb-3">
              <span class="detail-item__label">Database</span>
              <span class="detail-item__value" style="font-family:Consolas,monospace;font-size:.82rem">guinhawa_db (MySQL)</span>
            </div>
            <div class="detail-item mb-3">
              <span class="detail-item__label">Last updated</span>
              <span class="detail-item__value" id="dataUpdated">&mdash;</span>
            </div>

            <div class="alert-app mb-3">
              <i class="bi bi-info-circle" aria-hidden="true"></i>
              <div>
                This page reads live records through the authenticated PHP API. The JSON snapshot is
                read-only; for a full backup use a MySQL dump from phpMyAdmin.
              </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-soft" type="button" id="exportBackup">
                <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> Export JSON snapshot
              </button>
            </div>

            <hr class="divider my-4">

            <div class="alert-app">
              <i class="bi bi-shield-lock" aria-hidden="true"></i>
              <div>
                <strong>Backups are managed outside the browser.</strong> The catalog, staff, sales,
                attendance, and stock ledger live in the <code>guinhawa_db</code> MySQL database.
                Export a SQL dump from phpMyAdmin to back up or restore data.
              </div>
            </div>
          </section>

          <!-- System information -->
          <section class="settings-card" id="system">
            <header class="settings-card__head">
              <span class="settings-card__icon" aria-hidden="true"><i class="bi bi-info-circle"></i></span>
              <div class="settings-card__headtext">
                <h2 class="settings-card__title">System information</h2>
                <p class="settings-card__sub">Server build, database, and connection details.</p>
              </div>
            </header>

            <div class="detail-grid">
              <div class="detail-item"><span class="detail-item__label">Application</span><span class="detail-item__value">Guinhawa Poultry Supply Admin</span></div>
              <div class="detail-item"><span class="detail-item__label">Version</span><span class="detail-item__value" id="systemVersion">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Database</span><span class="detail-item__value" id="systemStorage" style="font-family:Consolas,monospace;font-size:.82rem">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Browser</span><span class="detail-item__value" id="systemBrowser">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Session</span><span class="detail-item__value" id="systemSession">&mdash;</span></div>
              <div class="detail-item"><span class="detail-item__label">Data updated</span><span class="detail-item__value" id="systemUpdated">&mdash;</span></div>
            </div>
            <div class="alert-app mt-3">
              <i class="bi bi-info-circle" aria-hidden="true"></i>
              <div>
                PHP 8 and MySQL backend with HTML5, CSS3, vanilla JavaScript, Bootstrap 5, and
                Bootstrap Icons. Every record is read from and written to the
                <code>guinhawa_db</code> database through the authenticated API.
              </div>
            </div>
          </section>
      </div>
