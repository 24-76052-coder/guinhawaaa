<?php
declare(strict_types=1);

require __DIR__ . '/../backend/bootstrap.php';

\Guinhawa\Web::boot($config);
header_remove('Content-Security-Policy');

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$activation = \Guinhawa\StaffActivationService::describe($token);
$state = (string) ($activation['state'] ?? 'invalid');
$employee = $activation['employee'] ?? null;

$fullName = is_array($employee) ? (string) ($employee['fullName'] ?? '') : '';
$username = is_array($employee) ? (string) ($employee['username'] ?? '') : '';
$employeeId = is_array($employee) ? (string) ($employee['employeeId'] ?? '') : '';
$roleLabel = 'staff';
if (is_array($employee)) {
    $roleKey = (string) ($employee['role'] ?? '');
    $roleLabel = $roleKey === 'cashier' ? 'cashier' : 'inventory staff';
}

$stateTitle = 'Activate your account';
$stateMessage = 'Choose a password to finish setting up your Guinhawa staff account.';
$stateTone = 'info';

if ($state === 'invalid') {
    $stateTitle = 'Activation link not valid';
    $stateMessage = 'This link is invalid or has been replaced by a newer one. Ask your Administrator to resend the activation email.';
    $stateTone = 'error';
} elseif ($state === 'expired') {
    $stateTitle = 'Activation link expired';
    $stateMessage = 'Activation links expire after 24 hours. Ask your Administrator to resend a fresh link.';
    $stateTone = 'error';
} elseif ($state === 'used') {
    $stateTitle = 'Account already activated';
    $stateMessage = 'This activation link has already been used. Sign in with your password, or use Forgot password if you need a new one.';
    $stateTone = 'info';
}

$safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
$safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
$safeEmployeeId = htmlspecialchars($employeeId, ENT_QUOTES, 'UTF-8');
$safeRole = htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');
$safeStateTitle = htmlspecialchars($stateTitle, ENT_QUOTES, 'UTF-8');
$safeStateMessage = htmlspecialchars($stateMessage, ENT_QUOTES, 'UTF-8');
$safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark">
  <meta name="theme-color" content="#052e21">
  <meta name="robots" content="noindex, nofollow">
  <meta name="description" content="Activate your Guinhawa Agri-Poultry Supply staff account.">
  <title>Activate account &middot; Guinhawa Poultry Supply</title>

  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="preload" as="image" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="preload" as="image" href="/guinhawa_system/assets/images/login-background.jpg">
  <link rel="stylesheet" href="../shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <main class="scene">
    <section class="login-card" aria-labelledby="activate-title">
      <header class="brand">
        <div class="brand__mark">
          <img
            class="brand__logo"
            src="/guinhawa_system/assets/images/guinhawa-logo.png"
            alt="Guinhawa Poultry Supply logo"
            width="512"
            height="512"
          >
        </div>
        <p class="brand__name">Guinhawa Poultry Supply</p>
      </header>

      <div class="login-card__intro">
        <h1 class="login-card__title" id="activate-title"><?= $safeStateTitle ?></h1>
        <p class="login-card__subtitle">
          <?php if ($state === 'valid'): ?>
            Hi <?= $safeName ?>. Set a password for your <?= $safeRole ?> account
            <strong>@<?= $safeUsername ?></strong> (<?= $safeEmployeeId ?>).
          <?php else: ?>
            <?= $safeStateMessage ?>
          <?php endif; ?>
        </p>
      </div>

      <?php if ($state === 'valid'): ?>
        <form class="login-form" id="activationForm" novalidate data-token="<?= $safeToken ?>">
          <span class="step-tag"><i class="bi bi-shield-check" aria-hidden="true"></i> Account activation</span>

          <div class="field" data-field="newPassword">
            <label class="field__label" for="newPassword">Create a password</label>
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
            <p class="field__hint">Use 10+ characters with uppercase, lowercase and a number.</p>
            <p class="field__error" id="newPassword-error" role="alert"></p>
          </div>

          <div class="field" data-field="confirmPassword">
            <label class="field__label" for="confirmPassword">Confirm password</label>
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
                placeholder="Re-enter your password"
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
            <p class="field__error" id="confirmPassword-error" role="alert"></p>
          </div>

          <button class="login-button" id="activateButton" type="submit">
            <span class="login-button__spinner" aria-hidden="true"></span>
            <span class="login-button__label">Activate my account</span>
            <svg class="login-button__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4.5 12h15"/>
              <path d="M13.5 6l6 6-6 6"/>
            </svg>
          </button>

          <p class="form-status" id="activateStatus" role="status" aria-live="polite"></p>
        </form>

        <div class="is-hidden" id="successPanel">
          <div class="login-alert" style="margin-top:26px">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <div>
              <strong>Account activated.</strong>
              <span id="successMessage">Sign in with @<?= $safeUsername ?> and your new password.</span>
            </div>
          </div>
          <a class="login-button" href="index.php" style="margin-top:18px;text-decoration:none">
            <span class="login-button__label">Go to sign in</span>
            <svg class="login-button__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4.5 12h15"/>
              <path d="M13.5 6l6 6-6 6"/>
            </svg>
          </a>
        </div>
      <?php else: ?>
        <div class="login-alert" style="margin-top:26px">
          <i class="bi <?= $stateTone === 'error' ? 'bi-exclamation-triangle' : 'bi-info-circle' ?>" aria-hidden="true"></i>
          <div>
            <strong><?= $safeStateTitle ?>.</strong>
            <span><?= $safeStateMessage ?></span>
          </div>
        </div>
      <?php endif; ?>

      <div class="login-links">
        <?php if ($state === 'used'): ?>
          <a href="forgot-password.php">Forgot password</a>
        <?php endif; ?>
        <a href="index.php">Back to sign in</a>
      </div>

      <p class="login-card__note">
        Activation links are single-use and expire 24 hours after the Administrator
        creates the account. Only your Administrator can send a new one.
      </p>

      <footer class="login-card__footer">
        <p class="slogan">
          <span class="slogan__line" aria-hidden="true"></span>
          Healthy Poultry, Better Tomorrow
          <span class="slogan__line" aria-hidden="true"></span>
        </p>
        <p class="dev-badge">
          <span class="dev-badge__dot" aria-hidden="true"></span>
          Made by Dev, Assisted by AI.
        </p>
      </footer>
    </section>
  </main>

  <?php if ($state === 'valid'): ?>
    <script src="../shared/js/store.js?v=cleanup-20261122-2" defer></script>
    <script src="js/activate.js" defer></script>
  <?php endif; ?>
</body>
</html>
