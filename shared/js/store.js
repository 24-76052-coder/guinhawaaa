/*
 * Guinhawa Poultry Supply - data layer for the plain HTML dashboard.
 *
 * The screens read `Store.data` synchronously, so this adapter keeps the same
 * synchronous shape the demonstration used. The difference is where the data
 * comes from: every page loads a snapshot from the PHP/MySQL API and every
 * write goes back to the same API. Nothing is stored in localStorage.
 */
(function (window) {
  "use strict";

  var STORAGE_KEY = "guinhawa_admin_db_v1"; // removed once; legacy demo data
  var SESSION_KEY = "guinhawa_session"; // legacy demo session key
  var TOKEN_KEY = "guinhawa_csrf";
  var SCHEMA_VERSION = 3;
  var API_BASE = "/guinhawa_system/api";
  var LOGIN_URL = "/guinhawa_system/login/index.php";
  var CHANGE_PASSWORD_URL = "/guinhawa_system/login/change-password.php";

  var ROLE_HOME = {
    Admin: "/guinhawa_system/admin/index.php",
    "Inventory Staff": "/guinhawa_system/inventory/index.php",
    Cashier: "/guinhawa_system/cashier/index.php",
  };

  // Every employee follows the same fixed working schedule.
  var STANDARD_WORK_SCHEDULE = "8:00 AM \u2013 5:00 PM";

  var DEMO_IMAGES = [
    "chick.svg", "drinker.svg", "egg-tray.svg", "feeder.svg", "feed-sack.svg",
    "generic.svg", "hen.svg", "rice-hull.svg", "vitamins.svg",
  ];

  var UNIT_FROM_API = {
    kilograms: "kg", kilogram: "kg", kg: "kg",
    sacks: "sack", sack: "sack",
    bags: "bag", bag: "bag",
    pieces: "piece", piece: "piece", pcs: "piece",
    trays: "tray", tray: "tray",
    heads: "head", head: "head",
    bottles: "bottle", bottle: "bottle",
    packs: "pack", pack: "pack",
    boxes: "box", box: "box",
    cans: "can", can: "can",
    pouches: "pouch", pouch: "pouch",
    tablets: "tablet", tablet: "tablet",
    units: "unit", unit: "unit",
  };

  var UNIT_TO_API = {
    kg: "Kilograms", sack: "Sacks", bag: "Bags", piece: "Pieces",
    tray: "Trays", head: "Heads", bottle: "Bottles", pack: "Packs",
    box: "Boxes", can: "Cans", pouch: "Pouches", tablet: "Tablets",
    unit: "Units",
  };

  var OUT_REASON = {
    "Damaged goods": "Damaged",
    "Expired stock": "Expired",
    "Internal use": "Usage",
    "Stock correction": "Usage",
    "Transfer to branch": "Transfer",
    Other: "Usage",
    "Customer Order": "Customer Order",
    Damaged: "Damaged",
    Expired: "Expired",
    Usage: "Usage",
    Transfer: "Transfer",
  };

  var data = emptyData();
  var currentUser = null;
  var booted = false;
  var adminAccounts = null;

  /* ------------------------------- Utilities ------------------------------ */

  function pad(n) {
    return String(n).padStart(2, "0");
  }

  function dateISO(date) {
    return (
      date.getFullYear() +
      "-" +
      pad(date.getMonth() + 1) +
      "-" +
      pad(date.getDate())
    );
  }

  function todayISO() {
    return dateISO(new Date());
  }

  function addDaysISO(offset) {
    var d = new Date();
    d.setDate(d.getDate() + Number(offset || 0));
    return dateISO(d);
  }

  function nowISO() {
    var d = new Date();
    return (
      dateISO(d) +
      "T" +
      pad(d.getHours()) +
      ":" +
      pad(d.getMinutes()) +
      ":" +
      pad(d.getSeconds())
    );
  }

  function uid(prefix) {
    return (
      (prefix || "id") +
      "_" +
      Date.now().toString(36) +
      Math.random().toString(36).slice(2, 6)
    );
  }

  function escapeHTML(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (ch) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch];
    });
  }

  function toISO(value, withTime) {
    if (!value) return "";
    var text = String(value).trim();
    if (!text) return "";

    // Already ISO-like: keep the original local date/time.
    var isoMatch = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
    if (isoMatch) {
      var datePart = isoMatch[1] + "-" + isoMatch[2] + "-" + isoMatch[3];
      if (!withTime) return datePart;
      return (
        datePart +
        "T" +
        (isoMatch[4] || "00") +
        ":" +
        (isoMatch[5] || "00") +
        ":" +
        (isoMatch[6] || "00")
      );
    }

    var parsed = new Date(text);
    if (isNaN(parsed.getTime())) return text;
    if (!withTime) return dateISO(parsed);
    return (
      dateISO(parsed) +
      "T" +
      pad(parsed.getHours()) +
      ":" +
      pad(parsed.getMinutes()) +
      ":" +
      pad(parsed.getSeconds())
    );
  }

  function api(method, path, body) {
    var xhr = new XMLHttpRequest();
    xhr.open(method, API_BASE + path, false);
    xhr.withCredentials = true;
    xhr.setRequestHeader("Accept", "application/json");

    var token = getToken();
    if (token) xhr.setRequestHeader("X-CSRF-Token", token);
    if (body !== undefined && body !== null) {
      xhr.setRequestHeader("Content-Type", "application/json");
    }

    try {
      xhr.send(body === undefined || body === null ? null : JSON.stringify(body));
    } catch (error) {
      return { ok: false, status: 0, data: { error: "Cannot reach the server. Check that Apache and MySQL are running." } };
    }

    var payload = null;
    try {
      payload = xhr.responseText ? JSON.parse(xhr.responseText) : null;
    } catch (error) {
      payload = null;
    }

    return {
      ok: xhr.status >= 200 && xhr.status < 300,
      status: xhr.status,
      data: payload || {},
      message: payload && payload.error ? payload.error : "The server could not complete that request.",
    };
  }

  function getToken() {
    try {
      return window.sessionStorage.getItem(TOKEN_KEY) || window.localStorage.getItem(TOKEN_KEY);
    } catch (error) {
      return null;
    }
  }

  function setToken(token, remember) {
    if (!token) return;
    try {
      window.sessionStorage.removeItem(TOKEN_KEY);
      window.localStorage.removeItem(TOKEN_KEY);
      window[remember ? "localStorage" : "sessionStorage"].setItem(TOKEN_KEY, token);
    } catch (error) {
      /* token memory is optional */
    }
  }

  function clearToken() {
    try {
      window.sessionStorage.removeItem(TOKEN_KEY);
      window.localStorage.removeItem(TOKEN_KEY);
    } catch (error) {
      /* ignore */
    }
  }

  function isLoginPage() {
    return window.location.pathname.indexOf("/login/") !== -1;
  }

  function sessionExpired() {
    clearToken();
    if (isLoginPage()) return;
    var page = window.location.pathname.replace(/^\/guinhawa_system\//, "") + window.location.search;
    window.location.replace(
      LOGIN_URL + "?expired=1&redirect=" + encodeURIComponent(page)
    );
  }

  function handleUnauthorized(result) {
    if (result && result.status === 401) {
      sessionExpired();
      return true;
    }
    return false;
  }

  /* -------------------------------- Mapping ------------------------------- */

  function emptyData() {
    return {
      meta: { demo: false, version: SCHEMA_VERSION, seededOn: todayISO(), dirty: false, updatedAt: nowISO() },
      settings: {
        businessName: "Guinhawa Poultry Supply",
        owner: "",
        email: "",
        phone: "",
        address: "",
        currency: "\u20B1",
        lowStockThreshold: 20,
        receiptFooter: "",
        theme: "light",
        notifications: { lowStock: true, dailySales: true, newOrders: true, attendance: false },
      },
      accounts: [],
      products: [],
      suppliers: [],
      transactions: [],
      attendance: [],
      history: [],
    };
  }

  function demoUnit(unit) {
    var key = String(unit || "").trim().toLowerCase();
    return UNIT_FROM_API[key] || key || "unit";
  }

  function apiUnit(unit) {
    var key = String(unit || "").trim().toLowerCase();
    return UNIT_TO_API[key] || "Units";
  }

  function demoRole(user) {
    if (!user) return "Cashier";
    if (user.role === "admin") return "Admin";
    if (user.role === "inventory-staff") return "Inventory Staff";
    return "Cashier";
  }

  function accountStatus(value) {
    if (value === "pending") return "Pending";
    if (value === "inactive") return "Inactive";
    return "Active";
  }

  function mapUser(user) {
    if (!user) return null;
    return {
      id: user.type === "admin" ? "acc-admin" : "acc" + user.id,
      dbId: Number(user.id) || 0,
      type: user.type || "employee",
      name: user.name || user.fullName || "",
      fullName: user.fullName || user.name || "",
      username: user.username || "",
      email: user.email || "",
      emailVerified: !!user.emailVerified,
      phone: user.contactNumber || "",
      employeeId: user.code || user.employeeId || "",
      staffId: user.staffId || user.code || user.employeeId || "",
      role: demoRole(user),
      dbRole: user.role || "",
      roleLabel: user.roleLabel || "",
      status: accountStatus(user.status),
      accountStatus: user.accountStatus || user.status || "active",
      workShift: STANDARD_WORK_SCHEDULE,
      shift: STANDARD_WORK_SCHEDULE,
      lastActive: user.lastLoginAt || null,
      lastPasswordChange: user.lastPasswordChange || null,
      createdAt: user.createdAt || null,
      avatar: user.avatar || "",
      mustChangePassword: !!user.mustChangePassword,
    };
  }

  function mapAccount(employee) {
    return {
      id: "acc" + employee.id,
      dbId: Number(employee.id) || 0,
      name: employee.name || employee.fullName || "",
      fullName: employee.fullName || employee.name || "",
      employeeId: employee.employeeId || employee.code || "",
      staffId: employee.staffId || employee.employeeId || employee.code || "",
      username: employee.username || "",
      email: employee.email || "",
      emailVerified: !!employee.emailVerified,
      phone: employee.contactNumber || "",
      role: employee.role === "inventory-staff" ? "Inventory Staff" : demoRole(employee),
      dbRole: employee.dbRole || employee.role || "cashier",
      status: accountStatus(employee.status),
      accountStatus: employee.accountStatus || employee.status || "active",
      workShift: STANDARD_WORK_SCHEDULE,
      shift: STANDARD_WORK_SCHEDULE,
      joined: toISO(employee.dateJoined || employee.createdAt),
      dateJoined: toISO(employee.dateJoined || employee.createdAt),
      createdAt: employee.createdAt ? toISO(employee.createdAt, true) : null,
      lastActive: employee.lastLoginAt ? toISO(employee.lastLoginAt, true) : null,
      lastPasswordChange: employee.lastPasswordChange ? toISO(employee.lastPasswordChange, true) : null,
      mustChangePassword: !!employee.mustChangePassword,
      activationSentAt: employee.activationSentAt ? toISO(employee.activationSentAt, true) : null,
      activationExpiresAt: employee.activationExpiresAt ? toISO(employee.activationExpiresAt, true) : null,
      deletedAt: employee.deletedAt ? toISO(employee.deletedAt, true) : null,
      deletedBy: employee.deletedBy || "",
      archived: !!employee.deletedAt,
      password: "",
    };
  }

  function mapSupplier(supplier) {
    return {
      id: supplier.id,
      name: supplier.name,
      contact: supplier.contact || "",
      phone: supplier.contact || "",
      email: supplier.email || "",
      address: supplier.address || "",
      categories: [],
      terms: "",
      status: "Active",
      since: "",
    };
  }

  function supplierName(id) {
    var match = data.suppliers.find(function (supplier) {
      return supplier.id === id;
    });
    return match ? match.name : "";
  }

  function supplierId(name) {
    if (!name) return "";
    var match = data.suppliers.find(function (supplier) {
      return String(supplier.name).toLowerCase() === String(name).toLowerCase();
    });
    return match ? match.id : "";
  }

  function flattenProducts(list, archivedOnly) {
    var out = [];
    (list || []).forEach(function (product) {
      var variants = product.variants || [];
      if (!variants.length) return;
      var multi = variants.length > 1;
      variants.forEach(function (variant) {
        var unit = demoUnit(variant.unit);
        out.push({
          id: "p" + product.id + ":" + variant.key,
          productId: Number(product.id),
          variant: variant.key,
          variants: variants,
          sku: product.sku,
          barcode: product.barcode || "",
          name:
            multi && variant.key !== "A"
              ? product.name + " \u00B7 " + variant.label
              : product.name,
          baseName: product.name,
          category: product.category,
          subcategory: product.subcategory || "",
          brand: product.brand || "",
          description: product.description || "",
          unit: unit,
          apiUnit: variant.unit,
          qty: Number(variant.stock) || 0,
          minStock: Number(product.minimum) || parseInt(data.settings.lowStockThreshold, 10) || 0,
          cost: Number(product.cost) || 0,
          price: Number(variant.price) || 0,
          expiry: toISO(product.expiration_date),
          sellByWeight: /kilo|kg/i.test(String(variant.unit)),
          supplierId: supplierId(product.supplier),
          supplierName: product.supplier || "",
          image: product.image || "",
          status: archivedOnly || product.archived ? "Archived" : "Active",
          archived: !!(archivedOnly || product.archived),
        });
      });
    });
    return out;
  }

  function mapSale(sale) {
    return {
      id: "s" + sale.id,
      saleId: Number(sale.id),
      invoice: sale.receipt_no,
      date: toISO(sale.created_at, true) || toISO(sale.date, true),
      customer: sale.customer || "Walk-in Customer",
      orderType: sale.order_type || "Walk-in",
      cashierId: "",
      cashier: sale.cashier || "",
      staffId: sale.staff_id || "",
      items: (sale.items || []).map(function (item) {
        return {
          productId:
            item.product_id === null || item.product_id === undefined
              ? ""
              : "p" + item.product_id + ":" + (item.variant || "A"),
          name: item.product,
          unit: demoUnit(item.unit),
          qty: Number(item.quantity) || 0,
          price: Number(item.price) || 0,
        };
      }),
      itemCount: (sale.items || []).reduce(function (sum, item) {
        return sum + (Number(item.quantity) || 0);
      }, 0),
      subtotal: Number(sale.subtotal) || 0,
      discount: Number(sale.discount) || 0,
      total: Number(sale.total) || 0,
      payment: sale.payment_method || "Cash",
      status: sale.status === "Paid" ? "Completed" : sale.status || "Completed",
      notes: "",
    };
  }

  function mapStockMove(move) {
    var type = String(move.action || "").toLowerCase() === "stock in" ? "in" : "out";
    return {
      id: "h" + move.id,
      date: toISO(String(move.date || "") + " " + String(move.time || "00:00"), true),
      productId:
        move.product_id === null || move.product_id === undefined
          ? ""
          : "p" + move.product_id + ":" + (move.variant || "A"),
      productName: move.product,
      sku: skuFor(move.product_id, move.variant),
      type: type,
      qty: Number(move.quantity) || 0,
      unit: demoUnit(move.unit),
      before: Number(move.previous) || 0,
      after: Number(move.next) || 0,
      ref: move.reference || "",
      reason: move.action || "",
      notes: move.remarks || "",
      user: move.user || "",
    };
  }

  function skuFor(productId, variantKey) {
    var found = data.products.find(function (product) {
      return product.productId === Number(productId) && product.variant === variantKey;
    });
    return found ? found.sku : "";
  }

  function mapAttendanceRow(row) {
    var account = data.accounts.find(function (item) {
      return item.employeeId === row.code;
    });
    return {
      id: "att" + row.id,
      accountId: account ? account.id : "acc-code-" + row.code,
      name: row.name,
      role: account ? account.role : "",
      date: toISO(row.date),
      timeIn: row.in || null,
      timeOut: row.out || null,
      hours: Number(row.hours) || 0,
      status:
        row.status || (row.in ? "Present" : "Not recorded"),
      note: row.note || "",
    };
  }

  function mapSettings(settings) {
    if (!settings) return emptyData().settings;
    return {
      businessName: settings.store_name || "Guinhawa Poultry Supply",
      owner: settings.owner || "",
      email: settings.email || "",
      phone: settings.phone || "",
      address: settings.address || "",
      currency: settings.currency === "USD" ? "$" : "\u20B1",
      lowStockThreshold: Number(settings.low_stock_threshold) || 20,
      receiptFooter: settings.receipt_footer || settings.description || "",
      gcashQr: settings.gcash_qr || "",
      theme: readTheme(),
      notifications: {
        lowStock: settings.inventory_alerts !== false,
        dailySales: settings.email_notifications !== false,
        newOrders: settings.order_notifications !== false,
        attendance: false,
      },
    };
  }

  function readTheme() {
    try {
      return window.localStorage.getItem("guinhawa-theme") || "light";
    } catch (error) {
      return "light";
    }
  }

  function applyState(state, employees) {
    if (!state) return;
    data.settings = mapSettings(state.settings);
    data.suppliers = (state.suppliers || []).map(mapSupplier);
    data.products = flattenProducts(state.products, false).concat(
      flattenProducts(state.archivedProducts, true)
    );

    data.accounts = [];
    if (currentUser) data.accounts.push(currentUser);
    if (employees) {
      employees.forEach(function (employee) {
        if (currentUser && currentUser.id === "acc" + employee.id) return;
        data.accounts.push(mapAccount(employee));
      });
      adminAccounts = employees;
    } else if (currentUser && currentUser.role !== "Admin") {
      // Staff pages only need their own account.
      adminAccounts = null;
    }

    data.transactions = (state.sales || []).map(mapSale);
    data.history = (state.stockMoves || []).map(mapStockMove);
    data.attendance = (state.attendance || []).map(mapAttendanceRow);
    data.meta = {
      demo: false,
      version: SCHEMA_VERSION,
      seededOn: todayISO(),
      dirty: false,
      updatedAt: nowISO(),
    };
  }

  function refreshFromResponse(result) {
    if (!result.ok) {
      handleUnauthorized(result);
      return false;
    }
    var state = result.data && result.data.state ? result.data.state : null;
    if (state) applyState(state, adminAccounts);
    return true;
  }

  function reload() {
    var result = api("GET", "/state");
    if (!result.ok) {
      handleUnauthorized(result);
      return false;
    }

    // Re-fetch the staff directory so create, delete, restore and status
    // changes are reflected immediately instead of reusing a stale cache.
    if (currentUser && currentUser.role === "Admin") {
      var directory = api("GET", "/admin/employees");
      if (directory.ok && directory.data && Array.isArray(directory.data.employees)) {
        adminAccounts = directory.data.employees;
      }
    }

    applyState(result.data.state, adminAccounts);
    return true;
  }

  /* -------------------------------- Boot ---------------------------------- */

  function purgeDemoStorage() {
    try {
      window.localStorage.removeItem(STORAGE_KEY);
      window.sessionStorage.removeItem(SESSION_KEY);
      window.localStorage.removeItem(SESSION_KEY);
    } catch (error) {
      /* ignore */
    }
  }

  function boot() {
    if (booted) return;
    booted = true;

    purgeDemoStorage();

    if (isLoginPage()) {
      return;
    }

    var me = api("GET", "/auth/me");
    if (!me.ok) {
      handleUnauthorized(me);
      return;
    }

    setToken(me.data.csrfToken, false);
    currentUser = mapUser(me.data.user);

    if (currentUser && currentUser.mustChangePassword) {
      window.location.replace(CHANGE_PASSWORD_URL + "?required=1");
      return;
    }

    syncAppearance();

    var employees = null;
    if (currentUser && currentUser.role === "Admin") {
      var directory = api("GET", "/admin/employees");
      if (directory.ok && directory.data && Array.isArray(directory.data.employees)) {
        employees = directory.data.employees;
      }
    }

    var state = api("GET", "/state");
    if (!state.ok) {
      handleUnauthorized(state);
      return;
    }

    applyState(state.data.state, employees);
    bindSignOut();
  }

  function bindSignOut() {
    document.addEventListener("click", function (event) {
      var link = event.target.closest ? event.target.closest("[data-signout]") : null;
      if (!link) return;
      event.preventDefault();
      logout();
    });
  }

  /* -------------------------------- Formatting ---------------------------- */

  function money(value, withSymbol) {
    var symbol = withSymbol === false ? "" : data.settings.currency || "\u20B1";
    var number = Number(value) || 0;
    return (
      symbol +
      number.toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }

  function number(value) {
    return (Number(value) || 0).toLocaleString("en-PH");
  }

  function unitLabel(unit, quantity) {
    var label = String(unit || "");
    if (!label) return "unit";
    if (Math.abs(Number(quantity) || 0) === 1) return label;
    if (label === "kg" || label === "pc") return label;
    return label + "s";
  }

  function formatDate(value) {
    if (!value) return "\u2014";
    var d = new Date(String(value).slice(0, 10) + "T00:00:00");
    if (isNaN(d.getTime())) return value;
    return d.toLocaleDateString("en-PH", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  }

  function formatDateTime(value) {
    if (!value) return "\u2014";
    var d = new Date(value);
    if (isNaN(d.getTime())) return value;
    return (
      d.toLocaleDateString("en-PH", {
        month: "short",
        day: "numeric",
        year: "numeric",
      }) +
      " \u00B7 " +
      d.toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" })
    );
  }

  function formatTime(value) {
    if (!value) return "\u2014";
    var parts = String(value).split(":");
    var hours = Number(parts[0]);
    var minutes = parts[1] || "00";
    if (isNaN(hours)) return value;
    var suffix = hours >= 12 ? "PM" : "AM";
    var display = hours % 12 || 12;
    return display + ":" + minutes + " " + suffix;
  }

  function relativeDay(dateStr) {
    if (!dateStr) return "";
    var date = String(dateStr).slice(0, 10);
    if (date === todayISO()) return "Today";
    if (date === addDaysISO(-1)) return "Yesterday";
    return formatDate(date);
  }

  function roundQty(value) {
    return Math.round((Number(value) || 0) * 1000) / 1000;
  }

  /* ------------------------------- Sessions ------------------------------- */

  function getSession() {
    return currentUser;
  }

  function getCurrentUser() {
    return currentUser || data.accounts[0] || null;
  }

  function roleHome(role) {
    return ROLE_HOME[role] || "/guinhawa_system/index.php";
  }

  function login(identifier, password, remember) {
    return fetch(API_BASE + "/auth/login", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        identifier: String(identifier || "").trim(),
        password: String(password || ""),
        remember: !!remember,
      }),
    })
      .then(function (response) {
        return response.json().then(function (body) {
          return { status: response.status, body: body || {} };
        });
      })
      .then(function (result) {
        if (result.status < 200 || result.status >= 300) {
          return { ok: false, message: result.body.error || "Incorrect email or password." };
        }
        setToken(result.body.csrfToken, !!remember);
        currentUser = mapUser(result.body.user);
        if (currentUser && currentUser.mustChangePassword) {
          return {
            ok: true,
            user: currentUser,
            home: "/guinhawa_system/login/change-password.php?required=1",
            mustChangePassword: true,
          };
        }
        return { ok: true, user: currentUser, home: roleHome(currentUser.role) };
      })
      .catch(function () {
        return { ok: false, message: "Cannot reach the server. Check that Apache and MySQL are running." };
      });
  }

  function logout() {
    try {
      api("POST", "/auth/logout");
    } catch (error) {
      /* clear the browser state even if the server call fails */
    }
    clearToken();
    currentUser = null;
    window.location.href = LOGIN_URL + "?signedout=1";
  }

  /* --------------------------- Appearance sync ---------------------------- */

  function loadAppearance() {
    var result = api("GET", "/appearance");
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message, preferences: null };
    }
    var appearance = (result.data && result.data.appearance) || {};
    return {
      ok: true,
      preferences: appearance.preferences || null,
      updatedAt: appearance.updatedAt || null,
    };
  }

  function saveAppearanceRemote(prefs) {
    var result = api("PUT", "/appearance", { preferences: prefs });
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    return { ok: true, appearance: (result.data && result.data.appearance) || null };
  }

  function resetAppearanceRemote() {
    var result = api("DELETE", "/appearance");
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    return { ok: true, appearance: (result.data && result.data.appearance) || null };
  }

  /**
   * Pull this account's saved appearance from MySQL and apply it locally.
   * localStorage still wins for instant painting, but the account row is the
   * source of truth across devices and roles.
   */
  function syncAppearance() {
    var theme = window.GuinhawaTheme;
    if (!theme) return;

    var remote = api("GET", "/appearance");
    if (!remote.ok) return;

    var appearance = remote.data && remote.data.appearance;
    var prefs = appearance && appearance.preferences;
    if (!prefs) return;

    var normalized = theme.normalize(prefs);
    var local = theme.read();
    if (JSON.stringify(local) !== JSON.stringify(normalized)) {
      theme.save(normalized);
    }
  }

  /* --------------------- Staff password recovery ------------------------- */

  function postPublic(path, body) {
    return fetch(API_BASE + path, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(body || {}),
    })
      .then(function (response) {
        return response.json().then(function (payload) {
          return { status: response.status, body: payload || {} };
        });
      })
      .then(function (result) {
        if (result.status < 200 || result.status >= 300) {
          return {
            ok: false,
            message: result.body.error || result.body.message || "The request could not be completed.",
            retryAfter: Number(result.body.retry_after) || 0,
          };
        }
        return { ok: true, body: result.body };
      })
      .catch(function () {
        return { ok: false, message: "Cannot reach the server. Check that Apache and MySQL are running." };
      });
  }

  function staffForgotPassword(identifier) {
    return postPublic("/auth/staff/forgot-password", {
      identifier: String(identifier || "").trim(),
    });
  }

  function staffVerifyResetCode(identifier, code) {
    return postPublic("/auth/staff/verify-code", {
      identifier: String(identifier || "").trim(),
      code: String(code || "").trim(),
    });
  }

  function staffResetPassword(newPassword, confirmPassword) {
    return postPublic("/auth/staff/reset-password", {
      newPassword: String(newPassword || ""),
      confirmPassword: String(confirmPassword || ""),
    });
  }

  /* ---------------------- Email OTP password recovery --------------------- */

  function forgotPassword(email) {
    return postPublic("/auth/forgot-password", {
      email: String(email || "").trim(),
    });
  }

  function verifyPasswordOtp(code) {
    return postPublic("/auth/verify-otp", {
      code: String(code || "").replace(/\D+/g, ""),
    });
  }

  function completePasswordReset(newPassword, confirmPassword) {
    return postPublic("/auth/reset-password", {
      newPassword: String(newPassword || ""),
      confirmPassword: String(confirmPassword || ""),
    });
  }

  function activateStaffAccount(token, newPassword, confirmPassword) {
    return postPublic("/auth/activate", {
      token: String(token || ""),
      newPassword: String(newPassword || ""),
      confirmPassword: String(confirmPassword || ""),
    });
  }

  /* -------------------------------- Lookups ------------------------------- */

  function findProduct(id) {
    return data.products.find(function (product) {
      return product.id === id;
    });
  }

  function findAccount(id) {
    return data.accounts.find(function (account) {
      return account.id === id;
    });
  }

  function findSupplier(id) {
    return data.suppliers.find(function (supplier) {
      return supplier.id === id;
    });
  }

  function productImage(product) {
    var value = product && product.image ? String(product.image) : "";
    if (/^https?:\/\//i.test(value) || value.charAt(0) === "/") return value;
    var file = value && DEMO_IMAGES.indexOf(value) !== -1 ? value : "generic.svg";
    return "/guinhawa_system/shared/assets/products/" + file;
  }

  function imageReference(value) {
    var text = String(value || "").trim();
    if (!text) return "";
    if (/^https?:\/\//i.test(text) || text.charAt(0) === "/") return text;
    if (DEMO_IMAGES.indexOf(text) !== -1) {
      return "/guinhawa_system/shared/assets/products/" + text;
    }
    return "";
  }

  /* -------------------------------- Metrics ------------------------------- */

  function stockState(product) {
    var qty = Number(product.qty) || 0;
    var minimum = Number(product.minStock) || 0;
    if (qty <= 0) return { key: "out", label: "Out of Stock", badge: "danger" };
    if (qty <= minimum) return { key: "low", label: "Low Stock", badge: "warning" };
    return { key: "ok", label: "In Stock", badge: "success" };
  }

  function expiryState(product) {
    var expiry = product && product.expiry;
    if (!expiry) return { key: "none", label: "No expiry", badge: "neutral", days: null };
    var date = new Date(String(expiry).slice(0, 10) + "T00:00:00");
    if (isNaN(date.getTime())) {
      return { key: "none", label: "No expiry", badge: "neutral", days: null };
    }
    var today = new Date(todayISO() + "T00:00:00");
    var days = Math.round((date.getTime() - today.getTime()) / 86400000);
    if (days < 0) return { key: "expired", label: "Expired", badge: "danger", days: days };
    if (days <= 30) {
      return {
        key: "soon",
        label: days + (days === 1 ? " day left" : " days left"),
        badge: "warning",
        days: days,
      };
    }
    return { key: "ok", label: "Fresh", badge: "success", days: days };
  }

  function expiringProducts(days) {
    var limit = Number(days) || 30;
    return data.products.filter(function (product) {
      if (!product.expiry || product.archived) return false;
      var state = expiryState(product);
      return state.days !== null && state.days >= 0 && state.days <= limit;
    });
  }

  function lowStockProducts() {
    return data.products.filter(function (product) {
      if (product.archived) return false;
      return Number(product.qty) <= Number(product.minStock || 0);
    });
  }

  function lowStockCount() {
    return lowStockProducts().length;
  }

  function inventoryValue() {
    return data.products.reduce(function (sum, product) {
      if (product.archived) return sum;
      return sum + Number(product.qty) * Number(product.cost);
    }, 0);
  }

  function retailValue() {
    return data.products.reduce(function (sum, product) {
      if (product.archived) return sum;
      return sum + Number(product.qty) * Number(product.price);
    }, 0);
  }

  function todaySales() {
    return salesTotalBetween(todayISO(), todayISO());
  }

  function todayTransactions() {
    return salesBetween(todayISO(), todayISO());
  }

  function salesBetween(startDate, endDate) {
    var start = String(startDate || "").slice(0, 10);
    var end = String(endDate || "").slice(0, 10);
    return data.transactions.filter(function (txn) {
      var day = String(txn.date || "").slice(0, 10);
      return day >= start && day <= end;
    });
  }

  function salesTotalBetween(startDate, endDate) {
    return salesBetween(startDate, endDate).reduce(function (sum, txn) {
      return sum + Number(txn.total);
    }, 0);
  }

  function rangeSales(startDate, endDate) {
    var out = [];
    var start = new Date(String(startDate).slice(0, 10) + "T00:00:00");
    var end = new Date(String(endDate).slice(0, 10) + "T00:00:00");
    if (isNaN(start.getTime()) || isNaN(end.getTime()) || start.getTime() > end.getTime()) {
      return out;
    }
    for (var d = new Date(start.getTime()); d.getTime() <= end.getTime(); d.setDate(d.getDate() + 1)) {
      var iso = dateISO(d);
      out.push({
        date: iso,
        label: d.toLocaleDateString("en-PH", { weekday: "short" }),
        fullLabel: d.toLocaleDateString("en-PH", { weekday: "short", month: "short", day: "numeric" }),
        value: salesTotalBetween(iso, iso),
      });
    }
    return out;
  }

  function weekSales(days) {
    var length = days || 7;
    return rangeSales(addDaysISO(-(length - 1)), todayISO());
  }

  function topProducts(days, limit) {
    var range = days || 7;
    var start = addDaysISO(-(range - 1));
    var totals = {};
    salesBetween(start, todayISO()).forEach(function (txn) {
      (txn.items || []).forEach(function (item) {
        var key = item.productId || item.name;
        if (!totals[key]) {
          totals[key] = { id: key, name: item.name, unit: item.unit, qty: 0, revenue: 0 };
        }
        totals[key].qty += Number(item.qty) || 0;
        totals[key].revenue += (Number(item.qty) || 0) * (Number(item.price) || 0);
      });
    });
    return Object.keys(totals)
      .map(function (key) {
        return totals[key];
      })
      .sort(function (a, b) {
        return b.revenue - a.revenue;
      })
      .slice(0, limit || 5);
  }

  function lastNDays(n) {
    var out = [];
    for (var i = Number(n || 7) - 1; i >= 0; i--) out.push(addDaysISO(-i));
    return out;
  }

  function nextInvoice() {
    var highest = 0;
    data.transactions.forEach(function (txn) {
      var match = String(txn.invoice || "").match(/(\d+)\s*$/);
      if (match) highest = Math.max(highest, Number(match[1]));
    });
    return "RCP-" + String(highest + 1).padStart(3, "0");
  }

  function addHistory(entry) {
    var record = Object.assign({ id: uid("hist"), date: nowISO(), user: getCurrentUser() ? getCurrentUser().name : "" }, entry);
    data.history.unshift(record);
    return record;
  }

  /* ------------------------------- Products ------------------------------- */

  function saveProduct(values, editingId) {
    var existing = editingId ? findProduct(editingId) : null;
    var payload = {
      name: values.name,
      sku: values.sku,
      category: values.category,
      description: values.description || "",
      minimum: values.minStock,
      cost: values.cost,
      expiration_date: values.expiry || "",
      supplier: supplierName(values.supplierId) || values.supplierName || "",
      image: imageReference(values.image),
    };

    var result;
    if (existing) {
      var variants = (existing.variants || []).map(function (variant) {
        if (variant.key !== existing.variant) {
          return {
            key: variant.key,
            unit: variant.unit,
            unit_raw: variant.unit_raw || "",
            price: variant.price,
            stock: variant.stock,
            minimum: variant.minimum,
            assumed: !!variant.assumed,
            unit_verified: variant.unit_verified !== false,
          };
        }
        return {
          key: variant.key,
          unit: apiUnit(values.unit),
          unit_raw: variant.unit_raw || "",
          price: values.price,
          stock: variant.stock,
          minimum: values.minStock,
          assumed: !!variant.assumed,
          unit_verified: true,
        };
      });
      payload.variants = variants;
      result = api("PUT", "/products/" + existing.productId, payload);
    } else {
      payload.unit_a = apiUnit(values.unit);
      payload.price_a = values.price;
      payload.stock_a = values.qty;
      payload.sack_weight_kg = 0;
      result = api("POST", "/products", payload);
    }

    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }

    reload();
    return { ok: true };
  }

  function archiveProduct(id) {
    var product = findProduct(id);
    if (!product) return { ok: false, message: "Product not found." };
    var result = api("DELETE", "/products/" + product.productId);
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    reload();
    return { ok: true };
  }

  /* -------------------------------- Stock --------------------------------- */

  function adjustStock(productId, direction, qty, info) {
    var product = findProduct(productId);
    if (!product) return { ok: false, message: "Product not found." };
    if (!(Number(qty) > 0)) return { ok: false, message: "Enter a quantity greater than zero." };

    info = info || {};
    var payload = {
      product_id: product.productId,
      variant: product.variant,
      quantity: roundQty(qty),
      remarks: [info.notes, info.reason].filter(function (part) {
        return part;
      }).join(" "),
      reference: info.ref || "",
    };

    var result;
    if (direction === "in") {
      payload.supplier_id = info.supplierId || "";
      payload.supplier = supplierName(info.supplierId) || info.supplierName || "";
      result = api("POST", "/stock/in", payload);
    } else {
      payload.reason = OUT_REASON[info.reason] || "Usage";
      result = api("POST", "/stock/out", payload);
    }

    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }

    reload();
    var unit = unitLabel(product.unit, qty);
    return {
      ok: true,
      message:
        Store.number(qty) +
        " " +
        unit +
        " " +
        (direction === "in" ? "added to " : "removed from ") +
        product.name +
        ".",
    };
  }

  /* -------------------------------- Sales --------------------------------- */

  function addTransaction(payload) {
    var items = (payload.items || []).filter(function (item) {
      return item && item.productId && Number(item.qty) > 0;
    });
    if (!items.length) return { ok: false, message: "Add at least one product to the sale." };

    var lines = items.map(function (item) {
      var product = findProduct(item.productId);
      if (!product) return null;
      return {
        product_id: product.productId,
        variant: product.variant,
        quantity: roundQty(item.qty),
      };
    });
    if (lines.some(function (line) { return line === null; })) {
      return { ok: false, message: "A selected product no longer exists." };
    }

    var body = {
      items: lines,
      payment_method: payload.payment === "GCash" ? "GCash" : "Cash",
      customer: payload.customer || "Walk-in Customer",
      order_type: payload.orderType === "Online" ? "Online" : "Walk-in",
      discount: Number(payload.discount) || 0,
      transaction_id: "WEB-" + uid("txn").toUpperCase(),
      amount_tendered: payload.amountTendered,
    };

    var result = api("POST", "/sales/checkout", body);
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }

    applyState(result.data.state, adminAccounts);
    var sale = result.data.sale;
    var transaction = sale ? mapSale(sale) : data.transactions[data.transactions.length - 1];
    return { ok: true, transaction: transaction };
  }

  function setTransactionStatus(id, status) {
    var txn = data.transactions.find(function (item) {
      return item.id === id;
    });
    if (!txn) return { ok: false, message: "Sale not found." };
    var result = api("PATCH", "/sales/" + txn.saleId + "/status", { status: status });
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    applyState(result.data.state, adminAccounts);
    return { ok: true };
  }

  /* ------------------------------- Partners ------------------------------- */

  function saveSupplier(values, editingId) {
    var body = {
      name: values.name,
      contact: values.contact || values.phone || "",
      email: values.email || "",
      address: values.address || "",
    };
    var result = editingId
      ? api("PUT", "/suppliers/" + encodeURIComponent(editingId), body)
      : api("POST", "/suppliers", body);
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    reload();
    return { ok: true };
  }

  function deleteSupplier(id) {
    var result = api("DELETE", "/suppliers/" + encodeURIComponent(id));
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    reload();
    return { ok: true };
  }

  /* ------------------------------- Accounts ------------------------------- */

  function accountPayload(values) {
    var payload = {
      fullName: values.name,
      username: values.username,
      email: values.email || "",
      contactNumber: values.phone || "",
      role: values.role === "Inventory Staff" ? "inventory-staff" : "cashier",
      status: values.status || "active",
    };
    if (values.password) payload.password = values.password;
    return payload;
  }

  function saveAccount(values, editingId) {
    var existing = editingId ? findAccount(editingId) : null;
    if (existing && existing.role === "Admin") {
      return { ok: false, message: "The Administrator account is managed through the profile page." };
    }

    var body = accountPayload(values);
    var result = existing
      ? api("PUT", "/admin/employees/" + existing.dbId, body)
      : api("POST", "/admin/employees", body);
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }

    reload();
    var account = null;
    if (result.data.account) account = mapAccount(result.data.account);
    return {
      ok: true,
      account: account,
      activation: result.data.activation || null,
      notification: result.data.notification || null,
      message: result.data.message || "",
    };
  }

  function setAccountStatus(id, active) {
    var account = findAccount(id);
    if (!account || account.role === "Admin") {
      return { ok: false, message: "That account cannot be changed." };
    }
    var result = api("PATCH", "/admin/employees/" + account.dbId + "/status", {
      status: active ? "active" : "inactive",
    });
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    reload();
    return { ok: true };
  }

  function pendingActivations() {
    var result = api("GET", "/admin/activations");
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message, activations: [] };
    }
    return { ok: true, activations: result.data.activations || [] };
  }

  function resendActivation(id) {
    var account = findAccount(id);
    if (!account || account.role === "Admin") {
      return { ok: false, message: "That account cannot be changed." };
    }
    var result = api("POST", "/admin/employees/" + account.dbId + "/resend-activation", {});
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    reload();
    return {
      ok: true,
      activation: result.data.activation || null,
      message: result.data.message || "A new activation link was sent.",
    };
  }

  function cancelActivation(id) {
    var account = findAccount(id);
    if (!account || account.role === "Admin") {
      return { ok: false, message: "That account cannot be changed." };
    }
    var result = api("DELETE", "/admin/employees/" + account.dbId + "/activation");
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    reload();
    return { ok: true };
  }

  function resetAccountPassword(id, password) {
    var account = findAccount(id);
    if (!account || account.role === "Admin") {
      return { ok: false, message: "That account cannot be reset here." };
    }
    var result = api(
      "POST",
      "/admin/employees/" + account.dbId + "/reset-password",
      password ? { password: String(password) } : {}
    );
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    reload();
    return {
      ok: true,
      password: result.data.temporaryPassword || "",
      message: result.data.message || "Password reset.",
    };
  }

  function deleteAccount(id) {
    var account = findAccount(id);
    if (!account || account.role === "Admin") {
      return { ok: false, message: "That account cannot be removed." };
    }
    var result = api("DELETE", "/admin/employees/" + account.dbId);
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    reload();
    return {
      ok: true,
      archived: mapAccount(result.data.removed || {}),
      message: result.data.message || "The staff account was deleted and archived.",
    };
  }

  function archivedStaff() {
    var result = api("GET", "/admin/employees/archived");
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message, archived: [] };
    }
    return { ok: true, archived: result.data.archived || [] };
  }

  function restoreAccount(id) {
    var result = api("POST", "/admin/employees/" + Number(id) + "/restore", {});
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    reload();
    return {
      ok: true,
      account: result.data.account ? mapAccount(result.data.account) : null,
      message: result.data.message || "The staff record was restored.",
    };
  }

  /* --------------------- Staff password requests -------------------------- */

  function passwordResetRequests() {
    var result = api("GET", "/admin/password-reset-requests");
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message, requests: [] };
    }
    return { ok: true, requests: result.data.requests || [] };
  }

  function issueResetCode(id) {
    var result = api("POST", "/admin/password-reset-requests/" + id + "/issue", {});
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    return {
      ok: true,
      code: result.data.code || "",
      expiresAt: result.data.expiresAt || "",
      request: result.data.request || null,
    };
  }

  function dismissResetRequest(id) {
    var result = api("DELETE", "/admin/password-reset-requests/" + id);
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    return { ok: true };
  }

  /* ------------------------------- Settings ------------------------------- */

  function saveProfile(values) {
    var body = {
      fullName: values.name,
      contactNumber: values.phone || "",
    };
    if (Object.prototype.hasOwnProperty.call(values, "email")) body.email = values.email || "";
    if (Object.prototype.hasOwnProperty.call(values, "avatar")) body.avatar = values.avatar || "";
    var result = api("PUT", "/auth/profile", body);
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    if (result.data.user) currentUser = mapUser(result.data.user);
    return { ok: true };
  }

  function getAccountEmailVerification() {
    var result = api("GET", "/auth/email-verification");
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message, verification: null };
    }
    return { ok: true, verification: result.data.verification || null };
  }

  function requestAccountEmailVerification(email) {
    var result = api("POST", "/auth/email-verification/request", { email: email || "" });
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    return result.data.result || { ok: true };
  }

  function verifyAccountEmail(code) {
    var result = api("POST", "/auth/email-verification/verify", { code: code || "" });
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    setToken(result.data.csrfToken, false);
    currentUser = mapUser(result.data.user);
    return { ok: true, user: currentUser, verification: result.data.verification || null };
  }

  function saveSettings(values) {
    var body = {
      store_name: values.businessName,
      owner: values.owner || "",
      email: values.email || "",
      phone: values.phone || "",
      address: values.address || "",
      receipt_footer: values.receiptFooter || "",
    };
    var result = api("PUT", "/settings", body);
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    applyState(result.data.state, adminAccounts);
    return { ok: true };
  }

  /** Persist the GCash QR (base64 data URL or image path) in settings. */
  function saveGcashQr(dataUrl) {
    var result = api("PUT", "/settings", { gcash_qr: dataUrl || "" });
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    applyState(result.data.state, adminAccounts);
    return { ok: true };
  }

  function saveNotifications(notifications) {
    var body = {
      inventory_alerts: !!notifications.lowStock,
      email_notifications: !!notifications.dailySales,
      order_notifications: !!notifications.newOrders,
    };
    var result = api("PUT", "/settings", body);
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    applyState(result.data.state, adminAccounts);
    return { ok: true };
  }

  /* --------------------------- Email delivery ---------------------------- */

  function getMailSettings() {
    var result = api("GET", "/settings/mail");
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message, mail: null };
    }
    return { ok: true, mail: result.data.mail || null };
  }

  function saveMailSettings(values) {
    var result = api("PUT", "/settings/mail", {
      username: values.username,
      password: values.password || "",
      fromEmail: values.fromEmail || values.username,
      fromName: values.fromName || "",
    });
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message, mail: null };
    }
    return {
      ok: true,
      mail: result.data.mail || null,
      message: result.data.message || "Email delivery settings saved.",
    };
  }

  function sendTestMail(to) {
    var result = api("POST", "/settings/mail/test", { to: to || "" });
    if (!result.ok) {
      handleUnauthorized(result);
      return {
        ok: false,
        message: result.message,
        test: result.data.test || { sent: false, error: result.message },
      };
    }
    return {
      ok: true,
      test: result.data.test || { sent: false, error: "The test email could not be sent." },
    };
  }

  function changePassword(currentPassword, newPassword, confirmPassword) {
    var result = api("POST", "/auth/change-password", {
      currentPassword: currentPassword,
      newPassword: newPassword,
      confirmPassword: confirmPassword || newPassword,
    });
    if (!result.ok) return { ok: false, message: result.message, status: result.status };
    setToken(result.data.csrfToken, false);
    currentUser = mapUser(result.data.user);
    return { ok: true, user: currentUser };
  }

  /* ------------------------------ Attendance ------------------------------ */

  function clockIn(accountId, date, name) {
    var account = findAccount(accountId);
    if (!account) return { ok: false, message: "Account not found." };
    if (date && date !== todayISO()) {
      return { ok: false, message: "Only today's attendance can be recorded." };
    }
    var result = api("POST", "/attendance/clock-in", {
      code: account.employeeId,
      name: name || account.name,
    });
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    applyState(result.data.state, adminAccounts);
    return { ok: true };
  }

  function clockOut(accountId, date, name) {
    var account = findAccount(accountId);
    if (!account) return { ok: false, message: "Account not found." };
    if (date && date !== todayISO()) {
      return { ok: false, message: "Only today's attendance can be recorded." };
    }
    var result = api("POST", "/attendance/clock-out", {
      code: account.employeeId,
      name: name || account.name,
    });
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    applyState(result.data.state, adminAccounts);
    return { ok: true };
  }

  /**
   * Admin manual edit. `record` carries timeIn/timeOut in HH:MM, a status
   * (Present, Late, On Leave, Absent, Not recorded) and an optional note.
   */
  function saveAttendance(accountId, date, record) {
    var account = findAccount(accountId);
    if (!account) return { ok: false, message: "Account not found." };
    var payload = {
      code: account.employeeId,
      name: account.name,
      date: date,
      time_in: (record && record.timeIn) || "",
      time_out: (record && record.timeOut) || "",
      status: (record && record.status) || "Present",
      note: (record && record.note) || "",
    };
    var result = api("PUT", "/attendance/record", payload);
    if (!result.ok) {
      handleUnauthorized(result);
      return { ok: false, message: result.message };
    }
    applyState(result.data.state, adminAccounts);
    return { ok: true };
  }

  /* -------------------------------- Exports ------------------------------- */

  function exportJSON() {
    return JSON.stringify(data, null, 2);
  }

  function save() {
    // Writes go through the explicit Store.* calls above. This remains for
    // pages that persist a purely visual preference.
    return true;
  }

  function reset() {
    reload();
    return data;
  }

  function storageAvailable() {
    return true;
  }

  boot();

  window.GuinhawaStore = {
    STORAGE_KEY: STORAGE_KEY,
    SESSION_KEY: SESSION_KEY,
    ROLE_HOME: ROLE_HOME,
    storageAvailable: storageAvailable,

    get data() {
      return data;
    },
    save: save,
    reset: reset,
    reload: reload,
    exportJSON: exportJSON,
    importJSON: function () {
      return {
        ok: false,
        message: "Backup import is disabled. Business data now lives in MySQL and is managed by the API.",
      };
    },

    /* formatting */
    money: money,
    number: number,
    unitLabel: unitLabel,
    formatDate: formatDate,
    formatDateTime: formatDateTime,
    formatTime: formatTime,
    relativeDay: relativeDay,
    todayISO: todayISO,
    addDaysISO: addDaysISO,
    nowISO: nowISO,
    escapeHTML: escapeHTML,
    uid: uid,
    roundQty: roundQty,
    toISO: toISO,

    /* auth */
    login: login,
    logout: logout,
    changePassword: changePassword,
    staffForgotPassword: staffForgotPassword,
    staffVerifyResetCode: staffVerifyResetCode,
    staffResetPassword: staffResetPassword,
    forgotPassword: forgotPassword,
    verifyPasswordOtp: verifyPasswordOtp,
    completePasswordReset: completePasswordReset,
    activateStaffAccount: activateStaffAccount,
    getSession: getSession,
    roleHome: roleHome,

    /* lookups */
    getCurrentUser: getCurrentUser,
    findProduct: findProduct,
    findAccount: findAccount,
    findSupplier: findSupplier,
    productImage: productImage,
    stockState: stockState,
    expiryState: expiryState,
    expiringProducts: expiringProducts,
    lowStockProducts: lowStockProducts,
    lowStockCount: lowStockCount,

    /* metrics */
    inventoryValue: inventoryValue,
    retailValue: retailValue,
    todaySales: todaySales,
    todayTransactions: todayTransactions,
    salesBetween: salesBetween,
    salesTotalBetween: salesTotalBetween,
    weekSales: weekSales,
    rangeSales: rangeSales,
    topProducts: topProducts,
    lastNDays: lastNDays,

    /* writes */
    saveProduct: saveProduct,
    archiveProduct: archiveProduct,
    adjustStock: adjustStock,
    addTransaction: addTransaction,
    setTransactionStatus: setTransactionStatus,
    addHistory: addHistory,
    nextInvoice: nextInvoice,

    saveSupplier: saveSupplier,
    deleteSupplier: deleteSupplier,

    saveAccount: saveAccount,
    setAccountStatus: setAccountStatus,
    resetAccountPassword: resetAccountPassword,
    pendingActivations: pendingActivations,
    resendActivation: resendActivation,
    cancelActivation: cancelActivation,
    loadAppearance: loadAppearance,
    saveAppearanceRemote: saveAppearanceRemote,
    resetAppearanceRemote: resetAppearanceRemote,
    deleteAccount: deleteAccount,
    archivedStaff: archivedStaff,
    restoreAccount: restoreAccount,
    passwordResetRequests: passwordResetRequests,
    issueResetCode: issueResetCode,
    dismissResetRequest: dismissResetRequest,

    saveProfile: saveProfile,
    getAccountEmailVerification: getAccountEmailVerification,
    requestAccountEmailVerification: requestAccountEmailVerification,
    verifyAccountEmail: verifyAccountEmail,
    saveSettings: saveSettings,
    saveGcashQr: saveGcashQr,
    saveNotifications: saveNotifications,
    getMailSettings: getMailSettings,
    saveMailSettings: saveMailSettings,
    sendTestMail: sendTestMail,

    clockIn: clockIn,
    clockOut: clockOut,
    saveAttendance: saveAttendance,
  };
})(window);
