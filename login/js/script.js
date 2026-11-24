/* ==========================================================================
   GUINHAWA POULTRY SUPPLY — Login interactions
   Vanilla JavaScript only (no libraries, no build step).
   ========================================================================== */

(function () {
  "use strict";

  /* -------------------------------- Intro -------------------------------- */

  var intro = document.getElementById("intro");
  var introSkip = document.getElementById("introSkip");
  var introStarted = false;
  var introDone = false;
  var introTimers = [];
  var introFadeDelay = 4700;
  var introFlightMs = 960;
  var introSkipMs = 620;

  if (intro) {
    document.documentElement.classList.add("intro-lock");
  }

  function clearIntroTimers() {
    introTimers.forEach(function (timer) {
      window.clearTimeout(timer);
    });
    introTimers = [];
  }

  function spawnIntroParticles() {
    var host = document.getElementById("introParticles");
    if (!host) return;

    var mobile = window.innerWidth < 600;
    var fragment = document.createDocumentFragment();

    function addParticles(type, count, options) {
      for (var i = 0; i < count; i += 1) {
        var dot = document.createElement("span");
        dot.className = "intro__particle intro__particle--" + type;
        dot.style.setProperty("--x", (Math.random() * 100).toFixed(2) + "%");
        dot.style.setProperty(
          "--s",
          (options.minSize + Math.random() * (options.maxSize - options.minSize))
            .toFixed(2) + "px"
        );
        dot.style.setProperty(
          "--delay",
          (Math.random() * options.maxDelay).toFixed(2) + "s"
        );
        dot.style.setProperty(
          "--d",
          (options.minDur + Math.random() * (options.maxDur - options.minDur))
            .toFixed(2) + "s"
        );
        dot.style.setProperty(
          "--drift",
          Math.round((Math.random() - 0.5) * options.drift) + "px"
        );
        dot.style.setProperty(
          "--p-o",
          (options.minOpacity + Math.random() * 0.2).toFixed(2)
        );

        if (type === "spark") {
          dot.style.setProperty(
            "--y",
            (10 + Math.random() * 80).toFixed(2) + "%"
          );
        }

        if (type === "leaf") {
          dot.style.setProperty(
            "--spin",
            Math.round(90 + Math.random() * 220) + "deg"
          );
        }

        fragment.appendChild(dot);
      }
    }

    addParticles("emerald", mobile ? 8 : 14, {
      minSize: 1.4,
      maxSize: 4.2,
      maxDelay: 3.2,
      minDur: 6,
      maxDur: 11,
      drift: 140,
      minOpacity: 0.6,
    });
    addParticles("gold", mobile ? 4 : 7, {
      minSize: 1.8,
      maxSize: 5,
      maxDelay: 2.8,
      minDur: 7,
      maxDur: 12,
      drift: 170,
      minOpacity: 0.55,
    });
    addParticles("leaf", mobile ? 3 : 5, {
      minSize: 4,
      maxSize: 9,
      maxDelay: 2.4,
      minDur: 8,
      maxDur: 13,
      drift: 190,
      minOpacity: 0.4,
    });
    addParticles("spark", mobile ? 3 : 5, {
      minSize: 1.5,
      maxSize: 2.4,
      maxDelay: 5,
      minDur: 4,
      maxDur: 8,
      drift: 0,
      minOpacity: 0.85,
    });

    host.appendChild(fragment);
  }

  function removeIntro() {
    if (intro && intro.parentNode) intro.parentNode.removeChild(intro);
    document.body.classList.remove("intro-landing", "intro-play");
    document.documentElement.classList.remove("intro-lock");
  }

  function endIntro(instant, fast) {
    if (!intro || introDone) return;
    introDone = true;
    clearIntroTimers();

    if (instant) {
      document.body.classList.remove("intro-active", "intro-play");
      document.body.classList.add("intro-revealed");
      removeIntro();
      return;
    }

    document.body.classList.remove("intro-active");
    document.body.classList.add("intro-revealed");

    var logo = intro.querySelector(".intro__logo");
    var target = document.querySelector(".brand__logo");

    // Skip / Escape: just dissolve the overlay, no flight.
    if (fast || !logo || !target) {
      intro.classList.add("is-leaving");
      window.setTimeout(removeIntro, introSkipMs);
      return;
    }

    // Measure before the static login logo is hidden, so the target is stable.
    var from = logo.getBoundingClientRect();
    var to = target.getBoundingClientRect();

    if (!from.width || !to.width) {
      intro.classList.add("is-leaving");
      window.setTimeout(removeIntro, introSkipMs);
      return;
    }

    var dx = to.left + to.width / 2 - (from.left + from.width / 2);
    var dy = to.top + to.height / 2 - (from.top + from.height / 2);
    var scale = to.width / from.width;

    document.body.classList.add("intro-landing");
    intro.classList.add("is-landing");

    // Freeze the logo at its current displayed state...
    logo.style.animation = "none";
    logo.style.transition = "none";
    logo.style.opacity = "1";
    logo.style.transform = "scale(1)";
    logo.style.filter = "drop-shadow(0 10px 18px rgba(0, 0, 0, 0.45))";

    // ...then fly it onto the login card's logo slot.
    void logo.offsetWidth;

    logo.style.transition =
      "transform " + introFlightMs + "ms cubic-bezier(0.22, 0.61, 0.24, 1)";
    logo.style.transform =
      "translate3d(" +
      dx.toFixed(2) +
      "px, " +
      dy.toFixed(2) +
      "px, 0) scale(" +
      scale.toFixed(4) +
      ")";

    window.setTimeout(removeIntro, introFlightMs + 90);
  }

  function startIntro() {
    if (!intro || introDone || introStarted) return;
    introStarted = true;
    document.body.classList.add("intro-play");
    spawnIntroParticles();
    introTimers.push(
      window.setTimeout(function () {
        endIntro(false);
      }, introFadeDelay)
    );
  }

  if (intro) {
    var reduceMotion = false;
    try {
      reduceMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
      ).matches;
    } catch (error) {
      reduceMotion = false;
    }

    if (introSkip) {
      introSkip.addEventListener("click", function () {
        endIntro(false, true);
      });
    }

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !introDone) endIntro(false, true);
    });

    if (reduceMotion || document.documentElement.classList.contains("no-intro")) {
      endIntro(true);
    } else {
      // Start once the artwork can paint, so nothing pops in mid-fall.
      var introImages = Array.prototype.slice.call(intro.querySelectorAll("img"));
      var decodeAll = Promise.resolve();

      if (introImages.length) {
        decodeAll = Promise.all(
          introImages.map(function (image) {
            if (image.decode) {
              return image.decode().catch(function () {
                /* Decoding is a nicety, not a requirement. */
              });
            }
            return Promise.resolve();
          })
        );
      }

      decodeAll.then(startIntro);

      // Never let a slow decode hold the intro hostage.
      introTimers.push(window.setTimeout(startIntro, 400));
    }
  }

  /* -------------------------------- Login -------------------------------- */

  var form = document.getElementById("loginForm");
  var username = document.getElementById("username");
  var password = document.getElementById("password");
  var toggle = document.getElementById("togglePassword");
  var submitButton = document.getElementById("loginButton");
  var status = document.getElementById("formStatus");
  var capsHint = document.getElementById("caps-hint");
  var remember = document.getElementById("rememberMe");
  var Store = window.GuinhawaStore;

  if (!form || !username || !password || !toggle || !submitButton || !status) {
    return;
  }

  var buttonLabel = submitButton.querySelector(".login-button__label");
  var defaultLabel = buttonLabel ? buttonLabel.textContent : "Login";

  /* ----------------------------- Helpers ------------------------------- */

  function setFieldError(input, message) {
    var field = input.closest(".field");
    if (!field) return;

    var error = field.querySelector(".field__error");

    if (message) {
      field.classList.add("is-invalid");
      input.setAttribute("aria-invalid", "true");
      if (error) error.textContent = message;
    } else {
      field.classList.remove("is-invalid");
      input.removeAttribute("aria-invalid");
      if (error) error.textContent = "";
    }
  }

  function clearStatus() {
    status.textContent = "";
    status.className = "form-status";
  }

  function showStatus(message, type) {
    status.textContent = message;
    status.className = "form-status " + (type || "is-info");
  }

  /* --------------------------- Show / hide ----------------------------- */

  toggle.addEventListener("click", function () {
    var shouldShow = password.type === "password";

    password.type = shouldShow ? "text" : "password";
    toggle.classList.toggle("is-visible", shouldShow);
    toggle.setAttribute("aria-pressed", String(shouldShow));
    toggle.setAttribute(
      "aria-label",
      shouldShow ? "Hide password" : "Show password"
    );

    // Keep the caret at the end when switching back to a text field.
    if (shouldShow) {
      var end = password.value.length;
      try {
        password.setSelectionRange(end, end);
      } catch (error) {
        /* setSelectionRange is not supported by every environment. */
      }
    }
  });

  /* ----------------------------- Caps Lock ----------------------------- */

  function updateCapsLock(event) {
    if (!capsHint) return;

    if (typeof event.getModifierState === "function") {
      capsHint.hidden = !event.getModifierState("CapsLock");
    }
  }

  password.addEventListener("keydown", updateCapsLock);
  password.addEventListener("keyup", updateCapsLock);
  password.addEventListener("blur", function () {
    if (capsHint) capsHint.hidden = true;
  });

  /* ---------------------------- Validation ----------------------------- */

  function validate() {
    var isValid = true;

    if (!username.value.trim()) {
      setFieldError(username, "Enter your email or username.");
      isValid = false;
    } else {
      setFieldError(username, "");
    }

    if (!password.value) {
      setFieldError(password, "Enter your password.");
      isValid = false;
    } else if (password.value.length < 6) {
      setFieldError(password, "Password must be at least 6 characters.");
      isValid = false;
    } else {
      setFieldError(password, "");
    }

    if (!isValid) {
      var firstInvalid = form.querySelector(".field.is-invalid .field__input");
      if (firstInvalid) firstInvalid.focus();
    }

    return isValid;
  }

  [username, password].forEach(function (input) {
    input.addEventListener("input", function () {
      var field = input.closest(".field");

      if (field && field.classList.contains("is-invalid")) {
        setFieldError(input, "");
      }

      if (status.textContent) clearStatus();
    });
  });

  /* ------------------------------ Submit ------------------------------- */

  function resetButton() {
    submitButton.classList.remove("is-loading");
    submitButton.disabled = false;
    submitButton.removeAttribute("aria-busy");
    if (buttonLabel) buttonLabel.textContent = defaultLabel;
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    clearStatus();

    if (!validate()) {
      return;
    }

    submitButton.classList.add("is-loading");
    submitButton.disabled = true;
    submitButton.setAttribute("aria-busy", "true");

    if (buttonLabel) buttonLabel.textContent = "Signing in…";

    window.setTimeout(function () {
      var pending =
        Store && typeof Store.login === "function"
          ? Store.login(
              username.value,
              password.value,
              !!(remember && remember.checked)
            )
          : Promise.resolve({
              ok: false,
              message:
                "Sign-in is unavailable. Reload the page and try again.",
            });

      Promise.resolve(pending).then(function (result) {
      if (!result.ok) {
        resetButton();
        setFieldError(password, result.message);
        showStatus(result.message, "is-error");
        password.focus();
        return;
      }

      if (result.mustChangePassword) {
        showStatus(
          "Your temporary password must be replaced before the dashboard opens.",
          "is-info"
        );
        window.setTimeout(function () {
          window.location.href = result.home;
        }, 650);
        return;
      }

      var firstName = String(result.user.name || "there").split(" ")[0];
      showStatus(
        "Welcome back, " +
          firstName +
          "! Opening your " +
          result.user.role +
          " dashboard\u2026",
        "is-info"
      );

      var allowed = {
        Admin: [
          "admin/index.php", "admin/products.php", "admin/inventory.php",
          "admin/stock-in.php", "admin/stock-out.php", "admin/suppliers.php",
          "admin/transactions.php", "admin/attendance.php", "admin/history.php",
          "admin/accounts.php", "admin/reports.php", "admin/settings.php",
          "inventory/index.php",
        ],
        "Inventory Staff": [
          "inventory/index.php", "inventory/products.php", "inventory/inventory.php",
          "inventory/stock-in.php", "inventory/stock-out.php", "inventory/history.php",
          "inventory/attendance.php", "inventory/settings.php",
        ],
        Cashier: [
          "cashier/index.php", "cashier/products.php", "cashier/transactions.php",
          "cashier/attendance.php", "cashier/settings.php",
        ],
      };
      var requested = new URLSearchParams(window.location.search).get("redirect") || "";
      var requestedFile = requested.split("?")[0].replace(/^\/+/, "");
      var permitted = allowed[result.user.role] || [];
      var destination =
        requestedFile && permitted.indexOf(requestedFile) > -1
          ? "/guinhawa_system/" + requested.replace(/^\/+/, "")
          : result.home;

      window.setTimeout(function () {
        window.location.href = destination;
      }, 650);
      });
    }, 600);
  });
})();
