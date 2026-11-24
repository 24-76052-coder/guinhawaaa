<?php require __DIR__ . '/../shared/guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00291c">
  <meta name="description" content="Guinhawa Poultry Supply stock control.">
  <title>Inventory &middot; Guinhawa Admin</title>

  <script src="/guinhawa_system/shared/js/theme-init.js?v=cleanup-20261122-2"></script>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap5/css/bootstrap.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/css/style.css">
</head>
<body data-area="inventory" data-page="inventory">
  <a class="skip-link" href="#mainContent">Skip to content</a>

  <div id="sidebarRoot"></div>
  <div id="offcanvasRoot"></div>

  <div class="app-main">
    <div id="topbarRoot"></div>

    <main class="page-content" id="mainContent">
      <div class="page-head">
        <div>
          <div class="page-eyebrow"><i class="bi bi-archive" aria-hidden="true"></i> Operations</div>
          <h1 class="page-title">Inventory</h1>
          <p class="page-sub">
            Live stock levels for every product. Record deliveries and releases here —
            every movement is written to the inventory history ledger.
          </p>
        </div>
        <div class="page-actions">
          <button class="btn btn-outline-app" type="button" id="exportInventory">
            <i class="bi bi-download" aria-hidden="true"></i> Export CSV
          </button>
          <button class="btn btn-soft" type="button" id="stockOutBtn">
            <i class="bi bi-box-arrow-up" aria-hidden="true"></i> Stock out
          </button>
          <button class="btn btn-brand" type="button" id="stockInBtn">
            <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Stock in
          </button>
        </div>
      </div>

      <div class="inv-tabs" role="tablist" aria-label="Inventory sections">
        <button type="button" class="inv-tab is-active" id="invTabBtnStock" role="tab" aria-selected="true" aria-controls="invPaneStock" data-inv-tab="stock">
          <i class="bi bi-list-ul" aria-hidden="true"></i> Stock on hand
        </button>
        <button type="button" class="inv-tab" id="invTabBtnHistory" role="tab" aria-selected="false" aria-controls="invPaneHistory" data-inv-tab="history">
          <i class="bi bi-clock-history" aria-hidden="true"></i> Movement history
        </button>
      </div>

      <div id="invPaneStock" role="tabpanel" aria-labelledby="invTabBtnStock">
      <div class="row row-cols-2 row-cols-lg-3 row-cols-xl-5 g-3 mb-3">
        <div class="col">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-boxes" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Active SKUs</span><span class="inventory-stat__value" id="statSkus">&mdash;</span></div>
          </div>
        </div>
        <div class="col">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-stack" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Units on hand</span><span class="inventory-stat__value" id="statUnits">&mdash;</span></div>
          </div>
        </div>
        <div class="col">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Low stock</span><span class="inventory-stat__value" id="statLow">&mdash;</span></div>
          </div>
        </div>
        <div class="col">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-x-octagon" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Out of stock</span><span class="inventory-stat__value" id="statOut">&mdash;</span></div>
          </div>
        </div>
        <div class="col">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-cash-stack" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Stock value</span><span class="inventory-stat__value" id="statValue">&mdash;</span></div>
          </div>
        </div>
      </div>

      <section class="panel mb-3">
        <div class="panel__body panel__body--filters">
          <div class="filter-bar">
            <div class="search-field">
              <i class="bi bi-search" aria-hidden="true"></i>
              <input class="form-control" type="search" id="inventorySearch" placeholder="Search product name, SKU, or category" aria-label="Search inventory">
            </div>
            <select class="form-select" id="inventoryCategory" aria-label="Filter by category" style="max-width:190px">
              <option value="">All categories</option>
            </select>
            <select class="form-select" id="inventoryStatus" aria-label="Filter by stock status" style="max-width:170px">
              <option value="">All stock levels</option>
              <option value="ok">In stock</option>
              <option value="low">Low stock</option>
              <option value="out">Out of stock</option>
            </select>
            <button class="btn btn-outline-app" type="button" id="clearInventoryFilters">
              <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Clear
            </button>
            <span class="pill-count ms-auto" id="inventoryCount">0 items</span>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel__header">
          <div>
            <h2 class="panel__title"><i class="bi bi-clipboard-data" aria-hidden="true"></i> Stock ledger</h2>
            <p class="panel__sub">Balance updates immediately after each movement</p>
          </div>
          <a class="panel-link" href="?tab=history">Movement history <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="panel__body panel__body--flush">
          <div class="table-responsive table-scroll">
            <table class="table table-app" id="inventoryTable">
              <thead>
                <tr>
                  <th class="sortable">Product <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Category <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable text-end">On hand <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable text-end">Minimum <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th>Level</th>
                  <th class="sortable">Status <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Expiry <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="text-end">Movement</th>
                </tr>
              </thead>
              <tbody id="inventoryBody"></tbody>
            </table>
          </div>
          <div class="table-foot">
            <span id="inventoryFoot">Showing 0 of 0 items</span>
            <span class="d-inline-flex align-items-center gap-2">
              <span class="legend-chip"><span class="dot" style="background:var(--gq-emerald)"></span> Healthy</span>
              <span class="legend-chip"><span class="dot" style="background:var(--gq-amber)"></span> Low</span>
              <span class="legend-chip"><span class="dot" style="background:var(--gq-rose)"></span> Out</span>
            </span>
          </div>
        </div>
      </section>
      </div><!-- /invPaneStock -->

      <div id="invPaneHistory" role="tabpanel" aria-labelledby="invTabBtnHistory" hidden>
        <div class="page-actions mb-3">
          <button class="btn btn-outline-app" type="button" id="exportHistory">
            <i class="bi bi-download" aria-hidden="true"></i> Export history CSV
          </button>
        </div>

        <div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
          <div class="col">
            <div class="inventory-stat">
              <div class="inventory-stat__icon"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></div>
              <div><span class="inventory-stat__label">Movements logged</span><span class="inventory-stat__value" id="histStatMovements">&mdash;</span></div>
            </div>
          </div>
          <div class="col">
            <div class="inventory-stat">
              <div class="inventory-stat__icon"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i></div>
              <div><span class="inventory-stat__label">Units received</span><span class="inventory-stat__value" id="histStatIn">&mdash;</span></div>
            </div>
          </div>
          <div class="col">
            <div class="inventory-stat">
              <div class="inventory-stat__icon"><i class="bi bi-box-arrow-up" aria-hidden="true"></i></div>
              <div><span class="inventory-stat__label">Units released</span><span class="inventory-stat__value" id="histStatOut">&mdash;</span></div>
            </div>
          </div>
          <div class="col">
            <div class="inventory-stat">
              <div class="inventory-stat__icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></div>
              <div><span class="inventory-stat__label">Today&rsquo;s movements</span><span class="inventory-stat__value" id="histStatToday">&mdash;</span></div>
            </div>
          </div>
        </div>

        <section class="panel mb-3">
          <div class="panel__body panel__body--filters">
            <div class="filter-bar">
              <div class="search-field">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input class="form-control" type="search" id="historySearch" placeholder="Search product, SKU, reference, or staff" aria-label="Search history">
              </div>
              <select class="form-select" id="historyType" aria-label="Filter by movement type" style="max-width:170px">
                <option value="">All movement types</option>
                <option value="in">Stock in</option>
                <option value="out">Stock out</option>
                <option value="sale">Sales</option>
              </select>
              <div class="d-flex align-items-center gap-1">
                <input class="form-control" type="date" id="historyFrom" aria-label="From date" style="max-width:150px">
                <span class="text-app-muted" style="font-size:.8rem">to</span>
                <input class="form-control" type="date" id="historyTo" aria-label="To date" style="max-width:150px">
              </div>
              <button class="btn btn-outline-app" type="button" id="clearHistoryFilters">
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Clear
              </button>
              <span class="pill-count ms-auto" id="historyCount">0 movements</span>
            </div>
          </div>
        </section>

        <section class="panel">
          <div class="panel__header">
            <div>
              <h2 class="panel__title"><i class="bi bi-journal-code" aria-hidden="true"></i> Movement ledger</h2>
              <p class="panel__sub">Balances show the stock level before and after each movement</p>
            </div>
            <span class="demo-watermark"><span class="dot"></span> Immutable stock log</span>
          </div>
          <div class="panel__body panel__body--flush">
            <div class="table-responsive table-scroll">
              <table class="table table-app" id="historyTable">
                <thead>
                  <tr>
                    <th class="sortable">Date &amp; time <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="sortable">Product <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="sortable">Type <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="sortable text-end">Quantity <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    <th class="text-end">Balance</th>
                    <th>Reference</th>
                    <th>Supplier / reason</th>
                    <th class="sortable">Recorded by <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  </tr>
                </thead>
                <tbody id="historyBody"></tbody>
              </table>
            </div>
            <div class="table-foot">
              <span id="historyFoot">Showing 0 of 0 movements</span>
              <span class="d-inline-flex align-items-center gap-2">
                <span class="legend-chip"><span class="dot" style="background:var(--gq-emerald)"></span> Stock in</span>
                <span class="legend-chip"><span class="dot" style="background:var(--gq-amber)"></span> Stock out</span>
                <span class="legend-chip"><span class="dot" style="background:var(--gq-blue)"></span> Sale</span>
              </span>
            </div>
          </div>
        </section>
      </div>
    </main>

    <div id="footerRoot"></div>
  </div>

  <!-- Stock in side panel -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="stockInModal" aria-labelledby="stockInTitle">
    <div class="offcanvas-header">
      <div class="d-flex align-items-center gap-3">
        <div class="modal-icon"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i></div>
        <div>
          <h2 class="modal-title" id="stockInTitle">Record stock in</h2>
          <div class="text-app-muted" style="font-size:.8rem">Receive a delivery or return</div>
        </div>
      </div>
      <button type="button" class="btn-close btn-close-app" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form id="stockInForm" novalidate class="d-flex flex-column flex-grow-1">
      <div class="offcanvas-body flex-grow-1">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="stockInProduct">Product <span class="req">*</span></label>
                <select class="form-select" id="stockInProduct" required></select>
                <div class="invalid-feedback">Choose the product received.</div>
                <div class="form-text" id="stockInCurrent">Select a product to see its current balance.</div>
              </div>
              <div class="col-6">
                <label class="form-label" for="stockInQty">Quantity received <span class="req">*</span></label>
                <input class="form-control" id="stockInQty" type="number" min="1" step="1" value="1" required>
                <div class="invalid-feedback">Enter a quantity of at least 1.</div>
              </div>
              <div class="col-6">
                <label class="form-label" for="stockInSupplier">Supplier</label>
                <select class="form-select" id="stockInSupplier">
                  <option value="">No supplier</option>
                </select>
              </div>
              <div class="col-12 col-sm-6">
                <label class="form-label" for="stockInExpiry">Expiry date</label>
                <input class="form-control" id="stockInExpiry" type="date">
                <div class="form-text">Optional &middot; updates the product expiry.</div>
              </div>
              <div class="col-12 col-sm-6">
                <label class="form-label" for="stockInRef">Reference number</label>
                <input class="form-control" id="stockInRef" type="text" placeholder="Delivery receipt number">
              </div>
              <div class="col-12">
                <label class="form-label" for="stockInNotes">Notes</label>
                <textarea class="form-control" id="stockInNotes" rows="2" placeholder="Optional delivery notes."></textarea>
              </div>
              <div class="col-12">
                <div class="alert-app" id="stockInPreview">
                  <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                  <div>Choose a product and quantity to preview the new balance.</div>
                </div>
              </div>
            </div>
      </div>
      <div class="offcanvas-footer p-3 border-top">
        <button type="button" class="btn btn-outline-app" data-bs-dismiss="offcanvas">Cancel</button>
        <button type="submit" class="btn btn-brand"><i class="bi bi-check2" aria-hidden="true"></i> Confirm stock in</button>
      </div>
    </form>
  </div>

  <!-- Stock out side panel -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="stockOutModal" aria-labelledby="stockOutTitle">
    <div class="offcanvas-header">
      <div class="d-flex align-items-center gap-3">
        <div class="modal-icon modal-icon--warn"><i class="bi bi-box-arrow-up" aria-hidden="true"></i></div>
        <div>
          <h2 class="modal-title" id="stockOutTitle">Record stock out</h2>
          <div class="text-app-muted" style="font-size:.8rem">Remove stock with a reason</div>
        </div>
      </div>
      <button type="button" class="btn-close btn-close-app" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form id="stockOutForm" novalidate class="d-flex flex-column flex-grow-1">
      <div class="offcanvas-body flex-grow-1">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="stockOutProduct">Product <span class="req">*</span></label>
                <select class="form-select" id="stockOutProduct" required></select>
                <div class="invalid-feedback">Choose the product to release.</div>
                <div class="form-text" id="stockOutCurrent">Select a product to see its current balance.</div>
              </div>
              <div class="col-6">
                <label class="form-label" for="stockOutQty">Quantity removed <span class="req">*</span></label>
                <input class="form-control" id="stockOutQty" type="number" min="1" step="1" value="1" required>
                <div class="invalid-feedback">Enter a quantity of at least 1.</div>
              </div>
              <div class="col-6">
                <label class="form-label" for="stockOutReason">Reason <span class="req">*</span></label>
                <select class="form-select" id="stockOutReason" required>
                  <option value="Damaged goods">Damaged goods</option>
                  <option value="Expired stock">Expired stock</option>
                  <option value="Internal use">Internal use</option>
                  <option value="Stock correction">Stock correction</option>
                  <option value="Transfer to branch">Transfer to branch</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label" for="stockOutNotes">Notes</label>
                <textarea class="form-control" id="stockOutNotes" rows="2" placeholder="Explain this movement for the ledger."></textarea>
              </div>
              <div class="col-12">
                <div class="alert-app" id="stockOutPreview">
                  <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                  <div>Choose a product and quantity to preview the new balance.</div>
                </div>
              </div>
            </div>
      </div>
      <div class="offcanvas-footer p-3 border-top">
        <button type="button" class="btn btn-outline-app" data-bs-dismiss="offcanvas">Cancel</button>
        <button type="submit" class="btn btn-brand"><i class="bi bi-check2" aria-hidden="true"></i> Confirm stock out</button>
      </div>
    </form>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="/guinhawa_system/shared/bootstrap5/js/bootstrap.bundle.min.js" defer></script>
  <script src="/guinhawa_system/shared/js/store.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/charts.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/app.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/inventory.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/history.js?v=cleanup-20261122-2" defer></script>
</body>
</html>
