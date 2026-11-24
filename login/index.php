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
  <meta name="description" content="Sign in to the Guinhawa Poultry Supply management system.">
  <title>Sign in &middot; Guinhawa Poultry Supply</title>

  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="preload" as="image" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="preload" as="image" href="/guinhawa_system/assets/images/feather-hd.png">
  <link rel="preload" as="image" href="/guinhawa_system/assets/images/login-background.jpg">
  <link rel="stylesheet" href="../shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css?v=login-story-20261123-4">

  <!-- Skip the intro unless this is a fresh visit to the system. It must not
       replay when the page is refreshed or when someone signs out. -->
  <script>
    (function () {
      try {
        var params = new URLSearchParams(window.location.search);
        var signedOut = params.has("signedout");

        var seen = false;
        try {
          seen = window.sessionStorage.getItem("guinhawa_intro_seen") === "1";
        } catch (error) {
          /* sessionStorage is optional. */
        }

        var reduceMotion =
          window.matchMedia &&
          window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        var skip = signedOut || seen || reduceMotion;

        if (skip) {
          document.documentElement.classList.add("no-intro");
        }

        // Remember the visit either way, so returning to the sign-in page in
        // this tab (including after signing out) never replays the intro.
        try {
          window.sessionStorage.setItem("guinhawa_intro_seen", "1");
        } catch (error) {
          /* sessionStorage is optional. */
        }
      } catch (error) {
        document.documentElement.classList.add("no-intro");
      }
    })();
  </script>
  <noscript>
    <style>
      .intro { display: none !important; }
      body.intro-active { overflow: auto !important; }
      body.intro-active .scene { opacity: 1 !important; visibility: visible !important; }
    </style>
  </noscript>
</head>
<body class="intro-active">
  <div class="intro" id="intro">
    <div class="intro__backdrop" aria-hidden="true"></div>

    <div class="intro__fx" aria-hidden="true">
      <div class="intro__lighting">
        <span class="intro__ray intro__ray--one"></span>
        <span class="intro__ray intro__ray--two"></span>
        <span class="intro__ray intro__ray--three"></span>
        <span class="intro__ray intro__ray--four"></span>
        <span class="intro__ray intro__ray--five"></span>
      </div>

      <div class="intro__horizon">
        <span class="intro__horizon-glow"></span>
        <span class="intro__horizon-line"></span>
        <span class="intro__horizon-reflection"></span>
      </div>

      <div class="intro__atmosphere">
        <span class="intro__orb intro__orb--one"></span>
        <span class="intro__orb intro__orb--two"></span>
      </div>

      <div class="intro__particles" id="introParticles"></div>

      <div class="intro__trails">
        <span class="intro__trail intro__trail--one"><span class="intro__trail-ring"></span></span>
        <span class="intro__trail intro__trail--two"><span class="intro__trail-ring"></span></span>
        <span class="intro__trail intro__trail--three"><span class="intro__trail-ring"></span></span>
      </div>

      <div class="intro__feathers">
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
        <div class="intro__feather">
          <img class="intro__feather-img" src="/guinhawa_system/assets/images/feather-hd.png" alt="" width="1841" height="1309" decoding="async">
        </div>
      </div>
    </div>

    <div class="intro__stage">
      <div class="intro__logo-fx" aria-hidden="true">
        <span class="intro__logo-burst"></span>
        <span class="intro__logo-glow"></span>
        <span class="intro__lens-flare"></span>
      </div>
      <img
        class="intro__logo"
        src="/guinhawa_system/assets/images/guinhawa-logo.png"
        alt="Guinhawa Poultry Supply"
        width="512"
        height="512"
        decoding="async"
      >
    </div>

    <button class="intro__skip" id="introSkip" type="button">Skip intro</button>
  </div>

  <main class="scene">
    <aside class="login-story" aria-label="Guinhawa Poultry Supply introduction">
      <p class="login-story__eyebrow">YOUR POULTRY SUPPLY PARTNER</p>
      <h2 class="login-story__title">
        Growing Better Poultry,<br>
        <em>Together.</em>
      </h2>
      <p class="login-story__description">
        Your trusted partner in quality poultry supplies, feeds, and farm essentials.
      </p>
      <div class="login-story__ornament" aria-hidden="true">
        <span></span>
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M19.5 4.5C11 4.5 5 7.7 5 13.1c0 3.2 2.2 5.4 5.1 5.4 5.4 0 8.6-6 9.4-14Z" />
          <path d="M4.5 20c2.4-4 5.7-6.8 10-8.8" />
        </svg>
        <span></span>
      </div>
      <p class="login-story__tagline">HEALTHY POULTRY, BETTER TOMORROW</p>
    </aside>

    <section class="login-card" aria-labelledby="signin-title">
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
        <h1 class="login-card__title" id="signin-title">Sign in</h1>
        <p class="login-card__subtitle">
          Sign in with your email or username.
        </p>
      </div>

      <?php if (isset($_GET['reset'])): ?>
        <div class="login-alert" role="status" style="margin-bottom:18px">
          <i class="bi bi-check-circle" aria-hidden="true"></i>
          <div>
            <strong>Password updated.</strong>
            <span>Sign in again with your new password.</span>
          </div>
        </div>
      <?php endif; ?>

      <form class="login-form" id="loginForm" novalidate>
        <div class="field" data-field="username">
          <label class="field__label" for="username">Account</label>
          <div class="field__control">
            <span class="field__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="3.6"/>
                <path d="M4.7 20a7.3 7.3 0 0 1 14.6 0"/>
              </svg>
            </span>
            <input
              class="field__input"
              id="username"
              name="username"
              type="text"
              placeholder="Email or username"
              autocomplete="username"
              autocapitalize="none"
              spellcheck="false"
              enterkeyhint="next"
              aria-describedby="username-error"
              required
            >
          </div>
          <p class="field__error" id="username-error" role="alert"></p>
        </div>

        <div class="field" data-field="password">
          <label class="field__label" for="password">Password</label>
          <div class="field__control">
            <span class="field__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4.6" y="10.4" width="14.8" height="10" rx="2.6"/>
                <path d="M8.2 10.4V7.9a3.8 3.8 0 0 1 7.6 0v2.5"/>
              </svg>
            </span>
            <input
              class="field__input field__input--password"
              id="password"
              name="password"
              type="password"
              placeholder="Enter your password"
              autocomplete="current-password"
              enterkeyhint="go"
              aria-describedby="password-error caps-hint"
              required
            >
            <button
              class="field__toggle"
              id="togglePassword"
              type="button"
              aria-label="Show password"
              aria-pressed="false"
              aria-controls="password"
            >
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
          <p class="field__hint" id="caps-hint" hidden>Caps Lock is on.</p>
          <p class="field__error" id="password-error" role="alert"></p>
        </div>

        <div class="login-options">
          <label class="login-check" for="rememberMe">
            <input type="checkbox" id="rememberMe" name="remember">
            <span class="login-check__box" aria-hidden="true"></span>
            <span>Remember me on this device</span>
          </label>
          <a class="login-options__link" href="forgot-password.php">Forgot password?</a>
        </div>

        <button class="login-button" id="loginButton" type="submit">
          <span class="login-button__spinner" aria-hidden="true"></span>
          <span class="login-button__label">Sign in</span>
          <svg class="login-button__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4.5 12h15"/>
            <path d="M13.5 6l6 6-6 6"/>
          </svg>
        </button>

        <p class="form-status" id="formStatus" role="status" aria-live="polite"></p>
      </form>

      <p class="login-card__note">Accounts are created by your administrator. Public registration is unavailable.</p>

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

  <script src="../shared/js/store.js?v=store-email-only-20261123" defer></script>
  <script src="js/script.js?v=login-brand-20261123" defer></script>
</body>
</html>
