/* ==========================================================================
   GUINHAWA — Inventory history ledger
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
    type: "",
    from: "",
    to: "",
  };

  /* ------------------------------- Utilities ------------------------------ */

  function movementKind(entry) {
    if (entry.type === "in") {
      return { key: "in", label: "Stock in", variant: "success", icon: "bi-box-arrow-in-down" };
    }
    if (entry.reason === "Sale") {
      return { key: "sale", label: "Sale", variant: "info", icon: "bi-receipt" };
    }
    return { key: "out", label: "Stock out", variant: "warning", icon: "bi-box-arrow-up" };
  }

  function filteredHistory() {
    var q = state.search.trim().toLowerCase();
    return Store.data.history.filter(function (entry) {
      var kind = movementKind(entry);
      if (state.type && kind.key !== state.type) return false;
      var day = String(entry.date).slice(0, 10);
      if (state.from && day < state.from) return false;
      if (state.to && day > state.to) return false;
      if (!q) return true;
      return (
        (entry.productName || "") + " " +
        (entry.sku || "") + " " +
        (entry.ref || "") + " " +
        (entry.supplierName || "") + " " +
        (entry.reason || "") + " " +
        (entry.notes || "") + " " +
        (entry.user || "")
      )
        .toLowerCase()
        .indexOf(q) > -1;
    });
  }

  /* ------------------------------- Rendering ------------------------------ */

  function renderStats() {
    var history = Store.data.history;
    var inUnits = history
      .filter(function (entry) {
        return entry.type === "in";
      })
      .reduce(function (sum, entry) {
        return sum + (Number(entry.qty) || 0);
      }, 0);
    var outUnits = history
      .filter(function (entry) {
        return entry.type === "out";
      })
      .reduce(function (sum, entry) {
        return sum + (Number(entry.qty) || 0);
      }, 0);
    var today = history.filter(function (entry) {
      return String(entry.date).slice(0, 10) === Store.todayISO();
    }).length;

    $("histStatMovements").textContent = Store.number(history.length);
    $("histStatIn").textContent = Store.number(inUnits);
    $("histStatOut").textContent = Store.number(outUnits);
    $("histStatToday").textContent = Store.number(today);
  }

  function productThumb(entry) {
    var product = Store.findProduct(entry.productId);
    var image = product
      ? Store.productImage(product)
      : "/guinhawa_system/shared/assets/products/generic.svg";
    return '<img class="product-thumb" src="' + image + '" alt="" width="42" height="42">';
  }

  function renderTable() {
    var tbody = $("historyBody");
    var rows = filteredHistory();

    if (!rows.length) {
      tbody.innerHTML = UI.emptyRow(
        8,
        "bi-clock-history",
        "No movements match",
        "Adjust the search, type, or date filters to see more of the ledger."
      );
    } else {
      tbody.innerHTML = rows
        .map(function (entry) {
          var kind = movementKind(entry);
          var signed =
            (entry.type === "in" ? "+" : "\u2212") + Store.number(entry.qty) + " " + UI.escape(entry.unit || "");
          var supplierCell =
            entry.type === "in"
              ? entry.supplierName || "No supplier recorded"
              : entry.reason || "Stock out";
          return (
            "<tr>" +
            '<td class="text-nowrap" data-sort-value="' + UI.escape(entry.date) + '">' + Store.formatDateTime(entry.date) + "</td>" +
            '<td><div class="product-cell">' + productThumb(entry) +
            "<div>" +
            '<span class="cell-strong">' + UI.escape(entry.productName) + "</span>" +
            '<span class="cell-sub">' + UI.escape(entry.sku || "") + "</span>" +
            "</div></div></td>" +
            "<td>" + UI.statusBadge(kind.label, kind.variant) + "</td>" +
            '<td class="text-end fw-650 text-tabular ' + (entry.type === "in" ? "text-app-success" : "text-app-danger") + '" data-sort-value="' + Number(entry.qty) + '">' +
            signed + "</td>" +
            '<td class="text-end text-tabular text-app-muted">' + Store.number(entry.before) + " &rarr; " + Store.number(entry.after) + "</td>" +
            '<td class="text-nowrap"><span style="font-family:Consolas,monospace;font-size:.82rem">' + UI.escape(entry.ref || "\u2014") + "</span></td>" +
            '<td class="text-truncate" style="max-width:190px">' + UI.escape(supplierCell) + "</td>" +
            "<td>" + UI.escape(entry.user || "\u2014") + "</td>" +
            "</tr>"
          );
        })
        .join("");
    }

    $("historyCount").textContent =
      Store.number(rows.length) + (rows.length === 1 ? " movement" : " movements");
    $("historyFoot").textContent =
      "Showing " + Store.number(rows.length) + " of " + Store.number(Store.data.history.length) + " movements";
  }

  function renderAll() {
    renderStats();
    renderTable();
  }

  /* --------------------------------- Export ------------------------------- */

  function exportHistory() {
    var rows = filteredHistory().map(function (entry) {
      var kind = movementKind(entry);
      return [
        String(entry.date).replace("T", " "),
        entry.productName,
        entry.sku,
        kind.label,
        entry.qty,
        entry.unit,
        entry.before,
        entry.after,
        entry.ref || "",
        entry.supplierName || "",
        entry.reason || "",
        entry.notes || "",
        entry.user || "",
      ];
    });

    UI.exportCSV(
      "guinhawa-inventory-history-" + Store.todayISO() + ".csv",
      ["Date", "Product", "SKU", "Movement", "Quantity", "Unit", "Before", "After", "Reference", "Supplier", "Reason", "Notes", "Recorded by"],
      rows
    );
    UI.toast("Inventory ledger CSV exported.", {
      type: "info",
      title: "Export ready",
    });
  }

  /* --------------------------------- Events ------------------------------- */

  function on(id, eventName, handler) {
    var element = $(id);
    if (element) element.addEventListener(eventName, handler);
  }

  function bindEvents() {
    on("historySearch", "input", UI.debounce(function (event) {
      state.search = event.target.value;
      renderTable();
    }, 160));

    on("historyType", "change", function (event) {
      state.type = event.target.value;
      renderTable();
    });

    on("historyFrom", "change", function (event) {
      state.from = event.target.value;
      renderTable();
    });

    on("historyTo", "change", function (event) {
      state.to = event.target.value;
      renderTable();
    });

    on("clearHistoryFilters", "click", function () {
      state = { search: "", type: "", from: "", to: "" };
      if ($("historySearch")) $("historySearch").value = "";
      if ($("historyType")) $("historyType").value = "";
      if ($("historyFrom")) $("historyFrom").value = "";
      if ($("historyTo")) $("historyTo").value = "";
      renderTable();
    });

    on("exportHistory", "click", exportHistory);
  }

  /* ---------------------------------- Init -------------------------------- */

  function init() {
    if (!$("historyBody")) return;
    renderAll();
    bindEvents();
  }

  window.GuinhawaHistory = { render: renderAll };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
