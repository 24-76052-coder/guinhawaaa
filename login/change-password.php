<?php
declare(strict_types=1);

require __DIR__ . '/../backend/bootstrap.php';

\Guinhawa\Web::boot($config);
header_remove('Content-Security-Policy');

$user = \Guinhawa\Auth::user();
if (!$user) {
    \Guinhawa\Web::go('/guinhawa_system/login/index.php?expired=1');
}

$required = (string) ($_GET['required'] ?? '') === '1' || !empty($user['mustChangePassword']);
$csrf = \Guinhawa\Auth::csrfToken();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#052e21">
  <title>Change password &middot; Guinhawa Poultry Supply</title>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="../shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .change-card { width: min(500px, 100%); }
    .change-notice {
      margin: 0 0 18px;
      padding: 12px 14px;
      border-radius: 12px;
      background: rgba(22, 101, 52, .18);
      border: 1px solid rgba(120, 210, 160, .3);
      color: #d8f5e4;
      font-size: 13px;
      line-height: 1.55;
    }
    .change-status { min-height: 20px; margin: 14px 0 0; font-size: 13px; line-height: 1.5; color: #b42318; }
    .change-status.is-success { color: #6ee7a8; }
    .change-status.is-info { color: #cfe9da; }
    .change-rules { margin: 8px 0 0; padding-left: 18px; font-size: 12px; line-height: 1.7; color: rgba(255,255,255,.72); }
    .change-links { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-top: 18px; font-size: 13px; }
    .change-links a { color: #bfe8d0; text-decoration: none; font-weight: 600; }
    .change-links a:hover { text-decoration: underline; }
    .change-success {
      display: none;
      margin-top: 18px;
      padding: 16px;
      border-radius: 14px;
      background: rgba(22, 101, 52, .28);
      border: 1px solid rgba(120, 210, 160, .4);
      color: #e7fff1;
      font-size: 14px;
      line-height: 1.55;
    }
    .change-success.is-visible { display: block; }
    .change-success strong { display: block; margin-bottom: 4px; }
  </style>
</head>
<body>
  <main class="scene">
    <section class="login-card change-card" aria-labelledby="change-title">
      <header class="brand">
        <div class="brand__mark">
          <img class="brand__logo" src="/guinhawa_system/assets/images/guinhawa-logo.png" alt="Guinhawa Poultry Supply logo" width="512" height="512">
        </div>
        <p class="brand__name">Guinhawa Poultry Supply</p>
      </header>

      <div class="login-card__intro">
        <h1 class="login-card__title" id="change-title">Change password</h1>
        <p class="login-card__subtitle">
          Replace your current or default password with one only you know.
        </p>
      </div>

      <p class="change-notice" id="requiredNotice" <?= $required ? '' : 'hidden' ?>>
        <i class="bi bi-shield-lock" aria-hidden="true"></i>
        Your account was created with a default password. Choose a new password to open your dashboard.
      </p>

      <form id="passwordForm" novalidate>
        <input type="hidden" id="csrfToken" value="<?= \Guinhawa\Web::e($csrf) ?>">

        <div class="field" data-field="currentPassword">
          <label class="field__label" for="currentPassword">Current / default password</label>
          <div class="field__control">
            <span class="field__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4.6" y="10.4" width="14.8" height="10" rx="2.6"/>
                <path d="M8.2 10.4V7.9a3.8 3.8 0 0 1 7.6 0v2.5"/>
              </svg>
            </span>
            <input class="field__input field__input--password" id="currentPassword" type="password" autocomplete="current-password" required>
            <button class="field__toggle" id="toggleCurrentPassword" type="button" aria-label="Show password" aria-pressed="false" aria-controls="currentPassword">
              <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2.6 12S6.2 6.2 12 6.2 21.4 12 21.4 12 17.8 17.8 12 17.8 2.6 12 2.6 12Z"/>
                <circle cx="12" cy="12" r="2.7"/>
              </svg>
              <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 4l16 16"/>
                <path d="M10.7 6.4A9.8 9.8 0 0 1 12 6.2c5.8 0 9.4 5.8 9.4 5.8a17.4 17.4 0 0 1-3.4 3.9"/>
                <path d="M6.5 8A17 17 0 0 0 2.6 12s3.6 5.8 9.4 5.8a9.7 9.7 0 0 0 4.1-.9"/>
                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="field" data-field="newPassword">
          <label class="field__label" for="newPassword">New password</label>
          <div class="field__control">
            <span class="field__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3l7 3v5c0 4.4-2.9 8.3-7 9.6-4.1-1.3-7-5.2-7-9.6V6l7-3Z"/>
                <path d="M9.5 12l1.8 1.8 3.4-3.6"/>
              </svg>
            </span>
            <input class="field__input field__input--password" id="newPassword" type="password" autocomplete="new-password" minlength="10" required>
            <button class="field__toggle" id="toggleNewPassword" type="button" aria-label="Show password" aria-pressed="false" aria-controls="newPassword">
              <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2.6 12S6.2 6.2 12 6.2 21.4 12 21.4 12 17.8 17.8 12 17.8 2.6 12 2.6 12Z"/>
                <circle cx="12" cy="12" r="2.7"/>
              </svg>
              <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 4l16 16"/>
                <path d="M10.7 6.4A9.8 9.8 0 0 1 12 6.2c5.8 0 9.4 5.8 9.4 5.8a17.4 17.4 0 0 1-3.4 3.9"/>
                <path d="M6.5 8A17 17 0 0 0 2.6 12s3.6 5.8 9.4 5.8a9.7 9.7 0 0 0 4.1-.9"/>
                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
              </svg>
            </button>
          </div>
          <ul class="change-rules">
            <li>At least 10 characters</li>
            <li>At least one uppercase and one lowercase letter</li>
            <li>At least one number</li>
            <li>Different from your default password</li>
          </ul>
        </div>

        <div class="field" data-field="confirmPassword">
          <label class="field__label" for="confirmPassword">Confirm new password</label>
          <div class="field__control">
            <span class="field__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4.6" y="10.4" width="14.8" height="10" rx="2.6"/>
                <path d="M8.2 10.4V7.9a3.8 3.8 0 0 1 7.6 0v2.5"/>
              </svg>
            </span>
            <input class="field__input field__input--password" id="confirmPassword" type="password" autocomplete="new-password" minlength="10" required>
            <button class="field__toggle" id="toggleConfirmPassword" type="button" aria-label="Show password" aria-pressed="false" aria-controls="confirmPassword">
              <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2.6 12S6.2 6.2 12 6.2 21.4 12 21.4 12 17.8 17.8 12 17.8 2.6 12 2.6 12Z"/>
                <circle cx="12" cy="12" r="2.7"/>
              </svg>
              <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 4l16 16"/>
                <path d="M10.7 6.4A9.8 9.8 0 0 1 12 6.2c5.8 0 9.4 5.8 9.4 5.8a17.4 17.4 0 0 1-3.4 3.9"/>
                <path d="M6.5 8A17 17 0 0 0 2.6 12s3.6 5.8 9.4 5.8a9.7 9.7 0 0 0 4.1-.9"/>
                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
              </svg>
            </button>
          </div>
        </div>

        <button class="login-button" id="submitButton" type="submit">
          <span class="login-button__spinner" aria-hidden="true"></span>
          <span class="login-button__label">Save new password</span>
          <svg class="login-button__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4.5 12h15"/>
            <path d="M13.5 6l6 6-6 6"/>
          </svg>
        </button>

        <p class="change-status" id="status" role="status" aria-live="polite"></p>
        <div class="change-success" id="successPanel" role="status" aria-live="polite">
          <strong>Password saved.</strong>
          <span id="successMessage">Opening your dashboard&hellip;</span>
        </div>
      </form>

      <div class="change-links">
        <a href="index.php?signout=1" id="logoutLink">Sign out</a>
        <a href="/guinhawa_system/index.php">Back to dashboard</a>
      </div>

      <p class="login-card__note">
        Passwords are stored as one-way hashes. Guinhawa staff will never ask you to share your password.
      </p>
    </section>
  </main>

  <script src="js/change-password.js" defer></script>
</body>
</html>
