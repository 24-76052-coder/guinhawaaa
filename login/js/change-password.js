(function () {
  "use strict";

  var API = "/guinhawa_system/api";
  var form = document.getElementById("passwordForm");
  var current = document.getElementById("currentPassword");
  var next = document.getElementById("newPassword");
  var confirm = document.getElementById("confirmPassword");
  var button = document.getElementById("submitButton");
  var status = document.getElementById("status");
  var successPanel = document.getElementById("successPanel");
  var successMessage = document.getElementById("successMessage");
  var csrfField = document.getElementById("csrfToken");
  var logoutLink = document.getElementById("logoutLink");

  var csrfToken = csrfField ? csrfField.value : "";

  function roleHome(user) {
    var role = user && user.role ? String(user.role) : "";
    if (role === "admin") return "/guinhawa_system/admin/index.php";
    if (role === "inventory-staff") return "/guinhawa_system/inventory/index.php";
    if (role === "cashier" || role === "counter-staff" || role === "staff") {
      return "/guinhawa_system/cashier/index.php";
    }
    return "/guinhawa_system/index.php";
  }

  function setStatus(message, kind) {
    status.textContent = message || "";
    status.className = "change-status" + (kind ? " is-" + kind : "");
  }

  function fail(message) {
    setStatus(message, "error");
    button.disabled = false;
    button.classList.remove("is-loading");
    var label = button.querySelector(".login-button__label");
    if (label) label.textContent = "Save new password";
  }

  function wireToggle(buttonId, input) {
    var toggle = document.getElementById(buttonId);
    if (!toggle || !input) return;
    toggle.addEventListener("click", function () {
      var visible = input.type === "text";
      input.type = visible ? "password" : "text";
      toggle.classList.toggle("is-visible", !visible);
      toggle.setAttribute("aria-pressed", String(!visible));
      toggle.setAttribute("aria-label", visible ? "Show password" : "Hide password");
      input.focus();
    });
  }

  wireToggle("toggleCurrentPassword", current);
  wireToggle("toggleNewPassword", next);
  wireToggle("toggleConfirmPassword", confirm);

  function passwordProblem(value) {
    if (value.length < 10) return "The new password must be at least 10 characters.";
    if (value.length > 128) return "The new password must be 128 characters or fewer.";
    if (!/[a-z]/.test(value)) return "Include at least one lowercase letter.";
    if (!/[A-Z]/.test(value)) return "Include at least one uppercase letter.";
    if (!/[0-9]/.test(value)) return "Include at least one number.";
    return "";
  }

  // A direct /auth/me call also refreshes the CSRF token if the page was open
  // for a long time or the session was re-issued.
  fetch(API + "/auth/me", { credentials: "same-origin", headers: { Accept: "application/json" } })
    .then(function (response) {
      if (response.status === 401) {
        window.location.replace("/guinhawa_system/login/index.php?expired=1");
        throw new Error("signed out");
      }
      return response.json();
    })
    .then(function (body) {
      if (body && body.csrfToken) {
        csrfToken = body.csrfToken;
        if (csrfField) csrfField.value = csrfToken;
      }
    })
    .catch(function (error) {
      if (error && error.message === "signed out") return;
      // The embedded token still works for the normal case.
    });

  [current, next, confirm].forEach(function (input) {
    input.addEventListener("input", function () {
      setStatus("", "");
    });
  });

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    setStatus("", "");
    successPanel.classList.remove("is-visible");

    if (!current.value || !next.value || !confirm.value) {
      fail("Fill in all three password fields.");
      return;
    }
    if (current.value === next.value) {
      fail("Choose a new password that is different from your default password.");
      next.focus();
      return;
    }

    var problem = passwordProblem(next.value);
    if (problem) {
      fail(problem);
      next.focus();
      return;
    }
    if (next.value !== confirm.value) {
      fail("The new passwords do not match.");
      confirm.focus();
      return;
    }

    button.disabled = true;
    button.classList.add("is-loading");
    var label = button.querySelector(".login-button__label");
    if (label) label.textContent = "Saving";
    setStatus("Saving your new password\u2026", "info");

    fetch(API + "/auth/change-password", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify({
        currentPassword: current.value,
        newPassword: next.value,
        confirmPassword: confirm.value,
      }),
    })
      .then(function (response) {
        return response.json().then(function (body) {
          return { ok: response.ok, status: response.status, body: body || {} };
        });
      })
      .then(function (result) {
        if (!result.ok) {
          if (result.status === 401) {
            window.location.replace("/guinhawa_system/login/index.php?expired=1");
            return;
          }
          fail(result.body.error || "The password could not be changed.");
          return;
        }

        if (result.body.csrfToken) csrfToken = result.body.csrfToken;
        var user = result.body.user || {};
        var destination = roleHome(user);
        var roleLabel = user.roleLabel || "your dashboard";

        setStatus("", "");
        successPanel.classList.add("is-visible");
        successMessage.textContent =
          "Your new password is active. Opening " + roleLabel + "\u2026";

        window.setTimeout(function () {
          window.location.href = destination;
        }, 1100);
      })
      .catch(function () {
        fail("Cannot reach the server. Reload the page and try again.");
      });
  });

  if (logoutLink) {
    logoutLink.addEventListener("click", function (event) {
      event.preventDefault();
      fetch(API + "/auth/logout", {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-Token": csrfToken },
      }).finally(function () {
        window.location.href = "/guinhawa_system/login/index.php?signedout=1";
      });
    });
  }
})();
