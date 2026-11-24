<?php
declare(strict_types=1);

require __DIR__ . '/../backend/bootstrap.php';

\Guinhawa\Web::boot($config);
header_remove('Content-Security-Policy');

if (\Guinhawa\Auth::user()) {
    \Guinhawa\Web::go('/guinhawa_system/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark">
  <meta name="theme-color" content="#052e21">
  <meta name="description" content="Reset your Guinhawa Poultry Supply password with your registered email. A secure code will be sent to that address.">
  <title>Forgot password &middot; Guinhawa Poultry Supply</title>

  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="preload" as="image" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="preload" as="image" href="/guinhawa_system/assets/images/login-background.jpg">
  <link rel="stylesheet" href="../shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css?v=login-brand-20261123">
</head>
<body>
  <main class="scene">
    <section class="login-card" aria-labelledby="forgot-title">
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
        <h1 class="login-card__title" id="forgot-title">Forgot password</h1>
        <p class="login-card__subtitle" id="forgotSubtitle">
          We'll send a reset code to your email.
        </p>
      </div>

      <!-- Step 1: registered email -->
      <form class="login-form" id="emailForm" novalidate>
        <span class="step-tag"><i class="bi bi-envelope" aria-hidden="true"></i> 1 of 3 &middot; Email</span>

        <div class="field" data-field="email">
          <label class="field__label" for="email">Email</label>
          <div class="field__control">
            <span class="field__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5.5" width="18" height="13" rx="2.6"/>
                <path d="M4 7l8 6 8-6"/>
              </svg>
            </span>
            <input
              class="field__input"
              id="email"
              name="email"
              type="email"
              placeholder="name@example.com"
              autocomplete="email"
              inputmode="email"
              autocapitalize="none"
              spellcheck="false"
              required
            >
          </div>
          <p class="field__error" id="email-error" role="alert"></p>
        </div>

        <button class="login-button" id="sendCodeButton" type="submit">
          <span class="login-button__spinner" aria-hidden="true"></span>
          <span class="login-button__label">Send code</span>
          <svg class="login-button__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4.5 12h15"/>
            <path d="M13.5 6l6 6-6 6"/>
          </svg>
        </button>

        <p class="form-status" id="emailStatus" role="status" aria-live="polite"></p>
      </form>

      <!-- Step 2: 6-digit code -->
      <form class="login-form is-hidden" id="otpForm" novalidate>
        <span class="step-tag"><i class="bi bi-shield-lock" aria-hidden="true"></i> 2 of 3 &middot; Code</span>

        <div class="field" data-field="otp">
          <label class="field__label" for="otp">Verification code</label>
          <div class="field__control">
            <span class="field__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="8" cy="12" r="3.4"/>
                <path d="M11.4 12H21"/>
                <path d="M18 12v2.6"/>
                <path d="M15.4 12v1.8"/>
              </svg>
            </span>
            <input
              class="field__input otp-input"
              id="otp"
              name="otp"
              type="text"
              placeholder="000000"
              inputmode="numeric"
              pattern="[0-9]{6}"
              autocomplete="one-time-code"
              maxlength="6"
              required
            >
          </div>
          <p class="field__error" id="otp-error" role="alert"></p>
        </div>

        <button class="login-button" id="verifyButton" type="submit">
          <span class="login-button__spinner" aria-hidden="true"></span>
          <span class="login-button__label">Verify code</span>
          <svg class="login-button__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4.5 12h15"/>
            <path d="M13.5 6l6 6-6 6"/>
          </svg>
        </button>

        <div class="resend-row">
          <button class="resend-button" id="resendButton" type="button" disabled>
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
            <span id="resendLabel">Resend code in 60s</span>
          </button>
          <button class="resend-button" id="changeEmailButton" type="button">Use a different email</button>
        </div>

        <p class="form-status" id="otpStatus" role="status" aria-live="polite"></p>
      </form>

      <!-- Step 3: new password -->
      <form class="login-form is-hidden" id="passwordForm" novalidate>
        <span class="step-tag"><i class="bi bi-key" aria-hidden="true"></i> 3 of 3 &middot; Password</span>

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
          <p class="field__hint">10+ characters, uppercase, lowercase and a number.</p>
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
              placeholder="Re-enter password"
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

        <button class="login-button" id="savePasswordButton" type="submit">
          <span class="login-button__spinner" aria-hidden="true"></span>
          <span class="login-button__label">Save password</span>
          <svg class="login-button__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4.5 12h15"/>
            <path d="M13.5 6l6 6-6 6"/>
          </svg>
        </button>

        <p class="form-status" id="passwordStatus" role="status" aria-live="polite"></p>
      </form>

      <!-- Success -->
      <div class="is-hidden" id="successPanel">
        <div class="login-alert" style="margin-top:20px">
          <i class="bi bi-check-circle" aria-hidden="true"></i>
          <div>
            <strong>Password updated.</strong>
            <span id="successMessage">You can sign in now.</span>
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

      <div class="login-links" id="forgotLinks">
        <a href="index.php">Back to sign in</a>
      </div>

      <footer class="login-card__footer">
        <p class="dev-badge">
          <span class="dev-badge__dot" aria-hidden="true"></span>
          Built by Dev &middot; Assisted by AI.
        </p>
      </footer>
    </section>
  </main>

  <script src="../shared/js/store.js?v=store-email-only-20261123" defer></script>
  <script src="js/forgot-password.js?v=forgot-simple-20261123" defer></script>
</body>
</html>
