/* ==========================================================================
   GUINHAWA — Admin staff account management
   ========================================================================== */

(function (window, document) {
  "use strict";

  var Store = window.GuinhawaStore;
  var UI = window.GuinhawaUI;
  if (!Store || !UI) return;

  var $ = function (id) {
    return document.getElementById(id);
  };

  var STANDARD_SCHEDULE = "8:00 AM \u2013 5:00 PM";

  var state = {
    search: UI.param("q"),
    role: "",
    status: "",
  };

  var editingId = null;
  var resetTargetId = null;
  var usernameTouched = false;
  var currentUser = Store.getCurrentUser();

  /* ------------------------------- Feedback ------------------------------- */

  function hasSwal() {
    return typeof window.Swal !== "undefined" && window.Swal && typeof window.Swal.fire === "function";
  }

  function notify(icon, title, text) {
    if (hasSwal()) {
      window.Swal.fire({
        toast: true,
        position: "top-end",
        icon: icon,
        title: title,
        text: text || "",
        showConfirmButton: false,
        timer: 3400,
        timerProgressBar: true,
      });
      return;
    }
    UI.toast(text || title, {
      type: icon === "error" ? "danger" : icon === "warning" ? "warning" : "success",
      title: title,
    });
  }

  function confirmAction(options) {
    if (hasSwal()) {
      var payload = {
        title: options.title,
        icon: options.icon || "question",
        showCancelButton: true,
        confirmButtonText: options.confirmText || "Confirm",
        cancelButtonText: options.cancelText || "Cancel",
        reverseButtons: true,
        focusCancel: true,
      };
      if (options.html) {
        payload.html = options.html;
      } else {
        payload.text = options.text || "";
      }
      return window.Swal.fire(payload).then(function (result) {
        return !!result.isConfirmed;
      });
    }
    return Promise.resolve(window.confirm(options.text || options.title || "Continue?"));
  }

  function htmlEscape(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (ch) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch];
    });
  }

  /* ------------------------------ Password helpers ------------------------ */

  function passwordProblem(value) {
    var password = String(value == null ? "" : value);
    if (!password) return "Enter a default password.";
    if (password.length < 10) return "Use at least 10 characters.";
    if (password.length > 128) return "Use 128 characters or fewer.";
    if (!/[a-z]/.test(password)) return "Include at least one lowercase letter.";
    if (!/[A-Z]/.test(password)) return "Include at least one uppercase letter.";
    if (!/[0-9]/.test(password)) return "Include at least one number.";
    return "";
  }

  function randomPassword() {
    var upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    var lower = "abcdefghijkmnopqrstuvwxyz";
    var digits = "23456789";
    var symbols = "!@#$%&*?";
    var all = upper + lower + digits + symbols;
    var chars = [pick(upper), pick(lower), pick(digits), pick(symbols)];
    for (var i = 0; i < 8; i += 1) chars.push(pick(all));
    for (var j = chars.length - 1; j > 0; j -= 1) {
      var k = Math.floor(Math.random() * (j + 1));
      var temp = chars[j];
      chars[j] = chars[k];
      chars[k] = temp;
    }
    return chars.join("");
  }

  function pick(characters) {
    return characters.charAt(Math.floor(Math.random() * characters.length));
  }

  function setPasswordVisibility(input, button, visible) {
    if (!input || !button) return;
    input.type = visible ? "text" : "password";
    button.setAttribute("aria-pressed", visible ? "true" : "false");
    button.setAttribute("aria-label", visible ? "Hide password" : "Show password");
    var icon = button.querySelector("i");
    if (icon) icon.className = visible ? "bi bi-eye-slash" : "bi bi-eye";
  }

  /* ------------------------------- Rendering ------------------------------ */

  function displayStatus(account) {
    if (account.status === "Active" && account.mustChangePassword) return "Must Change Password";
    return account.status || "Inactive";
  }

  function statusMeta(account) {
    var label = displayStatus(account);
    if (label === "Active") return { label: label, variant: "success" };
    if (label === "Must Change Password") return { label: label, variant: "warning" };
    if (label === "Pending") return { label: label, variant: "warning" };
    return { label: "Inactive", variant: "neutral" };
  }

  function filteredAccounts() {
    var q = state.search.trim().toLowerCase();
    return Store.data.accounts.filter(function (account) {
      if (state.role && account.role !== state.role) return false;
      if (state.status && displayStatus(account) !== state.status) return false;
      if (!q) return true;
      return (
        account.name + " " +
        (account.staffId || "") + " " +
        (account.employeeId || "") + " " +
        (account.username || "") + " " +
        (account.email || "") + " " +
        (account.phone || "") + " " +
        account.role
      )
        .toLowerCase()
        .indexOf(q) > -1;
    });
  }

  function renderStats() {
    var accounts = Store.data.accounts;
    $("statAccounts").textContent = Store.number(accounts.length);
    $("statAdmins").textContent = Store.number(
      accounts.filter(function (a) {
        return a.role === "Admin";
      }).length
    );
    $("statInventory").textContent = Store.number(
      accounts.filter(function (a) {
        return a.role === "Inventory Staff";
      }).length
    );
    $("statCashiers").textContent = Store.number(
      accounts.filter(function (a) {
        return a.role === "Cashier";
      }).length
    );
  }

  function isProtected(account) {
    return account.role === "Admin" || account.id === currentUser.id;
  }

  function renderActions(account) {
    var id = UI.escape(account.id);
    var actions =
      '<button class="btn btn-icon" type="button" data-edit="' + id + '" title="Edit account" aria-label="Edit account"><i class="bi bi-pencil"></i></button> ';

    if (account.role === "Admin") {
      actions +=
        '<button class="btn btn-icon" type="button" disabled title="Administrator password is managed by the owner" aria-label="Reset password unavailable"><i class="bi bi-key"></i></button> ';
      actions +=
        '<button class="btn btn-icon" type="button" disabled title="Administrator accounts cannot be deactivated" aria-label="Deactivate unavailable"><i class="bi bi-lock"></i></button>';
      return actions;
    }

    actions +=
      '<button class="btn btn-icon" type="button" data-reset="' + id + '" title="Reset password" aria-label="Reset password"><i class="bi bi-key"></i></button> ';

    var active = account.status === "Active";
    actions +=
      '<button class="btn btn-icon ' + (active ? "btn-icon--danger" : "btn-icon--success") + '" type="button" data-toggle="' + id + '" title="' +
      (active ? "Deactivate account" : "Activate account") + '" aria-label="' +
      (active ? "Deactivate account" : "Activate account") + '"><i class="bi bi-' +
      (active ? "pause" : "play") + '"></i></button> ';

    actions +=
      '<button class="btn btn-icon btn-icon--danger" type="button" data-delete="' + id + '" title="Delete staff (kept in archive)" aria-label="Delete staff"><i class="bi bi-trash3"></i></button>';
    return actions;
  }

  function renderTable() {
    var tbody = $("accountsBody");
    var rows = filteredAccounts();

    if (!rows.length) {
      tbody.innerHTML = UI.emptyRow(
        9,
        "bi-people",
        "No accounts match",
        "Adjust the search or filters. Only Admins can create new team accounts."
      );
    } else {
      tbody.innerHTML = rows
        .map(function (account) {
          var status = statusMeta(account);
          var initials = UI.initials(account.name);
          var protectedRow = isProtected(account);
          var staffId = account.staffId || account.employeeId || "\u2014";
          var created = account.createdAt || account.joined;
          var lastPasswordChange = account.lastPasswordChange
            ? Store.formatDate(account.lastPasswordChange)
            : account.mustChangePassword
            ? '<span class="text-app-muted">Default password pending</span>'
            : '<span class="text-app-muted">\u2014</span>';

          return (
            '<tr data-id="' + UI.escape(account.id) + '">' +
            "<td>" +
            '<div class="avatar-cell">' +
            '<span class="avatar ' + UI.avatarClass(account.role) + '">' + UI.escape(initials) + "</span>" +
            "<div>" +
            '<span class="avatar-cell__name">' + UI.escape(account.name) +
            (protectedRow ? ' <i class="bi bi-patch-check-fill text-app-success" title="Protected account" aria-label="Protected account"></i>' : "") +
            "</span>" +
            '<span class="avatar-cell__meta">' + UI.escape(account.email || "No email added") +
            (account.email ? ' &middot; ' + UI.escape(account.emailVerified ? "Verified" : "Not verified") : "") + "</span>" +
            '<span class="avatar-cell__meta">' + (account.username ? "@" + UI.escape(account.username) : "\u2014") + "</span>" +
            "</div></div></td>" +
            '<td class="text-tabular">' + UI.escape(staffId) + "</td>" +
            "<td>" + UI.roleBadge(account.role) + "</td>" +
            '<td class="text-nowrap">' + UI.escape(account.phone || "\u2014") + "</td>" +
            '<td class="text-nowrap" data-sort-value="' + UI.escape(created || "") + '">' +
            (created ? Store.formatDate(created) : '<span class="text-app-muted">\u2014</span>') + "</td>" +
            '<td class="text-nowrap">' + lastPasswordChange + "</td>" +
            '<td class="text-nowrap" data-sort-value="' + UI.escape(account.lastActive || "") + '">' +
            (account.status === "Pending" ? '<span class="text-app-muted">Awaiting activation</span>' : Store.formatDateTime(account.lastActive)) + "</td>" +
            "<td>" + UI.statusBadge(status.label, status.variant) + "</td>" +
            '<td class="text-end text-nowrap">' + renderActions(account) + "</td>" +
            "</tr>"
          );
        })
        .join("");
    }

    $("accountCount").textContent =
      Store.number(rows.length) + (rows.length === 1 ? " account" : " accounts");
    $("accountsFoot").textContent =
      "Showing " + Store.number(rows.length) + " of " + Store.number(Store.data.accounts.length) + " accounts";
  }

  function renderAll() {
    renderStats();
    renderTable();
  }

  /* -------------------------------- Accounts ------------------------------ */

  function nextStaffId() {
    var highest = 0;
    Store.data.accounts.forEach(function (account) {
      [account.staffId, account.employeeId].forEach(function (value) {
        var match = /^STF-(\d+)$/i.exec(String(value || ""));
        if (match) highest = Math.max(highest, Number(match[1]) || 0);
      });
    });
    return "STF-" + String(highest + 1).padStart(3, "0");
  }

  function roleHelpText(role) {
    if (role === "Cashier") {
      return "Cashiers can record sales and view receipts.";
    }
    return "Inventory staff can receive and release stock.";
  }

  function normalizeUsername(value) {
    return String(value == null ? "" : value).trim().toLowerCase();
  }

  function usernameTaken(value) {
    var key = normalizeUsername(value);
    if (!key) return false;
    return Store.data.accounts.some(function (account) {
      return account.id !== editingId && normalizeUsername(account.username) === key;
    });
  }

  function resetPasswordField() {
    var input = $("accountPassword");
    if (!input) return;
    input.value = "";
    input.type = "password";
    input.setCustomValidity("");
    input.classList.remove("is-invalid");
    setPasswordVisibility(input, $("toggleAccountPassword"), false);
  }

  function openModal(accountId) {
    editingId = accountId || null;
    usernameTouched = false;
    var form = $("accountForm");
    form.reset();
    form.classList.remove("was-validated");

    var account = accountId ? Store.findAccount(accountId) : null;
    var roleSelect = $("accountRoleInput");
    var note = $("activationNote");
    var saveLabel = $("saveAccountLabel");
    var emailInput = $("accountEmail");
    var statusInput = $("accountStatusInput");
    var passwordSection = $("passwordSection");
    var passwordInput = $("accountPassword");

    resetPasswordField();

    if (account) {
      $("accountModalTitle").textContent = "Edit staff account";
      $("accountModalSub").textContent = account.role + " account";
      $("accountName").value = account.name;
      $("accountStaffId").value = account.staffId || account.employeeId || "";
      $("accountUsername").value = account.username || "";
      $("accountEmail").value = account.email || "";
      $("accountPhone").value = account.phone || "";
      statusInput.value = String(account.status || "Active").toLowerCase();
      statusInput.disabled = false;
      roleSelect.value = account.role;
      usernameTouched = true;
      if (note) note.classList.add("d-none");
      if (passwordSection) passwordSection.classList.add("d-none");
      if (passwordInput) passwordInput.required = false;
      if (saveLabel) saveLabel.textContent = "Save changes";

      if (account.role === "Admin") {
        roleSelect.disabled = true;
        $("roleHelp").textContent = "Administrator role and status are protected.";
      } else {
        roleSelect.disabled = false;
        $("roleHelp").textContent = roleHelpText(account.role);
      }
    } else {
      $("accountModalTitle").textContent = "Add new staff";
      $("accountModalSub").textContent = "Inventory Staff and Cashier accounts only";
      roleSelect.disabled = false;
      roleSelect.value = "Inventory Staff";
      statusInput.value = "active";
      statusInput.disabled = false;
      $("accountStaffId").value = nextStaffId();
      $("accountUsername").value = "";
      $("accountName").value = "";
      $("accountEmail").value = "";
      $("accountPhone").value = "";
      $("roleHelp").textContent = roleHelpText("Inventory Staff");
      if (note) note.classList.remove("d-none");
      if (passwordSection) passwordSection.classList.remove("d-none");
      if (passwordInput) passwordInput.required = true;
      if (saveLabel) saveLabel.textContent = "Create staff account";
    }

    bootstrap.Modal.getOrCreateInstance($("accountModal")).show();
  }

  function validateAccount() {
    var form = $("accountForm");
    var emailInput = $("accountEmail");
    var email = emailInput.value.trim().toLowerCase();
    var valid = true;

    emailInput.setCustomValidity("");
    emailInput.classList.remove("is-invalid");

    var emailTaken =
      !!email &&
      Store.data.accounts.some(function (account) {
        return account.id !== editingId && String(account.email || "").toLowerCase() === email;
      });

    if (emailTaken) {
      emailInput.setCustomValidity("This email is already assigned to a team account.");
      emailInput.classList.add("is-invalid");
      valid = false;
    }

    var staffId = $("accountStaffId").value.trim().toUpperCase();
    var idTaken = Store.data.accounts.some(function (account) {
      if (account.id === editingId) return false;
      return (
        String(account.staffId || "").toUpperCase() === staffId ||
        String(account.employeeId || "").toUpperCase() === staffId
      );
    });

    if (idTaken) {
      $("accountStaffId").setCustomValidity("This Staff ID is already in use.");
      $("accountStaffId").classList.add("is-invalid");
      valid = false;
    } else {
      $("accountStaffId").setCustomValidity("");
      $("accountStaffId").classList.remove("is-invalid");
    }

    var usernameField = $("accountUsername");
    var username = normalizeUsername(usernameField.value);
    var duplicateUsername = usernameTaken(username);
    if (duplicateUsername) {
      usernameField.setCustomValidity("This username is already taken.");
      usernameField.classList.add("is-invalid");
      valid = false;
    } else {
      usernameField.setCustomValidity("");
      usernameField.classList.remove("is-invalid");
    }

    if (!editingId) {
      var passwordInput = $("accountPassword");
      var problem = passwordProblem(passwordInput.value);
      passwordInput.setCustomValidity(problem);
      passwordInput.classList.toggle("is-invalid", !!problem);
      if (problem) valid = false;
    }

    if (!form.checkValidity()) valid = false;
    form.classList.add("was-validated");

    if (emailTaken) {
      notify("warning", "Duplicate email", "That email is already assigned to a team account.");
    }
    if (idTaken) {
      notify("warning", "Duplicate Staff ID", "That Staff ID is already in use. Reload the directory and try again.");
    }
    if (duplicateUsername) {
      notify("warning", "Duplicate username", "Choose a different username; usernames must be unique.");
    }

    return valid;
  }

  function collectValues() {
    return {
      name: $("accountName").value.trim(),
      staffId: $("accountStaffId").value.trim().toUpperCase(),
      username: normalizeUsername($("accountUsername").value),
      email: $("accountEmail").value.trim().toLowerCase(),
      phone: $("accountPhone").value.trim(),
      role: $("accountRoleInput").value,
      status: $("accountStatusInput").value,
      password: editingId ? "" : $("accountPassword").value,
    };
  }

  function saveAccount(event) {
    event.preventDefault();
    if (!validateAccount()) return;

    var values = collectValues();
    if (values.role === "Admin" && !editingId) {
      notify("error", "Role not allowed", "Admin accounts are provisioned by the system owner.");
      return;
    }

    if (editingId) {
      var editResult = Store.saveAccount(values, editingId);
      if (!editResult.ok) {
        notify("error", "Account not saved", editResult.message);
        return;
      }
      bootstrap.Modal.getOrCreateInstance($("accountModal")).hide();
      renderAll();
      notify("success", "Account saved", values.name + " was updated.");
      return;
    }

    confirmAction({
      title: "Create this staff account?",
      html:
        '<p class="mb-2"><strong>' + htmlEscape(values.name) + "</strong> will be added as a <strong>" +
        htmlEscape(values.role) + "</strong> with status <strong>Active</strong>.</p>" +
        '<p class="mb-2">Sign-in email: <strong>' + htmlEscape(values.email) + "</strong></p>" +
        '<p class="mb-0">They must replace the default password the first time they sign in before any dashboard opens.</p>',
      icon: "question",
      confirmText: "Create account",
      cancelText: "Review details",
    }).then(function (confirmed) {
      if (!confirmed) return;

      var result = Store.saveAccount(values, null);
      if (!result.ok) {
        notify("error", "Account not created", result.message);
        return;
      }

      bootstrap.Modal.getOrCreateInstance($("accountModal")).hide();
      renderAll();
      values.staffId = result.account && result.account.staffId ? result.account.staffId : values.staffId;
      values.username = result.account && result.account.username ? result.account.username : values.username;
      values.status = result.account && result.account.status ? result.account.status : "Active";
      showAccountCreated(values, result.message || "The staff account is ready to use.");
    });
  }

  function showAccountCreated(values, message) {
    var body =
      '<p class="mb-2"><strong>' + htmlEscape(values.name) + "</strong> was created as a <strong>" +
      htmlEscape(values.role) + "</strong> account and is <strong>" + htmlEscape(values.status) + "</strong>.</p>" +
      '<div style="display:grid;gap:6px;text-align:left;margin:0 0 12px">' +
      '<div><span style="opacity:.7">Staff ID:</span> <strong>' + htmlEscape(values.staffId) + "</strong></div>" +
      '<div><span style="opacity:.7">Sign-in email:</span> <strong>' + htmlEscape(values.email) + "</strong></div>" +
      '<div><span style="opacity:.7">Username:</span> <strong>@' + htmlEscape(values.username) + "</strong></div>" +
      '<div><span style="opacity:.7">Default password:</span> <code style="font-size:.95em">' + htmlEscape(values.password) + "</code></div>" +
      "</div>" +
      '<p class="mb-0" style="font-size:.85rem;opacity:.8">Share these details securely. The password is not stored in readable form and cannot be shown again.</p>';

    if (hasSwal()) {
      window.Swal.fire({
        icon: "success",
        title: "Account created",
        html: body,
        confirmButtonText: "Got it",
      });
      return;
    }
    UI.toast(values.name + "'s account is ready.", { title: "Account created" });
  }

  /* ----------------------------- Reset password --------------------------- */

  function openResetModal(id) {
    var account = Store.findAccount(id);
    if (!account) return;
    if (account.role === "Admin") {
      notify("warning", "Protected account", "The Administrator password is managed through the profile page.");
      return;
    }

    resetTargetId = id;
    $("resetPasswordSub").textContent = account.name + " \u00B7 " + (account.staffId || account.employeeId || "");
    var input = $("resetPasswordInput");
    input.value = "";
    input.setCustomValidity("");
    input.classList.remove("is-invalid");
    setPasswordVisibility(input, $("toggleResetPassword"), false);
    bootstrap.Modal.getOrCreateInstance($("resetPasswordModal")).show();
  }

  function submitResetPassword(event) {
    event.preventDefault();
    if (!resetTargetId) return;

    var account = Store.findAccount(resetTargetId);
    var input = $("resetPasswordInput");
    var value = input.value;
    var problem = value ? passwordProblem(value) : "";

    input.setCustomValidity(problem);
    input.classList.toggle("is-invalid", !!problem);
    if (problem) {
      notify("warning", "Check the password", problem);
      return;
    }

    var result = Store.resetAccountPassword(resetTargetId, value);
    if (!result.ok) {
      notify("error", "Password not reset", result.message);
      return;
    }

    bootstrap.Modal.getOrCreateInstance($("resetPasswordModal")).hide();
    renderAll();
    resetTargetId = null;
    showResetResult(account, result.password);
  }

  function showResetResult(account, password) {
    var name = account ? account.name : "The staff member";
    var body =
      '<p class="mb-2">A new temporary password was issued for <strong>' + htmlEscape(name) + "</strong>.</p>" +
      '<p class="mb-2">They must change it the next time they sign in. Any active sessions were signed out.</p>' +
      (password
        ? '<p class="mb-0">Temporary password: <code style="font-size:.95em">' + htmlEscape(password) +
          "</code></p><p class=\"mb-0\" style=\"font-size:.82rem;opacity:.75\">Copy it now; it cannot be shown again.</p>"
        : '<p class="mb-0" style="font-size:.85rem;opacity:.8">Share the password you entered with the staff member securely.</p>');

    if (hasSwal()) {
      window.Swal.fire({
        icon: "success",
        title: "Password reset",
        html: body,
        confirmButtonText: "Got it",
      });
      return;
    }
    notify("success", "Password reset", name + " must choose a new password on next sign-in.");
  }

  /* --------------------------- Pending activations ------------------------ */

  function parseDbDate(value) {
    if (!value) return null;
    var text = String(value).trim().replace(" ", "T");
    var date = new Date(text);
    return isNaN(date.getTime()) ? null : date;
  }

  function renderActivations() {
    var tbody = $("activationBody");
    if (!tbody) return;

    var result = Store.pendingActivations();
    var activations = result.activations || [];

    var count = $("activationCount");
    if (count) count.textContent = Store.number(activations.length);

    var panel = $("activationPanel");
    if (panel) panel.classList.toggle("d-none", !activations.length);

    if (!activations.length) {
      tbody.innerHTML = UI.emptyRow(
        6,
        "bi-envelope-paper",
        "No pending activations",
        "Legacy staff invitations appear here until their emailed activation link is used."
      );
      return;
    }

    var now = Date.now();
    tbody.innerHTML = activations
      .map(function (account) {
        var dbId = Number(account.id) || 0;
        var accountKey = "acc" + dbId;
        var sent = parseDbDate(account.activationSentAt);
        var expires = parseDbDate(account.activationExpiresAt);
        var expired = expires ? expires.getTime() < now : false;
        var roleLabel = account.roleLabel || (account.role === "Inventory Staff" ? "Inventory Staff" : "Cashier");

        var expiryCell = expires
          ? '<span class="' + (expired ? "text-app-danger" : "text-app-muted") + '">' +
            (expired ? "Expired " : "") + UI.escape(Store.formatDateTime(account.activationExpiresAt)) +
            "</span>"
          : '<span class="text-app-muted">No link sent</span>';

        var actions =
          '<button class="btn btn-icon" type="button" data-resend="' + UI.escape(accountKey) + '" title="Resend activation link" aria-label="Resend activation link"><i class="bi bi-send"></i></button> ' +
          '<button class="btn btn-icon btn-icon--danger" type="button" data-cancel="' + UI.escape(accountKey) + '" title="Cancel invitation" aria-label="Cancel invitation"><i class="bi bi-x-lg"></i></button>';

        return (
          '<tr data-id="' + dbId + '">' +
          "<td>" +
          '<div class="avatar-cell">' +
          '<span class="avatar ' + UI.avatarClass(roleLabel) + '">' + UI.escape(UI.initials(account.fullName || account.name)) + "</span>" +
          "<div>" +
          '<span class="avatar-cell__name">' + UI.escape(account.fullName || account.name) + "</span>" +
          '<span class="avatar-cell__meta">' + UI.escape(account.employeeId) + " &middot; @" + UI.escape(account.username) + "</span>" +
          "</div></div></td>" +
          '<td class="text-nowrap">' + UI.escape(account.email || "\u2014") + "</td>" +
          "<td>" + UI.roleBadge(roleLabel) + "</td>" +
          '<td class="text-nowrap">' + (sent ? UI.escape(Store.formatDateTime(account.activationSentAt)) : '<span class="text-app-muted">\u2014</span>') + "</td>" +
          '<td class="text-nowrap">' + expiryCell + "</td>" +
          '<td class="text-end text-nowrap">' + actions + "</td>" +
          "</tr>"
        );
      })
      .join("");
  }

  function resendActivation(id) {
    var account = Store.findAccount(id);
    if (!account) return;

    confirmAction({
      title: "Resend activation link?",
      text: account.name + " will receive a new activation link by email. Any earlier link stops working.",
      icon: "question",
      confirmText: "Resend link",
      cancelText: "Cancel",
    }).then(function (confirmed) {
      if (!confirmed) return;
      var result = Store.resendActivation(id);
      if (!result.ok) {
        notify("error", "Link not sent", result.message);
        return;
      }
      renderAll();
      renderActivations();
      if (result.activation && result.activation.sent === false) {
        notify("warning", "Email not sent", result.activation.error || result.message);
      } else {
        notify("success", "Activation link sent", "A fresh link is on its way to " + account.email + ".");
      }
    });
  }

  function cancelActivation(id) {
    var account = Store.findAccount(id);
    if (!account) return;

    confirmAction({
      title: "Cancel this invitation?",
      text: account.name + " will no longer be able to activate the account with the emailed link. You can create a new invitation later.",
      icon: "warning",
      confirmText: "Cancel invitation",
      cancelText: "Keep invitation",
    }).then(function (confirmed) {
      if (!confirmed) return;
      var result = Store.cancelActivation(id);
      if (!result.ok) {
        notify("error", "Invitation not cancelled", result.message);
        return;
      }
      renderAll();
      renderActivations();
      notify("success", "Invitation cancelled", account.name + " is now inactive.");
    });
  }

  function toggleAccount(id) {
    var account = Store.findAccount(id);
    if (!account) return;
    if (isProtected(account)) {
      notify("warning", "Protected account", "Administrator accounts cannot be deactivated.");
      return;
    }

    var activating = account.status !== "Active";
    confirmAction({
      title: (activating ? "Activate" : "Deactivate") + " account?",
      text: activating
        ? account.name + " will regain access to the console."
        : account.name + " will no longer be able to sign in. Their past sales and attendance records stay intact.",
      confirmText: activating ? "Activate account" : "Deactivate account",
      cancelText: "Cancel",
      icon: "question",
    }).then(function (confirmed) {
      if (!confirmed) return;
      var result = Store.setAccountStatus(id, activating);
      if (!result.ok) {
        notify("error", "Account not updated", result.message);
        return;
      }
      renderAll();
      notify(activating ? "success" : "warning", "Account updated", account.name + " is now " + (activating ? "active" : "inactive") + ".");
    });
  }

  /* ----------------------------- Archived staff --------------------------- */

  var archivedRows = [];

  function archivedAccount(id) {
    return archivedRows.filter(function (row) {
      return Number(row.id) === Number(id);
    })[0];
  }

  function renderArchived() {
    var panel = $("archivedPanel");
    var tbody = $("archivedBody");
    if (!panel || !tbody) return;

    var result = Store.archivedStaff();
    archivedRows = result.archived || [];

    var count = $("archivedCount");
    if (count) count.textContent = Store.number(archivedRows.length);

    panel.classList.toggle("d-none", archivedRows.length === 0);
    if (!archivedRows.length) {
      tbody.innerHTML = "";
      return;
    }

    tbody.innerHTML = archivedRows
      .map(function (account) {
        var dbId = Number(account.id) || 0;
        var roleLabel = account.roleLabel || account.role || "Staff";
        var actions =
          '<button class="btn btn-icon" type="button" data-view-archived="' + dbId + '" title="View saved record" aria-label="View saved record"><i class="bi bi-eye"></i></button> ' +
          '<button class="btn btn-icon btn-icon--success" type="button" data-restore="' + dbId + '" title="Restore staff record" aria-label="Restore staff record"><i class="bi bi-arrow-counterclockwise"></i></button>';

        return (
          '<tr data-id="' + dbId + '">' +
          "<td>" +
          '<div class="avatar-cell">' +
          '<span class="avatar ' + UI.avatarClass(roleLabel) + '">' + UI.escape(UI.initials(account.fullName || account.name)) + "</span>" +
          "<div>" +
          '<span class="avatar-cell__name">' + UI.escape(account.fullName || account.name) + "</span>" +
          '<span class="avatar-cell__meta">' + UI.escape(account.username ? "@" + account.username : "\u2014") + " &middot; " + UI.escape(account.email || "\u2014") + "</span>" +
          "</div></div></td>" +
          '<td class="text-tabular">' + UI.escape(account.staffId || account.employeeId) + "</td>" +
          "<td>" + UI.roleBadge(roleLabel) + "</td>" +
          '<td class="text-nowrap">' + (account.dateJoined ? UI.escape(Store.formatDate(account.dateJoined)) : '<span class="text-app-muted">\u2014</span>') + "</td>" +
          '<td class="text-nowrap">' + (account.deletedAt ? UI.escape(Store.formatDateTime(account.deletedAt)) : '<span class="text-app-muted">\u2014</span>') + "</td>" +
          '<td class="text-nowrap">' + UI.escape(account.deletedBy || "Administrator") + "</td>" +
          '<td class="text-end text-nowrap">' + actions + "</td>" +
          "</tr>"
        );
      })
      .join("");
  }

  function showArchivedRecord(id) {
    var account = archivedAccount(id);
    if (!account) return;

    var rows = [
      ["Staff member", account.fullName || account.name],
      ["Staff ID", account.staffId || account.employeeId],
      ["Username", "@" + (account.username || "\u2014")],
      ["Personal email", account.email || "\u2014"],
      ["Phone", account.contactNumber || "\u2014"],
      ["Role", account.roleLabel || account.role || "\u2014"],
      ["Work schedule", STANDARD_SCHEDULE],
      ["Date joined", account.dateJoined ? Store.formatDate(account.dateJoined) : "\u2014"],
      ["Last password change", account.lastPasswordChange ? Store.formatDate(account.lastPasswordChange) : "\u2014"],
      ["Last active", account.lastLoginAt ? Store.formatDateTime(account.lastLoginAt) : "Never signed in"],
      ["Deleted on", account.deletedAt ? Store.formatDateTime(account.deletedAt) : "\u2014"],
      ["Deleted by", account.deletedBy || "Administrator"],
    ];

    var html =
      '<div style="display:grid;gap:8px;text-align:left">' +
      rows
        .map(function (row) {
          return (
            '<div style="display:flex;justify-content:space-between;gap:18px;border-bottom:1px solid rgba(148,163,184,.18);padding-bottom:6px">' +
            '<span style="opacity:.7">' + htmlEscape(row[0]) + "</span>" +
            '<strong style="text-align:right">' + htmlEscape(row[1]) + "</strong>" +
            "</div>"
          );
        })
        .join("") +
      "</div>" +
      '<p style="margin:14px 0 0;font-size:.82rem;opacity:.75">This is a read-only backup. ' +
      "Sales, attendance and audit history still point to this staff member. " +
      "Use Restore to bring the record back into the team directory.</p>";

    if (hasSwal()) {
      window.Swal.fire({
        title: "Saved staff record",
        html: html,
        width: 560,
        confirmButtonText: "Close",
      });
      return;
    }
    UI.toast("This record is archived and kept as a backup.", { title: "Archived staff" });
  }

  function restoreArchived(id) {
    var account = archivedAccount(id);
    if (!account) return;
    var name = account.fullName || account.name || "This staff member";

    confirmAction({
      title: "Restore this staff record?",
      html:
        '<p class="mb-2"><strong>' + htmlEscape(name) + "</strong> will return to the team directory with all saved details.</p>" +
        '<p class="mb-0">The account comes back as <strong>Inactive</strong>. Activate it when the staff member is ready to sign in again.</p>',
      icon: "question",
      confirmText: "Restore record",
      cancelText: "Cancel",
    }).then(function (confirmed) {
      if (!confirmed) return;

      var result = Store.restoreAccount(id);
      if (!result.ok) {
        notify("error", "Record not restored", result.message);
        return;
      }

      renderAll();
      renderArchived();
      notify("success", "Record restored", name + " is back in the team directory as inactive.");
    });
  }

  function deleteStaffAccount(id) {
    var account = Store.findAccount(id);
    if (!account) return;
    if (isProtected(account)) {
      notify("warning", "Protected account", "Administrator accounts cannot be deleted.");
      return;
    }

    confirmAction({
      title: "Delete this staff account?",
      html:
        '<p class="mb-2"><strong>' + htmlEscape(account.name) + "</strong> (" + htmlEscape(account.staffId || account.employeeId) + ") will be removed from the team directory and can no longer sign in.</p>" +
        '<p class="mb-2">Nothing is erased: personal details, sales, attendance and audit history are kept in <strong>Archived staff</strong>.</p>' +
        '<p class="mb-0">You can restore the record later if needed.</p>',
      icon: "warning",
      confirmText: "Delete and archive",
      cancelText: "Keep account",
    }).then(function (confirmed) {
      if (!confirmed) return;

      var result = Store.deleteAccount(id);
      if (!result.ok) {
        notify("error", "Account not deleted", result.message);
        return;
      }

      renderAll();
      renderArchived();
      notify("success", "Account deleted", account.name + " was moved to Archived staff as a backup.");
    });
  }

  function exportAccounts() {
    var rows = filteredAccounts().map(function (account) {
      return [
        account.name,
        account.staffId || account.employeeId || "",
        account.username || "",
        account.email || "",
        account.phone || "",
        account.role,
        displayStatus(account),
        account.createdAt || account.joined || "",
        account.lastPasswordChange || "",
        account.lastActive || "",
      ];
    });

    UI.exportCSV(
      "guinhawa-accounts-" + Store.todayISO() + ".csv",
      ["Name", "Staff ID", "Username", "Email", "Phone", "Role", "Status", "Created", "Last password change", "Last active"],
      rows
    );
    UI.toast("Account CSV exported.", {
      type: "info",
      title: "Export ready",
    });
  }

  /* --------------------------------- Events ------------------------------- */

  function bindEvents() {
    var debouncedRender = UI.debounce(renderTable, 160);

    $("accountSearch").addEventListener("input", function (event) {
      state.search = event.target.value;
      debouncedRender();
    });

    $("accountRole").addEventListener("change", function (event) {
      state.role = event.target.value;
      renderTable();
    });

    $("accountStatus").addEventListener("change", function (event) {
      state.status = event.target.value;
      renderTable();
    });

    $("clearAccountFilters").addEventListener("click", function () {
      state = { search: "", role: "", status: "" };
      $("accountSearch").value = "";
      $("accountRole").value = "";
      $("accountStatus").value = "";
      renderTable();
    });

    $("addAccountBtn").addEventListener("click", function () {
      openModal(null);
    });

    $("exportAccounts").addEventListener("click", exportAccounts);
    $("accountForm").addEventListener("submit", saveAccount);
    $("resetPasswordForm").addEventListener("submit", submitResetPassword);

    $("accountRoleInput").addEventListener("change", function (event) {
      $("roleHelp").textContent = roleHelpText(event.target.value);
    });

    $("accountEmail").addEventListener("input", function () {
      this.setCustomValidity("");
      this.classList.remove("is-invalid");
    });

    $("accountUsername").addEventListener("input", function () {
      usernameTouched = true;
      this.setCustomValidity("");
      this.classList.remove("is-invalid");
    });

    $("toggleAccountPassword").addEventListener("click", function () {
      var input = $("accountPassword");
      setPasswordVisibility(input, this, input.type === "password");
    });

    $("generateAccountPassword").addEventListener("click", function () {
      var input = $("accountPassword");
      input.value = randomPassword();
      input.setCustomValidity("");
      input.classList.remove("is-invalid");
      setPasswordVisibility(input, $("toggleAccountPassword"), true);
      notify("info", "Password generated", "Share it securely. The staff member must change it on first sign-in.");
    });

    $("toggleResetPassword").addEventListener("click", function () {
      var input = $("resetPasswordInput");
      setPasswordVisibility(input, this, input.type === "password");
    });

    $("generateResetPassword").addEventListener("click", function () {
      var input = $("resetPasswordInput");
      input.value = randomPassword();
      input.setCustomValidity("");
      input.classList.remove("is-invalid");
      setPasswordVisibility(input, $("toggleResetPassword"), true);
    });

    $("resetPasswordInput").addEventListener("input", function () {
      this.setCustomValidity("");
      this.classList.remove("is-invalid");
    });

    $("accountsBody").addEventListener("click", function (event) {
      var editButton = event.target.closest("[data-edit]");
      if (editButton) {
        openModal(editButton.getAttribute("data-edit"));
        return;
      }
      var resetButton = event.target.closest("[data-reset]");
      if (resetButton) {
        openResetModal(resetButton.getAttribute("data-reset"));
        return;
      }
      var toggleButton = event.target.closest("[data-toggle]");
      if (toggleButton) {
        toggleAccount(toggleButton.getAttribute("data-toggle"));
        return;
      }
      var deleteButton = event.target.closest("[data-delete]");
      if (deleteButton) {
        deleteStaffAccount(deleteButton.getAttribute("data-delete"));
      }
    });

    var activationBody = $("activationBody");
    if (activationBody) {
      activationBody.addEventListener("click", function (event) {
        var resendButton = event.target.closest("[data-resend]");
        if (resendButton) {
          resendActivation(resendButton.getAttribute("data-resend"));
          return;
        }
        var cancelButton = event.target.closest("[data-cancel]");
        if (cancelButton) {
          cancelActivation(cancelButton.getAttribute("data-cancel"));
        }
      });
    }

    var archivedBody = $("archivedBody");
    if (archivedBody) {
      archivedBody.addEventListener("click", function (event) {
        var viewButton = event.target.closest("[data-view-archived]");
        if (viewButton) {
          showArchivedRecord(viewButton.getAttribute("data-view-archived"));
          return;
        }
        var restoreButton = event.target.closest("[data-restore]");
        if (restoreButton) {
          restoreArchived(restoreButton.getAttribute("data-restore"));
        }
      });
    }

    $("accountModal").addEventListener("hidden.bs.modal", function () {
      editingId = null;
      usernameTouched = false;
      var roleSelect = $("accountRoleInput");
      roleSelect.disabled = false;
      $("accountForm").classList.remove("was-validated");
      resetPasswordField();
    });

    $("resetPasswordModal").addEventListener("hidden.bs.modal", function () {
      resetTargetId = null;
      var input = $("resetPasswordInput");
      input.value = "";
      input.setCustomValidity("");
      input.classList.remove("is-invalid");
      setPasswordVisibility(input, $("toggleResetPassword"), false);
    });
  }

  /* ---------------------------------- Init -------------------------------- */

  function init() {
    renderAll();
    renderActivations();
    renderArchived();
    bindEvents();
    $("accountSearch").value = state.search;
    if (state.search) renderTable();

    if (currentUser.role !== "Admin") {
      $("addAccountBtn").disabled = true;
      $("addAccountBtn").title = "Only Admins can add team accounts";
      UI.toast("Only Admin accounts can create staff or cashier accounts.", {
        type: "warning",
        title: "Restricted action",
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
