<?php require __DIR__ . '/../shared/guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00291c">
  <meta name="description" content="Guinhawa Poultry Supply inventory staff dashboard.">
  <title>Inventory Dashboard &middot; Guinhawa Admin</title>

  <script src="/guinhawa_system/shared/js/theme-init.js?v=cleanup-20261122-2"></script>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap5/css/bootstrap.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/css/style.css">
</head>
<body data-area="inventory" data-page="inventory-dashboard">
  <a class="skip-link" href="#mainContent">Skip to content</a>

  <div id="sidebarRoot"></div>
  <div id="offcanvasRoot"></div>

  <div class="app-main">
    <div id="topbarRoot"></div>

    <main class="page-content" id="mainContent">
      <section class="hero mb-3 mb-lg-4">
        <div class="hero__main">
          <div class="hero__eyebrow">
            <i class="bi bi-clipboard-data" aria-hidden="true"></i>
            <span id="invHeroDate">Today</span>
          </div>
          <h1 class="hero__title" id="invHeroGreeting">Inventory control.</h1>
          <p class="hero__text" id="invHeroSummary">
            Here is a quick look at your stock, low-stock items, and expiring products.
          </p>
        </div>
        <div class="hero__side">
          <div class="hero-clock" role="timer" aria-label="Current local time">
            <span class="hero-clock__time" id="invHeroClockTime">--:--:--</span>
            <span class="hero-clock__hint"><span class="hero-clock__dot" aria-hidden="true"></span> Live local time</span>
          </div>
          <div class="hero__actions">
            <a class="btn-light-app" href="inventory.php?panel=in">
              <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Stock in
            </a>
            <a class="btn-ghost-app" href="inventory.php?panel=out">
              <i class="bi bi-box-arrow-up" aria-hidden="true"></i> Stock out
            </a>
          </div>
        </div>
      </section>

      <div class="row row-cols-2 row-cols-lg-3 row-cols-xl-5 g-3 mb-3 mb-lg-4">
        <div class="col">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-boxes" aria-hidden="true"></i></div>
            <div>
              <span class="inventory-stat__label">Total Products</span>
              <span class="inventory-stat__value" id="invStatProducts">&mdash;</span>
              <span class="inventory-stat__meta" id="invMetaProducts">in your catalog</span>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-stack" aria-hidden="true"></i></div>
            <div>
              <span class="inventory-stat__label">Available Stock</span>
              <span class="inventory-stat__value" id="invStatAvailable">&mdash;</span>
              <span class="inventory-stat__meta" id="invMetaAvailable">units on hand</span>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="inventory-stat inventory-stat--warning">
            <div class="inventory-stat__icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
            <div>
              <span class="inventory-stat__label">Low Stock</span>
              <span class="inventory-stat__value" id="invStatLow">&mdash;</span>
              <span class="inventory-stat__meta" id="invMetaLow">need restocking</span>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="inventory-stat inventory-stat--danger">
            <div class="inventory-stat__icon"><i class="bi bi-x-octagon" aria-hidden="true"></i></div>
            <div>
              <span class="inventory-stat__label">Out of Stock</span>
              <span class="inventory-stat__value" id="invStatOut">&mdash;</span>
              <span class="inventory-stat__meta" id="invMetaOut">cannot be sold</span>
            </div>
          </div>
        </div>
        <div class="col">
          <div class="inventory-stat inventory-stat--warning">
            <div class="inventory-stat__icon"><i class="bi bi-calendar2-x" aria-hidden="true"></i></div>
            <div>
              <span class="inventory-stat__label">Expiring Soon</span>
              <span class="inventory-stat__value" id="invStatExpiring">&mdash;</span>
              <span class="inventory-stat__meta" id="invMetaExpiring">within 30 days</span>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3 mb-lg-4">
        <div class="col-12 col-xl-8">
          <section class="panel">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-clock-history" aria-hidden="true"></i> Recent stock activities</h2>
                <p class="panel__sub">Deliveries, releases, and sales written to the ledger</p>
              </div>
              <a class="panel-link" href="inventory.php?tab=history">Full history <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="panel__body panel__body--flush">
              <div class="table-responsive">
                <table class="table table-app align-middle">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Product</th>
                      <th>Movement</th>
                      <th class="text-end">Qty</th>
                      <th class="text-end">Stock Left</th>
                      <th>Recorded By</th>
                    </tr>
                  </thead>
                  <tbody id="recentStockBody"></tbody>
                </table>
              </div>
            </div>
          </section>
        </div>
        <div class="col-12 col-xl-4">
          <div class="panel-stack">
            <section class="panel">
              <div class="panel__header">
                <div>
                  <h2 class="panel__title"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Stock alerts</h2>
                  <p class="panel__sub">Products that are low or out of stock</p>
                </div>
                <span class="badge-soft" id="lowStockCount">&mdash;</span>
              </div>
              <div class="panel__body" id="invLowList"></div>
            </section>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-xl-6">
          <section class="panel h-100">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-calendar2-x" aria-hidden="true"></i> Expiration watch</h2>
                <p class="panel__sub">Expired and expiring within 30 days</p>
              </div>
              <span class="badge-soft" id="expiryCount">&mdash;</span>
            </div>
            <div class="panel__body">
              <div class="expiry-list" id="expiringList"></div>
            </div>
            <div class="panel__footer">
              <p class="panel__sub m-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Check dates before selling. Record expired items as stock out.</p>
            </div>
          </section>
        </div>
        <div class="col-12 col-xl-6">
          <section class="panel h-100">
            <div class="panel__header">
              <div>
                <h2 class="panel__title"><i class="bi bi-bar-chart" aria-hidden="true"></i> Stock by category</h2>
                <p class="panel__sub">Distribution of units currently on hand</p>
              </div>
            </div>
            <div class="panel__body" id="categoryStock"></div>
            <div class="panel__footer">
              <p class="panel__sub m-0"><i class="bi bi-bar-chart" aria-hidden="true"></i> Bar length shows each category&rsquo;s share of total stock on hand.</p>
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
  <script src="/guinhawa_system/shared/js/inventory-dashboard.js?v=cleanup-20261122-2" defer></script>
</body>
</html>
