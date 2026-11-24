<?php require __DIR__ . '/../shared/guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00291c">
  <meta name="description" content="Guinhawa Poultry Supply product catalog.">
  <title>Products &middot; Guinhawa Admin</title>

  <script src="/guinhawa_system/shared/js/theme-init.js?v=cleanup-20261122-2"></script>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap5/css/bootstrap.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/css/style.css">
</head>
<body data-area="inventory" data-page="products">
  <a class="skip-link" href="#mainContent">Skip to content</a>

  <div id="sidebarRoot"></div>
  <div id="offcanvasRoot"></div>

  <div class="app-main">
    <div id="topbarRoot"></div>

    <main class="page-content" id="mainContent">
      <div class="page-head">
        <div>
          <div class="page-eyebrow"><i class="bi bi-box-seam" aria-hidden="true"></i> Catalog</div>
          <h1 class="page-title">Products</h1>
          <p class="page-sub">
            Feeds, livestock, health products, and farm equipment sold at the counter.
            This catalog is stored in the shared MySQL database.
          </p>
        </div>
        <div class="page-actions">
          <button class="btn btn-outline-app" type="button" id="exportProducts">
            <i class="bi bi-download" aria-hidden="true"></i> Export CSV
          </button>
          <button class="btn btn-brand" type="button" id="addProductBtn">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Add product
          </button>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-boxes" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Total products</span><span class="inventory-stat__value" id="statProductCount">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-tags" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Categories</span><span class="inventory-stat__value" id="statCategoryCount">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-cash-stack" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Retail value</span><span class="inventory-stat__value" id="statRetailValue">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Needs reorder</span><span class="inventory-stat__value" id="statNeedsReorder">&mdash;</span></div>
          </div>
        </div>
      </div>

      <section class="panel mb-3">
        <div class="panel__body panel__body--filters">
          <div class="filter-bar">
            <div class="search-field">
              <i class="bi bi-search" aria-hidden="true"></i>
              <input class="form-control" type="search" id="productSearch" placeholder="Search by name, SKU, or category" aria-label="Search products">
            </div>
            <select class="form-select" id="stockFilter" aria-label="Filter by stock status" style="max-width:170px">
              <option value="">All stock levels</option>
              <option value="ok">In stock</option>
              <option value="low">Low stock</option>
              <option value="out">Out of stock</option>
            </select>
            <select class="form-select" id="statusFilter" aria-label="Filter by product status" style="max-width:150px">
              <option value="">All statuses</option>
              <option value="Active">Active</option>
              <option value="Archived">Archived</option>
            </select>
            <button class="btn btn-outline-app" type="button" id="clearFilters">
              <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Clear
            </button>
            <span class="pill-count ms-auto" id="productResultCount">0 products</span>
          </div>
          <div class="category-chips" id="categoryChips" role="group" aria-label="Filter products by category"></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel__header">
          <div>
            <h2 class="panel__title"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Product catalog</h2>
            <p class="panel__sub">Click any column heading to sort</p>
          </div>
          <span class="demo-watermark"><span class="dot"></span> Live catalog</span>
        </div>
        <div class="panel__body panel__body--flush">
          <div class="table-responsive table-scroll">
            <table class="table table-app" id="productsTable">
              <thead>
                <tr>
                  <th class="sortable">Product <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Category <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable text-end">Selling price <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable text-end">Cost <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Stock <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Supplier <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Status <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="productsBody"></tbody>
            </table>
          </div>
          <div class="table-foot">
            <span id="productsFoot">Showing 0 of 0 products</span>
            <div class="d-flex align-items-center gap-2">
              <a class="panel-link" href="inventory.php">Manage stock <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>
      </section>
    </main>

    <div id="footerRoot"></div>
  </div>

  <!-- Add / edit product modal -->
  <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-3">
            <div class="modal-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
            <div>
              <h2 class="modal-title" id="productModalTitle">Add product</h2>
              <div class="text-app-muted" style="font-size:.8rem">Catalog details and opening stock</div>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-app" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="productForm" novalidate>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label" for="productName">Product name <span class="req">*</span></label>
                <input class="form-control" id="productName" name="name" type="text" placeholder="e.g. Layer Mash 50 kg" required>
                <div class="invalid-feedback">Enter a product name.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="productSku">SKU <span class="req">*</span></label>
                <input class="form-control" id="productSku" name="sku" type="text" placeholder="GQ-XXX-000" required>
                <div class="invalid-feedback">Enter a unique SKU.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="productCategory">Category <span class="req">*</span></label>
                <select class="form-select" id="productCategory" required></select>
                <div class="invalid-feedback">Choose a category.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="productUnit">Selling unit <span class="req">*</span></label>
                <select class="form-select" id="productUnit" required>
                  <option value="sack">Sack</option>
                  <option value="tray">Tray</option>
                  <option value="head">Head</option>
                  <option value="bottle">Bottle</option>
                  <option value="pack">Pack</option>
                  <option value="piece">Piece</option>
                  <option value="kg">Kilogram</option>
                  <option value="bag">Bag</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="productCost">Cost per unit <span class="req">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">&#8369;</span>
                  <input class="form-control" id="productCost" type="number" min="0" step="0.01" placeholder="0.00" required>
                </div>
                <div class="invalid-feedback">Enter the supplier cost.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="productPrice">Selling price <span class="req">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">&#8369;</span>
                  <input class="form-control" id="productPrice" type="number" min="0" step="0.01" placeholder="0.00" required>
                </div>
                <div class="invalid-feedback">Enter the selling price.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="productMinStock">Minimum stock</label>
                <input class="form-control" id="productMinStock" type="number" min="0" step="1" value="10">
                <div class="form-text">Trigger a low-stock alert at or below this level.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="productQty">Stock on hand</label>
                <input class="form-control" id="productQty" type="number" min="0" step="1" value="0">
                <div class="form-text" id="qtyHelp">Opening stock for this new product.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="productSupplier">Supplier</label>
                <select class="form-select" id="productSupplier">
                  <option value="">No supplier linked</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="productStatus">Catalog status</label>
                <select class="form-select" id="productStatus">
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="productExpiry">Expiry date</label>
                <input class="form-control" id="productExpiry" type="date">
                <div class="form-text">Leave blank for products without an expiry.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Product illustration</label>
                <div class="image-picker" id="imagePicker"></div>
              </div>
              <div class="col-12">
                <label class="form-label" for="productDescription">Description</label>
                <textarea class="form-control" id="productDescription" rows="2" placeholder="Short description shown to staff."></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-app" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-brand" id="saveProductBtn">
              <i class="bi bi-check2" aria-hidden="true"></i> Save product
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
  <script src="/guinhawa_system/shared/js/products.js?v=cleanup-20261122-2" defer></script>
</body>
</html>
