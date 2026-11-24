<?php require __DIR__ . '/../shared/guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00291c">
  <meta name="description" content="Guinhawa Poultry Supply admin dashboard.">
  <title>Dashboard &middot; Guinhawa Admin</title>

  <script src="/guinhawa_system/shared/js/theme-init.js?v=cleanup-20261122-2"></script>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap5/css/bootstrap.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/css/style.css">
</head>
<body data-page="dashboard">
  <a class="skip-link" href="#mainContent">Skip to content</a>

  <div id="sidebarRoot"></div>
  <div id="offcanvasRoot"></div>

  <div class="app-main">
    <div id="topbarRoot"></div>

    <main class="page-content" id="mainContent">
      <section class="hero mb-3 mb-lg-4">
        <div class="hero__main">
          <div class="hero__eyebrow">
            <i class="bi bi-sunrise" aria-hidden="true"></i>
            <span id="heroDate">Today</span>
          </div>
          <h1 class="hero__title" id="heroGreeting">Welcome back.</h1>
          <p class="hero__text" id="heroSummary">
            Here is a quick look at what is happening in your store today.
          </p>
        </div>
        <div class="hero__side">
          <div class="hero-clock" role="timer" aria-label="Current local time">
            <span class="hero-clock__time" id="heroClockTime">--:--:--</span>
            <span class="hero-clock__hint"><span class="hero-clock__dot" aria-hidden="true"></span> Live local time</span>
          </div>
        </div>
      </section>

      <div class="row g-3 mb-3 mb-lg-4" id="statCards">
        <div class="col-12 col-sm-6 col-xl-4">
          <article class="stat-card">
            <div class="stat-card__top">
              <div class="stat-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
              <div class="stat-spark" id="sparkProducts" aria-hidden="true"></div>
            </div>
            <p class="stat-label">Products</p>
            <p class="stat-value" id="statProducts">&mdash;</p>
            <p class="stat-meta" id="metaProducts">Loading&hellip;</p>
          </article>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
          <article class="stat-card">
            <div class="stat-card__top">
              <div class="stat-icon stat-icon--blue"><i class="bi bi-cash-stack" aria-hidden="true"></i></div>
              <div class="stat-spark" id="sparkValue" aria-hidden="true"></div>
            </div>
            <p class="stat-label">Inventory Value</p>
            <p class="stat-value" id="statValue">&mdash;</p>
            <p class="stat-meta" id="metaValue">Loading&hellip;</p>
          </article>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
          <article class="stat-card">
            <div class="stat-card__top">
              <div class="stat-icon stat-icon--amber"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
              <div class="stat-spark" id="sparkLow" aria-hidden="true"></div>
            </div>
            <p class="stat-label">Low Stock</p>
            <p class="stat-value" id="statLow">&mdash;</p>
            <p class="stat-meta" id="metaLow">Loading&hellip;</p>
          </article>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
          <article class="stat-card">
            <div class="stat-card__top">
              <div class="stat-icon"><i class="bi bi-receipt" aria-hidden="true"></i></div>
              <div class="stat-spark" id="sparkSales" aria-hidden="true"></div>
            </div>
            <p class="stat-label">Sales Today</p>
            <p class="stat-value" id="statSales">&mdash;</p>
            <p class="stat-meta" id="metaSales">Loading&hellip;</p>
          </article>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
          <article class="stat-card">
            <div class="stat-card__top">
              <div class="stat-icon stat-icon--violet"><i class="bi bi-people" aria-hidden="true"></i></div>
              <div class="stat-spark" id="sparkStaff" aria-hidden="true"></div>
            </div>
            <p class="stat-label">Team Members</p>
            <p class="stat-value" id="statStaff">&mdash;</p>
            <p class="stat-meta" id="metaStaff">Loading&hellip;</p>
          </article>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
          <article class="stat-card">
            <div class="stat-card__top">
              <div class="stat-icon stat-icon--rose"><i class="bi bi-bag-check" aria-hidden="true"></i></div>
              <div class="stat-spark" id="sparkTxns" aria-hidden="true"></div>
            </div>
            <p class="stat-label">Transactions Today</p>
            <p class="stat-value" id="statTxns">&mdash;</p>
            <p class="stat-meta" id="metaTxns">Loading&hellip;</p>
          </article>
        </div>
      </div>

      <div class="row g-3 mb-3 mb-lg-4">
        <div class="col-12 col-xl-8">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-graph-up" aria-hidden="true"></i> Sales report</h2>
                <p class="panel__sub" id="salesSub">Completed sales for the last seven days</p>
              </div>
              <span class="badge-soft" id="salesTotal">&mdash;</span>
            </div>
            <div class="panel__body">
              <div class="sales-toolbar">
                <div class="seg-toggle" role="group" aria-label="Sales report range">
                  <button type="button" class="seg-toggle__btn" data-sales-mode="today" aria-pressed="false">Today</button>
                  <button type="button" class="seg-toggle__btn" data-sales-mode="date" aria-pressed="false">Specific date</button>
                  <button type="button" class="seg-toggle__btn is-active" data-sales-mode="rolling" aria-pressed="true">Last 7 days</button>
                  <button type="button" class="seg-toggle__btn" data-sales-mode="month" aria-pressed="false">Specific month</button>
                  <button type="button" class="seg-toggle__btn" data-sales-mode="year" aria-pressed="false">Yearly</button>
                </div>
                <div class="sales-picker" id="salesDatePick" hidden>
                  <label class="sales-week-pick" for="salesSpecificDate">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    <input type="date" id="salesSpecificDate" aria-label="Select a date">
                  </label>
                </div>
                <div class="sales-picker" id="salesMonthPick" hidden>
                  <label class="sales-week-pick" for="salesMonth">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    <input type="month" id="salesMonth" aria-label="Select a month">
                  </label>
                </div>
                <div class="sales-picker" id="salesYearPick" hidden>
                  <label class="sales-week-pick" for="salesYear">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    <input type="number" id="salesYear" min="2000" max="2100" step="1" aria-label="Select a year">
                  </label>
                </div>
              </div>
              <div class="chart-wrap" id="salesChart"></div>
              <div class="legend">
                <span class="legend-item"><span class="legend-dot" style="background:var(--gq-emerald)"></span> Daily sales</span>
                <span class="legend-item ms-auto text-app-muted" id="salesAverage">&mdash;</span>
              </div>
            </div>
          </section>
        </div>
        <div class="col-12 col-xl-4">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-wallet2" aria-hidden="true"></i> Payment breakdown</h2>
                <p class="panel__sub" id="paymentSub">Revenue by payment method</p>
              </div>
            </div>
            <div class="panel__body">
              <div id="paymentDonut"></div>
              <div class="chart-legend-list mt-3" id="paymentLegend"></div>
            </div>
          </section>
        </div>
      </div>

      <div class="row g-3 mb-3 mb-lg-4">
        <div class="col-12 col-xl-4">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-trophy" aria-hidden="true"></i> Top selling products</h2>
                <p class="panel__sub">By quantity sold &middot; last 30 days</p>
              </div>
            </div>
            <div class="panel__body">
              <div class="rank-list" id="topProductsList"></div>
            </div>
            <div class="panel__footer">
              <a class="panel-link" href="reports.php">View product report <i class="bi bi-arrow-right"></i></a>
            </div>
          </section>
        </div>

        <div class="col-12 col-xl-8">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i> Recent transactions</h2>
                <p class="panel__sub">Latest sales recorded at the counter</p>
              </div>
              <a class="panel-link" href="transactions.php">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="panel__body panel__body--flush">
              <div class="table-responsive">
                <table class="table table-app align-middle">
                  <thead>
                    <tr>
                      <th>Invoice</th>
                      <th>Customer</th>
                      <th>Cashier</th>
                      <th class="text-end">Total</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody id="recentTransactionsBody"></tbody>
                </table>
              </div>
            </div>
          </section>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-xl-8">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Low stock alerts</h2>
                <p class="panel__sub">Products at or below their minimum stock level</p>
              </div>
              <a class="panel-link" href="inventory.php?status=low">Manage stock <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="panel__body" id="lowStockList"></div>
          </section>
        </div>
        <div class="col-12 col-xl-4">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-lightning-charge" aria-hidden="true"></i> Quick actions</h2>
                <p class="panel__sub">Common counter and stock tasks</p>
              </div>
            </div>
            <div class="panel__body">
              <div class="quick-grid">
                <a class="quick-action" href="products.php?new=1"><i class="bi bi-plus-square" aria-hidden="true"></i> Add product</a>
                <a class="quick-action" href="attendance.php"><i class="bi bi-fingerprint" aria-hidden="true"></i> Attendance</a>
                <a class="quick-action" href="reports.php"><i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i> Reports</a>
              </div>
              <div class="alert-app mt-3">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <div>
                  Live workspace. Stock, sales, and account changes are saved to the shared
                  MySQL database. <a class="fw-650" href="settings.php">Manage settings</a>.
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </main>

    <div id="footerRoot"></div>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="/guinhawa_system/shared/bootstrap5/js/bootstrap.bundle.min.js" defer></script>
  <script src="/guinhawa_system/shared/js/store.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/charts.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/app.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/dashboard.js?v=cleanup-20261122-2" defer></script>
</body>
</html>
