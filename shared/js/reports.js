/* ==========================================================================
   GUINHAWA — Reports
   ========================================================================== */

(function (window, document) {
  "use strict";

  var Store = window.GuinhawaStore;
  var Charts = window.GuinhawaCharts;
  var UI = window.GuinhawaUI;
  if (!Store || !Charts || !UI) return;

  var $ = function (id) {
    return document.getElementById(id);
  };

  var range = {
    start: Store.addDaysISO(-29),
    end: Store.todayISO(),
  };

  var cache = {
    daily: [],
    transactions: [],
    products: [],
    cashiers: [],
    payments: [],
    categories: [],
    low: [],
  };

  /* -------------------------------- Helpers ------------------------------- */

  function completedInRange() {
    return Store.salesBetween(range.start, range.end);
  }

  function dayList() {
    var out = [];
    var cursor = new Date(range.start + "T00:00:00");
    var end = new Date(range.end + "T00:00:00");
    while (cursor <= end) {
      out.push(
        cursor.getFullYear() +
          "-" +
          String(cursor.getMonth() + 1).padStart(2, "0") +
          "-" +
          String(cursor.getDate()).padStart(2, "0")
      );
      cursor.setDate(cursor.getDate() + 1);
    }
    return out;
  }

  function rangeLabel() {
    if (range.start === Store.addDaysISO(-6) && range.end === Store.todayISO()) {
      return "Last 7 days";
    }
    if (range.start === Store.addDaysISO(-29) && range.end === Store.todayISO()) {
      return "Last 30 days";
    }
    if (range.start === Store.addDaysISO(-89) && range.end === Store.todayISO()) {
      return "Last 90 days";
    }
    return Store.formatDate(range.start) + " \u2013 " + Store.formatDate(range.end);
  }

  function sizeValue(value) {
    return (Number(value) || 0).toFixed(2);
  }

  /* ------------------------------- Compute -------------------------------- */

  function computeDaily() {
    var byDay = {};
    cache.transactions.forEach(function (txn) {
      var day = txn.date.slice(0, 10);
      if (!byDay[day]) {
        byDay[day] = { date: day, value: 0, count: 0, items: 0 };
      }
      byDay[day].value += txn.total;
      byDay[day].count += 1;
      byDay[day].items += txn.itemCount;
    });

    cache.daily = dayList().map(function (day) {
      var row = byDay[day] || { date: day, value: 0, count: 0, items: 0 };
      var d = new Date(day + "T00:00:00");
      return {
        date: day,
        label: d.toLocaleDateString("en-PH", { month: "short", day: "numeric" }),
        value: row.value,
        count: row.count,
        items: row.items,
      };
    });
  }

  function computeProducts() {
    var totals = {};
    cache.transactions.forEach(function (txn) {
      txn.items.forEach(function (item) {
        if (!totals[item.productId]) {
          totals[item.productId] = {
            productId: item.productId,
            name: item.name,
            sku: item.sku,
            unit: item.unit,
            qty: 0,
            revenue: 0,
          };
        }
        totals[item.productId].qty += item.qty;
        totals[item.productId].revenue += item.qty * item.price;
      });
    });

    var grand = Object.keys(totals).reduce(function (sum, key) {
      return sum + totals[key].revenue;
    }, 0);

    cache.products = Object.keys(totals)
      .map(function (key) {
        var row = totals[key];
        var product = Store.findProduct(row.productId);
        return {
          productId: row.productId,
          name: row.name,
          sku: row.sku,
          category: product ? product.category : "Removed",
          unit: row.unit,
          qty: row.qty,
          revenue: row.revenue,
          share: grand ? (row.revenue / grand) * 100 : 0,
        };
      })
      .sort(function (a, b) {
        return b.revenue - a.revenue;
      });
  }

  function computeCashiers() {
    var totals = {};
    cache.transactions.forEach(function (txn) {
      if (!totals[txn.cashier]) {
        totals[txn.cashier] = { cashier: txn.cashier, count: 0, revenue: 0 };
      }
      totals[txn.cashier].count += 1;
      totals[txn.cashier].revenue += txn.total;
    });
    cache.cashiers = Object.keys(totals)
      .map(function (key) {
        return totals[key];
      })
      .sort(function (a, b) {
        return b.revenue - a.revenue;
      });
  }

  function computePayments() {
    var totals = {};
    cache.transactions.forEach(function (txn) {
      totals[txn.payment] = (totals[txn.payment] || 0) + txn.total;
    });
    var keys = Object.keys(totals).sort(function (a, b) {
      return totals[b] - totals[a];
    });
    cache.payments = keys.map(function (key, index) {
      return {
        label: key,
        value: totals[key],
        color: Charts.palette[index % Charts.palette.length],
        display: Store.money(totals[key]),
      };
    });
  }

  function computeCategories() {
    var totals = {};
    cache.transactions.forEach(function (txn) {
      txn.items.forEach(function (item) {
        var product = Store.findProduct(item.productId);
        var category = product ? product.category : "Removed";
        totals[category] = (totals[category] || 0) + item.qty * item.price;
      });
    });
    cache.categories = Object.keys(totals)
      .map(function (key) {
        return { label: key, value: totals[key] };
      })
      .sort(function (a, b) {
        return b.value - a.value;
      })
      .slice(0, 6);
  }

  /* ------------------------------- Rendering ------------------------------ */

  function renderRange() {
    $("reportStart").value = range.start;
    $("reportEnd").value = range.end;
    $("reportStart").max = Store.todayISO();
    $("reportEnd").max = Store.todayISO();
    $("reportRangeLabel").textContent = rangeLabel();
    $("salesTrendSub").textContent = "Daily completed sales \u00B7 " + rangeLabel();

    document.querySelectorAll(".report-range [data-days]").forEach(function (button) {
      var days = Number(button.getAttribute("data-days"));
      var active =
        range.end === Store.todayISO() &&
        range.start === Store.addDaysISO(-(days - 1));
      button.classList.toggle("active", active);
    });
  }

  function renderStats() {
    var gross = cache.transactions.reduce(function (sum, txn) {
      return sum + txn.total;
    }, 0);
    var items = cache.transactions.reduce(function (sum, txn) {
      return sum + txn.itemCount;
    }, 0);

    $("reportGross").textContent = Store.money(gross);
    $("reportTxns").textContent = Store.number(cache.transactions.length);
    $("reportItems").textContent = Store.number(items);
    $("reportAverage").textContent = Store.money(
      cache.transactions.length ? gross / cache.transactions.length : 0
    );
    $("reportStockValue").textContent = Store.money(Store.inventoryValue());
    $("reportLowStock").textContent = Store.number(Store.lowStockCount());
  }

  function renderCharts() {
    var total = cache.daily.reduce(function (sum, row) {
      return sum + row.value;
    }, 0);
    var peak = cache.daily.reduce(function (best, row) {
      return row.value > best.value ? row : best;
    }, { value: 0, label: "\u2014" });

    $("salesTrendTotal").textContent = Store.money(total) + " total";
    $("salesPeak").textContent = peak.value
      ? "Peak " + peak.label + " \u00B7 " + Store.money(peak.value)
      : "No completed sales in range";

    Charts.lineChart($("reportSalesChart"), cache.daily, {
      currency: true,
      symbol: Store.data.settings.currency,
      height: 280,
      ariaLabel: "Daily completed sales for the selected range",
    });

    Charts.donutChart($("reportPaymentDonut"), cache.payments, {
      centerLabel: "Collected",
      centerValue: Charts.compact(total, Store.data.settings.currency),
      ariaLabel: "Revenue by payment method",
    });
    $("reportPaymentLegend").innerHTML = cache.payments.length
      ? Charts.legendHTML(cache.payments)
      : '<div class="text-app-muted" style="font-size:.8rem">No payments in this range.</div>';

    Charts.barChart($("reportCategoryChart"), cache.categories, {
      currency: true,
      height: 240,
      ariaLabel: "Revenue by product category",
    });
  }

  function renderTopProducts() {
    var tbody = $("topProductsBody");
    var rows = cache.products.slice(0, 6);
    if (!rows.length) {
      tbody.innerHTML = UI.emptyRow(
        3,
        "bi-trophy",
        "No product sales",
        "Completed transactions in this range will rank products here."
      );
      return;
    }
    tbody.innerHTML = rows
      .map(function (row, index) {
        return (
          "<tr>" +
          "<td>" +
          '<div class="d-flex align-items-center gap-2">' +
          '<span class="rank-number">' + (index + 1) + "</span>" +
          "<div>" +
          '<span class="cell-strong">' + UI.escape(row.name) + "</span>" +
          '<span class="cell-sub">' + UI.escape(row.sku) + "</span>" +
          "</div></div></td>" +
          '<td class="text-end text-tabular">' + Store.number(row.qty) + "</td>" +
          '<td class="text-end text-tabular">' + Store.money(row.revenue) + "</td>" +
          "</tr>"
        );
      })
      .join("");
  }

  function renderProductPerformance() {
    var tbody = $("productPerformanceBody");
    var rows = cache.products.slice(0, 12);
    if (!rows.length) {
      tbody.innerHTML = UI.emptyRow(
        5,
        "bi-table",
        "No product performance",
        "There are no completed sales in the selected date range."
      );
    } else {
      tbody.innerHTML = rows
        .map(function (row) {
          return (
            "<tr>" +
            "<td><span class=\"cell-strong\">" + UI.escape(row.name) + "</span><span class=\"cell-sub\">" + UI.escape(row.sku) + "</span></td>" +
            "<td>" + UI.escape(row.category) + "</td>" +
            '<td class="text-end text-tabular">' + Store.number(row.qty) + "</td>" +
            '<td class="text-end text-tabular fw-650">' + Store.money(row.revenue) + "</td>" +
            '<td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1"><div class="progress-bar" style="width:' + Math.min(100, row.share) + '%"></div></div><span class="text-app-muted" style="font-size:.82rem">' + row.share.toFixed(1) + "%</span></div></td>" +
            "</tr>"
          );
        })
        .join("");
    }
    $("productPerformanceFoot").textContent = rows.length
      ? "Top " + rows.length + " products by revenue · " + rangeLabel()
      : "No sales in this range";
  }

  function renderCashiers() {
    var tbody = $("cashierBody");
    if (!cache.cashiers.length) {
      tbody.innerHTML = UI.emptyRow(
        3,
        "bi-person-workspace",
        "No cashier activity",
        "Completed sales will appear here."
      );
      return;
    }
    tbody.innerHTML = cache.cashiers
      .map(function (row) {
        return (
          "<tr>" +
          '<td><span class="cell-strong">' + UI.escape(row.cashier) + "</span></td>" +
          '<td class="text-end text-tabular">' + Store.number(row.count) + "</td>" +
          '<td class="text-end text-tabular">' + Store.money(row.revenue) + "</td>" +
          "</tr>"
        );
      })
      .join("");
  }

  function renderLowStock() {
    var tbody = $("lowStockReportBody");
    cache.low = Store.lowStockProducts();
    if (!cache.low.length) {
      tbody.innerHTML = UI.emptyRow(
        7,
        "bi-check2-circle",
        "All stocked up",
        "No products are at or below their minimum stock level."
      );
      return;
    }
    tbody.innerHTML = cache.low
      .map(function (product) {
        var state = Store.stockState(product);
        var suggested = Math.max(0, product.minStock * 2 - product.qty);
        return (
          "<tr>" +
          "<td><div class=\"product-cell\"><img class=\"product-thumb\" src=\"" + Store.productImage(product) + "\" alt=\"\" width=\"42\" height=\"42\"><div><span class=\"cell-strong\">" + UI.escape(product.name) + "</span><span class=\"cell-sub\">" + UI.escape(product.sku) + "</span></div></div></td>" +
          "<td>" + UI.escape(product.category) + "</td>" +
          '<td class="text-end text-tabular">' + Store.number(product.qty) + " " + UI.escape(product.unit) + "</td>" +
          '<td class="text-end text-tabular text-app-muted">' + Store.number(product.minStock) + "</td>" +
          '<td class="text-end text-tabular fw-650">' + Store.number(suggested) + "</td>" +
          "<td>" + UI.statusBadge(state.label, state.badge) + "</td>" +
          '<td class="text-end"><a class="btn btn-icon btn-icon--success" href="inventory.php?panel=in&product=' + encodeURIComponent(product.id) + '" title="Stock in" aria-label="Stock in"><i class="bi bi-plus-lg"></i></a></td>' +
          "</tr>"
        );
      })
      .join("");
  }

  function renderAll() {
    cache.transactions = completedInRange();
    computeDaily();
    computeProducts();
    computeCashiers();
    computePayments();
    computeCategories();
    renderRange();
    renderStats();
    renderCharts();
    renderTopProducts();
    renderProductPerformance();
    renderCashiers();
    renderLowStock();
  }

  /* --------------------------------- Exports ------------------------------ */

  function exportDailySales() {
    UI.exportCSV(
      "guinhawa-sales-" + range.start + "-to-" + range.end + ".csv",
      ["Date", "Completed transactions", "Items sold", "Gross sales"],
      cache.daily.map(function (row) {
        return [row.date, row.count, row.items, sizeValue(row.value)];
      })
    );
    UI.toast("Daily sales CSV exported.", { type: "info", title: "Export ready" });
  }

  function exportProductReport() {
    if (!cache.products.length) {
      UI.toast("There are no product sales in this date range to export.", { type: "warning" });
      return;
    }
    UI.exportCSV(
      "guinhawa-product-performance-" + range.start + "-to-" + range.end + ".csv",
      ["Product", "SKU", "Category", "Units sold", "Revenue", "Share %"],
      cache.products.map(function (row) {
        return [row.name, row.sku, row.category, row.qty, sizeValue(row.revenue), row.share.toFixed(2)];
      })
    );
    UI.toast("Product performance CSV exported.", { type: "info", title: "Export ready" });
  }

  function exportCashierReport() {
    if (!cache.cashiers.length) {
      UI.toast("There are no cashier sales in this date range to export.", { type: "warning" });
      return;
    }
    UI.exportCSV(
      "guinhawa-cashier-performance-" + range.start + "-to-" + range.end + ".csv",
      ["Cashier", "Completed sales", "Revenue"],
      cache.cashiers.map(function (row) {
        return [row.cashier, row.count, sizeValue(row.revenue)];
      })
    );
    UI.toast("Cashier performance CSV exported.", { type: "info", title: "Export ready" });
  }

  function exportLowStockReport() {
    UI.exportCSV(
      "guinhawa-low-stock-" + Store.todayISO() + ".csv",
      ["Product", "SKU", "Category", "On hand", "Unit", "Minimum", "Suggested order", "Status"],
      cache.low.map(function (product) {
        return [
          product.name,
          product.sku,
          product.category,
          product.qty,
          product.unit,
          product.minStock,
          Math.max(0, product.minStock * 2 - product.qty),
          Store.stockState(product).label,
        ];
      })
    );
    UI.toast("Low stock CSV exported.", { type: "info", title: "Export ready" });
  }

  /* --------------------------------- Events ------------------------------- */

  function setRange(start, end) {
    if (start > end) {
      UI.toast("The start date must be before the end date.", {
        type: "warning",
        title: "Check the date range",
      });
      renderRange();
      return;
    }
    range.start = start;
    range.end = end > Store.todayISO() ? Store.todayISO() : end;
    renderAll();
  }

  function bindEvents() {
    document.querySelectorAll(".report-range [data-days]").forEach(function (button) {
      button.addEventListener("click", function () {
        var days = Number(button.getAttribute("data-days"));
        setRange(Store.addDaysISO(-(days - 1)), Store.todayISO());
      });
    });

    $("reportStart").addEventListener("change", function (event) {
      if (!event.target.value) return;
      setRange(event.target.value, range.end);
    });

    $("reportEnd").addEventListener("change", function (event) {
      if (!event.target.value) return;
      setRange(range.start, event.target.value);
    });

    $("printReport").addEventListener("click", function () {
      window.print();
    });

    $("exportDailySales").addEventListener("click", exportDailySales);
    $("exportProductsReport").addEventListener("click", exportProductReport);
    $("exportCashierReport").addEventListener("click", exportCashierReport);
    $("exportLowStockReport").addEventListener("click", exportLowStockReport);

    document.addEventListener("guinhawa:themechange", function () {
      renderCharts();
      renderPaymentLegendOnly();
    });
  }

  function renderPaymentLegendOnly() {
    $("reportPaymentLegend").innerHTML = cache.payments.length
      ? Charts.legendHTML(cache.payments)
      : '<div class="text-app-muted" style="font-size:.8rem">No payments in this range.</div>';
  }

  /* ---------------------------------- Init -------------------------------- */

  function init() {
    renderAll();
    bindEvents();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
