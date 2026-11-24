<?php
declare(strict_types=1);

/**
 * Secure emailed password-reset link.
 *
 * GET  login/reset-password.php?token=...
 * POST the new password with the same token.
 *
 * Only the SHA-256 hash of the link token is stored in MySQL. The link expires
 * after one hour, works once, and is invalidated by any other password reset.
 */

require __DIR__ . '/../backend/bootstrap.php';

use Guinhawa\Auth;
use Guinhawa\RecoveryService;
use Guinhawa\Web;

Web::boot($config);
header_remove('Content-Security-Policy');

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$request = $token !== '' ? RecoveryService::linkRequest($token) : null;
$error = '';
$success = false;

if (!$request) {
    $error = 'This reset link is invalid or has expired. Request a new link from the Forgot Password page.';
}

if (Web::isPost() && $request) {
    Web::verifyCsrf();

    $result = RecoveryService::completeLinkReset(
        $token,
        (string) ($_POST['newPassword'] ?? ''),
        (string) ($_POST['confirmPassword'] ?? ''),
    );

    if ($result['ok']) {
        Web::flash('success', 'Your password was reset. Sign in with your new password.');
        Web::go('/guinhawa_system/login/index.php?reset=1');
    }

    $error = (string) ($result['error'] ?? 'The password could not be reset.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#052e21">
  <meta name="description" content="Choose a new Guinhawa Poultry Supply password.">
  <title>Reset password &middot; Guinhawa Poultry Supply</title>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="../shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css?v=login-brand-20261123">
  <style>
    .reset-card { width: min(450px, 100%); }
    .reset-status { margin: 16px 0 0; font-size: 13px; line-height: 1.5; color: #ffe4e6; }
    .reset-status.is-success { color: #bbf7d0; }
    .reset-back { display: inline-flex; align-items: center; gap: 8px; margin-top: 18px; color: rgba(167, 243, 208, 0.9); font-weight: 600; text-decoration: none; }
    .reset-back:hover { text-decoration: underline; }
    .password-rules { margin: 8px 0 0; padding-left: 18px; font-size: 12px; line-height: 1.7; color: rgba(255,255,255,.72); }
    .reset-expiry { margin: 0 0 18px; padding: 10px 14px; border-radius: 12px; background: rgba(22,101,52,.16); border: 1px solid rgba(120,210,160,.28); color: #d8f5e4; font-size: 13px; }
  </style>
</head>
<body>
  <main class="scene">
    <section class="login-card reset-card" aria-labelledby="reset-title">
      <header class="brand">
        <div class="brand__mark">
          <img class="brand__logo" src="/guinhawa_system/assets/images/guinhawa-logo.png" alt="Guinhawa Poultry Supply logo" width="512" height="512">
        </div>
        <p class="brand__name">Guinhawa Poultry Supply</p>
      </header>

      <div class="login-card__intro">
        <h1 class="login-card__title" id="reset-title">Choose a new password</h1>
        <p class="login-card__subtitle">
          Enter and confirm a new password for your account. You will sign in again after it is saved.
        </p>
      </div>

      <?php if ($request): ?>
        <p class="reset-expiry">
          <i class="bi bi-shield-check" aria-hidden="true"></i>
          This secure link is valid until
          <strong><?= Web::e(date('M j, Y g:i A', strtotime((string) $request['token_expires_at']))) ?></strong>.
        </p>

        <form class="login-form" method="post" action="reset-password.php" novalidate>
          <?= Web::csrfField() ?>
          <input type="hidden" name="token" value="<?= Web::e($token) ?>">

          <div class="field" data-field="newPassword">
            <label class="field__label" for="newPassword">New password</label>
            <div class="field__control">
              <span class="field__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="4.6" y="10.4" width="14.8" height="10" rx="2.6"/>
                  <path d="M8.2 10.4V7.9a3.8 3.8 0 0 1 7.6 0v2.5"/>
                </svg>
              </span>
              <input
                class="field__input field__input--password"
                id="newPassword"
                name="newPassword"
                type="password"
                placeholder="At least 10 characters"
                autocomplete="new-password"
                minlength="10"
                required
              >
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
            <ul class="password-rules">
              <li>At least 10 characters</li>
              <li>At least one uppercase and one lowercase letter</li>
              <li>At least one number</li>
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
              <input
                class="field__input field__input--password"
                id="confirmPassword"
                name="confirmPassword"
                type="password"
                placeholder="Re-enter the new password"
                autocomplete="new-password"
                minlength="10"
                required
              >
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

          <p class="reset-status" role="alert" aria-live="polite"><?= Web::e($error) ?></p>

          <button class="login-button" type="submit">
            <span class="login-button__label">Save new password</span>
            <svg class="login-button__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4.5 12h15"/>
              <path d="M13.5 6l6 6-6 6"/>
            </svg>
          </button>
        </form>
      <?php else: ?>
        <div class="login-alert" style="margin-top:20px">
          <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
          <div>
            <strong>Link unavailable.</strong>
            <span><?= Web::e($error) ?></span>
          </div>
        </div>
        <a class="reset-back" href="forgot-password.php">
          <i class="bi bi-arrow-left" aria-hidden="true"></i> Request a new reset link
        </a>
      <?php endif; ?>

      <div class="login-links">
        <a href="index.php">Back to sign in</a>
      </div>

      <p class="login-card__note">
        Reset links expire after one hour and work only once. Guinhawa staff will never ask you to share your password.
      </p>

      <footer class="login-card__footer">
        <p class="slogan">
          <span class="slogan__line" aria-hidden="true"></span>
          Healthy Poultry, Better Tomorrow
          <span class="slogan__line" aria-hidden="true"></span>
        </p>
        <p class="dev-badge">
          <span class="dev-badge__dot" aria-hidden="true"></span>
          Built by Dev &middot; Assisted by AI.
        </p>
      </footer>
    </section>
  </main>

  <script>
    (function () {
      function wire(buttonId, inputId) {
        var button = document.getElementById(buttonId);
        var input = document.getElementById(inputId);
        if (!button || !input) return;
        button.addEventListener("click", function () {
          var visible = input.type === "text";
          input.type = visible ? "password" : "text";
          button.classList.toggle("is-visible", !visible);
          button.setAttribute("aria-pressed", String(!visible));
          button.setAttribute("aria-label", visible ? "Show password" : "Hide password");
          input.focus();
        });
      }
      wire("toggleNewPassword", "newPassword");
      wire("toggleConfirmPassword", "confirmPassword");
    })();
  </script>
</body>
</html>
