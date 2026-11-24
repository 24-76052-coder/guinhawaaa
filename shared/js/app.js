/* ==========================================================================
   GUINHAWA — Shared application shell + UI utilities
   ========================================================================== */

(function (window, document) {
  "use strict";

  var Store = window.GuinhawaStore;
  if (!Store) {
    throw new Error("GuinhawaStore is required before app.js");
  }

  var ALL_ROLES = ["Admin", "Inventory Staff", "Cashier"];
  var ADMIN_ONLY = ["Admin"];

  var NAV = [
    { section: "Overview" },
    {
      id: "dashboard",
      label: "Admin Dashboard",
      icon: "bi-grid-1x2-fill",
      href: "/guinhawa_system/admin/index.php",
      roles: ADMIN_ONLY,
    },
    {
      id: "inventory-dashboard",
      label: "Inventory Dashboard",
      icon: "bi-clipboard-data",
      href: "/guinhawa_system/inventory/index.php",
      roles: ["Admin", "Inventory Staff"],
    },
    { section: "Inventory Management" },
    {
      id: "products",
      label: "Products",
      icon: "bi-box-seam",
      href: "products.php",
      roles: ALL_ROLES,
    },
    {
      id: "inventory",
      label: "Inventory",
      icon: "bi-archive",
      href: "inventory.php",
      roles: ["Admin", "Inventory Staff"],
      lowStockBadge: true,
    },
    {
      id: "suppliers",
      label: "Suppliers",
      icon: "bi-truck",
      href: "suppliers.php",
      roles: ADMIN_ONLY,
    },
    { section: "Sales & Operations" },
    {
      id: "cashier-dashboard",
      label: "Cashier / POS",
      icon: "bi-cart3",
      href: "/guinhawa_system/cashier/index.php",
      roles: ["Cashier"],
    },
    {
      id: "transactions",
      label: "Transactions",
      icon: "bi-receipt-cutoff",
      href: "transactions.php",
      roles: ["Admin", "Cashier"],
    },
    {
      id: "attendance",
      label: "Staff Attendance",
      icon: "bi-calendar2-check",
      href: "attendance.php",
      roles: ALL_ROLES,
    },
    { section: "System Management" },
    {
      id: "accounts",
      label: "User Management",
      icon: "bi-people",
      href: "accounts.php",
      roles: ADMIN_ONLY,
    },
    {
      id: "reports",
      label: "Reports",
      icon: "bi-graph-up-arrow",
      href: "reports.php",
      roles: ADMIN_ONLY,
    },
    {
      id: "settings",
      label: "Settings",
      icon: "bi-gear",
      href: "settings.php",
      roles: ALL_ROLES,
    },
  ];

  var PAGE_INFO = {};
  NAV.forEach(function (item) {
    if (!item.section) {
      PAGE_INFO[item.id] = item;
    }
  });

  var pageId = document.body.getAttribute("data-page") || "dashboard";
  var pageInfo = PAGE_INFO[pageId] || null;
  var activePage = pageInfo ? pageId : "dashboard";
  if (!pageInfo) pageInfo = PAGE_INFO[activePage];
  var user = Store.getSession() || Store.getCurrentUser();
  var toastCounter = 0;

  /* ------------------------------ Auth guard ------------------------------ */

  function canSee(item) {
    if (!item || !item.roles) return true;
    return item.roles.indexOf(user.role) > -1;
  }

  function guardRoute() {
    var session = Store.getSession();
    if (!session) {
      var redirect = window.location.pathname.replace(/^\/guinhawa_system\//, "") + window.location.search;
      window.location.replace(
        "/guinhawa_system/login/index.php?redirect=" + encodeURIComponent(redirect)
      );
      return false;
    }
    user = session;
    if (pageInfo && pageInfo.roles && pageInfo.roles.indexOf(user.role) === -1) {
      window.location.replace(Store.roleHome(user.role));
      return false;
    }
    return true;
  }

  /* ------------------------------- Helpers -------------------------------- */

  function escapeHTML(value) {
    return Store.escapeHTML(value);
  }

  function initials(name) {
    return String(name || "?")
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map(function (part) {
        return part.charAt(0).toUpperCase();
      })
      .join("");
  }

  function avatarImageSource(value) {
    var source = String(value || "").trim();
    return /^(?:https?:\/\/|\/|data:image\/(?:png|jpeg|jpg|webp);base64,)/i.test(source)
      ? source
      : "";
  }

  function avatarMarkup(account) {
    var source = avatarImageSource(account && account.avatar);
    return source
      ? '<img class="avatar__image" src="' + escapeHTML(source) + '" alt="" style="display:block;width:100%;height:100%;object-fit:cover">'
      : escapeHTML(initials(account && account.name));
  }

  function renderAvatar(element, account) {
    if (!element) return;
    var source = avatarImageSource(account && account.avatar);
    element.replaceChildren();
    if (!source) {
      element.textContent = initials(account && account.name);
      return;
    }
    var image = document.createElement("img");
    image.className = "avatar__image";
    image.alt = "";
    image.style.display = "block";
    image.style.width = "100%";
    image.style.height = "100%";
    image.style.objectFit = "cover";
    image.src = source;
    image.addEventListener("error", function () {
      element.textContent = initials(account && account.name);
    }, { once: true });
    element.appendChild(image);
  }

  function param(name) {
    return new URLSearchParams(window.location.search).get(name) || "";
  }

  function lowStockCount() {
    return Store.lowStockCount();
  }

  function themeResolved() {
    return document.documentElement.getAttribute("data-bs-theme") === "dark"
      ? "dark"
      : "light";
  }

  /* ------------------------------ Navigation ------------------------------ */

  var AREA_PAGES = {
    admin: [
      "index", "products", "inventory", "stock-in", "stock-out", "suppliers",
      "transactions", "attendance", "history", "accounts", "reports", "settings",
    ],
    inventory: [
      "index", "products", "inventory", "stock-in", "stock-out", "history",
      "attendance", "settings",
    ],
    cashier: ["index", "products", "transactions", "attendance", "settings"],
  };

  function currentArea() {
    var match = window.location.pathname.match(
      /^\/guinhawa_system\/(admin|inventory|cashier)\//
    );
    return match ? match[1] : "admin";
  }

  function pageExistsIn(area, file) {
    var name = String(file || "").replace(/\.php$/, "");
    return !!AREA_PAGES[area] && AREA_PAGES[area].indexOf(name) > -1;
  }

  function roleAreas() {
    if (user.role === "Admin") return ["admin", "inventory"];
    if (user.role === "Inventory Staff") return ["inventory"];
    return ["cashier"];
  }

  // Point a relative page link at a folder the signed-in role can open.
  function canonicalHref(href) {
    var text = String(href || "");
    if (!/^[A-Za-z0-9_-]+\.php(?:[?#].*)?$/.test(text)) return href;
    var area = currentArea();
    var file = text.split(/[?#]/)[0];
    if (pageExistsIn(area, file)) return text;
    var allowed = roleAreas();
    for (var i = 0; i < allowed.length; i += 1) {
      if (pageExistsIn(allowed[i], file)) {
        return "/guinhawa_system/" + allowed[i] + "/" + text;
      }
    }
    return text;
  }

  function navHref(item) {
    if (item.id === "dashboard") return "/guinhawa_system/admin/index.php";
    if (item.id === "inventory-dashboard") {
      return "/guinhawa_system/inventory/index.php";
    }
    if (item.id === "cashier-dashboard") {
      return "/guinhawa_system/cashier/index.php";
    }
    return canonicalHref(item.href);
  }

  function visibleGroups() {
    var groups = [];
    var current = null;
    NAV.forEach(function (item) {
      if (item.section) {
        current = { section: item.section, items: [] };
        groups.push(current);
      } else if (canSee(item)) {
        if (!current) {
          current = { section: "", items: [] };
          groups.push(current);
        }
        current.items.push(item);
      }
    });
    return groups.filter(function (group) {
      return group.items.length > 0;
    });
  }

  function navMarkup() {
    var count = lowStockCount();
    return visibleGroups()
      .map(function (group) {
        var label = group.section
          ? '<div class="sidebar-label">' + escapeHTML(group.section) + "</div>"
          : "";
        var links = group.items
          .map(function (item) {
            var active = item.id === activePage;
            var badge =
              item.lowStockBadge && count > 0
                ? '<span class="nav-badge" data-low-stock-badge>' + count + "</span>"
                : item.lowStockBadge
                  ? '<span class="nav-badge d-none" data-low-stock-badge>0</span>'
                  : "";
            return (
              '<a class="nav-link' +
              (active ? " active" : "") +
              '" href="' +
              navHref(item) +
              '"' +
              (active ? ' aria-current="page"' : "") +
              ">" +
              '<i class="bi ' +
              item.icon +
              '" aria-hidden="true"></i>' +
              "<span>" +
              escapeHTML(item.label) +
              "</span>" +
              badge +
              "</a>"
            );
          })
          .join("");
        return label + links;
      })
      .join("");
  }

  function brandMarkup() {
    return (
      '<div class="sidebar-head">' +
      '<div class="sidebar-head__top">' +
      '<button class="sidebar-collapse" type="button" data-sidebar-toggle aria-pressed="false" aria-label="Collapse navigation" title="Collapse navigation (Ctrl+B)">' +
      '<i class="bi bi-chevron-double-left" aria-hidden="true"></i>' +
      "</button>" +
      "</div>" +
      '<a class="sidebar-brand text-decoration-none" href="' + Store.roleHome(user.role) + '" aria-label="Guinhawa Poultry Supply dashboard">' +
      '<img class="sidebar-brand__logo" src="/guinhawa_system/assets/images/guinhawa-logo.png" alt="Guinhawa Poultry Supply logo" width="44" height="44">' +
      "<span>" +
      '<span class="sidebar-brand__word">Guinhawa</span>' +
      '<span class="sidebar-brand__sub">Poultry Supply</span>' +
      "</span>" +
      "</a>" +
      "</div>"
    );
  }

  function sidebarFootMarkup() {
    return (
      '<div class="sidebar-foot">' +
      '<div class="demo-chip"><span class="dot"></span><span>Connected to the Guinhawa MySQL database.</span></div>' +
      '<a class="sidebar-signout" href="/guinhawa_system/login/index.php" data-signout>' +
      '<i class="bi bi-box-arrow-left" aria-hidden="true"></i><span>Sign out</span>' +
      "</a>" +
      "</div>"
    );
  }

  function renderSidebar() {
    var sidebarRoot = document.getElementById("sidebarRoot");
    if (sidebarRoot) {
      sidebarRoot.innerHTML =
        '<aside class="app-sidebar d-none d-lg-flex" aria-label="Main navigation">' +
        brandMarkup() +
        '<div class="sidebar-scroll">' +
        '<nav class="sidebar-nav">' +
        navMarkup() +
        "</nav>" +
        sidebarFootMarkup() +
        "</div>" +
        "</aside>";

      sidebarRoot
        .querySelectorAll("[data-sidebar-toggle]")
        .forEach(function (button) {
          button.addEventListener("click", toggleSidebar);
        });
      updateSidebarToggle();
    }

    var offcanvasRoot = document.getElementById("offcanvasRoot");
    if (offcanvasRoot) {
      offcanvasRoot.innerHTML =
        '<div class="offcanvas offcanvas-start sidebar-offcanvas" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">' +
        '<div class="offcanvas-header">' +
        '<div class="d-flex align-items-center gap-2" id="mobileSidebarLabel">' +
        '<img src="/guinhawa_system/assets/images/guinhawa-logo.png" alt="" width="38" height="38" style="border-radius:50%;background:rgba(255,255,255,.06);padding:2px">' +
        "<div>" +
        '<span class="sidebar-brand__word">Guinhawa</span>' +
        '<span class="sidebar-brand__sub">Poultry Supply</span>' +
        "</div>" +
        "</div>" +
        '<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>' +
        "</div>" +
        '<div class="offcanvas-body">' +
        '<nav class="sidebar-nav" aria-label="Mobile navigation">' +
        navMarkup() +
        "</nav>" +
        sidebarFootMarkup() +
        "</div>" +
        "</div>";

      var offcanvas = document.getElementById("mobileSidebar");
      offcanvas.querySelectorAll("a.nav-link").forEach(function (link) {
        link.addEventListener("click", function () {
          var instance = bootstrap.Offcanvas.getInstance(offcanvas);
          if (instance) instance.hide();
        });
      });
    }
  }

  /* -------------------------------- Topbar -------------------------------- */

  function notificationMarkup() {
    var low = canSee(PAGE_INFO.inventory) ? Store.lowStockProducts().slice(0, 4) : [];
    var pending = canSee(PAGE_INFO.transactions)
      ? Store.data.transactions
          .filter(function (t) {
            return t.status === "Pending";
          })
          .slice(0, 2)
      : [];

    var items = low
      .map(function (product) {
        return (
          '<a class="notif-item" href="' +
          canonicalHref(
            "inventory.php?status=" +
              (Store.stockState(product).key === "out" ? "out" : "low")
          ) +
          '">' +
          '<span class="notif-item__icon"><i class="bi bi-exclamation-triangle"></i></span>' +
          '<span class="notif-item__body">' +
          '<span class="notif-item__title">' +
          escapeHTML(product.name) +
          "</span>" +
          '<span class="notif-item__meta">' +
          (product.qty <= 0
            ? "Out of stock \u00B7 reorder soon"
            : product.qty + " " + product.unit + " left \u00B7 minimum " + product.minStock) +
          "</span>" +
          "</span>" +
          "</a>"
        );
      })
      .concat(
        pending.map(function (txn) {
          return (
            '<a class="notif-item" href="' +
            canonicalHref(
              "transactions.php?q=" + encodeURIComponent(txn.invoice)
            ) +
            '">' +
            '<span class="notif-item__icon notif-item__icon--info"><i class="bi bi-receipt"></i></span>' +
            '<span class="notif-item__body">' +
            '<span class="notif-item__title">Pending sale ' +
            escapeHTML(txn.invoice) +
            "</span>" +
            '<span class="notif-item__meta">' +
            escapeHTML(txn.customer) +
            " \u00B7 " +
            Store.money(txn.total) +
            "</span>" +
            "</span>" +
            "</a>"
          );
        })
      );

    if (!items.length) {
      items = [
        '<div class="px-3 py-4 text-center text-app-muted" style="font-size:.82rem">' +
          '<i class="bi bi-check2-circle d-block mb-2" style="font-size:1.4rem;color:var(--gq-emerald)"></i>' +
          "You are all caught up." +
          "</div>",
      ];
    }

    return (
      '<div class="dropdown">' +
      '<button class="icon-btn icon-btn--plain position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">' +
      '<i class="bi bi-bell"></i>' +
      (low.length
        ? '<span class="badge-dot" data-notif-dot aria-hidden="true"></span>'
        : "") +
      "</button>" +
      '<div class="dropdown-menu dropdown-menu-end notif-menu">' +
      '<div class="notif-menu__head">' +
      '<h6 class="notif-menu__title">Notifications</h6>' +
      '<span class="pill-count">' +
      (low.length + pending.length) +
      " new</span>" +
      "</div>" +
      '<div class="notif-list">' +
      items.join("") +
      "</div>" +
      '<div class="notif-menu__foot">' +
      (canSee(PAGE_INFO.inventory)
        ? '<a class="panel-link" href="' + canonicalHref("inventory.php?status=low") + '">Review low stock <i class="bi bi-arrow-right"></i></a>'
        : "") +
      "</div>" +
      "</div>" +
      "</div>"
    );
  }

  function renderTopbar() {
    var root = document.getElementById("topbarRoot");
    if (!root) return;

    var section = "Overview";
    var groups = visibleGroups();
    for (var g = 0; g < groups.length; g++) {
      if (
        groups[g].items.some(function (item) {
          return item.id === activePage;
        })
      ) {
        section = groups[g].section || "Overview";
        break;
      }
    }

    root.innerHTML =
      '<header class="topbar">' +
      '<button class="icon-btn icon-btn--plain d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open navigation">' +
      '<i class="bi bi-list" style="font-size:1.2rem"></i>' +
      "</button>" +
      '<div class="topbar__title">' +
      '<div class="topbar__crumb"><span>Guinhawa</span><i class="bi bi-chevron-right"></i><span>' +
      escapeHTML(section) +
      "</span></div>" +
      '<div class="topbar__page">' +
      escapeHTML(pageInfo.label) +
      "</div>" +
      "</div>" +
      '<div class="topbar__actions">' +
      '<div class="quick-search d-none d-md-block" id="quickSearchWrap">' +
      '<i class="bi bi-search quick-search__icon"></i>' +
      '<input id="globalSearch" class="quick-search__input" type="search" placeholder="Search products, sales, people..." autocomplete="off" aria-label="Search store data" aria-expanded="false" aria-controls="searchPanel">' +
      '<kbd class="quick-search__kbd">/</kbd>' +
      '<div class="quick-search__panel" id="searchPanel" role="listbox" aria-label="Search results"></div>' +
      "</div>" +
      '<button class="icon-btn" type="button" id="themeToggle" aria-label="Switch to dark mode" title="Switch to dark mode" data-bs-toggle="tooltip">' +
      '<i class="bi bi-moon-stars" aria-hidden="true"></i>' +
      "</button>" +
      notificationMarkup() +
      '<div class="dropdown">' +
      '<button class="user-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">' +
      '<span class="avatar avatar--emerald">' +
      avatarMarkup(user) +
      "</span>" +
      "<span>" +
      '<span class="user-menu__name">' +
      escapeHTML(user.name) +
      "</span>" +
      '<span class="user-menu__role">' +
      escapeHTML(user.role) +
      "</span>" +
      "</span>" +
      '<i class="bi bi-chevron-down chev" aria-hidden="true"></i>' +
      "</button>" +
      '<div class="dropdown-menu dropdown-menu-end" style="min-width:230px">' +
      '<div class="px-2 pt-2 pb-1">' +
      '<div class="fw-650" style="font-size:.84rem">' +
      escapeHTML(user.name) +
      "</div>" +
      '<div class="text-app-muted" style="font-size:.8rem">@' +
      escapeHTML(user.username || "") +
      " &middot; " +
      escapeHTML(user.employeeId || "") +
      "</div>" +
      "</div>" +
      '<div class="dropdown-divider"></div>' +
      (user.role === "Admin"
        ? '<a class="dropdown-item" href="' + canonicalHref("accounts.php") + '"><i class="bi bi-people"></i> User management</a>'
        : "") +
      '<a class="dropdown-item" href="' + Store.roleHome(user.role) + '"><i class="bi bi-speedometer2"></i> My dashboard</a>' +
      '<a class="dropdown-item" href="' + canonicalHref("settings.php") + '"><i class="bi bi-gear"></i> Workspace settings</a>' +
      '<div class="dropdown-divider"></div>' +
      '<a class="dropdown-item text-danger" href="/guinhawa_system/login/index.php" data-signout><i class="bi bi-box-arrow-right"></i> Sign out</a>' +
      "</div>" +
      "</div>" +
      "</div>" +
      "</header>";

    updateThemeButton();
    bindThemeToggle();
    bindQuickSearch();
  }

  function renderFooter() {
    var root = document.getElementById("footerRoot");
    if (!root) return;
    root.innerHTML =
      '<footer class="app-footer">' +
      "<div>&copy; " +
      new Date().getFullYear() +
      ' Guinhawa Poultry Supply <span class="text-app-muted">\u00B7 Admin console</span></div>' +
      '<div class="d-flex align-items-center gap-2">' +
      '<span class="demo-tag"><span class="dot"></span>Live database</span>' +
      "<span>Frontend v1.0</span>" +
      "</div>" +
      "</footer>";
  }

  /* --------------------------------- Theme -------------------------------- */

  function updateThemeButton() {
    var button = document.getElementById("themeToggle");
    if (!button) return;
    var resolved = themeResolved();
    var icon = button.querySelector("i");
    if (icon) {
      icon.className = resolved === "dark" ? "bi bi-sun" : "bi bi-moon-stars";
    }
    button.setAttribute(
      "aria-label",
      resolved === "dark" ? "Switch to light mode" : "Switch to dark mode"
    );
    button.setAttribute("title", button.getAttribute("aria-label"));
  }

  function applyTheme(preference, options) {
    var opts = options || {};
    var theme = window.GuinhawaTheme;
    if (!theme) return;

    var prefs = theme.read();
    prefs.mode =
      preference === "dark" || preference === "light" ? preference : "system";

    if (opts.silent) {
      theme.apply(prefs);
    } else {
      theme.save(prefs);
      if (typeof Store.saveAppearanceRemote === "function") {
        Store.saveAppearanceRemote(theme.read());
      }
    }

    var settings = Store.data.settings;
    settings.theme = prefs.mode;
    if (!opts.silent) {
      Store.save();
    }

    updateThemeButton();
  }

  function bindThemeToggle() {
    var button = document.getElementById("themeToggle");
    if (!button) return;
    button.addEventListener("click", function () {
      var next = themeResolved() === "dark" ? "light" : "dark";
      saveAppearance(Object.assign(readAppearance(), { mode: next }));
    });
  }

  /* ------------------------------ Appearance ------------------------------ */
  /*
   * The full theme engine lives in shared/js/theme-init.js so the saved theme
   * can be applied before first paint. This module is only a thin facade for
   * the rest of the UI.
   */

  var THEME = window.GuinhawaTheme;
  var APPEARANCE_KEY = THEME ? THEME.KEY : "guinhawa-appearance";
  var APPEARANCE_DEFAULTS = THEME ? THEME.DEFAULTS : {
    mode: "light",
    theme: "forest",
    fontSize: "medium",
    density: "comfortable",
    corners: "rounded",
    animations: "subtle",
    sidebar: "expanded",
    cardStyle: "bordered",
    background: "solid",
  };

  function normalizeAppearance(input) {
    return THEME ? THEME.normalize(input) : Object.assign({}, APPEARANCE_DEFAULTS);
  }

  function readAppearance() {
    return THEME ? THEME.read() : Object.assign({}, APPEARANCE_DEFAULTS);
  }

  function sidebarIsCollapsed() {
    return document.documentElement.getAttribute("data-sidebar") === "collapsed";
  }

  function updateSidebarToggle() {
    var collapsed = sidebarIsCollapsed();
    document.querySelectorAll("[data-sidebar-toggle]").forEach(function (button) {
      var label = collapsed ? "Expand navigation" : "Collapse navigation";
      button.setAttribute("aria-pressed", collapsed ? "true" : "false");
      button.setAttribute("aria-label", label);
      button.setAttribute("title", label + " (Ctrl+B)");
      var icon = button.querySelector("i");
      if (icon) {
        icon.className =
          "bi " +
          (collapsed ? "bi-chevron-double-right" : "bi-chevron-double-left");
      }
    });
  }

  function toggleSidebar() {
    saveAppearance(
      Object.assign(readAppearance(), {
        sidebar: sidebarIsCollapsed() ? "expanded" : "collapsed",
      })
    );
  }

  function bindSidebarShortcut() {
    document.addEventListener("keydown", function (event) {
      if (!(event.ctrlKey || event.metaKey) || event.altKey) return;
      if (event.key.toLowerCase() !== "b") return;

      var target = event.target;
      if (
        target &&
        (target.isContentEditable ||
          /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName || ""))
      ) {
        return;
      }

      event.preventDefault();
      toggleSidebar();
    });
  }

  function syncSidebarTitles() {
    var collapsed =
      document.documentElement.getAttribute("data-sidebar") === "collapsed";

    document
      .querySelectorAll(".app-sidebar .sidebar-nav .nav-link")
      .forEach(function (link) {
        if (!collapsed) {
          link.removeAttribute("title");
          return;
        }
        var label = link.querySelector("span");
        if (label) link.setAttribute("title", label.textContent.trim());
      });
  }

  function applyAppearance(input, options) {
    var opts = options || {};
    if (!THEME) return normalizeAppearance(input);

    var prefs = THEME.normalize(input);
    var applied = opts.persist ? THEME.save(prefs) : THEME.apply(prefs);

    syncSidebarTitles();
    updateSidebarToggle();
    updateThemeButton();
    return applied;
  }

  function saveAppearance(input) {
    var applied = applyAppearance(input, { persist: true });
    if (Store && typeof Store.saveAppearanceRemote === "function") {
      Store.saveAppearanceRemote(applied);
    }
    return applied;
  }

  function resetAppearance() {
    var prefs = THEME ? THEME.reset() : normalizeAppearance({});
    if (Store && typeof Store.resetAppearanceRemote === "function") {
      Store.resetAppearanceRemote();
    }
    syncSidebarTitles();
    updateSidebarToggle();
    updateThemeButton();
    return prefs;
  }

  /* ------------------------------ Quick search ---------------------------- */

  function searchIndex() {
    var data = Store.data;
    var rows = [];

    if (canSee(PAGE_INFO.products)) {
      data.products.forEach(function (product) {
        rows.push({
          kind: "Product",
          label: product.name,
          meta: product.sku + " \u00B7 " + product.category,
          href: canonicalHref("products.php?q=" + encodeURIComponent(product.name)),
          icon: "bi-box-seam",
        });
      });
    }

    if (canSee(PAGE_INFO.suppliers)) {
      data.suppliers.forEach(function (supplier) {
        rows.push({
          kind: "Supplier",
          label: supplier.name,
          meta: supplier.contact + " \u00B7 " + supplier.phone,
          href: canonicalHref("suppliers.php?q=" + encodeURIComponent(supplier.name)),
          icon: "bi-truck",
        });
      });
    }

    if (canSee(PAGE_INFO.accounts)) {
      data.accounts.forEach(function (account) {
        rows.push({
          kind: "Account",
          label: account.name,
          meta: account.role + " \u00B7 " + account.email,
          href: canonicalHref("accounts.php?q=" + encodeURIComponent(account.name)),
          icon: "bi-person-badge",
        });
      });
    }

    if (canSee(PAGE_INFO.transactions)) {
      data.transactions.slice(0, 25).forEach(function (txn) {
        rows.push({
          kind: "Sale",
          label: txn.invoice,
          meta: txn.customer + " \u00B7 " + Store.money(txn.total),
          href: canonicalHref("transactions.php?q=" + encodeURIComponent(txn.invoice)),
          icon: "bi-receipt",
        });
      });
    }

    return rows;
  }

  function bindQuickSearch() {
    var wrap = document.getElementById("quickSearchWrap");
    var input = document.getElementById("globalSearch");
    var panel = document.getElementById("searchPanel");
    if (!wrap || !input || !panel) return;

    var index = null;
    var results = [];

    function close() {
      panel.classList.remove("show");
      input.setAttribute("aria-expanded", "false");
    }

    function render(query) {
      if (!index) index = searchIndex();
      var q = query.trim().toLowerCase();

      if (!q) {
        results = [];
        panel.innerHTML =
          '<div class="quick-empty">' +
          '<div class="mb-2" style="font-size:1.2rem;color:var(--gq-emerald)"><i class="bi bi-search"></i></div>' +
          "Search products, suppliers, accounts, and invoices." +
          "</div>";
      } else {
        results = index
          .filter(function (row) {
            return (
              row.label.toLowerCase().indexOf(q) > -1 ||
              row.meta.toLowerCase().indexOf(q) > -1 ||
              row.kind.toLowerCase().indexOf(q) > -1
            );
          })
          .slice(0, 8);

        if (!results.length) {
          panel.innerHTML =
            '<div class="quick-empty">No records match <strong>' +
            escapeHTML(query) +
            "</strong>.</div>";
        } else {
          panel.innerHTML = results
            .map(function (row, i) {
              return (
                '<a class="quick-result" role="option" href="' +
                row.href +
                '" data-result-index="' +
                i +
                '">' +
                '<span class="quick-result__icon"><i class="bi ' +
                row.icon +
                '"></i></span>' +
                "<span>" +
                escapeHTML(row.label) +
                '<span class="quick-result__meta">' +
                escapeHTML(row.kind) +
                " \u00B7 " +
                escapeHTML(row.meta) +
                "</span>" +
                "</span>" +
                "</a>"
              );
            })
            .join("");
        }
      }

      panel.classList.add("show");
      input.setAttribute("aria-expanded", "true");
    }

    input.addEventListener("focus", function () {
      render(input.value);
    });

    input.addEventListener("input", function () {
      render(input.value);
    });

    input.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        close();
        input.blur();
      }
      if (event.key === "Enter" && results.length) {
        event.preventDefault();
        window.location.href = results[0].href;
      }
    });

    document.addEventListener("click", function (event) {
      if (!wrap.contains(event.target)) close();
    });

    document.addEventListener("keydown", function (event) {
      var tag = (event.target.tagName || "").toLowerCase();
      var typing =
        tag === "input" || tag === "textarea" || tag === "select" || event.target.isContentEditable;

      if (event.key === "/" && !typing) {
        event.preventDefault();
        input.focus();
      }

      if (event.key === "Escape") close();
    });
  }

  /* --------------------------------- Toasts -------------------------------- */

  function toast(message, options) {
    var opts = options || {};
    var type = opts.type || "success";
    var icons = {
      success: "bi-check-circle-fill",
      danger: "bi-exclamation-octagon-fill",
      warning: "bi-exclamation-triangle-fill",
      info: "bi-info-circle-fill",
    };
    var container = document.getElementById("toastContainer");
    if (!container) {
      container = document.createElement("div");
      container.id = "toastContainer";
      container.className = "toast-container";
      document.body.appendChild(container);
    }

    var id = "gqToast" + ++toastCounter;
    var wrapper = document.createElement("div");
    wrapper.innerHTML =
      '<div class="toast toast-app toast-app--' +
      type +
      '" id="' +
      id +
      '" role="status" aria-live="polite" aria-atomic="true">' +
      '<div class="d-flex align-items-start">' +
      '<div class="toast-body">' +
      '<i class="bi ' +
      (icons[type] || icons.success) +
      ' toast-icon" aria-hidden="true"></i>' +
      '<div style="flex:1">' +
      (opts.title
        ? '<div class="fw-650 mb-1">' + escapeHTML(opts.title) + "</div>"
        : "") +
      "<div>" +
      escapeHTML(message) +
      "</div>" +
      "</div>" +
      "</div>" +
      '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close notification"></button>' +
      "</div>" +
      "</div>";

    var element = wrapper.firstChild;
    container.appendChild(element);

    var instance = bootstrap.Toast.getOrCreateInstance(element, {
      delay: opts.delay || 3800,
      autohide: true,
    });

    element.addEventListener("hidden.bs.toast", function () {
      element.remove();
    });

    instance.show();
    return element;
  }

  /* -------------------------------- Confirm -------------------------------- */

  var confirmResolver = null;

  function ensureConfirmModal() {
    if (document.getElementById("gqConfirmModal")) return;
    var host = document.createElement("div");
    host.innerHTML =
      '<div class="modal fade" id="gqConfirmModal" tabindex="-1" aria-labelledby="gqConfirmTitle" aria-hidden="true">' +
      '<div class="modal-dialog modal-dialog-centered modal-sm">' +
      '<div class="modal-content">' +
      '<div class="modal-body text-center p-4">' +
      '<div class="modal-icon modal-icon--danger mx-auto mb-3" id="gqConfirmIcon"><i class="bi bi-exclamation-triangle"></i></div>' +
      '<h5 class="modal-title mb-2" id="gqConfirmTitle">Confirm action</h5>' +
      '<p class="text-app-muted mb-0" id="gqConfirmText" style="font-size:.82rem;line-height:1.55">Are you sure?</p>' +
      "</div>" +
      '<div class="modal-footer justify-content-center border-0 pt-0 pb-4">' +
      '<button type="button" class="btn btn-outline-app" data-bs-dismiss="modal" id="gqConfirmCancel">Cancel</button>' +
      '<button type="button" class="btn btn-danger-soft" id="gqConfirmAccept">Confirm</button>' +
      "</div>" +
      "</div>" +
      "</div>" +
      "</div>";
    document.body.appendChild(host.firstChild);

    var modalEl = document.getElementById("gqConfirmModal");
    var accepted = false;

    document.getElementById("gqConfirmAccept").addEventListener("click", function () {
      accepted = true;
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    });

    modalEl.addEventListener("hidden.bs.modal", function () {
      var resolver = confirmResolver;
      var wasAccepted = accepted;
      confirmResolver = null;
      accepted = false;

      // If this dialog was stacked over another open modal (for example the
      // sale receipt), Bootstrap drops the scroll lock when it hides. Restore
      // it and focus the surviving modal so Escape continues to work.
      var visible = document.querySelector(".modal.show");
      if (visible) {
        document.body.classList.add("modal-open");
        if (typeof visible.focus === "function") visible.focus();
      }

      if (resolver) resolver(wasAccepted);
    });
  }

  function confirmDialog(options) {
    ensureConfirmModal();
    var opts = options || {};
    var modalEl = document.getElementById("gqConfirmModal");
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.getElementById("gqConfirmTitle").textContent =
      opts.title || "Confirm action";
    document.getElementById("gqConfirmText").textContent =
      opts.message || "Are you sure you want to continue?";
    var accept = document.getElementById("gqConfirmAccept");
    accept.textContent = opts.confirmLabel || "Confirm";
    accept.className =
      "btn " + (opts.variant === "brand" ? "btn-brand" : "btn-danger-soft");
    var icon = document.getElementById("gqConfirmIcon");
    icon.className =
      "modal-icon mx-auto mb-3 " +
      (opts.variant === "brand" ? "" : "modal-icon--danger");
    icon.innerHTML =
      '<i class="bi ' + (opts.icon || "bi-exclamation-triangle") + '"></i>';

    modal.show();
    return new Promise(function (resolve) {
      confirmResolver = resolve;
    });
  }

  /* ------------------------------ CSV export ------------------------------ */

  function exportCSV(filename, headers, rows) {
    function cell(value) {
      return '"' + String(value == null ? "" : value).replace(/"/g, '""') + '"';
    }

    var csv =
      "\uFEFF" +
      [headers.map(cell).join(",")]
        .concat(
          rows.map(function (row) {
            return row.map(cell).join(",");
          })
        )
        .join("\r\n");

    var blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    var url = URL.createObjectURL(blob);
    var link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(function () {
      URL.revokeObjectURL(url);
    }, 1200);
  }

  /* ------------------------------ Table sorting --------------------------- */

  function sortValue(cell) {
    if (!cell) return "";
    if (cell.dataset.sortValue != null) return cell.dataset.sortValue;
    return cell.textContent.trim();
  }

  function sortTable(table, header) {
    var tbody = table.tBodies[0];
    if (!tbody) return;
    var rows = Array.prototype.slice
      .call(tbody.rows)
      .filter(function (row) {
        return !row.classList.contains("table-empty-row");
      });
    if (rows.length < 2) return;

    var direction =
      header.getAttribute("aria-sort") === "ascending" ? "desc" : "asc";
    var index = header.cellIndex;

    table.querySelectorAll("th").forEach(function (th) {
      th.removeAttribute("aria-sort");
    });
    header.setAttribute(
      "aria-sort",
      direction === "asc" ? "ascending" : "descending"
    );

    var icon = header.querySelector(".sort-icon");
    if (icon) {
      icon.className =
        "bi " +
        (direction === "asc" ? "bi-sort-up" : "bi-sort-down") +
        " sort-icon";
    }

    rows.sort(function (a, b) {
      var aRaw = sortValue(a.cells[index]);
      var bRaw = sortValue(b.cells[index]);

      var aNum = Number(String(aRaw).replace(/[\u20B1,\s]/g, ""));
      var bNum = Number(String(bRaw).replace(/[\u20B1,\s]/g, ""));
      var bothNumeric =
        aRaw !== "" &&
        bRaw !== "" &&
        !isNaN(aNum) &&
        !isNaN(bNum) &&
        /^[\u20B1\s\d,.+\-\u2212]+$/.test(aRaw) &&
        /^[\u20B1\s\d,.+\-\u2212]+$/.test(bRaw);

      var result = bothNumeric
        ? aNum - bNum
        : String(aRaw).localeCompare(String(bRaw), "en", {
            numeric: true,
            sensitivity: "base",
          });

      return direction === "asc" ? result : -result;
    });

    rows.forEach(function (row) {
      tbody.appendChild(row);
    });
  }

  function initSortableTables() {
    document.querySelectorAll("table.table-app").forEach(function (table) {
      table.querySelectorAll("th.sortable").forEach(function (header) {
        if (header.dataset.sortBound) return;
        header.dataset.sortBound = "1";
        header.setAttribute("tabindex", "0");
        header.setAttribute("role", "button");
        header.setAttribute("aria-label", "Sort by " + header.textContent.trim());
        header.addEventListener("click", function () {
          sortTable(table, header);
        });
        header.addEventListener("keydown", function (event) {
          if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            sortTable(table, header);
          }
        });
      });
    });
  }

  /* --------------------------------- Misc --------------------------------- */

  function refreshBadges() {
    var count = lowStockCount();
    document.querySelectorAll("[data-low-stock-badge]").forEach(function (el) {
      el.textContent = count;
      el.classList.toggle("d-none", count === 0);
    });
    var dot = document.querySelector("[data-notif-dot]");
    if (dot) dot.classList.toggle("d-none", count === 0);
  }

  function statusBadge(label, variant) {
    return (
      '<span class="badge-status badge-status--' +
      (variant || "neutral") +
      '">' +
      escapeHTML(label) +
      "</span>"
    );
  }

  function emptyRow(colspan, icon, title, text) {
    return (
      '<tr class="table-empty-row"><td colspan="' +
      colspan +
      '" class="table-empty">' +
      '<div class="empty-state">' +
      '<div class="empty-state__icon"><i class="bi ' +
      (icon || "bi-inbox") +
      '"></i></div>' +
      "<h6>" +
      escapeHTML(title || "Nothing here yet") +
      "</h6>" +
      "<p>" +
      escapeHTML(text || "Try adjusting your search or filters.") +
      "</p>" +
      "</div>" +
      "</td></tr>"
    );
  }

  function debounce(fn, wait) {
    var timer = null;
    return function () {
      var args = arguments;
      var context = this;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        fn.apply(context, args);
      }, wait || 180);
    };
  }

  function roleBadge(role) {
    var map = {
      Admin: "admin",
      "Inventory Staff": "staff",
      Cashier: "cashier",
    };
    return (
      '<span class="badge-role badge-role--' +
      (map[role] || "staff") +
      '">' +
      escapeHTML(role) +
      "</span>"
    );
  }

  function avatarClass(role) {
    return role === "Admin"
      ? "avatar--emerald"
      : role === "Cashier"
        ? "avatar--amber"
        : "avatar--blue";
  }

  /* ------------------------------ Loading / UX ---------------------------- */

  function injectLoader() {
    if (document.getElementById("appLoader")) return;
    var loader = document.createElement("div");
    loader.id = "appLoader";
    loader.className = "app-loader";
    loader.innerHTML =
      '<div class="app-loader__inner">' +
      '<img src="/guinhawa_system/assets/images/guinhawa-logo.png" alt="" width="56" height="56">' +
      '<span class="spinner-border" role="status" aria-hidden="true"></span>' +
      '<span class="app-loader__text">Loading workspace&hellip;</span>' +
      "</div>";
    document.body.appendChild(loader);
  }

  function hideLoader() {
    var loader = document.getElementById("appLoader");
    if (!loader) return;
    window.setTimeout(function () {
      loader.classList.add("is-hidden");
      window.setTimeout(function () {
        if (loader.parentNode) loader.remove();
      }, 320);
    }, 180);
  }

  function setBusy(button, busy, label) {
    if (!button) return;
    if (busy) {
      if (!button.getAttribute("data-original-html")) {
        button.setAttribute("data-original-html", button.innerHTML);
      }
      button.disabled = true;
      button.setAttribute("aria-busy", "true");
      button.classList.add("is-busy");
      button.innerHTML =
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' +
        escapeHTML(label || "Working\u2026");
    } else {
      button.disabled = false;
      button.removeAttribute("aria-busy");
      button.classList.remove("is-busy");
      var original = button.getAttribute("data-original-html");
      if (original) {
        button.innerHTML = original;
        button.removeAttribute("data-original-html");
      }
    }
  }

  function modalBusy(modalEl, busy, message) {
    if (!modalEl) return;
    var content = modalEl.querySelector(".modal-content");
    if (!content) return;
    var overlay = content.querySelector("[data-modal-loading]");
    if (busy) {
      if (!overlay) {
        overlay = document.createElement("div");
        overlay.className = "modal-loading";
        overlay.setAttribute("data-modal-loading", "");
        overlay.innerHTML =
          '<div class="modal-loading__inner">' +
          '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>' +
          "<span></span>" +
          "</div>";
        content.appendChild(overlay);
      }
      var text = overlay.querySelector("span:last-child");
      if (text) text.textContent = message || "Working\u2026";
      overlay.classList.add("is-active");
    } else if (overlay) {
      overlay.classList.remove("is-active");
      window.setTimeout(function () {
        if (overlay.parentNode) overlay.remove();
      }, 180);
    }
  }

  function initTooltips(root) {
    if (!window.bootstrap || !bootstrap.Tooltip) return;
    (root || document)
      .querySelectorAll('[data-bs-toggle="tooltip"], #themeToggle[title]')
      .forEach(function (element) {
        if (!bootstrap.Tooltip.getInstance(element)) {
          new bootstrap.Tooltip(element);
        }
      });
  }

  function bindSignout() {
    document.addEventListener("click", function (event) {
      var link = event.target.closest("[data-signout]");
      if (link) Store.logout();
    });
  }

  /* -------------------------------- Init ---------------------------------- */

  function bindAreaLinks() {
    document.addEventListener("click", function (event) {
      if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
      ) {
        return;
      }
      var link = event.target.closest ? event.target.closest("a[href]") : null;
      if (!link || link.hasAttribute("download")) return;
      if (link.target && link.target !== "_self") return;
      var href = link.getAttribute("href");
      var next = canonicalHref(href);
      if (next && next !== href) {
        event.preventDefault();
        window.location.href = next;
      }
    });
  }

  function init() {
    if (!guardRoute()) return;

    if (user.role !== "Admin") {
      document.querySelectorAll("[data-admin-only]").forEach(function (el) {
        el.classList.add("d-none");
      });
    }

    bindAreaLinks();
    bindSignout();
    renderSidebar();
    syncSidebarTitles();
    bindSidebarShortcut();
    renderTopbar();
    renderFooter();
    initTooltips();

    // The theme engine follows the device setting on its own and dispatches
    // "guinhawa:themechange", so charts stay in sync without extra wiring.

    initSortableTables();

    if (!Store.storageAvailable()) {
      window.setTimeout(function () {
        toast(
          "Browser storage is unavailable. Changes will only last for this session.",
          { type: "warning", title: "Storage warning", delay: 6000 }
        );
      }, 700);
    }

    hideLoader();
  }

  window.GuinhawaUI = {
    nav: NAV,
    pageId: activePage,
    page: pageInfo,
    get user() {
      return user;
    },
    get role() {
      return user.role;
    },
    canSee: canSee,
    areaHref: canonicalHref,
    roleHome: Store.roleHome,
    logout: Store.logout,
    initials: initials,
    renderAvatar: renderAvatar,
    param: param,
    escape: escapeHTML,
    toast: toast,
    confirm: confirmDialog,
    exportCSV: exportCSV,
    initSortableTables: initSortableTables,
    sortTable: sortTable,
    refreshBadges: refreshBadges,
    statusBadge: statusBadge,
    emptyRow: emptyRow,
    roleBadge: roleBadge,
    avatarClass: avatarClass,
    debounce: debounce,
    busy: setBusy,
    modalBusy: modalBusy,
    initTooltips: initTooltips,
    applyTheme: applyTheme,
    theme: themeResolved,
    appearance: {
      key: APPEARANCE_KEY,
      defaults: APPEARANCE_DEFAULTS,
      read: readAppearance,
      apply: function (prefs) {
        return applyAppearance(prefs, { persist: false });
      },
      save: saveAppearance,
      reset: resetAppearance,
      engine: THEME,
    },
    storage: Store,
    charts: window.GuinhawaCharts,
  };

  injectLoader();

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
