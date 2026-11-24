<?php require __DIR__ . '/../shared/guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00291c">
  <meta name="description" content="Guinhawa Poultry Supply business reports.">
  <title>Reports &middot; Guinhawa Admin</title>

  <script src="/guinhawa_system/shared/js/theme-init.js?v=cleanup-20261122-2"></script>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap5/css/bootstrap.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/css/style.css">
</head>
<body data-page="reports">
  <a class="skip-link" href="#mainContent">Skip to content</a>

  <div id="sidebarRoot"></div>
  <div id="offcanvasRoot"></div>

  <div class="app-main">
    <div id="topbarRoot"></div>

    <main class="page-content" id="mainContent">
      <div class="page-head">
        <div>
          <div class="page-eyebrow"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Administration</div>
          <h1 class="page-title">Reports</h1>
          <p class="page-sub">
            Sales, product performance, and stock health for any date range.
            All figures are computed from the live database records.
          </p>
        </div>
        <div class="page-actions">
          <button class="btn btn-outline-app" type="button" id="printReport">
            <i class="bi bi-printer" aria-hidden="true"></i> Print
          </button>
          <button class="btn btn-brand" type="button" id="exportDailySales">
            <i class="bi bi-download" aria-hidden="true"></i> Export sales CSV
          </button>
        </div>
      </div>

      <section class="panel mb-3">
        <div class="panel__body panel__body--filters">
          <div class="filter-bar">
            <div class="report-range" role="group" aria-label="Quick date range">
              <button class="btn" type="button" data-days="7">7 days</button>
              <button class="btn active" type="button" data-days="30">30 days</button>
              <button class="btn" type="button" data-days="90">90 days</button>
            </div>
            <div class="d-flex align-items-center gap-1">
              <input class="form-control" type="date" id="reportStart" aria-label="Report start date" style="max-width:150px">
              <span class="text-app-muted" style="font-size:.8rem">to</span>
              <input class="form-control" type="date" id="reportEnd" aria-label="Report end date" style="max-width:150px">
            </div>
            <span class="pill-count ms-auto" id="reportRangeLabel">&mdash;</span>
          </div>
        </div>
      </section>

      <div class="row g-3 mb-3">
        <div class="col-6 col-md-4 col-xl-2">
          <div class="report-tile">
            <div class="report-tile__label">Gross sales</div>
            <div class="report-tile__value" id="reportGross">&mdash;</div>
            <div class="report-tile__meta">Completed only</div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="report-tile">
            <div class="report-tile__label">Transactions</div>
            <div class="report-tile__value" id="reportTxns">&mdash;</div>
            <div class="report-tile__meta">Recorded sales</div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="report-tile">
            <div class="report-tile__label">Items sold</div>
            <div class="report-tile__value" id="reportItems">&mdash;</div>
            <div class="report-tile__meta">Total units</div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="report-tile">
            <div class="report-tile__label">Average sale</div>
            <div class="report-tile__value" id="reportAverage">&mdash;</div>
            <div class="report-tile__meta">Per transaction</div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="report-tile">
            <div class="report-tile__label">Inventory value</div>
            <div class="report-tile__value" id="reportStockValue">&mdash;</div>
            <div class="report-tile__meta">Current at cost</div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="report-tile">
            <div class="report-tile__label">Low stock</div>
            <div class="report-tile__value" id="reportLowStock">&mdash;</div>
            <div class="report-tile__meta">Needs attention</div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-graph-up" aria-hidden="true"></i> Sales trend</h2>
                <p class="panel__sub" id="salesTrendSub">Daily completed sales</p>
              </div>
              <span class="badge-soft" id="salesTrendTotal">&mdash;</span>
            </div>
            <div class="panel__body">
              <div class="chart-wrap" id="reportSalesChart"></div>
              <div class="legend">
                <span class="legend-item"><span class="legend-dot" style="background:var(--gq-emerald)"></span> Daily gross sales</span>
                <span class="legend-item ms-auto text-app-muted" id="salesPeak">&mdash;</span>
              </div>
            </div>
          </section>
        </div>
        <div class="col-12 col-xl-4">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-wallet2" aria-hidden="true"></i> Payment mix</h2>
                <p class="panel__sub">Revenue by payment method</p>
              </div>
            </div>
            <div class="panel__body">
              <div id="reportPaymentDonut"></div>
              <div class="chart-legend-list mt-3" id="reportPaymentLegend"></div>
            </div>
          </section>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-bar-chart" aria-hidden="true"></i> Revenue by category</h2>
                <p class="panel__sub">Completed sales in the selected range</p>
              </div>
            </div>
            <div class="panel__body">
              <div class="chart-wrap" id="reportCategoryChart"></div>
            </div>
          </section>
        </div>
        <div class="col-12 col-xl-6">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-trophy" aria-hidden="true"></i> Top products</h2>
                <p class="panel__sub">Ranked by units sold</p>
              </div>
            </div>
            <div class="panel__body panel__body--flush">
              <div class="table-responsive">
                <table class="table table-app">
                  <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
                  <tbody id="topProductsBody"></tbody>
                </table>
              </div>
            </div>
          </section>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-table" aria-hidden="true"></i> Product performance</h2>
                <p class="panel__sub">Units, revenue, and share of total sales</p>
              </div>
              <button class="btn btn-outline-app btn-row" type="button" id="exportProductsReport">
                <i class="bi bi-download" aria-hidden="true"></i> CSV
              </button>
            </div>
            <div class="panel__body panel__body--flush">
              <div class="table-responsive">
                <table class="table table-app">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Category</th>
                      <th class="text-end">Units sold</th>
                      <th class="text-end">Revenue</th>
                      <th style="width:130px">Share</th>
                    </tr>
                  </thead>
                  <tbody id="productPerformanceBody"></tbody>
                </table>
              </div>
              <div class="table-foot">
                <span id="productPerformanceFoot">No sales in this range</span>
              </div>
            </div>
          </section>
        </div>
        <div class="col-12 col-xl-4">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-person-workspace" aria-hidden="true"></i> Cashier performance</h2>
                <p class="panel__sub">Completed sales handled</p>
              </div>
              <button class="btn btn-outline-app btn-row" type="button" id="exportCashierReport">
                <i class="bi bi-download" aria-hidden="true"></i> CSV
              </button>
            </div>
            <div class="panel__body panel__body--flush">
              <div class="table-responsive">
                <table class="table table-app">
                  <thead><tr><th>Cashier</th><th class="text-end">Sales</th><th class="text-end">Revenue</th></tr></thead>
                  <tbody id="cashierBody"></tbody>
                </table>
              </div>
            </div>
          </section>
        </div>
      </div>

      <section class="panel">
        <div class="panel__header">
          <div>
            <h2 class="panel__title"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Low stock report</h2>
            <p class="panel__sub">Current stock at or below minimum levels</p>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-app btn-row" type="button" id="exportLowStockReport">
              <i class="bi bi-download" aria-hidden="true"></i> CSV
            </button>
            <a class="btn btn-soft btn-row" href="inventory.php?status=low">Manage stock</a>
          </div>
        </div>
        <div class="panel__body panel__body--flush">
          <div class="table-responsive">
            <table class="table table-app">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Category</th>
                  <th class="text-end">On hand</th>
                  <th class="text-end">Minimum</th>
                  <th class="text-end">Suggested order</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="lowStockReportBody"></tbody>
            </table>
          </div>
        </div>
      </section>
    </main>

    <div id="footerRoot"></div>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="/guinhawa_system/shared/bootstrap5/js/bootstrap.bundle.min.js" defer></script>
  <script src="/guinhawa_system/shared/js/store.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/charts.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/app.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/reports.js?v=cleanup-20261122-2" defer></script>
</body>
</html>
