/* ==========================================================================
   GUINHAWA — Dashboard
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

  var statusVariants = {
    Completed: "success",
    Pending: "warning",
    Refunded: "neutral",
    Void: "danger",
  };

  var salesView = {
    mode: "rolling",
    date: Store.todayISO(),
    month: Store.todayISO().slice(0, 7),
    year: String(new Date().getFullYear()),
  };

  function isoDate(date) {
    return (
      date.getFullYear() +
      "-" +
      String(date.getMonth() + 1).padStart(2, "0") +
      "-" +
      String(date.getDate()).padStart(2, "0")
    );
  }

  function parseISO(iso) {
    var d = new Date(String(iso || "").slice(0, 10) + "T00:00:00");
    return isNaN(d.getTime()) ? null : d;
  }

  function firstOfMonth(yearMonth) {
    var parts = String(yearMonth || "").split("-");
    var year = Number(parts[0]);
    var month = Number(parts[1]);
    if (!year || !month || month < 1 || month > 12) {
      return Store.todayISO().slice(0, 8) + "01";
    }
    return isoDate(new Date(year, month - 1, 1));
  }

  function lastOfMonth(yearMonth) {
    var parts = String(yearMonth || "").split("-");
    var year = Number(parts[0]);
    var month = Number(parts[1]);
    if (!year || !month || month < 1 || month > 12) {
      return Store.todayISO();
    }
    return isoDate(new Date(year, month, 0));
  }

  function monthLabel(yearMonth) {
    var d = parseISO(firstOfMonth(yearMonth));
    return d
      ? d.toLocaleDateString("en-PH", { month: "long", year: "numeric" })
      : String(yearMonth || "");
  }

  function salesRange() {
    var start;
    var end;
    var label;

    if (salesView.mode === "today") {
      start = end = Store.todayISO();
      label = "Today\u2019s completed sales \u00b7 " + Store.formatDate(start);
    } else if (salesView.mode === "date") {
      start = end = salesView.date || Store.todayISO();
      label = "Completed sales on " + Store.formatDate(start);
    } else if (salesView.mode === "month") {
      start = firstOfMonth(salesView.month);
      end = lastOfMonth(salesView.month);
      label = "Completed sales for " + monthLabel(salesView.month);
    } else if (salesView.mode === "year") {
      start = String(salesView.year) + "-01-01";
      end = salesView.year + "-12-31";
      label = "Completed sales for " + salesView.year;

      var months = [];
      for (var m = 1; m <= 12; m++) {
        var key = salesView.year + "-" + String(m).padStart(2, "0");
        var monthDate = new Date(Number(salesView.year), m - 1, 1);
        months.push({
          date: firstOfMonth(key),
          label: monthDate.toLocaleDateString("en-PH", { month: "short" }),
          fullLabel: monthDate.toLocaleDateString("en-PH", { month: "long", year: "numeric" }),
          value: Store.salesTotalBetween(firstOfMonth(key), lastOfMonth(key)),
        });
      }
      return { start: start, end: end, rows: months, label: label };
    } else {
      var rows = Store.weekSales(7);
      return {
        start: rows.length ? rows[0].date : Store.todayISO(),
        end: rows.length ? rows[rows.length - 1].date : Store.todayISO(),
        rows: rows,
        label: "Completed sales for the last seven days",
      };
    }

    return { start: start, end: end, rows: Store.rangeSales(start, end), label: label };
  }

  function renderGreeting() {
    var now = new Date();
    var hour = now.getHours();
    var greeting =
      hour < 12 ? "Good morning" : hour < 18 ? "Good afternoon" : "Good evening";
    var firstName = (UI.user.name || "Admin").split(" ")[0];

    if ($("heroGreeting")) {
      $("heroGreeting").textContent = greeting + ", " + firstName + ".";
    }
    if ($("heroDate")) {
      $("heroDate").textContent = now.toLocaleDateString("en-PH", {
        weekday: "long",
        month: "long",
        day: "numeric",
      });
    }
  }

  function renderClock() {
    var host = $("heroClockTime");
    if (!host) return;
    host.textContent = new Date().toLocaleTimeString("en-PH", {
      hour: "numeric",
      minute: "2-digit",
      second: "2-digit",
      hour12: true,
    });
  }

  function renderSummary() {
    var host = $("heroSummary");
    if (!host) return;

    var low = Store.lowStockProducts();
    var outCount = low.filter(function (product) {
      return product.qty <= 0;
    }).length;
    var todaysTxns = Store.todayTransactions();
    var parts = [];

    if (todaysTxns.length) {
      parts.push(
        "You have made " +
          Store.money(Store.todaySales()) +
          " in sales today from " +
          todaysTxns.length +
          (todaysTxns.length === 1 ? " transaction." : " transactions.")
      );
    } else {
      parts.push("No sales have been recorded yet today.");
    }

    if (low.length) {
      parts.push(
        low.length +
          (low.length === 1 ? " product is" : " products are") +
          " running low" +
          (outCount ? " (" + outCount + " out of stock)" : "") +
          "."
      );
    } else {
      parts.push("All products are above their minimum stock level.");
    }

    host.textContent = parts.join(" ");
  }

  function trendHTML(value, suffix) {
    if (!isFinite(value)) {
      return '<span class="text-app-muted">' + suffix + "</span>";
    }
    var up = value >= 0;
    return (
      '<span class="trend ' +
      (up ? "trend--up" : "trend--down") +
      '"><i class="bi ' +
      (up ? "bi-arrow-up-right" : "bi-arrow-down-right") +
      '"></i>' +
      Math.abs(value).toFixed(1) +
      "%</span> " +
      '<span class="text-app-muted">' +
      suffix +
      "</span>"
    );
  }

  function renderStats() {
    var products = Store.data.products;
    var accounts = Store.data.accounts;
    var categories = {};
    products.forEach(function (product) {
      categories[product.category] = true;
    });

    $("statProducts").textContent = Store.number(products.length);
    $("metaProducts").innerHTML =
      Object.keys(categories).length +
      " categories &middot; " +
      products.filter(function (p) {
        return p.qty > 0;
      }).length +
      " in stock";

    $("statValue").textContent = Store.money(Store.inventoryValue());
    $("metaValue").innerHTML =
      '<span class="text-app-muted">At cost &middot; ' +
      Store.money(Store.retailValue()) +
      " if sold at retail</span>";

    var low = Store.lowStockProducts();
    var outCount = low.filter(function (p) {
      return p.qty <= 0;
    }).length;
    $("statLow").textContent = Store.number(low.length);
    $("metaLow").innerHTML =
      '<span class="text-app-muted">' +
      outCount +
      " out of stock &middot; " +
      (low.length - outCount) +
      " running low</span>";

    var today = Store.todaySales();
    var yesterday = Store.salesTotalBetween(
      Store.addDaysISO(-1),
      Store.addDaysISO(-1)
    );
    $("statSales").textContent = Store.money(today);
    if (yesterday > 0) {
      $("metaSales").innerHTML = trendHTML(
        ((today - yesterday) / yesterday) * 100,
        "vs yesterday"
      );
    } else {
      $("metaSales").innerHTML =
        '<span class="text-app-muted">No sales recorded yesterday</span>';
    }

    var activeAccounts = accounts.filter(function (account) {
      return account.status === "Active";
    });
    $("statStaff").textContent = Store.number(activeAccounts.length);
    $("metaStaff").innerHTML =
      '<span class="text-app-muted">' +
      activeAccounts.filter(function (a) {
        return a.role === "Admin";
      }).length +
      " admin &middot; " +
      activeAccounts.filter(function (a) {
        return a.role !== "Admin";
      }).length +
      " staff</span>";

    var todaysTxns = Store.todayTransactions();
    var completed = todaysTxns.filter(function (t) {
      return t.status === "Completed";
    }).length;
    $("statTxns").textContent = Store.number(todaysTxns.length);
    $("metaTxns").innerHTML =
      '<span class="text-app-muted">' +
      completed +
      " completed &middot; " +
      (todaysTxns.length - completed) +
      " pending</span>";

    renderSparklines(low);
  }

  function renderSparklines(low) {
    var week = Store.weekSales(7).map(function (row) {
      return row.value;
    });
    var txnCounts = Store.weekSales(7).map(function (row) {
      return Store.data.transactions.filter(function (t) {
        return t.date.slice(0, 10) === row.date;
      }).length;
    });

    if ($("sparkProducts")) {
      $("sparkProducts").innerHTML = Charts.sparkline(
        [7, 8, 8, 9, 9, 10, Store.data.products.length],
        { color: "var(--gq-emerald)" }
      );
    }
    if ($("sparkValue")) {
      $("sparkValue").innerHTML = Charts.sparkline(
        [55, 58, 56, 61, 63, 62, 67],
        { color: "var(--gq-blue)" }
      );
    }
    if ($("sparkLow")) {
      $("sparkLow").innerHTML = Charts.sparkline(
        [2, 3, 3, 4, 4, low.length, low.length],
        { color: "var(--gq-amber)" }
      );
    }
    if ($("sparkSales")) {
      $("sparkSales").innerHTML = Charts.sparkline(week, {
        color: "var(--gq-emerald)",
      });
    }
    if ($("sparkStaff")) {
      $("sparkStaff").innerHTML = Charts.sparkline(
        [4, 4, 5, 5, 5, 6, 6],
        { color: "var(--gq-violet)" }
      );
    }
    if ($("sparkTxns")) {
      $("sparkTxns").innerHTML = Charts.sparkline(txnCounts, {
        color: "var(--gq-rose)",
      });
    }
  }

  function renderPayments(range) {
    var totals = {};
    Store.salesBetween(range.start, range.end).forEach(function (txn) {
      var method = txn.payment || "Cash";
      totals[method] = (totals[method] || 0) + Number(txn.total || 0);
    });

    var segments = Object.keys(totals)
      .sort(function (a, b) {
        return totals[b] - totals[a];
      })
      .map(function (method, index) {
        return {
          label: method,
          value: totals[method],
          color: Charts.palette[index % Charts.palette.length],
          display: Store.money(totals[method]),
        };
      });

    var total = segments.reduce(function (sum, row) {
      return sum + row.value;
    }, 0);

    if ($("paymentSub")) {
      $("paymentSub").textContent = range.label;
    }
    if ($("paymentDonut")) {
      Charts.donutChart($("paymentDonut"), segments, {
        outsideLabels: true,
        centerLabel: "Revenue",
        centerValue: Store.money(total),
        ariaLabel: "Revenue by payment method for " + range.label,
      });
    }
    if ($("paymentLegend")) {
      $("paymentLegend").innerHTML = segments.length
        ? Charts.legendHTML(segments)
        : '<div class="text-app-muted" style="font-size:.8rem">No payments in this range.</div>';
    }
  }

  function renderCharts() {
    var range = salesRange();
    var rows = range.rows;
    var total = rows.reduce(function (sum, row) {
      return sum + row.value;
    }, 0);
    var dayCount = rows.length || 1;

    if ($("salesTotal")) {
      $("salesTotal").textContent = Store.money(total);
    }
    if ($("salesSub")) {
      $("salesSub").textContent = range.label;
    }
    if ($("salesAverage")) {
      var averageLabel = salesView.mode === "year" ? "Monthly average " : "Daily average ";
      var averageValue = salesView.mode === "year" ? total / 12 : total / dayCount;
      $("salesAverage").textContent = averageLabel + Store.money(averageValue);
    }
    if ($("salesChart")) {
      Charts.lineChart($("salesChart"), rows, {
        currency: true,
        symbol: Store.data.settings.currency,
        height: 268,
        ariaLabel: "Sales report \u00b7 " + range.label,
      });
    }

    renderPayments(range);
  }

  function renderTopProducts() {
    var host = $("topProductsList");
    if (!host) return;
    var products = Store.topProducts(30, 5);

    if (!products.length) {
      host.innerHTML =
        '<div class="empty-state"><div class="empty-state__icon"><i class="bi bi-trophy"></i></div><h6>No sales yet</h6><p>Top sellers will appear after sales are recorded.</p></div>';
      return;
    }

    host.innerHTML = products
      .map(function (row, index) {
        var product = Store.findProduct(row.productId);
        return (
          '<div class="rank-item">' +
          '<span class="rank-number">' +
          (index + 1) +
          "</span>" +
          '<img class="product-thumb" src="' +
          Store.productImage(product) +
          '" alt="" width="42" height="42">' +
          '<span class="rank-body">' +
          '<span class="rank-name">' +
          UI.escape(row.name) +
          "</span>" +
          '<span class="rank-meta">' +
          Store.number(row.qty) +
          " " +
          UI.escape(product ? product.unit : "units") +
          " sold</span>" +
          "</span>" +
          '<span class="rank-value">' +
          Store.money(row.revenue) +
          "</span>" +
          "</div>"
        );
      })
      .join("");
  }

  function renderRecentTransactions() {
    var tbody = $("recentTransactionsBody");
    if (!tbody) return;
    var rows = Store.data.transactions.slice(0, 5);

    if (!rows.length) {
      tbody.innerHTML = UI.emptyRow(
        5,
        "bi-receipt",
        "No transactions",
        "Sales recorded at the counter will appear here."
      );
      return;
    }

    tbody.innerHTML = rows
      .map(function (txn) {
        return (
          "<tr>" +
          "<td>" +
          '<a class="cell-strong" href="transactions.php?q=' +
          encodeURIComponent(txn.invoice) +
          '" style="color:var(--gq-text)">' +
          UI.escape(txn.invoice) +
          "</a>" +
          '<span class="cell-sub">' +
          Store.formatDateTime(txn.date) +
          "</span>" +
          "</td>" +
          '<td class="text-truncate" style="max-width:180px">' +
          UI.escape(txn.customer) +
          "</td>" +
          "<td>" +
          UI.escape(txn.cashier) +
          "</td>" +
          '<td class="text-end fw-650 text-tabular">' +
          Store.money(txn.total) +
          "</td>" +
          "<td>" +
          UI.statusBadge(
            txn.status,
            statusVariants[txn.status] || "neutral"
          ) +
          "</td>" +
          "</tr>"
        );
      })
      .join("");
  }

  function renderLowStock() {
    var host = $("lowStockList");
    if (!host) return;
    var products = Store.lowStockProducts();

    if (!products.length) {
      host.innerHTML =
        '<div class="alert-app"><i class="bi bi-check2-circle"></i><div>All products are above their minimum stock levels. Nice work.</div></div>';
      return;
    }

    host.innerHTML = products
      .map(function (product) {
        var state = Store.stockState(product);
        return (
          '<div class="alert-row">' +
          '<img class="product-thumb" src="' +
          Store.productImage(product) +
          '" alt="" width="42" height="42">' +
          '<div class="alert-row__body">' +
          '<span class="alert-row__name">' +
          UI.escape(product.name) +
          "</span>" +
          '<span class="alert-row__meta">' +
          UI.escape(product.sku) +
          " &middot; " +
          Store.number(product.qty) +
          " " +
          UI.escape(product.unit) +
          " left &middot; min " +
          Store.number(product.minStock) +
          "</span>" +
          "</div>" +
          UI.statusBadge(state.label, state.badge) +
          '<a class="btn btn-icon btn-icon--success" href="inventory.php?panel=in&product=' +
          encodeURIComponent(product.id) +
          '" aria-label="Stock in ' +
          UI.escape(product.name) +
          '" title="Stock in"><i class="bi bi-plus-lg"></i></a>' +
          "</div>"
        );
      })
      .join("");
  }

  function syncSalesControls() {
    document.querySelectorAll("[data-sales-mode]").forEach(function (button) {
      var active = button.getAttribute("data-sales-mode") === salesView.mode;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-pressed", active ? "true" : "false");
    });

    if ($("salesDatePick")) $("salesDatePick").hidden = salesView.mode !== "date";
    if ($("salesMonthPick")) $("salesMonthPick").hidden = salesView.mode !== "month";
    if ($("salesYearPick")) $("salesYearPick").hidden = salesView.mode !== "year";

    if ($("salesSpecificDate")) {
      $("salesSpecificDate").value = salesView.date;
      $("salesSpecificDate").max = Store.todayISO();
    }
    if ($("salesMonth")) {
      $("salesMonth").value = salesView.month;
      $("salesMonth").max = Store.todayISO().slice(0, 7);
    }
    if ($("salesYear")) {
      $("salesYear").value = salesView.year;
      $("salesYear").max = String(new Date().getFullYear());
    }
  }

  function bindSalesControls() {
    document.querySelectorAll("[data-sales-mode]").forEach(function (button) {
      button.addEventListener("click", function () {
        salesView.mode = button.getAttribute("data-sales-mode");
        syncSalesControls();
        renderCharts();
      });
    });

    if ($("salesSpecificDate")) {
      $("salesSpecificDate").addEventListener("change", function (event) {
        if (!event.target.value) return;
        salesView.date = event.target.value;
        renderCharts();
      });
    }
    if ($("salesMonth")) {
      $("salesMonth").addEventListener("change", function (event) {
        if (!event.target.value) return;
        salesView.month = event.target.value;
        renderCharts();
      });
    }
    if ($("salesYear")) {
      $("salesYear").addEventListener("change", function (event) {
        var value = Number(event.target.value);
        if (!value || value < 2000 || value > 2100) return;
        salesView.year = String(value);
        renderCharts();
      });
    }
  }

  function init() {
    renderGreeting();
    renderClock();
    renderStats();
    renderSummary();
    bindSalesControls();
    syncSalesControls();
    renderCharts();
    renderTopProducts();
    renderRecentTransactions();
    renderLowStock();

    window.setInterval(renderClock, 1000);

    document.addEventListener("guinhawa:themechange", function () {
      renderCharts();
      renderStats();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
