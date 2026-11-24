<?php require __DIR__ . '/../shared/guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00291c">
  <meta name="description" content="Guinhawa Poultry Supply supplier directory.">
  <title>Suppliers &middot; Guinhawa Admin</title>

  <script src="/guinhawa_system/shared/js/theme-init.js?v=cleanup-20261122-2"></script>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap5/css/bootstrap.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/css/style.css">
</head>
<body data-page="suppliers">
  <a class="skip-link" href="#mainContent">Skip to content</a>

  <div id="sidebarRoot"></div>
  <div id="offcanvasRoot"></div>

  <div class="app-main">
    <div id="topbarRoot"></div>

    <main class="page-content" id="mainContent">
      <div class="page-head">
        <div>
          <div class="page-eyebrow"><i class="bi bi-truck" aria-hidden="true"></i> Operations</div>
          <h1 class="page-title">Suppliers</h1>
          <p class="page-sub">
            Feed mills, hatcheries, veterinary suppliers, and packaging partners.
            Keep delivery contacts current for faster restocking.
          </p>
        </div>
        <div class="page-actions">
          <button class="btn btn-outline-app" type="button" id="exportSuppliers">
            <i class="bi bi-download" aria-hidden="true"></i> Export CSV
          </button>
          <button class="btn btn-brand" type="button" id="addSupplierBtn">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Add supplier
          </button>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-truck" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Suppliers</span><span class="inventory-stat__value" id="statSuppliers">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-patch-check" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Active partners</span><span class="inventory-stat__value" id="statActive">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Linked products</span><span class="inventory-stat__value" id="statLinked">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-tags" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Categories covered</span><span class="inventory-stat__value" id="statCategories">&mdash;</span></div>
          </div>
        </div>
      </div>

      <section class="panel mb-3">
        <div class="panel__body panel__body--filters">
          <div class="filter-bar">
            <div class="search-field">
              <i class="bi bi-search" aria-hidden="true"></i>
              <input class="form-control" type="search" id="supplierSearch" placeholder="Search name, contact, category, or email" aria-label="Search suppliers">
            </div>
            <select class="form-select" id="supplierCategory" aria-label="Filter by category" style="max-width:190px">
              <option value="">All categories</option>
            </select>
            <select class="form-select" id="supplierStatus" aria-label="Filter by status" style="max-width:150px">
              <option value="">All statuses</option>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
            <select class="form-select" id="supplierSort" aria-label="Sort suppliers" style="max-width:180px">
              <option value="name">Name A to Z</option>
              <option value="newest">Newest partner</option>
              <option value="products">Most products</option>
            </select>
            <button class="btn btn-outline-app" type="button" id="clearSupplierFilters">
              <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Clear
            </button>
            <span class="pill-count ms-auto" id="supplierCount">0 suppliers</span>
          </div>
        </div>
      </section>

      <div class="row g-3" id="supplierGrid"></div>
    </main>

    <div id="footerRoot"></div>
  </div>

  <!-- Add / edit supplier modal -->
  <div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-3">
            <div class="modal-icon"><i class="bi bi-truck" aria-hidden="true"></i></div>
            <div>
              <h2 class="modal-title" id="supplierModalTitle">Add supplier</h2>
              <div class="text-app-muted" style="font-size:.8rem">Delivery and contact details</div>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-app" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="supplierForm" novalidate>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-7">
                <label class="form-label" for="supplierName">Company name <span class="req">*</span></label>
                <input class="form-control" id="supplierName" type="text" placeholder="e.g. Batangas Feeds Corporation" required>
                <div class="invalid-feedback">Enter the supplier company name.</div>
              </div>
              <div class="col-md-5">
                <label class="form-label" for="supplierSince">Partner since</label>
                <input class="form-control" id="supplierSince" type="date">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="supplierContact">Contact person <span class="req">*</span></label>
                <input class="form-control" id="supplierContact" type="text" placeholder="Full name" required>
                <div class="invalid-feedback">Enter a contact person.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="supplierPhone">Phone <span class="req">*</span></label>
                <input class="form-control" id="supplierPhone" type="tel" placeholder="+63 917 000 0000" required>
                <div class="invalid-feedback">Enter a contact number.</div>
              </div>
              <div class="col-md-7">
                <label class="form-label" for="supplierEmail">Email <span class="req">*</span></label>
                <input class="form-control" id="supplierEmail" type="email" placeholder="orders@supplier.ph" required>
                <div class="invalid-feedback">Enter a valid email address.</div>
              </div>
              <div class="col-md-5">
                <label class="form-label" for="supplierTerms">Payment terms</label>
                <select class="form-select" id="supplierTerms">
                  <option value="Cash on delivery">Cash on delivery</option>
                  <option value="Net 7">Net 7</option>
                  <option value="Net 15">Net 15</option>
                  <option value="Net 30">Net 30</option>
                  <option value="Net 45">Net 45</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label" for="supplierAddress">Address <span class="req">*</span></label>
                <input class="form-control" id="supplierAddress" type="text" placeholder="Barangay, city, province" required>
                <div class="invalid-feedback">Enter the supplier address.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Supply categories <span class="req">*</span></label>
                <div class="check-chips" id="supplierCategories"></div>
                <div class="form-text" id="supplierCategoryHelp">Choose at least one category.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="supplierStatusInput">Partner status</label>
                <select class="form-select" id="supplierStatusInput">
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-app" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-brand" id="saveSupplierBtn">
              <i class="bi bi-check2" aria-hidden="true"></i> Save supplier
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="/guinhawa_system/shared/bootstrap5/js/bootstrap.bundle.min.js" defer></script>
  <script src="/guinhawa_system/shared/js/store.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/charts.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/app.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/suppliers.js?v=cleanup-20261122-2" defer></script>
</body>
</html>
