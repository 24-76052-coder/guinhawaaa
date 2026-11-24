/* ==========================================================================
   GUINHAWA — Inventory control
   ========================================================================== */

(function (window, document) {
  "use strict";

  var Store = window.GuinhawaStore;
  var UI = window.GuinhawaUI;
  if (!Store || !UI) return;

  var $ = function (id) {
    return document.getElementById(id);
  };

  var state = {
    search: "",
    category: "",
    status: UI.param("status") === "low" || UI.param("status") === "out" ? UI.param("status") : "",
  };
  var stockActionBusy = false;

  /* ------------------------------- Rendering ------------------------------ */

  function filteredProducts() {
    var q = state.search.trim().toLowerCase();
    return Store.data.products.filter(function (product) {
      if (state.category && product.category !== state.category) return false;
      if (state.status && Store.stockState(product).key !== state.status) return false;
      if (!q) return true;
      return (
        product.name + " " + product.sku + " " + product.category
      )
        .toLowerCase()
        .indexOf(q) > -1;
    });
  }

  function renderStats() {
    var products = Store.data.products;
    var low = products.filter(function (p) {
      return Store.stockState(p).key === "low";
    }).length;
    var out = products.filter(function (p) {
      return Store.stockState(p).key === "out";
    }).length;
    var units = products.reduce(function (sum, p) {
      return sum + (Number(p.qty) || 0);
    }, 0);

    $("statSkus").textContent = Store.number(products.length);
    $("statUnits").textContent = Store.number(units);
    $("statLow").textContent = Store.number(low);
    $("statOut").textContent = Store.number(out);
    $("statValue").textContent = Store.money(Store.inventoryValue());
  }

  function categoryOptions() {
    var set = {};
    Store.data.products.forEach(function (p) {
      set[p.category] = true;
    });
    return Object.keys(set).sort();
  }

  function renderCategoryFilter() {
    var select = $("inventoryCategory");
    var current = state.category;
    select.innerHTML =
      '<option value="">All categories</option>' +
      categoryOptions()
        .map(function (category) {
          return '<option value="' + UI.escape(category) + '">' + UI.escape(category) + "</option>";
        })
        .join("");
    select.value = current;
  }

  function levelPercent(product) {
    var target = Math.max(1, Number(product.minStock) * 3);
    return Math.min(100, Math.round(((Number(product.qty) || 0) / target) * 100));
  }

  function renderTable() {
    var tbody = $("inventoryBody");
    var rows = filteredProducts();

    if (!rows.length) {
      tbody.innerHTML = UI.emptyRow(
        8,
        "bi-archive",
        "No stock records found",
        "Adjust your filters or add products to the catalog first."
      );
    } else {
      tbody.innerHTML = rows
        .map(function (product) {
          var stock = Store.stockState(product);
          var expiry = Store.expiryState(product);
          var barClass =
            stock.key === "out"
              ? "progress-bar--rose"
              : stock.key === "low"
                ? "progress-bar--amber"
                : "";
          return (
            '<tr data-id="' + UI.escape(product.id) + '">' +
            "<td>" +
            '<div class="product-cell">' +
            '<img class="product-thumb" src="' + Store.productImage(product) + '" alt="" width="42" height="42">' +
            "<div>" +
            '<span class="cell-strong">' + UI.escape(product.name) + "</span>" +
            '<span class="cell-sub">' + UI.escape(product.sku) + "</span>" +
            "</div></div></td>" +
            "<td>" + UI.escape(product.category) + "</td>" +
            '<td class="text-end" data-sort-value="' + Number(product.qty) + '">' +
            '<span class="qty ' + (stock.key === "ok" ? "" : "qty--" + stock.key) + '">' +
            Store.number(product.qty) + "</span>" +
            '<span class="cell-sub">' + UI.escape(Store.unitLabel(product.unit, product.qty)) + "</span>" +
            "</td>" +
            '<td class="text-end text-tabular text-app-muted">' + Store.number(product.minStock) + "</td>" +
            '<td><div class="stock-bar progress"><div class="progress-bar ' + barClass + '" style="width:' + levelPercent(product) + '%"></div></div></td>' +
            '<td data-sort-value="' + stock.label + '">' + UI.statusBadge(stock.label, stock.badge) + "</td>" +
            '<td class="text-nowrap" data-sort-value="' + UI.escape(product.expiry || "") + '">' +
            (product.expiry
              ? UI.statusBadge(expiry.label, expiry.badge)
              : '<span class="text-app-muted">No expiry</span>') +
            "</td>" +
            '<td class="text-end text-nowrap">' +
            '<button class="btn btn-soft btn-row" type="button" data-stock="in" data-product="' + UI.escape(product.id) + '"><i class="bi bi-plus-lg"></i> In</button> ' +
            '<button class="btn btn-outline-app btn-row" type="button" data-stock="out" data-product="' + UI.escape(product.id) + '"><i class="bi bi-dash-lg"></i> Out</button>' +
            "</td></tr>"
          );
        })
        .join("");
    }

    $("inventoryCount").textContent =
      Store.number(rows.length) + (rows.length === 1 ? " item" : " items");
    $("inventoryFoot").textContent =
      "Showing " + Store.number(rows.length) + " of " + Store.number(Store.data.products.length) + " items";
  }

  function renderAll() {
    renderStats();
    renderTable();
  }

  /* --------------------------------- Modals ------------------------------- */

  function productOptions(selectedId) {
    return Store.data.products
      .slice()
      .sort(function (a, b) {
        return a.name.localeCompare(b.name);
      })
      .map(function (product) {
        var suffix = product.qty <= 0 ? " (out of stock)" : " (" + product.qty + " " + Store.unitLabel(product.unit, product.qty) + ")";
        return (
          '<option value="' + UI.escape(product.id) + '"' +
          (product.id === selectedId ? " selected" : "") +
          ">" + UI.escape(product.name) + suffix + "</option>"
        );
      })
      .join("");
  }

  function supplierOptions(selectedId) {
    return (
      '<option value="">No supplier</option>' +
      Store.data.suppliers
        .map(function (supplier) {
          return (
            '<option value="' + UI.escape(supplier.id) + '"' +
            (supplier.id === selectedId ? " selected" : "") +
            ">" + UI.escape(supplier.name) + "</option>"
          );
        })
        .join("")
    );
  }

  function updateStockInPreview() {
    var product = Store.findProduct($("stockInProduct").value);
    var input = $("stockInQty");
    var qty = Store.roundQty(Math.max(0, Number(input.value) || 0));
    if (product && input) {
      input.step = product.sellByWeight ? "0.1" : "1";
      input.min = product.sellByWeight ? "0.1" : "1";
    }
    if (!product) {
      $("stockInCurrent").textContent = "Select a product to see its current balance.";
      $("stockInPreview").innerHTML =
        '<i class="bi bi-arrow-left-right" aria-hidden="true"></i><div>Choose a product and quantity to preview the new balance.</div>';
      return;
    }
    $("stockInCurrent").textContent =
      product.name + " currently has " + Store.number(product.qty) + " " + Store.unitLabel(product.unit, product.qty) + " on hand.";
    $("stockInPreview").innerHTML =
      '<i class="bi bi-arrow-right" aria-hidden="true"></i><div>New balance after receipt: <strong>' +
      Store.number(product.qty + qty) + " " + UI.escape(Store.unitLabel(product.unit, product.qty + qty)) +
      "</strong> &middot; minimum " + Store.number(product.minStock) + "</div>";
  }

  function updateStockOutPreview() {
    var product = Store.findProduct($("stockOutProduct").value);
    var input = $("stockOutQty");
    var qty = Store.roundQty(Math.max(0, Number(input.value) || 0));
    if (product && input) {
      input.step = product.sellByWeight ? "0.1" : "1";
      input.min = product.sellByWeight ? "0.1" : "1";
    }
    if (!product) {
      $("stockOutCurrent").textContent = "Select a product to see its current balance.";
      $("stockOutPreview").innerHTML =
        '<i class="bi bi-arrow-left-right" aria-hidden="true"></i><div>Choose a product and quantity to preview the new balance.</div>';
      return;
    }
    var after = product.qty - qty;
    $("stockOutCurrent").textContent =
      product.name + " currently has " + Store.number(product.qty) + " " + Store.unitLabel(product.unit, product.qty) + " on hand.";
    if (after < 0) {
      $("stockOutPreview").className = "alert-app alert-app--danger";
      $("stockOutPreview").innerHTML =
        '<i class="bi bi-exclamation-octagon" aria-hidden="true"></i><div>Not enough stock. Maximum available is <strong>' +
        Store.number(product.qty) + " " + UI.escape(Store.unitLabel(product.unit, product.qty)) + "</strong>.</div>";
    } else {
      $("stockOutPreview").className = "alert-app";
      $("stockOutPreview").innerHTML =
        '<i class="bi bi-arrow-right" aria-hidden="true"></i><div>New balance after release: <strong>' +
        Store.number(after) + " " + UI.escape(Store.unitLabel(product.unit, after)) +
        "</strong> &middot; minimum " + Store.number(product.minStock) + "</div>";
    }
  }

  function openStockIn(productId) {
    var select = $("stockInProduct");
    select.innerHTML = productOptions(productId || "");
    $("stockInQty").value = 1;
    $("stockInSupplier").innerHTML = supplierOptions("");
    $("stockInRef").value = "DR-" + new Date().getFullYear() + "-" + String(154 + Store.data.history.length).slice(-4);
    $("stockInExpiry").value = "";
    $("stockInNotes").value = "";
    $("stockInForm").classList.remove("was-validated");
    updateStockInPreview();
    bootstrap.Offcanvas.getOrCreateInstance($("stockInModal")).show();
  }

  function openStockOut(productId) {
    var select = $("stockOutProduct");
    select.innerHTML = productOptions(productId || "");
    $("stockOutQty").value = 1;
    $("stockOutReason").value = "Damaged goods";
    $("stockOutNotes").value = "";
    $("stockOutForm").classList.remove("was-validated");
    updateStockOutPreview();
    bootstrap.Offcanvas.getOrCreateInstance($("stockOutModal")).show();
  }

  function submitStockIn(event) {
    event.preventDefault();
    var form = $("stockInForm");
    var product = Store.findProduct($("stockInProduct").value);
    var qty = Store.roundQty(Number($("stockInQty").value));

    if (!product || !form.checkValidity() || !qty || qty <= 0) {
      form.classList.add("was-validated");
      return;
    }
    if (!product.sellByWeight && Math.round(qty) !== qty) {
      UI.toast(
        product.name + " is counted in whole " + Store.unitLabel(product.unit, 2) + ".",
        { type: "warning", title: "Whole quantity required" }
      );
      return;
    }

    var expiry = $("stockInExpiry").value;
    var supplierId = $("stockInSupplier").value;
    var before = Number(product.qty) || 0;
    var after = before + qty;

    if (stockActionBusy) return;
    stockActionBusy = true;

    UI.confirm({
      title: "Confirm Stock Addition",
      message:
        "Add " +
        Store.number(qty) +
        " " +
        Store.unitLabel(product.unit, qty) +
        " to " +
        product.name +
        "? Current stock: " +
        Store.number(before) +
        " " +
        Store.unitLabel(product.unit, before) +
        ". New balance: " +
        Store.number(after) +
        " " +
        Store.unitLabel(product.unit, after) +
        ".",
      confirmLabel: "Add stock",
      variant: "brand",
      icon: "bi-box-arrow-in-down",
    }).then(function (confirmed) {
      stockActionBusy = false;
      if (!confirmed) return;

      if (expiry) product.expiry = expiry;

      var result = Store.adjustStock(product.id, "in", qty, {
        supplierId: supplierId,
        ref: $("stockInRef").value.trim(),
        reason: supplierId ? "Supplier delivery" : "Stock received",
        notes: $("stockInNotes").value.trim(),
      });

      if (!result.ok) {
        UI.toast(result.message, { type: "danger", title: "Stock in failed" });
        return;
      }

      bootstrap.Offcanvas.getOrCreateInstance($("stockInModal")).hide();
      renderCategoryFilter();
      renderAll();
      if (window.GuinhawaHistory) window.GuinhawaHistory.render();
      UI.refreshBadges();
      UI.toast(result.message, { title: "Stock received" });
    });
  }

  function submitStockOut(event) {
    event.preventDefault();
    var form = $("stockOutForm");
    var product = Store.findProduct($("stockOutProduct").value);
    var qty = Store.roundQty(Number($("stockOutQty").value));

    if (!product || !form.checkValidity() || !qty || qty <= 0) {
      form.classList.add("was-validated");
      return;
    }
    if (!product.sellByWeight && Math.round(qty) !== qty) {
      UI.toast(
        product.name + " is counted in whole " + Store.unitLabel(product.unit, 2) + ".",
        { type: "warning", title: "Whole quantity required" }
      );
      return;
    }

    if (qty > Number(product.qty)) {
      UI.toast(
        "Only " +
          Store.number(product.qty) +
          " " +
          Store.unitLabel(product.unit, product.qty) +
          " of " +
          product.name +
          " is available.",
        { type: "warning", title: "Not enough stock" }
      );
      return;
    }

    var before = Number(product.qty) || 0;
    var after = before - qty;

    if (stockActionBusy) return;
    stockActionBusy = true;

    UI.confirm({
      title: "Confirm Stock Removal",
      message:
        "Remove " +
        Store.number(qty) +
        " " +
        Store.unitLabel(product.unit, qty) +
        " from " +
        product.name +
        "? Available stock: " +
        Store.number(before) +
        " " +
        Store.unitLabel(product.unit, before) +
        ". Remaining stock: " +
        Store.number(after) +
        " " +
        Store.unitLabel(product.unit, after) +
        ".",
      confirmLabel: "Remove stock",
      icon: "bi-box-arrow-up",
    }).then(function (confirmed) {
      stockActionBusy = false;
      if (!confirmed) return;

      var result = Store.adjustStock(product.id, "out", qty, {
        ref: "ADJ-" + new Date().getFullYear() + "-" + String(200 + Store.data.history.length).slice(-4),
        reason: $("stockOutReason").value,
        notes: $("stockOutNotes").value.trim(),
      });

      if (!result.ok) {
        UI.toast(result.message, { type: "danger", title: "Stock out failed" });
        return;
      }

      bootstrap.Offcanvas.getOrCreateInstance($("stockOutModal")).hide();
      renderCategoryFilter();
      renderAll();
      if (window.GuinhawaHistory) window.GuinhawaHistory.render();
      UI.refreshBadges();
      UI.toast(result.message, { title: "Stock released" });
    });
  }

  /* --------------------------------- Export ------------------------------- */

  function exportInventory() {
    var rows = filteredProducts().map(function (product) {
      return [
        product.name,
        product.sku,
        product.category,
        product.qty,
        product.unit,
        product.minStock,
        Store.stockState(product).label,
        product.expiry || "",
        product.cost.toFixed(2),
        (product.qty * product.cost).toFixed(2),
      ];
    });

    UI.exportCSV(
      "guinhawa-inventory-" + Store.todayISO() + ".csv",
      ["Product", "SKU", "Category", "On hand", "Unit", "Minimum stock", "Status", "Expiry", "Unit cost", "Stock value"],
      rows
    );
    UI.toast("Inventory CSV exported.", { type: "info", title: "Export ready" });
  }

  /* ---------------------------------- Tabs -------------------------------- */

  function setInvTab(name, updateUrl) {
    var stockPane = $("invPaneStock");
    var historyPane = $("invPaneHistory");
    if (!stockPane || !historyPane) return;

    var isHistory = name === "history";
    stockPane.hidden = isHistory;
    historyPane.hidden = !isHistory;

    document.querySelectorAll("[data-inv-tab]").forEach(function (button) {
      var active = button.getAttribute("data-inv-tab") === name;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-selected", active ? "true" : "false");
    });

    if (isHistory && window.GuinhawaHistory) {
      window.GuinhawaHistory.render();
    }

    if (updateUrl && window.history && window.history.replaceState) {
      var url = new URL(window.location.href);
      if (isHistory) {
        url.searchParams.set("tab", "history");
      } else {
        url.searchParams.delete("tab");
      }
      window.history.replaceState(null, "", url.pathname + url.search + url.hash);
    }
  }

  function bindTabs() {
    document.querySelectorAll("[data-inv-tab]").forEach(function (button) {
      button.addEventListener("click", function () {
        setInvTab(button.getAttribute("data-inv-tab"), true);
      });
    });
    setInvTab(UI.param("tab") === "history" ? "history" : "stock", false);
  }

  /* --------------------------------- Events ------------------------------- */

  function focusRow(productId) {
    var row = document.querySelector('#inventoryBody tr[data-id="' + productId + '"]');
    if (!row) return;
    row.classList.add("row-focus");
    row.scrollIntoView({ block: "center", behavior: "smooth" });
    window.setTimeout(function () {
      row.classList.remove("row-focus");
    }, 2600);
  }

  function bindEvents() {
    var debouncedRender = UI.debounce(renderTable, 160);

    $("inventorySearch").addEventListener("input", function (event) {
      state.search = event.target.value;
      debouncedRender();
    });

    $("inventoryCategory").addEventListener("change", function (event) {
      state.category = event.target.value;
      renderTable();
    });

    $("inventoryStatus").addEventListener("change", function (event) {
      state.status = event.target.value;
      renderTable();
    });

    $("clearInventoryFilters").addEventListener("click", function () {
      state = { search: "", category: "", status: "" };
      $("inventorySearch").value = "";
      $("inventoryCategory").value = "";
      $("inventoryStatus").value = "";
      renderTable();
    });

    $("stockInBtn").addEventListener("click", function () {
      openStockIn(null);
    });
    $("stockOutBtn").addEventListener("click", function () {
      openStockOut(null);
    });
    $("exportInventory").addEventListener("click", exportInventory);

    $("inventoryBody").addEventListener("click", function (event) {
      var button = event.target.closest("[data-stock]");
      if (!button) return;
      if (button.getAttribute("data-stock") === "in") {
        openStockIn(button.getAttribute("data-product"));
      } else {
        openStockOut(button.getAttribute("data-product"));
      }
    });

    ["stockInProduct", "stockInQty"].forEach(function (id) {
      $(id).addEventListener("input", updateStockInPreview);
      $(id).addEventListener("change", updateStockInPreview);
    });

    ["stockOutProduct", "stockOutQty"].forEach(function (id) {
      $(id).addEventListener("input", updateStockOutPreview);
      $(id).addEventListener("change", updateStockOutPreview);
    });

    $("stockInForm").addEventListener("submit", submitStockIn);
    $("stockOutForm").addEventListener("submit", submitStockOut);

    $("stockInModal").addEventListener("hidden.bs.offcanvas", function () {
      $("stockInForm").classList.remove("was-validated");
    });
    $("stockOutModal").addEventListener("hidden.bs.offcanvas", function () {
      $("stockOutForm").classList.remove("was-validated");
    });
  }

  /* ---------------------------------- Init -------------------------------- */

  function init() {
    renderCategoryFilter();
    renderAll();
    bindEvents();
    bindTabs();

    $("inventoryStatus").value = state.status;

    var productParam = UI.param("product");
    var panelParam = UI.param("panel") || UI.param("action");

    if (panelParam === "in" || panelParam === "out") {
      window.setTimeout(function () {
        if (panelParam === "in") {
          openStockIn(productParam || null);
        } else {
          openStockOut(productParam || null);
        }
      }, 220);
    } else if (productParam) {
      window.setTimeout(function () {
        focusRow(productParam);
      }, 250);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
