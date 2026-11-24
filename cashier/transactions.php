<?php require __DIR__ . '/../shared/guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00291c">
  <meta name="description" content="Guinhawa Poultry Supply sales and transactions.">
  <title>Transactions &middot; Guinhawa Admin</title>

  <script src="/guinhawa_system/shared/js/theme-init.js?v=cleanup-20261122-2"></script>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap5/css/bootstrap.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/css/style.css">
</head>
<body data-area="cashier" data-page="transactions">
  <a class="skip-link" href="#mainContent">Skip to content</a>

  <div id="sidebarRoot"></div>
  <div id="offcanvasRoot"></div>

  <div class="app-main">
    <div id="topbarRoot"></div>

    <main class="page-content" id="mainContent">
      <div class="page-head">
        <div>
          <div class="page-eyebrow"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i> Operations</div>
          <h1 class="page-title">Transactions &amp; Sales</h1>
          <p class="page-sub">
            Every counter sale, payment method, and receipt in one place.
            Recording a sale deducts stock and writes to inventory history automatically.
          </p>
        </div>
        <div class="page-actions">
          <button class="btn btn-outline-app" type="button" id="exportTransactions">
            <i class="bi bi-download" aria-hidden="true"></i> Export CSV
          </button>
          <a class="btn btn-outline-app" href="/guinhawa_system/cashier/index.php">
            <i class="bi bi-cart3" aria-hidden="true"></i> Open POS
          </a>
          <button class="btn btn-brand" type="button" id="newSaleBtn">
            <i class="bi bi-cart-plus" aria-hidden="true"></i> New sale
          </button>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
          <div class="summary-tile">
            <span class="summary-tile__label">Today&rsquo;s sales</span>
            <span class="summary-tile__value" id="statTodaySales">&mdash;</span>
            <span class="summary-tile__meta text-app-muted" id="metaTodaySales" style="font-size:.8rem"></span>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="summary-tile">
            <span class="summary-tile__label">Today&rsquo;s transactions</span>
            <span class="summary-tile__value" id="statTodayCount">&mdash;</span>
            <span class="summary-tile__meta text-app-muted" id="metaTodayCount" style="font-size:.8rem"></span>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="summary-tile">
            <span class="summary-tile__label">Average sale &middot; 30 days</span>
            <span class="summary-tile__value" id="statAverage">&mdash;</span>
            <span class="summary-tile__meta text-app-muted" style="font-size:.8rem">Completed transactions only</span>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="summary-tile">
            <span class="summary-tile__label">Pending payments</span>
            <span class="summary-tile__value" id="statPending">&mdash;</span>
            <span class="summary-tile__meta text-app-muted" id="metaPending" style="font-size:.8rem"></span>
          </div>
        </div>
      </div>

      <section class="panel mb-3">
        <div class="panel__body panel__body--filters">
          <div class="filter-bar">
            <div class="search-field">
              <i class="bi bi-search" aria-hidden="true"></i>
              <input class="form-control" type="search" id="transactionSearch" placeholder="Search invoice, customer, cashier, or product" aria-label="Search transactions">
            </div>
            <select class="form-select" id="transactionStatus" aria-label="Filter by status" style="max-width:160px">
              <option value="">All statuses</option>
              <option value="Completed">Completed</option>
              <option value="Pending">Pending</option>
              <option value="Refunded">Refunded</option>
              <option value="Void">Void</option>
            </select>
            <select class="form-select" id="transactionPayment" aria-label="Filter by payment method" style="max-width:170px">
              <option value="">All payments</option>
              <option value="Cash">Cash</option>
              <option value="GCash">GCash</option>
              <option value="Maya">Maya</option>
              <option value="Bank Transfer">Bank transfer</option>
              <option value="Store Credit">Store credit</option>
            </select>
            <div class="d-flex align-items-center gap-1">
              <input class="form-control" type="date" id="transactionFrom" aria-label="From date" style="max-width:150px">
              <span class="text-app-muted" style="font-size:.8rem">to</span>
              <input class="form-control" type="date" id="transactionTo" aria-label="To date" style="max-width:150px">
            </div>
            <button class="btn btn-outline-app" type="button" id="clearTransactionFilters">
              <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Clear
            </button>
            <span class="pill-count ms-auto" id="transactionCount">0 sales</span>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel__header">
          <div>
            <h2 class="panel__title"><i class="bi bi-journal-text" aria-hidden="true"></i> Sales journal</h2>
            <p class="panel__sub">Newest transactions first &middot; click a column to sort</p>
          </div>
          <a class="panel-link" href="/guinhawa_system/admin/reports.php" data-admin-only>Sales reports <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="panel__body panel__body--flush">
          <div class="table-responsive table-scroll">
            <table class="table table-app" id="transactionsTable">
              <thead>
                <tr>
                  <th class="sortable">Invoice <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Date &amp; time <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Customer <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th>Order type</th>
                  <th class="sortable">Items <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th>Cashier</th>
                  <th>Payment</th>
                  <th class="sortable text-end">Total <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Status <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="text-end">View</th>
                </tr>
              </thead>
              <tbody id="transactionsBody"></tbody>
            </table>
          </div>
          <div class="table-foot">
            <span id="transactionsFoot">Showing 0 of 0 transactions</span>
            <span class="text-app-muted">All amounts in Philippine pesos</span>
          </div>
        </div>
      </section>
    </main>

    <div id="footerRoot"></div>
  </div>

  <!-- New sale modal -->
  <div class="modal fade" id="saleModal" tabindex="-1" aria-labelledby="saleModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-3">
            <div class="modal-icon"><i class="bi bi-cart-plus" aria-hidden="true"></i></div>
            <div>
              <h2 class="modal-title" id="saleModalTitle">Record a new sale</h2>
              <div class="text-app-muted" style="font-size:.8rem">Stock is checked and deducted when you save</div>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-app" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="saleForm" novalidate>
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label" for="saleCustomer">Customer <span class="req">*</span></label>
                <input class="form-control" id="saleCustomer" type="text" list="customerOptions" placeholder="Walk-in Customer" required>
                <datalist id="customerOptions"></datalist>
                <div class="invalid-feedback">Enter a customer name or choose Walk-in Customer.</div>
              </div>
              <div class="col-md-3">
                <label class="form-label" for="saleCashier">Cashier</label>
                <select class="form-select" id="saleCashier"></select>
              </div>
              <div class="col-md-3">
                <label class="form-label" for="salePayment">Payment</label>
                <select class="form-select" id="salePayment">
                  <option value="Cash">Cash</option>
                  <option value="GCash">GCash</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label" for="saleOrderType">Order type</label>
                <select class="form-select" id="saleOrderType">
                  <option value="Walk-in">Walk-in</option>
                  <option value="Online">Online</option>
                </select>
              </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="form-section-title mb-0">Items sold</span>
              <span class="pill-count" id="saleLineCount">0 lines</span>
            </div>
            <div class="sale-lines mb-2" id="saleLines"></div>
            <button class="btn btn-soft btn-row mb-3" type="button" id="addSaleLine">
              <i class="bi bi-plus-lg" aria-hidden="true"></i> Add another product
            </button>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="saleNotes">Notes</label>
                <textarea class="form-control" id="saleNotes" rows="2" placeholder="Optional notes for this sale."></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="saleDiscount">Discount (&#8369;)</label>
                <input class="form-control" id="saleDiscount" type="number" min="0" step="0.01" value="0">
                <div class="form-text">Applied after the subtotal.</div>
              </div>
            </div>

            <div class="alert-app alert-app--danger mt-3 d-none" id="saleError"></div>

            <div class="sale-total mt-3">
              <div>
                <span class="text-app-muted" style="font-size:.8rem">Subtotal</span>
                <div class="text-tabular" id="saleSubtotal">&#8369;0.00</div>
              </div>
              <div class="text-end">
                <span class="text-app-muted" style="font-size:.8rem">Amount due</span>
                <strong id="saleTotal">&#8369;0.00</strong>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-app" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-brand" id="completeSaleBtn">
              <i class="bi bi-check2-circle" aria-hidden="true"></i> Complete sale
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Transaction detail modal -->
  <div class="modal fade" id="txnDetailModal" tabindex="-1" aria-labelledby="txnDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-3">
            <div class="modal-icon"><i class="bi bi-receipt" aria-hidden="true"></i></div>
            <div>
              <h2 class="modal-title" id="txnDetailTitle">Sale receipt</h2>
              <div class="text-app-muted" style="font-size:.8rem" id="txnDetailSub">&mdash;</div>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-app" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="txnDetailBody"></div>
        <div class="modal-footer justify-content-between">
          <div class="d-flex gap-2" id="txnDetailActions"></div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-app" id="printReceipt">
              <i class="bi bi-printer" aria-hidden="true"></i> Print
            </button>
            <button type="button" class="btn btn-brand" data-bs-dismiss="modal">Done</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="/guinhawa_system/shared/bootstrap5/js/bootstrap.bundle.min.js" defer></script>
  <script src="/guinhawa_system/shared/js/store.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/charts.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/app.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/transactions.js?v=cleanup-20261122-2" defer></script>
</body>
</html>
