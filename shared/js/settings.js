/* ==========================================================================
   GUINHAWA — Workspace settings
   ========================================================================== */

(function (window, document) {
  "use strict";

  var Store = window.GuinhawaStore;
  var UI = window.GuinhawaUI;
  if (!Store || !UI) return;
  var savedMailSender = "";
  var mailPasswordConfigured = false;

  var $ = function (id) {
    return document.getElementById(id);
  };

  /* -------------------------------- Profile ------------------------------- */

  var PROFILE_PHOTO_MAX_BYTES = 5 * 1024 * 1024;
  var avatarCropSource = null;
  var avatarCropZoom = 1;
  var avatarCropOffsetX = 0;
  var avatarCropOffsetY = 0;
  var avatarCropDrag = null;
  var avatarCropFrame = { x: 0, y: 0, size: 0 };

  function renderAvatarElement(element, avatar, name) {
    if (!element) return;
    var source = String(avatar || "");
    element.replaceChildren();
    if (/^(?:https?:\/\/|\/|data:image\/(?:png|jpeg|jpg|webp);base64,)/i.test(source)) {
      var image = document.createElement("img");
      image.alt = "";
      image.style.display = "block";
      image.style.width = "100%";
      image.style.height = "100%";
      image.style.objectFit = "cover";
      image.src = source;
      image.addEventListener("error", function () {
        element.textContent = UI.initials(name);
      }, { once: true });
      element.appendChild(image);
      return;
    }
    element.textContent = UI.initials(name);
  }

  function renderProfileAvatar(user) {
    var preview = $("profileAvatarPreview");
    if (!preview || !user) return;
    renderAvatarElement(preview, user.avatar, user.name);
    renderAvatarElement(document.querySelector(".user-menu .avatar"), user.avatar, user.name);
  }

  function avatarCropMetrics() {
    if (!avatarCropSource) return;
    var viewport = $("avatarCropStage");
    var size = viewport.clientWidth;
    if (!size) return null;
    var scale = Math.max(size / avatarCropSource.naturalWidth, size / avatarCropSource.naturalHeight) * avatarCropZoom;
    var width = avatarCropSource.naturalWidth * scale;
    var height = avatarCropSource.naturalHeight * scale;
    return { size: size, scale: scale, width: width, height: height };
  }

  function clampAvatarCrop(metrics) {
    var maxFrame = Math.min(metrics.size, metrics.width, metrics.height);
    if (!avatarCropFrame.size) {
      avatarCropFrame.size = maxFrame * 0.76;
      avatarCropFrame.x = (metrics.size - avatarCropFrame.size) / 2;
      avatarCropFrame.y = (metrics.size - avatarCropFrame.size) / 2;
    }
    avatarCropFrame.size = Math.max(Math.min(72, maxFrame), Math.min(maxFrame, avatarCropFrame.size));
    avatarCropFrame.x = Math.max(0, Math.min(metrics.size - avatarCropFrame.size, avatarCropFrame.x));
    avatarCropFrame.y = Math.max(0, Math.min(metrics.size - avatarCropFrame.size, avatarCropFrame.y));

    var minOffsetX = avatarCropFrame.x + avatarCropFrame.size - metrics.size / 2 - metrics.width / 2;
    var maxOffsetX = avatarCropFrame.x - metrics.size / 2 + metrics.width / 2;
    var minOffsetY = avatarCropFrame.y + avatarCropFrame.size - metrics.size / 2 - metrics.height / 2;
    var maxOffsetY = avatarCropFrame.y - metrics.size / 2 + metrics.height / 2;
    avatarCropOffsetX = Math.max(minOffsetX, Math.min(maxOffsetX, avatarCropOffsetX));
    avatarCropOffsetY = Math.max(minOffsetY, Math.min(maxOffsetY, avatarCropOffsetY));
  }

  function cropSourceRect(metrics) {
    var imageLeft = metrics.size / 2 + avatarCropOffsetX - metrics.width / 2;
    var imageTop = metrics.size / 2 + avatarCropOffsetY - metrics.height / 2;
    var side = Math.min(
      avatarCropSource.naturalWidth,
      avatarCropSource.naturalHeight,
      avatarCropFrame.size / metrics.scale
    );
    var x = (avatarCropFrame.x - imageLeft) / metrics.scale;
    var y = (avatarCropFrame.y - imageTop) / metrics.scale;
    x = Math.max(0, Math.min(avatarCropSource.naturalWidth - side, x));
    y = Math.max(0, Math.min(avatarCropSource.naturalHeight - side, y));
    return { x: x, y: y, side: side };
  }

  function updateAvatarCropPreview() {
    var metrics = avatarCropMetrics();
    if (!metrics) return;
    clampAvatarCrop(metrics);
    var image = $("avatarCropImage");
    var frame = $("avatarCropFrame");
    var imageLeft = metrics.size / 2 + avatarCropOffsetX - metrics.width / 2;
    var imageTop = metrics.size / 2 + avatarCropOffsetY - metrics.height / 2;

    image.style.width = metrics.width + "px";
    image.style.height = metrics.height + "px";
    image.style.left = "calc(50% + " + avatarCropOffsetX + "px)";
    image.style.top = "calc(50% + " + avatarCropOffsetY + "px)";
    frame.style.left = avatarCropFrame.x + "px";
    frame.style.top = avatarCropFrame.y + "px";
    frame.style.width = avatarCropFrame.size + "px";
    frame.style.height = avatarCropFrame.size + "px";

    var preview = $("avatarCropPreview");
    var context = preview.getContext("2d");
    if (context) {
      var crop = cropSourceRect(metrics);
      context.clearRect(0, 0, preview.width, preview.height);
      context.drawImage(avatarCropSource, crop.x, crop.y, crop.side, crop.side, 0, 0, preview.width, preview.height);
    }
  }

  function showAvatarCrop(image) {
    avatarCropSource = image;
    avatarCropZoom = 1;
    avatarCropOffsetX = 0;
    avatarCropOffsetY = 0;
    avatarCropFrame = { x: 0, y: 0, size: 0 };
    $("avatarCropImage").src = image.src;
    $("avatarCropZoom").value = "1";
    $("avatarCropModal").hidden = false;
    window.requestAnimationFrame(updateAvatarCropPreview);
    $("saveAvatarCrop").focus();
  }

  function closeAvatarCrop() {
    $("avatarCropModal").hidden = true;
    avatarCropSource = null;
    avatarCropDrag = null;
  }

  function resetAvatarCrop() {
    if (!avatarCropSource) return;
    avatarCropZoom = 1;
    avatarCropOffsetX = 0;
    avatarCropOffsetY = 0;
    avatarCropFrame = { x: 0, y: 0, size: 0 };
    $("avatarCropZoom").value = "1";
    updateAvatarCropPreview();
  }

  function saveAvatarCrop() {
    if (!avatarCropSource) return;
    var metrics = avatarCropMetrics();
    if (!metrics) return;
    clampAvatarCrop(metrics);
    var crop = cropSourceRect(metrics);
    var canvas = document.createElement("canvas");
    canvas.width = 512;
    canvas.height = 512;
    var context = canvas.getContext("2d");
    if (!context) {
      UI.toast("Your browser could not prepare this image.", { type: "danger", title: "Image unavailable" });
      return;
    }
    context.drawImage(avatarCropSource, crop.x, crop.y, crop.side, crop.side, 0, 0, 512, 512);
    var croppedAvatar = canvas.toDataURL("image/jpeg", 0.82);
    var user = Store.getCurrentUser();
    var button = $("saveAvatarCrop");
    button.disabled = true;
    var result = Store.saveProfile({ name: user.name, phone: user.phone, avatar: croppedAvatar });
    button.disabled = false;
    if (!result.ok) {
      UI.toast(result.message || "The profile picture could not be saved.", { type: "danger", title: "Avatar not saved" });
      return;
    }
    closeAvatarCrop();
    renderUserProfile();
    UI.toast("Your cropped profile picture was saved.", { title: "Avatar updated" });
  }

  function prepareProfilePhoto(file) {
    if (!file) return;
    if (["image/png", "image/jpeg", "image/webp"].indexOf(file.type) === -1) {
      UI.toast("Choose a PNG, JPG, or WebP image.", { type: "danger", title: "Unsupported image" });
      return;
    }
    if (file.size > PROFILE_PHOTO_MAX_BYTES) {
      UI.toast("Choose an image smaller than 5 MB.", { type: "danger", title: "Image is too large" });
      return;
    }

    var reader = new FileReader();
    reader.onerror = function () {
      UI.toast("The selected image could not be read.", { type: "danger", title: "Image unavailable" });
    };
    reader.onload = function () {
      var image = new Image();
      image.onerror = function () {
        UI.toast("The selected file is not a readable image.", { type: "danger", title: "Image unavailable" });
      };
      image.onload = function () {
        if (!image.naturalWidth || !image.naturalHeight) {
          UI.toast("The selected image has invalid dimensions.", { type: "danger", title: "Image unavailable" });
          return;
        }
        showAvatarCrop(image);
      };
      image.src = String(reader.result || "");
    };
    reader.readAsDataURL(file);
  }

  function renderUserProfile() {
    var user = Store.getCurrentUser();
    if (!user) return;
    renderProfileAvatar(user);
    $("profileUsername").textContent = "@" + (user.username || "\u2014");
    if ($("profileEmailDisplay")) $("profileEmailDisplay").textContent = user.email || "\u2014";
    $("profileEmployeeId").textContent = user.employeeId || "\u2014";
    $("profileRole").textContent = user.role || "\u2014";
    $("profileStatus").textContent = user.status || "\u2014";
    if ($("profileHeadRole")) $("profileHeadRole").textContent = user.role || "\u2014";
    $("profileName").value = user.name || "";
    if ($("profileEmail")) $("profileEmail").value = user.email || "";
    $("profilePhone").value = user.phone || "";
  }

  function saveUserProfile(event) {
    event.preventDefault();
    var form = $("profileForm");
    var name = $("profileName").value.trim();

    if (!name) {
      form.classList.add("was-validated");
      return;
    }
    if (!form.checkValidity()) {
      form.classList.add("was-validated");
      return;
    }

    var user = Store.getCurrentUser();
    var values = {
      name: name,
      phone: $("profilePhone").value.trim(),
    };
    var result = Store.saveProfile(values);
    if (!result.ok) {
      UI.toast(result.message, { type: "danger", title: "Profile not saved" });
      return;
    }
    user = Store.getCurrentUser();
    renderUserProfile();

    document.querySelectorAll(".user-menu__name").forEach(function (element) {
      element.textContent = user.name;
    });

    form.classList.remove("was-validated");
    UI.toast("Your profile details were saved.", { title: "Profile updated" });
  }

  /* ------------------------ Staff email verification --------------------- */

  function setAccountEmailStatus(message, tone) {
    var status = $("accountEmailStatus");
    if (!status) return;
    var toneClass = tone === "warning" ? " alert-app--warn" : tone === "danger" ? " alert-app--danger" : "";
    status.className = "alert-app mt-3" + toneClass;
    status.lastElementChild.textContent = message;
  }

  var accountEmailState = null;

  function renderAccountEmailVerification() {
    var result = Store.getAccountEmailVerification();
    accountEmailState = result.ok ? result.verification || null : null;
    if (!result.ok || !result.verification) {
      $("accountEmailCode").disabled = true;
      $("verifyAccountEmailCode").disabled = true;
      setAccountEmailStatus(result.message || "Email verification status could not be loaded.", "warning");
      return;
    }

    var verification = result.verification;
    var verified = verification.verified === true;
    var email = verification.email || "";
    var pendingEmail = verification.pendingEmail || "";
    $("accountEmailBadge").textContent = verified && !pendingEmail ? "Verified" : "Not verified";
    $("accountEmailCurrent").textContent = email
      ? email + (verified && !pendingEmail ? " · Verified" : " · Not verified")
      : "No email added";
    $("accountEmailAddress").value = pendingEmail || email;

    // The code box is always visible so it is easy to find, but it is only
    // usable when a code has actually been requested. A verified address with
    // no pending code never needs verification, so disable it and explain why.
    var needsCode = !!pendingEmail;
    var codeInput = $("accountEmailCode");
    var verifyButton = $("verifyAccountEmailCode");
    $("accountEmailCodePanel").classList.remove("d-none");
    $("accountEmailVerifyPanel").classList.remove("d-none");
    codeInput.disabled = !needsCode;
    verifyButton.disabled = !needsCode;
    codeInput.value = "";
    codeInput.placeholder = needsCode ? "000000" : "No code needed";
    codeInput.classList.remove("is-invalid");
    $("accountEmailCodeHelp").textContent = needsCode
      ? "Type the 6-digit code sent to " + pendingEmail + ". It expires in 10 minutes."
      : verified
        ? "Already verified. To use a different address, enter it above and send a code."
        : "Enter your email above and send a code, then type the 6-digit code here.";

    $("requestAccountEmailCode").innerHTML = needsCode
      ? '<i class="bi bi-arrow-repeat" aria-hidden="true"></i> Resend verification code'
      : '<i class="bi bi-send" aria-hidden="true"></i> Send verification code';
    if (needsCode) {
      setAccountEmailStatus("We sent a 6-digit code to " + pendingEmail + ". Enter it below within 10 minutes.", "warning");
    } else if (verified) {
      setAccountEmailStatus("Your email is verified and ready for sign-in and password recovery. No code needed right now.", "success");
    } else {
      setAccountEmailStatus("Add your personal email above, send a code, then enter the 6-digit code below. Sign-in works even if the email is not verified.");
    }
  }

  function requestAccountEmailCode() {
    var input = $("accountEmailAddress");
    if (!input.checkValidity()) {
      input.classList.add("is-invalid");
      input.focus();
      return;
    }
    input.classList.remove("is-invalid");

    var current = accountEmailState || {};
    if (!current.pendingEmail && current.verified &&
        input.value.trim().toLowerCase() === String(current.email || "").toLowerCase()) {
      setAccountEmailStatus("This is already your verified email. Enter a different address above to change it.", "success");
      return;
    }

    var button = $("requestAccountEmailCode");
    button.disabled = true;
    var result = Store.requestAccountEmailVerification(input.value.trim());
    button.disabled = false;
    if (!result.ok) {
      setAccountEmailStatus(result.message || "The verification code could not be sent.", "danger");
      return;
    }
    if (result.alreadyVerified) {
      renderAccountEmailVerification();
      UI.toast("That email address is already verified.", { title: "Email verified" });
      return;
    }
    renderAccountEmailVerification();
    setAccountEmailStatus(result.message || "Verification code sent. Check your inbox and spam folder.", "success");
  }

  function verifyAccountEmailCode() {
    var input = $("accountEmailCode");
    if (!input.checkValidity()) {
      input.classList.add("is-invalid");
      input.focus();
      return;
    }
    input.classList.remove("is-invalid");

    if (!accountEmailState || !accountEmailState.pendingEmail) {
      setAccountEmailStatus("No code is needed right now. To change your email, enter a new address above and send a code.", "success");
      input.value = "";
      return;
    }

    var result = Store.verifyAccountEmail(input.value.trim());
    if (!result.ok) {
      setAccountEmailStatus(result.message || "That verification code could not be confirmed.", "danger");
      return;
    }
    input.value = "";
    renderUserProfile();
    renderAccountEmailVerification();
    UI.toast("Your email address is verified.", { title: "Email verified" });
  }

  /* -------------------------------- Business ------------------------------ */

  function renderProfile() {
    var settings = Store.data.settings;
    $("settingsBusinessName").value = settings.businessName || "";
    $("settingsOwner").value = settings.owner || "";
    $("settingsEmail").value = settings.email || "";
    $("settingsPhone").value = settings.phone || "";
    $("settingsAddress").value = settings.address || "";
    $("settingsReceiptFooter").value = settings.receiptFooter || "";
  }

  function saveProfile(event) {
    event.preventDefault();
    var form = $("businessForm");
    if (!form.checkValidity()) {
      form.classList.add("was-validated");
      return;
    }

    var values = {
      businessName: $("settingsBusinessName").value.trim(),
      owner: $("settingsOwner").value.trim(),
      email: $("settingsEmail").value.trim(),
      phone: $("settingsPhone").value.trim(),
      address: $("settingsAddress").value.trim(),
      receiptFooter: $("settingsReceiptFooter").value.trim(),
    };
    var result = Store.saveSettings(values);
    if (!result.ok) {
      UI.toast(result.message, { type: "danger", title: "Settings not saved" });
      return;
    }
    form.classList.remove("was-validated");
    UI.toast("Business profile saved. Receipts will use the new details.", {
      title: "Profile updated",
    });
  }

  /* ------------------------------- Appearance ----------------------------- */

  var Theme = window.GuinhawaTheme;
  var appearanceDraft = null;
  var appearanceSaved = null;
  var appearanceSyncing = false;
  var appearanceCategory = "all";
  var appearanceQuery = "";

  var APPEARANCE_SEGMENTS = {
    fontSize: { small: "Small", medium: "Medium", large: "Large" },
    density: { compact: "Compact", comfortable: "Comfortable", spacious: "Spacious" },
    corners: { square: "Square", rounded: "Rounded" },
    animations: { off: "Off", subtle: "Subtle", smooth: "Smooth" },
    sidebar: { expanded: "Expanded", collapsed: "Collapsed" },
    cardStyle: { flat: "Flat", bordered: "Bordered", elevated: "Elevated" },
    background: { solid: "Solid", gradient: "Subtle gradient" },
  };

  function cloneAppearance(prefs) {
    return JSON.parse(JSON.stringify(prefs));
  }

  function appearanceInputs(name) {
    return Array.prototype.slice.call(
      document.querySelectorAll('input[name="' + name + '"]')
    );
  }

  function checkedAppearance(name) {
    var value = null;
    appearanceInputs(name).forEach(function (input) {
      if (input.checked) value = input.value;
    });
    return value;
  }

  function setAppearanceRadio(name, value) {
    appearanceInputs(name).forEach(function (input) {
      input.checked = input.value === value;
    });
  }

  function paletteFor(theme, mode) {
    return mode === "dark" ? theme.dark : theme.light;
  }

  function swatchMarkup(palette) {
    return ["sidebar", "bg", "card", "accent", "accent2", "text"]
      .map(function (key) {
        return '<i style="background:' + palette[key] + '"></i>';
      })
      .join("");
  }

  /* ------------------------- Combination library ------------------------- */

  function renderCategoryFilters() {
    var host = $("themeCategoryFilters");
    if (!host || !Theme) return;

    var filters = [{ id: "all", name: "All combinations" }].concat(Theme.CATEGORIES);

    host.innerHTML = filters
      .map(function (category) {
        var active = appearanceCategory === category.id;
        var count = Theme.countFor(category.id);
        return (
          '<button class="palette-filter' + (active ? " is-active" : "") + '" type="button"' +
          ' data-category="' + category.id + '"' +
          ' aria-pressed="' + (active ? "true" : "false") + '">' +
          UI.escape(category.name) +
          '<span class="palette-filter__count">' + count + "</span>" +
          "</button>"
        );
      })
      .join("");
  }

  function renderPaletteGrid() {
    var grid = $("paletteGrid");
    if (!grid || !Theme) return;

    var mode = appearanceDraft ? Theme.resolveMode(appearanceDraft) : "light";
    var selected = appearanceDraft ? appearanceDraft.theme : Theme.DEFAULTS.theme;
    var query = appearanceQuery.trim().toLowerCase();
    var themes = Theme.themesFor(appearanceCategory).filter(function (theme) {
      if (!query) return true;
      var family = Theme.CATEGORIES.filter(function (category) {
        return category.id === theme.category;
      })[0];
      return [theme.id, theme.name, theme.description, family ? family.name : ""]
        .join(" ")
        .toLowerCase()
        .indexOf(query) !== -1;
    });

    if ($("themeCount")) $("themeCount").textContent = Theme.THEMES.length;

    if ($("themeResult")) {
      var total = Theme.countFor(appearanceCategory);
      $("themeResult").textContent =
        themes.length === total
          ? "Showing all " + total + (total === 1 ? " combination" : " combinations")
          : "Showing " + themes.length + " of " + total + " combinations";
    }

    if (!themes.length) {
      grid.innerHTML =
        '<div class="palette-empty">' +
        '<strong>No combinations match</strong>' +
        "Try another colour name or clear the search." +
        "</div>";
      return;
    }

    grid.innerHTML = themes
      .map(function (theme) {
        var palette = paletteFor(theme, mode);
        var family = Theme.CATEGORIES.filter(function (category) {
          return category.id === theme.category;
        })[0];

        return (
          '<label class="palette-card" data-category="' + theme.category + '">' +
          '<input type="radio" name="themeCombination" value="' + theme.id + '"' +
          (theme.id === selected ? " checked" : "") + ">" +
          '<span class="palette-card__body">' +
          '<span class="palette-card__preview" style="background:' + palette.bg + '">' +
          '<span class="palette-card__rail" style="background:' + palette.sidebar + '"></span>' +
          '<span class="palette-card__canvas">' +
          '<span class="palette-card__bar" style="background:' + palette.card + ";border-color:" + palette.line + '"></span>' +
          '<span class="palette-card__row">' +
          '<i style="background:' + palette.accent + '"></i>' +
          '<i style="background:' + palette.accent2 + '"></i>' +
          '<i style="background:' + palette.line + '"></i>' +
          "</span>" +
          '<span class="palette-card__bar palette-card__bar--short" style="background:' + palette.card + ";border-color:" + palette.line + '"></span>' +
          "</span>" +
          '<span class="palette-card__selected" aria-hidden="true"><i class="bi bi-check-lg" aria-hidden="true"></i> Selected</span>' +
          "</span>" +
          '<span class="palette-card__info">' +
          '<span class="palette-card__title">' +
          "<strong>" + UI.escape(theme.name) + "</strong>" +
          '<span class="palette-card__meta">' +
          '<span class="palette-card__family">' + UI.escape(family ? family.name : theme.category) + "</span>" +
          (theme.id === Theme.DEFAULTS.theme
            ? '<span class="palette-card__badge">Default</span>'
            : "") +
          "</span>" +
          "</span>" +
          '<span class="palette-card__desc">' + UI.escape(theme.description) + "</span>" +
          '<span class="palette-card__swatches" aria-hidden="true">' + swatchMarkup(palette) + "</span>" +
          "</span>" +
          "</span>" +
          "</label>"
        );
      })
      .join("");
  }

  function renderCurrentTheme() {
    if (!Theme || !appearanceDraft) return;

    var theme = Theme.themeById(appearanceDraft.theme) || Theme.themesFor("all")[0];
    var mode = Theme.resolveMode(appearanceDraft);
    var palette = paletteFor(theme, mode);

    if ($("themeCurrentName")) $("themeCurrentName").textContent = theme.name;
    if ($("themeCurrentDesc")) $("themeCurrentDesc").textContent = theme.description;
    if ($("themeCurrent")) $("themeCurrent").setAttribute("data-mode", mode);

    if ($("themeCurrentMode")) {
      $("themeCurrentMode").textContent =
        appearanceDraft.mode === "system"
          ? "System \u00b7 " + (mode === "dark" ? "Dark" : "Light")
          : mode === "dark"
            ? "Dark variation"
            : "Light variation";
    }

    if ($("themeCurrentSwatches")) {
      $("themeCurrentSwatches").innerHTML = ["sidebar", "bg", "card", "accent", "accent2", "text"]
        .map(function (key) {
          return '<i style="background:' + palette[key] + '"></i>';
        })
        .join("");
    }
  }

  function renderPreviewSwatches() {
    var host = $("previewSwatches");
    if (!host || !Theme || !appearanceDraft) return;

    var theme = Theme.themeById(appearanceDraft.theme) || Theme.themesFor("all")[0];
    var palette = paletteFor(theme, Theme.resolveMode(appearanceDraft));

    var labels = {
      sidebar: "Sidebar",
      bg: "Background",
      card: "Cards",
      accent: "Primary",
      accent2: "Secondary",
      text: "Text",
    };

    host.innerHTML = ["sidebar", "bg", "card", "accent", "accent2", "text"]
      .map(function (key) {
        return (
          '<span class="pv-swatch"><i style="background:' + palette[key] + '"></i>' +
          UI.escape(labels[key]) + "</span>"
        );
      })
      .join("");
  }

  function renderAppearance(prefs) {
    if (!prefs) return;
    appearanceSyncing = true;

    setAppearanceRadio("themeMode", prefs.mode);
    setAppearanceRadio("themeCombination", prefs.theme);

    Object.keys(APPEARANCE_SEGMENTS).forEach(function (key) {
      setAppearanceRadio(key, prefs[key]);
      var label = $(key + "Value");
      if (label) {
        label.textContent = APPEARANCE_SEGMENTS[key][prefs[key]] || "\u2014";
      }
    });

    appearanceSyncing = false;

    renderCategoryFilters();
    renderPaletteGrid();
    renderCurrentTheme();
    renderPreviewSwatches();
  }

  function appearanceIsDirty() {
    if (!appearanceDraft || !appearanceSaved) return false;
    return JSON.stringify(appearanceDraft) !== JSON.stringify(appearanceSaved);
  }

  function setAppearanceState(dirty) {
    var state = $("appearanceState");
    var info = $("appearanceSavedInfo");
    var save = $("saveAppearanceBtn");

    if (state) {
      state.classList.toggle("is-dirty", dirty);
      state.innerHTML = dirty
        ? '<i class="bi bi-pencil-square" aria-hidden="true"></i> Unsaved changes'
        : '<i class="bi bi-check2-circle" aria-hidden="true"></i> Saved';
    }

    if (info) {
      var text = info.querySelector("span");
      if (text) {
        text.textContent = dirty
          ? "Review your changes, then save to keep them."
          : "Preferences are saved to your account and this browser.";
      }
    }

    if (save) save.disabled = !dirty;
  }

  function applyAppearanceDraft() {
    if (!appearanceDraft) return;
    appearanceDraft = Theme ? Theme.normalize(appearanceDraft) : appearanceDraft;
    appearanceSyncing = true;
    UI.appearance.apply(appearanceDraft);
    appearanceSyncing = false;
    renderCurrentTheme();
    renderPreviewSwatches();
    setAppearanceState(appearanceIsDirty());
  }

  function readAppearanceDraft() {
    var draft = Theme ? Theme.normalize(appearanceDraft) : {};

    var mode = checkedAppearance("themeMode");
    var theme = checkedAppearance("themeCombination");

    if (mode) draft.mode = mode;
    if (theme) draft.theme = theme;

    Object.keys(APPEARANCE_SEGMENTS).forEach(function (key) {
      var value = checkedAppearance(key);
      if (value) draft[key] = value;
    });

    return draft;
  }

  function saveAppearance() {
    var draft = readAppearanceDraft();

    var remote = Store.saveAppearanceRemote(draft);
    if (!remote.ok) {
      UI.toast(remote.message || "The server could not save your appearance.", {
        type: "danger",
        title: "Appearance not saved",
      });
      return;
    }

    appearanceSyncing = true;
    appearanceSaved = cloneAppearance(
      Theme ? Theme.save(draft) : UI.appearance.save(draft)
    );
    UI.appearance.apply(appearanceSaved);
    appearanceSyncing = false;

    appearanceDraft = cloneAppearance(appearanceSaved);
    renderAppearance(appearanceDraft);
    setAppearanceState(false);

    UI.toast("Your appearance is saved to your account and this browser.", {
      title: "Appearance updated",
    });
  }

  function resetAppearance() {
    UI.confirm({
      title: "Reset appearance to default?",
      message:
        "Light mode, the Forest Green combination, medium text, comfortable spacing, rounded corners, bordered cards and a solid background will be restored. This also clears the saved appearance for your account.",
      confirmLabel: "Reset appearance",
      icon: "bi-palette",
    }).then(function (confirmed) {
      if (!confirmed) return;

      var remote = Store.resetAppearanceRemote();
      if (!remote.ok) {
        UI.toast(remote.message || "The server could not reset your appearance.", {
          type: "danger",
          title: "Appearance not reset",
        });
        return;
      }

      appearanceSyncing = true;
      var defaults = Theme ? Theme.reset() : UI.appearance.reset();
      appearanceSyncing = false;

      appearanceDraft = cloneAppearance(defaults);
      appearanceSaved = cloneAppearance(defaults);
      appearanceCategory = "all";
      appearanceQuery = "";
      if ($("themeSearch")) $("themeSearch").value = "";
      if ($("themeSearchClear")) $("themeSearchClear").hidden = true;
      applyAppearanceDraft();
      renderAppearance(appearanceDraft);
      setAppearanceState(false);

      UI.toast("The original Guinhawa appearance was restored.", {
        type: "info",
        title: "Appearance reset",
      });
    });
  }

  function bindAppearance() {
    if (!$("appearance")) return;

    if ($("themeCategoryFilters")) {
      $("themeCategoryFilters").addEventListener("click", function (event) {
        var button = event.target.closest("[data-category]");
        if (!button) return;
        appearanceCategory = button.getAttribute("data-category") || "all";
        renderCategoryFilters();
        renderPaletteGrid();
      });
    }

    if ($("paletteGrid")) {
      $("paletteGrid").addEventListener("change", function (event) {
        if (event.target.name !== "themeCombination" || !event.target.checked) return;
        if (appearanceSyncing) return;
        appearanceDraft.theme = event.target.value;
        applyAppearanceDraft();
      });
    }

    if ($("themeSearch")) {
      $("themeSearch").addEventListener("input", function () {
        appearanceQuery = $("themeSearch").value;
        if ($("themeSearchClear")) $("themeSearchClear").hidden = !appearanceQuery;
        renderPaletteGrid();
      });
    }

    if ($("themeSearchClear")) {
      $("themeSearchClear").addEventListener("click", function () {
        appearanceQuery = "";
        if ($("themeSearch")) $("themeSearch").value = "";
        $("themeSearchClear").hidden = true;
        renderPaletteGrid();
        if ($("themeSearch")) $("themeSearch").focus();
      });
    }

    appearanceInputs("themeMode").forEach(function (input) {
      input.addEventListener("change", function () {
        if (!input.checked || appearanceSyncing) return;
        appearanceDraft.mode = input.value;
        applyAppearanceDraft();
        // The palette cards show the active variation, so repaint them.
        renderPaletteGrid();
      });
    });

    Object.keys(APPEARANCE_SEGMENTS).forEach(function (key) {
      appearanceInputs(key).forEach(function (input) {
        input.addEventListener("change", function () {
          if (!input.checked || appearanceSyncing) return;
          appearanceDraft[key] = input.value;
          var label = $(key + "Value");
          if (label) {
            label.textContent = APPEARANCE_SEGMENTS[key][input.value] || "\u2014";
          }
          applyAppearanceDraft();
        });
      });
    });

    if ($("saveAppearanceBtn")) {
      $("saveAppearanceBtn").addEventListener("click", saveAppearance);
    }

    if ($("cancelAppearanceBtn")) {
      $("cancelAppearanceBtn").addEventListener("click", function () {
        if (!appearanceIsDirty()) return;
        appearanceDraft = cloneAppearance(appearanceSaved);
        appearanceSyncing = true;
        UI.appearance.apply(appearanceDraft);
        appearanceSyncing = false;
        renderAppearance(appearanceDraft);
        setAppearanceState(false);
        UI.toast("Unsaved appearance changes were discarded.", {
          type: "info",
          title: "Changes cancelled",
        });
      });
    }

    if ($("resetAppearanceBtn")) {
      $("resetAppearanceBtn").addEventListener("click", resetAppearance);
    }

    // The top bar theme button and the sidebar collapse button are real saves;
    // mirror them here without discarding any edits in progress.
    document.addEventListener("guinhawa:appearancechange", function (event) {
      if (appearanceSyncing) return;

      var wasDirty = appearanceIsDirty();
      var applied = cloneAppearance(UI.appearance.read());
      appearanceSaved = applied;

      if (appearanceDraft) {
        if (!wasDirty) {
          appearanceDraft = cloneAppearance(applied);
          renderAppearance(appearanceDraft);
        } else {
          var changed = (event && event.detail && event.detail.preferences) || {};
          appearanceDraft.mode = applied.mode;
          if (changed.sidebar) {
            appearanceDraft.sidebar = applied.sidebar;
            setAppearanceRadio("sidebar", appearanceDraft.sidebar);
            if ($("sidebarValue")) {
              $("sidebarValue").textContent =
                APPEARANCE_SEGMENTS.sidebar[appearanceDraft.sidebar] || "\u2014";
            }
          }
          applyAppearanceDraft();
        }
      }

      renderPaletteGrid();
      renderCurrentTheme();
      renderPreviewSwatches();
      setAppearanceState(appearanceIsDirty());
    });
  }

  /* ----------------------------- Notifications ---------------------------- */

  function renderNotifications() {
    var prefs = Store.data.settings.notifications || {};
    $("notifyLowStock").checked = prefs.lowStock !== false;
    $("notifyDailySales").checked = prefs.dailySales !== false;
    $("notifyNewOrders").checked = prefs.newOrders !== false;
    $("notifyAttendance").checked = !!prefs.attendance;
  }

  function saveNotifications(event) {
    event.preventDefault();
    var preferences = {
      lowStock: $("notifyLowStock").checked,
      dailySales: $("notifyDailySales").checked,
      newOrders: $("notifyNewOrders").checked,
      attendance: $("notifyAttendance").checked,
    };
    var result = Store.saveNotifications(preferences);
    if (!result.ok) {
      UI.toast(result.message, { type: "danger", title: "Preferences not saved" });
      return;
    }
    UI.toast("Notification preferences saved.", {
      title: "Preferences saved",
    });
  }

  /* ----------------------------- Email delivery -------------------------- */

  function setMailStatus(message, type) {
    var status = $("mailSettingsStatus");
    if (!status) return;
    var toneClass = type === "warning" ? " alert-app--warn" : type === "danger" ? " alert-app--danger" : "";
    status.className = "alert-app mt-3" + toneClass;
    status.innerHTML = '<i class="bi bi-info-circle" aria-hidden="true"></i><div></div>';
    status.lastElementChild.textContent = message;
  }

  function renderMailSettings() {
    var result = Store.getMailSettings();
    if (!result.ok || !result.mail) {
      setMailStatus(result.message || "Email delivery settings could not be loaded.", "warning");
      return;
    }

    var mail = result.mail;
    $("mailSenderEmail").value = mail.username || "";
    savedMailSender = String(mail.username || "").trim().toLowerCase();
    mailPasswordConfigured = mail.passwordSet === true;
    $("mailAppPassword").value = "";
    $("mailPasswordHelp").textContent = mail.passwordSet
      ? "An App Password is saved. Leave this blank to keep it, or enter a new one to replace it."
      : "Enter a Google App Password, not your regular Gmail password.";

    if (mail.passwordSet) {
      setMailStatus("Sender is saved. Send a test email to its inbox to confirm delivery.", "success");
    } else {
      setMailStatus("Enter the sender Gmail and its App Password, then save these settings.", "warning");
    }
  }

  function saveMailSettings(event) {
    event.preventDefault();
    var form = $("mailSettingsForm");
    if (!form.checkValidity()) {
      form.classList.add("was-validated");
      return;
    }

    var result = Store.saveMailSettings({
      username: $("mailSenderEmail").value.trim(),
      password: $("mailAppPassword").value,
      fromEmail: $("mailSenderEmail").value.trim(),
    });
    if (!result.ok) {
      setMailStatus(result.message || "Email delivery settings could not be saved.", "danger");
      return;
    }

    form.classList.remove("was-validated");
    $("mailAppPassword").value = "";
    renderMailSettings();
    UI.toast(result.message || "Email delivery settings saved.", { title: "Email settings saved" });
  }

  function sendMailTest() {
    var sender = $("mailSenderEmail");
    if (!sender.checkValidity() || !sender.value.trim()) {
      $("mailSettingsForm").classList.add("was-validated");
      sender.focus();
      setMailStatus("Enter and save the sender Gmail before sending a test.", "warning");
      return;
    }
    if (
      !mailPasswordConfigured ||
      sender.value.trim().toLowerCase() !== savedMailSender ||
      $("mailAppPassword").value !== ""
    ) {
      setMailStatus("Save the sender Gmail and App Password first, then send the test.", "warning");
      return;
    }

    var button = $("testMailButton");
    button.disabled = true;
    var result = Store.sendTestMail(sender.value.trim());
    button.disabled = false;

    if (result.test && result.test.sent) {
      setMailStatus("Test email sent to " + result.test.email + ". Check that inbox and its spam folder.", "success");
      UI.toast("Test email sent to " + result.test.email + ".", { title: "Email delivery confirmed" });
      return;
    }

    var message = (result.test && result.test.error) || result.message || "The test email could not be sent.";
    setMailStatus(message, "danger");
    UI.toast(message, { type: "danger", title: "Test email failed" });
  }

  /* --------------------------------- Data --------------------------------- */

  function renderData() {
    var data = Store.data;
    $("dataProducts").textContent = Store.number(data.products.length);
    $("dataSuppliers").textContent = Store.number(data.suppliers.length);
    $("dataAccounts").textContent = Store.number(data.accounts.length);
    $("dataTransactions").textContent = Store.number(data.transactions.length);
    $("dataAttendance").textContent = Store.number(data.attendance.length);
    $("dataHistory").textContent = Store.number(data.history.length);
    $("dataUpdated").textContent = data.meta.updatedAt
      ? Store.formatDateTime(data.meta.updatedAt)
      : "Not recorded";
  }

  function exportBackup() {
    var json = Store.exportJSON();
    var blob = new Blob([json], { type: "application/json" });
    var url = URL.createObjectURL(blob);
    var link = document.createElement("a");
    link.href = url;
    link.download = "guinhawa-snapshot-" + Store.todayISO() + ".json";
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(function () {
      URL.revokeObjectURL(url);
    }, 1200);
    UI.toast("A read-only snapshot was downloaded. Database backups are SQL dumps kept outside the web folder.", {
      type: "info",
      title: "Snapshot ready",
    });
  }

  /* -------------------------------- GCash QR ------------------------------ */

  var GCASH_QR_MAX_BYTES = 2 * 1024 * 1024;

  function readGcashQr() {
    return (Store.data.settings && Store.data.settings.gcashQr) || "";
  }

  function paintGcashQr(src) {
    var image = $("gcashQrImage");
    var empty = $("gcashQrEmpty");
    var remove = $("gcashQrRemove");
    var preview = $("gcashQrPreview");
    if (!image || !empty || !remove || !preview) return;

    var hasImage = !!src;
    if (hasImage) {
      image.src = src;
    } else {
      image.removeAttribute("src");
    }
    image.hidden = !hasImage;
    empty.hidden = hasImage;
    remove.hidden = !hasImage;
    preview.classList.toggle("has-image", hasImage);
  }

  function renderGcashQr() {
    paintGcashQr(readGcashQr());
  }

  function shrinkQrImage(source, done) {
    var image = new Image();
    image.onload = function () {
      var maxEdge = 640;
      var scale = Math.min(1, maxEdge / Math.max(image.width, image.height));

      if (scale === 1 && source.length < 1200000) {
        done(source);
        return;
      }

      var width = Math.max(1, Math.round(image.width * scale));
      var height = Math.max(1, Math.round(image.height * scale));
      var canvas = document.createElement("canvas");
      canvas.width = width;
      canvas.height = height;
      var context = canvas.getContext("2d");
      context.fillStyle = "#ffffff";
      context.fillRect(0, 0, width, height);
      context.drawImage(image, 0, 0, width, height);
      done(canvas.toDataURL("image/png"));
    };
    image.onerror = function () {
      done("");
    };
    image.src = source;
  }

  function saveGcashQr(file) {
    if (!file) return;

    if (!/^image\//.test(file.type)) {
      UI.toast("Choose a PNG, JPG, or WebP image.", {
        type: "danger",
        title: "Unsupported file",
      });
      return;
    }

    if (file.size > GCASH_QR_MAX_BYTES) {
      UI.toast("That image is larger than 2 MB. Choose a smaller QR image.", {
        type: "danger",
        title: "Image too large",
      });
      return;
    }

    var reader = new FileReader();
    reader.onload = function () {
      shrinkQrImage(String(reader.result || ""), function (dataUrl) {
        if (!dataUrl) {
          UI.toast("That image could not be read.", {
            type: "danger",
            title: "Upload failed",
          });
          return;
        }

        var result = Store.saveGcashQr(dataUrl);
        if (!result.ok) {
          UI.toast(result.message || "The QR image could not be saved.", {
            type: "danger",
            title: "Could not save QR",
          });
          return;
        }

        paintGcashQr(dataUrl);
        UI.toast("GCash QR image saved to store settings.", {
          title: "QR updated",
        });
      });
    };
    reader.onerror = function () {
      UI.toast("That image could not be read.", {
        type: "danger",
        title: "Upload failed",
      });
    };
    reader.readAsDataURL(file);
  }

  function removeGcashQr() {
    UI.confirm({
      title: "Remove GCash QR image?",
      message: "The QR image will be removed from the shared store settings.",
      confirmLabel: "Remove image",
      icon: "bi-trash3",
    }).then(function (confirmed) {
      if (!confirmed) return;
      var result = Store.saveGcashQr("");
      if (!result.ok) {
        UI.toast(result.message || "The QR image could not be removed.", {
          type: "danger",
          title: "Not removed",
        });
        return;
      }
      paintGcashQr("");
      UI.toast("GCash QR image removed.", {
        type: "info",
        title: "QR removed",
      });
    });
  }

  function bindGcashQr() {
    var input = $("gcashQrInput");
    var preview = $("gcashQrPreview");

    if (input) {
      input.addEventListener("change", function (event) {
        var file = event.target.files && event.target.files[0];
        event.target.value = "";
        saveGcashQr(file);
      });
    }

    if (preview && input) {
      preview.addEventListener("click", function () {
        if (!readGcashQr()) input.click();
      });
    }

    if ($("gcashQrRemove")) {
      $("gcashQrRemove").addEventListener("click", removeGcashQr);
    }
  }

  /* ----------------------------- System info ----------------------------- */

  function browserLabel() {
    var agent = navigator.userAgent || "";
    var match;

    if ((match = agent.match(/Edg\/([\d.]+)/))) {
      return "Microsoft Edge " + match[1];
    }
    if ((match = agent.match(/OPR\/([\d.]+)/))) {
      return "Opera " + match[1];
    }
    if ((match = agent.match(/Firefox\/([\d.]+)/))) {
      return "Firefox " + match[1];
    }
    if ((match = agent.match(/Chrome\/([\d.]+)/))) {
      return "Chrome " + match[1];
    }
    if ((match = agent.match(/Version\/([\d.]+).*Safari/))) {
      return "Safari " + match[1];
    }
    return navigator.userAgentData && navigator.userAgentData.brands
      ? navigator.userAgentData.brands
          .map(function (brand) {
            return brand.brand + " " + brand.version;
          })
          .join(", ")
      : "Unknown browser";
  }

  function sessionLabel() {
    var user = typeof Store.getCurrentUser === "function" ? Store.getCurrentUser() : null;
    if (!user) return "No active session";
    return (
      "Secure PHP session cookie \u00b7 " +
      (user.name || user.username || "Signed in") +
      (user.role ? " (" + user.role + ")" : "")
    );
  }

  function renderSystemInfo() {
    var meta = Store.data.meta || {};
    $("systemVersion").textContent = "Schema v" + (meta.version || "\u2014");
    $("systemStorage").textContent = "MySQL 8 \u00b7 guinhawa_db";
    $("systemBrowser").textContent = browserLabel();
    $("systemSession").textContent = sessionLabel();
    $("systemUpdated").textContent = meta.updatedAt
      ? Store.formatDateTime(meta.updatedAt)
      : "Not recorded";
  }

  /* ---------------------------------- Init -------------------------------- */

  function init() {
    var signedIn = Store.getCurrentUser();

    renderUserProfile();
    renderProfile();
    renderGcashQr();
    appearanceSaved = cloneAppearance(UI.appearance.read());
    appearanceDraft = cloneAppearance(appearanceSaved);
    renderAppearance(appearanceDraft);
    setAppearanceState(false);
    renderNotifications();
    renderData();
    renderSystemInfo();
    bindAppearance();
    bindGcashQr();

    // Both Admin and Staff manage their own personal email here. Keeping this
    // panel available to Admin also fixes the only path to verify an Admin email.
    $("accountEmailSection").classList.remove("d-none");
    renderAccountEmailVerification();
    $("requestAccountEmailCode").addEventListener("click", requestAccountEmailCode);
    $("verifyAccountEmailCode").addEventListener("click", verifyAccountEmailCode);
    $("accountEmailAddress").addEventListener("input", function () {
      this.classList.remove("is-invalid");
    });
    $("accountEmailCode").addEventListener("input", function () {
      this.classList.remove("is-invalid");
      this.value = this.value.replace(/\D/g, "").slice(0, 6);
    });

    $("profileAvatarPreview").addEventListener("click", function () {
      $("profilePhotoInput").click();
    });
    $("profilePhotoInput").addEventListener("change", function (event) {
      prepareProfilePhoto(event.target.files && event.target.files[0]);
      event.target.value = "";
    });
    $("avatarCropZoom").addEventListener("input", function () {
      avatarCropZoom = Number(this.value) || 1;
      updateAvatarCropPreview();
    });
    $("avatarCropStage").addEventListener("pointerdown", function (event) {
      if (!avatarCropSource) return;
      event.preventDefault();
      var handle = event.target.closest(".avatar-crop-handle");
      if (handle) {
        var direction = handle.getAttribute("data-handle");
        var handleX = direction.indexOf("w") !== -1 ? -1 : 1;
        var handleY = direction.indexOf("n") !== -1 ? -1 : 1;
        avatarCropDrag = {
          type: "resize",
          pointerId: event.pointerId,
          x: event.clientX,
          y: event.clientY,
          handleX: handleX,
          handleY: handleY,
          anchorX: handleX < 0 ? avatarCropFrame.x + avatarCropFrame.size : avatarCropFrame.x,
          anchorY: handleY < 0 ? avatarCropFrame.y + avatarCropFrame.size : avatarCropFrame.y,
          startSize: avatarCropFrame.size,
        };
      } else {
        avatarCropDrag = {
          type: "image",
          pointerId: event.pointerId,
          x: event.clientX,
          y: event.clientY,
          offsetX: avatarCropOffsetX,
          offsetY: avatarCropOffsetY,
        };
      }
      this.setPointerCapture(event.pointerId);
    });
    $("avatarCropStage").addEventListener("pointermove", function (event) {
      if (!avatarCropDrag || event.pointerId !== avatarCropDrag.pointerId) return;
      var deltaX = event.clientX - avatarCropDrag.x;
      var deltaY = event.clientY - avatarCropDrag.y;
      if (avatarCropDrag.type === "resize") {
        var metrics = avatarCropMetrics();
        var maxSize = Math.min(
          metrics.size,
          metrics.width,
          metrics.height,
          avatarCropDrag.handleX < 0 ? avatarCropDrag.anchorX : metrics.size - avatarCropDrag.anchorX,
          avatarCropDrag.handleY < 0 ? avatarCropDrag.anchorY : metrics.size - avatarCropDrag.anchorY
        );
        var minSize = Math.min(64, maxSize);
        var change = (avatarCropDrag.handleX * deltaX + avatarCropDrag.handleY * deltaY) / 2;
        avatarCropFrame.size = Math.max(minSize, Math.min(maxSize, avatarCropDrag.startSize + change));
        avatarCropFrame.x = avatarCropDrag.handleX < 0 ? avatarCropDrag.anchorX - avatarCropFrame.size : avatarCropDrag.anchorX;
        avatarCropFrame.y = avatarCropDrag.handleY < 0 ? avatarCropDrag.anchorY - avatarCropFrame.size : avatarCropDrag.anchorY;
      } else {
        avatarCropOffsetX = avatarCropDrag.offsetX + deltaX;
        avatarCropOffsetY = avatarCropDrag.offsetY + deltaY;
      }
      updateAvatarCropPreview();
    });
    ["pointerup", "pointercancel"].forEach(function (eventName) {
      $("avatarCropStage").addEventListener(eventName, function (event) {
        if (avatarCropDrag && event.pointerId === avatarCropDrag.pointerId) avatarCropDrag = null;
      });
    });
    $("cancelAvatarCrop").addEventListener("click", closeAvatarCrop);
    $("resetAvatarCrop").addEventListener("click", resetAvatarCrop);
    $("saveAvatarCrop").addEventListener("click", saveAvatarCrop);
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !$("avatarCropModal").hidden) closeAvatarCrop();
    });
    window.addEventListener("resize", updateAvatarCropPreview);

    if (signedIn.role !== "Admin") {
      ["business", "gcash", "data", "mailDelivery"].forEach(function (id) {
        var section = $(id);
        if (section) section.classList.add("d-none");
      });
    } else {
      $("mailDelivery").classList.remove("d-none");
      renderMailSettings();
      $("mailSettingsForm").addEventListener("submit", saveMailSettings);
      $("testMailButton").addEventListener("click", sendMailTest);
      $("mailSenderEmail").addEventListener("input", function () {
        this.classList.remove("is-invalid");
      });
    }

    $("profileForm").addEventListener("submit", saveUserProfile);
    $("businessForm").addEventListener("submit", saveProfile);
    $("notificationsForm").addEventListener("submit", saveNotifications);
    $("exportBackup").addEventListener("click", exportBackup);

    if (!Store.storageAvailable()) {
      $("exportBackup").disabled = true;
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
