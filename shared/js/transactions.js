/* ==========================================================================
   GUINHAWA — Transactions & sales
   ========================================================================== */

(function (window, document) {
  "use strict";

  var Store = window.GuinhawaStore;
  var UI = window.GuinhawaUI;
  if (!Store || !UI) return;

  var $ = function (id) {
    return document.getElementById(id);
  };

  var on = function (id, eventName, handler) {
    var element = $(id);
    if (element) element.addEventListener(eventName, handler);
  };

  var STATUS_VARIANTS = {
    Completed: "success",
    Pending: "warning",
    Refunded: "neutral",
    Void: "danger",
  };

  var state = {
    search: UI.param("q"),
    status: "",
    payment: "",
    from: "",
    to: "",
  };

  var detailId = null;
  var saleLineSeq = 0;

  /* ------------------------------- Rendering ------------------------------ */

  function filteredTransactions() {
    var q = state.search.trim().toLowerCase();
    return Store.data.transactions.filter(function (txn) {
      if (state.status && txn.status !== state.status) return false;
      if (state.payment && txn.payment !== state.payment) return false;
      var day = txn.date.slice(0, 10);
      if (state.from && day < state.from) return false;
      if (state.to && day > state.to) return false;
      if (!q) return true;
      var items = txn.items
        .map(function (item) {
          return item.name + " " + item.sku;
        })
        .join(" ");
      return (
        txn.invoice + " " + txn.customer + " " + (txn.orderType || "Walk-in") + " " + txn.cashier + " " + txn.payment + " " + items
      )
        .toLowerCase()
        .indexOf(q) > -1;
    });
  }

  function renderStats() {
    var today = Store.todayTransactions();
    var todayCompleted = today.filter(function (txn) {
      return txn.status === "Completed";
    });
    var pending = Store.data.transactions.filter(function (txn) {
      return txn.status === "Pending";
    });
    var thirtyStart = Store.addDaysISO(-30);
    var last30 = Store.salesBetween(thirtyStart, Store.todayISO());
    var avg = last30.length ? Store.salesTotalBetween(thirtyStart, Store.todayISO()) / last30.length : 0;

    $("statTodaySales").textContent = Store.money(Store.todaySales());
    $("metaTodaySales").textContent =
      todayCompleted.length + " completed sale" + (todayCompleted.length === 1 ? "" : "s");
    $("statTodayCount").textContent = Store.number(today.length);
    $("metaTodayCount").textContent =
      today.length - todayCompleted.length + " pending or other";
    $("statAverage").textContent = Store.money(avg);
    $("statPending").textContent = Store.number(pending.length);
    $("metaPending").textContent = pending.length
      ? Store.money(
          pending.reduce(function (sum, txn) {
            return sum + txn.total;
          }, 0)
        ) + " awaiting settlement"
      : "All sales settled";
  }

  function renderTable() {
    var tbody = $("transactionsBody");
    var rows = filteredTransactions();

    if (!rows.length) {
      tbody.innerHTML = UI.emptyRow(
        10,
        "bi-receipt",
        "No transactions match",
        "Adjust the filters to see more sales."
      );
    } else {
      tbody.innerHTML = rows
        .map(function (txn) {
          return (
            '<tr data-id="' + UI.escape(txn.id) + '">' +
            '<td class="text-nowrap"><span class="cell-strong">' + UI.escape(txn.invoice) + "</span></td>" +
            '<td class="text-nowrap" data-sort-value="' + UI.escape(txn.date) + '">' + Store.formatDateTime(txn.date) + "</td>" +
            '<td class="text-truncate" style="max-width:180px">' + UI.escape(txn.customer) + "</td>" +
            '<td><span class="badge-soft">' + UI.escape(txn.orderType || "Walk-in") + "</span></td>" +
            '<td data-sort-value="' + Number(txn.itemCount) + '">' + Store.number(txn.itemCount) + " items</td>" +
            '<td class="text-nowrap">' + UI.escape(txn.cashier) + "</td>" +
            '<td class="text-nowrap">' + UI.escape(txn.payment) + "</td>" +
            '<td class="text-end text-tabular fw-650">' + Store.money(txn.total) + "</td>" +
            "<td>" + UI.statusBadge(txn.status, STATUS_VARIANTS[txn.status] || "neutral") + "</td>" +
            '<td class="text-end"><button class="btn btn-icon" type="button" data-view="' + UI.escape(txn.id) + '" title="View receipt" aria-label="View receipt"><i class="bi bi-eye"></i></button></td>' +
            "</tr>"
          );
        })
        .join("");
    }

    $("transactionCount").textContent =
      Store.number(rows.length) + (rows.length === 1 ? " sale" : " sales");
    $("transactionsFoot").textContent =
      "Showing " + Store.number(rows.length) + " of " + Store.number(Store.data.transactions.length) + " transactions";
  }

  function renderAll() {
    renderStats();
    renderTable();
  }

  /* -------------------------------- New sale ------------------------------ */

  function activeCashiers() {
    var cashiers = Store.data.accounts.filter(function (account) {
      return account.role === "Cashier" && account.status === "Active";
    });
    var admin = Store.getCurrentUser();
    if (admin) cashiers.unshift(admin);
    return cashiers;
  }

  function productOptions() {
    return Store.data.products
      .slice()
      .sort(function (a, b) {
        return a.name.localeCompare(b.name);
      })
      .map(function (product) {
        return (
          '<option value="' + UI.escape(product.id) + '" data-price="' + Number(product.price) + '"' +
          (product.qty <= 0 ? " disabled" : "") +
          ">" + UI.escape(product.name) + " — " + Store.number(product.qty) + " " + UI.escape(Store.unitLabel(product.unit, product.qty)) + " left</option>"
        );
      })
      .join("");
  }

  function customerOptions() {
    var names = {};
    Store.data.transactions.forEach(function (txn) {
      names[txn.customer] = true;
    });
    names["Walk-in Customer"] = true;
    return Object.keys(names)
      .sort()
      .map(function (name) {
        return '<option value="' + UI.escape(name) + '"></option>';
      })
      .join("");
  }

  function addSaleLine(productId) {
    var host = $("saleLines");
    var empty = host.querySelector(".sale-line--empty");
    if (empty) empty.remove();

    saleLineSeq++;
    var row = document.createElement("div");
    row.className = "sale-line sale-line--edit";
    row.setAttribute("data-line", String(saleLineSeq));
    row.innerHTML =
      '<select class="form-select sale-product" aria-label="Product">' +
      '<option value="">Choose product…</option>' +
      productOptions() +
      "</select>" +
      '<input class="form-control sale-qty" type="number" min="1" step="1" value="1" aria-label="Quantity">' +
      '<span class="sale-line__price">&#8369;0.00</span>' +
      '<button class="btn btn-icon btn-icon--danger" type="button" data-remove-line title="Remove line" aria-label="Remove line"><i class="bi bi-x-lg"></i></button>';
    host.appendChild(row);

    var select = row.querySelector(".sale-product");
    if (productId) select.value = productId;

    select.addEventListener("change", function () {
      var product = Store.findProduct(select.value);
      var qtyInput = row.querySelector(".sale-qty");
      if (product && product.sellByWeight) {
        qtyInput.step = "0.1";
        qtyInput.min = "0.1";
      } else {
        qtyInput.step = "1";
        qtyInput.min = "1";
      }
      updateTotals();
    });
    row.querySelector(".sale-qty").addEventListener("input", updateTotals);
    row.querySelector("[data-remove-line]").addEventListener("click", function () {
      row.remove();
      ensureSaleLines();
      updateTotals();
    });

    updateTotals();
  }

  function ensureSaleLines() {
    var host = $("saleLines");
    if (!host.querySelector("[data-line]")) {
      host.innerHTML = '<div class="sale-line sale-line--empty">No products added yet. Use “Add another product”.</div>';
    }
  }

  function collectLines() {
    return Array.prototype.slice
      .call(document.querySelectorAll("#saleLines [data-line]"))
      .map(function (row) {
        var productId = row.querySelector(".sale-product").value;
        var qty = Store.roundQty(Number(row.querySelector(".sale-qty").value) || 0);
        var product = productId ? Store.findProduct(productId) : null;
        return { row: row, productId: productId, qty: qty, product: product };
      })
      .filter(function (line) {
        return line.productId;
      });
  }

  function validateLines(lines) {
    var issues = [];
    var totals = {};

    if (!lines.length) {
      issues.push("Add at least one product to the sale.");
    }

    lines.forEach(function (line) {
      if (!line.product) {
        issues.push("One selected product no longer exists.");
        return;
      }
      if (!line.qty || line.qty <= 0) {
        issues.push("Enter a quantity above zero for " + line.product.name + ".");
      } else if (!line.product.sellByWeight && Math.round(line.qty) !== line.qty) {
        issues.push(
          line.product.name +
            " is counted in whole " +
            Store.unitLabel(line.product.unit, 2) +
            ". Enter a whole number."
        );
      }
      totals[line.productId] = (totals[line.productId] || 0) + Math.max(0, line.qty);
    });

    lines.forEach(function (line) {
      if (!line.product) return;
      var wanted = totals[line.productId] || 0;
      if (wanted > line.product.qty) {
        issues.push(
          line.product.name + " only has " + Store.number(line.product.qty) + " " + Store.unitLabel(line.product.unit, line.product.qty) + " left."
        );
      }
    });

    return { issues: issues.filter(function (value, index, list) { return list.indexOf(value) === index; }) };
  }

  function updateTotals() {
    var lines = collectLines();
    var subtotal = 0;

    lines.forEach(function (line) {
      var price = line.product ? line.product.price : 0;
      var lineTotal = price * Math.max(0, line.qty);
      subtotal += lineTotal;
      line.row.querySelector(".sale-line__price").textContent = Store.money(lineTotal);
      var qtyInput = line.row.querySelector(".sale-qty");
      if (line.product) {
        qtyInput.max = String(line.product.qty);
        qtyInput.step = line.product.sellByWeight ? "0.1" : "1";
        qtyInput.min = line.product.sellByWeight ? "0.1" : "1";
      }
      var over = line.product && line.qty > line.product.qty;
      qtyInput.classList.toggle("is-invalid", !!over);
    });

    var discount = Math.max(0, Number($("saleDiscount").value) || 0);
    var total = Math.max(0, subtotal - discount);

    $("saleSubtotal").textContent = Store.money(subtotal);
    $("saleTotal").textContent = Store.money(total);
    $("saleLineCount").textContent = lines.length + (lines.length === 1 ? " line" : " lines");

    var check = validateLines(lines);
    var errorBox = $("saleError");
    if (check.issues.length) {
      errorBox.classList.remove("d-none");
      errorBox.innerHTML =
        '<i class="bi bi-exclamation-octagon" aria-hidden="true"></i><div>' +
        check.issues.map(UI.escape).join("<br>") +
        "</div>";
    } else {
      errorBox.classList.add("d-none");
      errorBox.innerHTML = "";
    }
  }

  function openSaleModal() {
    if (!$("saleModal")) return;
    if (!Store.getCurrentUser()) return;
    var form = $("saleForm");
    form.reset();
    form.classList.remove("was-validated");
    saleLineSeq = 0;
    $("saleLines").innerHTML = "";
    $("saleDiscount").value = 0;
    $("saleCustomer").value = "Walk-in Customer";
    $("salePayment").value = "Cash";
    $("saleOrderType").value = "Walk-in";
    $("saleNotes").value = "";
    $("saleError").classList.add("d-none");

    $("customerOptions").innerHTML = customerOptions();
    $("saleCashier").innerHTML = activeCashiers()
      .map(function (account) {
        return (
          '<option value="' + UI.escape(account.id) + '"' +
          (account.id === Store.getCurrentUser().id ? " selected" : "") +
          ">" + UI.escape(account.name) + " (" + UI.escape(account.role) + ")</option>"
        );
      })
      .join("");

    addSaleLine(null);
    bootstrap.Modal.getOrCreateInstance($("saleModal")).show();
  }

  function submitSale(event) {
    event.preventDefault();
    var form = $("saleForm");
    var lines = collectLines();
    var check = validateLines(lines);

    if (!form.checkValidity() || check.issues.length) {
      form.classList.add("was-validated");
      updateTotals();
      if (check.issues.length) {
        UI.toast(check.issues[0], { type: "danger", title: "Sale not saved" });
      }
      return;
    }

    var payment = $("salePayment").value;
    var result = Store.addTransaction({
      customer: $("saleCustomer").value.trim() || "Walk-in Customer",
      orderType: $("saleOrderType").value,
      cashierId: $("saleCashier").value,
      payment: payment,
      discount: Math.max(0, Number($("saleDiscount").value) || 0),
      notes: $("saleNotes").value.trim(),
      status: "Completed",
      items: lines.map(function (line) {
        return {
          productId: line.productId,
          qty: line.qty,
          price: line.product.price,
        };
      }),
    });

    if (!result.ok) {
      UI.toast(result.message, { type: "danger", title: "Sale not saved" });
      return;
    }

    bootstrap.Modal.getOrCreateInstance($("saleModal")).hide();
    renderAll();
    UI.refreshBadges();
    UI.toast(
      result.transaction.invoice + " recorded for " + Store.money(result.transaction.total) + ".",
      { title: "Sale completed" }
    );

    window.setTimeout(function () {
      openDetail(result.transaction.id);
    }, 420);
  }

  /* ------------------------------- Detail view ---------------------------- */

  function receiptHTML(txn) {
    var settings = Store.data.settings;
    var lines = txn.items
      .map(function (item) {
        return (
          '<div class="receipt__row mb-1">' +
          "<span>" + UI.escape(item.name) + " <span class=\"text-app-muted\">&times;" + Store.number(item.qty) + " " + UI.escape(item.unit || "") + "</span></span>" +
          "<span>" + Store.money(item.qty * item.price) + "</span>" +
          "</div>"
        );
      })
      .join("");

    return (
      '<div class="receipt">' +
      '<div class="text-center mb-2">' +
      '<div class="fw-650" style="font-size:.92rem">' + UI.escape(settings.businessName) + "</div>" +
      '<div class="text-app-muted" style="font-size:.8rem">' + UI.escape(settings.address) + "</div>" +
      '<div class="text-app-muted" style="font-size:.8rem">' + UI.escape(settings.phone) + "</div>" +
      "</div>" +
      '<div class="receipt__divider"></div>' +
      '<div class="receipt__row"><span>Invoice</span><span>' + UI.escape(txn.invoice) + "</span></div>" +
      '<div class="receipt__row"><span>Date</span><span>' + Store.formatDateTime(txn.date) + "</span></div>" +
      '<div class="receipt__row"><span>Customer</span><span>' + UI.escape(txn.customer) + "</span></div>" +
      '<div class="receipt__row"><span>Order type</span><span>' + UI.escape(txn.orderType || "Walk-in") + "</span></div>" +
      '<div class="receipt__row"><span>Cashier</span><span>' + UI.escape(txn.cashier) + "</span></div>" +
      '<div class="receipt__row"><span>Payment</span><span>' + UI.escape(txn.payment) + "</span></div>" +
      '<div class="receipt__divider"></div>' +
      lines +
      '<div class="receipt__divider"></div>' +
      '<div class="receipt__row"><span>Subtotal</span><span>' + Store.money(txn.subtotal) + "</span></div>" +
      '<div class="receipt__row"><span>Discount</span><span>&minus;' + Store.money(txn.discount) + "</span></div>" +
      '<div class="receipt__row fw-650"><span>Total</span><span>' + Store.money(txn.total) + "</span></div>" +
      '<div class="receipt__divider"></div>' +
      '<div class="text-center text-app-muted" style="font-size:.8rem">' +
      UI.escape(settings.receiptFooter || "Thank you!") +
      "<br><span style=\"font-size:.8rem\">Sales receipt &middot; no tax computation</span>" +
      "</div>" +
      "</div>" +
      (txn.notes ? '<div class="alert-app mt-3"><i class="bi bi-sticky" aria-hidden="true"></i><div>' + UI.escape(txn.notes) + "</div></div>" : "")
    );
  }

  function openDetail(id) {
    var txn = Store.data.transactions.find(function (item) {
      return item.id === id;
    });
    if (!txn) return;
    detailId = id;

    $("txnDetailTitle").textContent = txn.invoice;
    $("txnDetailSub").innerHTML =
      Store.formatDateTime(txn.date) + " &middot; " + UI.statusBadge(txn.status, STATUS_VARIANTS[txn.status] || "neutral");
    $("txnDetailBody").innerHTML = receiptHTML(txn);

    var actions = $("txnDetailActions");
    actions.innerHTML = "";
    if (txn.status === "Pending") {
      actions.innerHTML =
        '<button type="button" class="btn btn-brand" data-mark="Completed"><i class="bi bi-check2-circle" aria-hidden="true"></i> Mark completed</button>';
    } else if (txn.status === "Completed") {
      actions.innerHTML =
        '<button type="button" class="btn btn-danger-soft" data-mark="Refunded"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Refund</button>';
    }

    bootstrap.Modal.getOrCreateInstance($("txnDetailModal")).show();
  }

  function markStatus(status) {
    if (!detailId) return;
    var txn = Store.data.transactions.find(function (item) {
      return item.id === detailId;
    });
    if (!txn) return;

    var message =
      status === "Refunded"
        ? "Mark " + txn.invoice + " as refunded? Stock is not restored automatically; adjust inventory separately if the goods come back."
        : "Mark " + txn.invoice + " as completed? Stock was already deducted when the sale was recorded.";

    UI.confirm({
      title: status === "Refunded" ? "Refund this sale?" : "Complete this sale?",
      message: message,
      confirmLabel: status === "Refunded" ? "Mark refunded" : "Mark completed",
      variant: status === "Refunded" ? "danger" : "brand",
      icon: status === "Refunded" ? "bi-arrow-counterclockwise" : "bi-check2-circle",
    }).then(function (confirmed) {
      if (!confirmed) return;
      Store.setTransactionStatus(txn.id, status);
      renderAll();
      openDetail(txn.id);
      UI.toast(txn.invoice + " is now " + status.toLowerCase() + ".", {
        type: status === "Refunded" ? "warning" : "success",
        title: "Status updated",
      });
    });
  }

  function printReceipt() {
    var txn = Store.data.transactions.find(function (item) {
      return item.id === detailId;
    });
    if (!txn) return;
    var settings = Store.data.settings;
    var rows = txn.items
      .map(function (item) {
        return (
          "<tr><td>" + UI.escape(item.name) + " x" + Store.number(item.qty) + " " + UI.escape(item.unit || "") + "</td><td style=\"text-align:right\">" +
          Store.money(item.qty * item.price) + "</td></tr>"
        );
      })
      .join("");

    var win = window.open("", "_blank", "width=460,height=680");
    if (!win) {
      UI.toast("Allow pop-ups to print the receipt.", { type: "warning" });
      return;
    }
    win.document.write(
      "<!DOCTYPE html><html><head><title>" + UI.escape(txn.invoice) + "</title><style>" +
      "body{font-family:Consolas,monospace;font-size:12px;color:#111;padding:18px;max-width:340px;margin:0 auto}" +
      "h1{font-size:14px;text-align:center;margin:0 0 2px}p{margin:2px 0;text-align:center;font-size:10px}" +
      "hr{border:0;border-top:1px dashed #999;margin:8px 0}table{width:100%;border-collapse:collapse;font-size:11px}" +
      "td{padding:2px 0;vertical-align:top}.row{display:flex;justify-content:space-between;font-size:11px}" +
      ".total{font-weight:700;font-size:13px}.foot{text-align:center;font-size:10px;color:#555;margin-top:10px}" +
      "</style></head><body>" +
      "<h1>" + UI.escape(settings.businessName) + "</h1>" +
      "<p>" + UI.escape(settings.address) + "</p><p>" + UI.escape(settings.phone) + "</p><hr>" +
      "<div class='row'><span>Invoice</span><span>" + UI.escape(txn.invoice) + "</span></div>" +
      "<div class='row'><span>Date</span><span>" + Store.formatDateTime(txn.date) + "</span></div>" +
      "<div class='row'><span>Customer</span><span>" + UI.escape(txn.customer) + "</span></div>" +
      "<div class='row'><span>Cashier</span><span>" + UI.escape(txn.cashier) + "</span></div>" +
      "<div class='row'><span>Payment</span><span>" + UI.escape(txn.payment) + "</span></div><hr>" +
      "<table>" + rows + "</table><hr>" +
      "<div class='row'><span>Subtotal</span><span>" + Store.money(txn.subtotal) + "</span></div>" +
      "<div class='row'><span>Discount</span><span>-" + Store.money(txn.discount) + "</span></div>" +
      "<div class='row total'><span>Total</span><span>" + Store.money(txn.total) + "</span></div><hr>" +
      "<p class='foot'>" + UI.escape(settings.receiptFooter || "Thank you!") + "<br>Sales receipt</p>" +
      "</body></html>"
    );
    win.document.close();
    win.focus();
    win.print();
  }

  /* --------------------------------- Export ------------------------------- */

  function exportTransactions() {
    var rows = filteredTransactions().map(function (txn) {
      return [
        txn.invoice,
        txn.date.replace("T", " "),
        txn.customer,
        txn.cashier,
        txn.itemCount,
        txn.payment,
        txn.subtotal.toFixed(2),
        txn.discount.toFixed(2),
        txn.total.toFixed(2),
        txn.status,
      ];
    });

    UI.exportCSV(
      "guinhawa-transactions-" + Store.todayISO() + ".csv",
      ["Invoice", "Date", "Customer", "Cashier", "Items", "Payment", "Subtotal", "Discount", "Total", "Status"],
      rows
    );
    UI.toast("Sales CSV exported.", {
      type: "info",
      title: "Export ready",
    });
  }

  /* --------------------------------- Events ------------------------------- */

  function bindEvents() {
    var debouncedRender = UI.debounce(renderTable, 160);

    $("transactionSearch").addEventListener("input", function (event) {
      state.search = event.target.value;
      debouncedRender();
    });

    $("transactionStatus").addEventListener("change", function (event) {
      state.status = event.target.value;
      renderTable();
    });

    $("transactionPayment").addEventListener("change", function (event) {
      state.payment = event.target.value;
      renderTable();
    });

    $("transactionFrom").addEventListener("change", function (event) {
      state.from = event.target.value;
      renderTable();
    });

    $("transactionTo").addEventListener("change", function (event) {
      state.to = event.target.value;
      renderTable();
    });

    $("clearTransactionFilters").addEventListener("click", function () {
      state = { search: "", status: "", payment: "", from: "", to: "" };
      $("transactionSearch").value = "";
      $("transactionStatus").value = "";
      $("transactionPayment").value = "";
      $("transactionFrom").value = "";
      $("transactionTo").value = "";
      renderTable();
    });

    on("newSaleBtn", "click", openSaleModal);
    on("exportTransactions", "click", exportTransactions);
    on("saleForm", "submit", submitSale);
    on("addSaleLine", "click", function () {
      addSaleLine(null);
    });
    on("saleDiscount", "input", updateTotals);

    $("transactionsBody").addEventListener("click", function (event) {
      var button = event.target.closest("[data-view]");
      if (button) openDetail(button.getAttribute("data-view"));
    });

    $("txnDetailActions").addEventListener("click", function (event) {
      var button = event.target.closest("[data-mark]");
      if (button) markStatus(button.getAttribute("data-mark"));
    });

    $("printReceipt").addEventListener("click", printReceipt);

    on("saleModal", "hidden.bs.modal", function () {
      $("saleForm").classList.remove("was-validated");
      $("saleError").classList.add("d-none");
    });

    $("txnDetailModal").addEventListener("hidden.bs.modal", function () {
      detailId = null;
    });
  }

  /* ---------------------------------- Init -------------------------------- */

  function init() {
    renderAll();
    bindEvents();
    $("transactionSearch").value = state.search;
    if (state.search) renderTable();

    if (UI.param("new") === "1" && $("saleModal")) {
      window.setTimeout(openSaleModal, 220);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
