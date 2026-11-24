<?php require __DIR__ . '/../shared/guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00291c">
  <meta name="description" content="Guinhawa Poultry Supply cashier point of sale.">
  <title>Cashier &amp; POS &middot; Guinhawa Admin</title>

  <script src="/guinhawa_system/shared/js/theme-init.js?v=cleanup-20261122-2"></script>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap5/css/bootstrap.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/css/style.css">
</head>
<body data-area="cashier" data-page="cashier-dashboard">
  <a class="skip-link" href="#mainContent">Skip to content</a>

  <div id="sidebarRoot"></div>
  <div id="offcanvasRoot"></div>

  <div class="app-main">
    <div id="topbarRoot"></div>

    <main class="page-content" id="mainContent">
      <div class="page-head">
        <div>
          <div class="page-eyebrow"><i class="bi bi-cart3" aria-hidden="true"></i> Cashier</div>
          <h1 class="page-title">Point of Sale</h1>
          <p class="page-sub">
            Search the catalog, build the cart, and complete the sale.
            Feeds are sold by kilogram &mdash; pick the exact weight when you add them.
          </p>
        </div>
        <div class="page-actions">
          <span class="badge-soft" id="cashierDate">&mdash;</span>
          <a class="btn btn-outline-app" href="attendance.php">
            <i class="bi bi-calendar2-check" aria-hidden="true"></i> My attendance
          </a>
          <a class="btn btn-outline-app" href="transactions.php">
            <i class="bi bi-receipt-cutoff" aria-hidden="true"></i> All transactions
          </a>
        </div>
      </div>

      <div class="pos-layout mb-3">
        <section class="panel" aria-label="Product catalog">
          <div class="pos-toolbar">
            <div class="search-field">
              <i class="bi bi-search" aria-hidden="true"></i>
              <input class="form-control" type="search" id="posSearch" placeholder="Search products, SKU, or category" aria-label="Search products">
            </div>
            <span class="pill-count" id="posCount">0 products</span>
          </div>
          <div class="pos-categories" id="posCategories" role="group" aria-label="Product categories"></div>
          <div class="pos-grid" id="posGrid"></div>
        </section>

        <aside class="panel pos-cart" aria-label="Current sale">
          <div class="pos-cart__head">
            <h2 class="pos-cart__title"><i class="bi bi-bag-check" aria-hidden="true"></i> Current sale</h2>
            <div class="d-flex align-items-center gap-2">
              <span class="pos-cart__count" id="posCartCount">0 items</span>
              <button class="btn btn-icon" type="button" id="posClearCart" aria-label="Clear cart" title="Clear cart">
                <i class="bi bi-trash3" aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <div class="pos-cart__customer">
            <div class="pos-customer-grid">
              <div>
                <label class="form-label" for="posCustomer">Customer</label>
                <div class="pos-input-icon">
                  <i class="bi bi-person" aria-hidden="true"></i>
                  <input class="form-control form-control-sm" id="posCustomer" type="text" list="posCustomerOptions" value="Walk-in Customer" placeholder="Walk-in Customer">
                </div>
                <datalist id="posCustomerOptions"></datalist>
              </div>
              <div>
                <label class="form-label">Order type</label>
                <div class="segmented segmented--order" id="posOrderTypeGroup" role="radiogroup" aria-label="Order type">
                  <button class="segmented__btn is-active" type="button" role="radio" aria-checked="true" data-value="Walk-in">
                    <i class="bi bi-shop" aria-hidden="true"></i> Walk-in
                  </button>
                  <button class="segmented__btn" type="button" role="radio" aria-checked="false" data-value="Online">
                    <i class="bi bi-globe2" aria-hidden="true"></i> Online
                  </button>
                </div>
                <input id="posOrderType" type="hidden" value="Walk-in">
              </div>
            </div>
          </div>

          <div class="pos-cart__items" id="posCartItems"></div>

          <div class="pos-summary">
            <div class="pos-summary__row">
              <span>Subtotal</span>
              <strong class="text-tabular" id="posSubtotal">&#8369;0.00</strong>
            </div>
            <div class="pos-summary__row">
              <label for="posDiscount">Discount</label>
              <span class="pos-discount-input">
                <span aria-hidden="true">&#8369;</span>
                <input class="pos-summary__discount" id="posDiscount" type="number" min="0" step="0.01" value="0" aria-label="Discount amount">
              </span>
            </div>
            <div class="pos-summary__row pos-summary__total">
              <span>Total</span>
              <strong class="text-tabular" id="posTotal">&#8369;0.00</strong>
            </div>
          </div>

          <div class="pos-payment">
            <div>
              <label class="form-label d-flex align-items-center justify-content-between">
                <span>Payment method</span>
                <span class="pos-payment__hint">Cash or GCash only</span>
              </label>
              <div class="pay-methods" id="posPaymentGroup" role="radiogroup" aria-label="Payment method">
                <button class="pay-method is-active" type="button" role="radio" aria-checked="true" data-value="Cash">
                  <span class="pay-method__icon"><i class="bi bi-cash-stack" aria-hidden="true"></i></span>
                  <span class="pay-method__body">
                    <span class="pay-method__name">Cash</span>
                    <span class="pay-method__meta">Counter payment</span>
                  </span>
                  <i class="bi bi-check-circle-fill pay-method__check" aria-hidden="true"></i>
                </button>
                <button class="pay-method" type="button" role="radio" aria-checked="false" data-value="GCash">
                  <span class="pay-method__icon"><i class="bi bi-phone" aria-hidden="true"></i></span>
                  <span class="pay-method__body">
                    <span class="pay-method__name">GCash</span>
                    <span class="pay-method__meta">Send via app</span>
                  </span>
                  <i class="bi bi-check-circle-fill pay-method__check" aria-hidden="true"></i>
                </button>
              </div>
              <input id="posPayment" type="hidden" value="Cash">
            </div>

            <div class="pos-cash-panel" id="posCashRow">
              <div class="pos-cash-panel__field">
                <label class="form-label" for="posCash">Cash received</label>
                <div class="pos-money-input">
                  <span aria-hidden="true">&#8369;</span>
                  <input class="form-control form-control-sm" id="posCash" type="number" min="0" step="0.01" placeholder="0.00">
                </div>
              </div>
              <div class="pos-change" id="posChange">
                <span>Change</span>
                <strong class="text-tabular" id="posChangeValue">&#8369;0.00</strong>
              </div>
            </div>

            <div class="pos-gcash-note d-none" id="posGcashNote">
              <i class="bi bi-shield-check" aria-hidden="true"></i>
              <span>Confirm the GCash payment in your app before completing the sale.</span>
            </div>

            <button class="btn btn-brand pos-checkout" type="button" id="posCheckout">
              <span><i class="bi bi-check2-circle" aria-hidden="true"></i> Complete sale</span>
              <span class="pos-checkout__total text-tabular" id="posCheckoutTotal">&#8369;0.00</span>
            </button>
          </div>
        </aside>
      </div>

      <div class="pos-mobile-bar" id="posMobileBar">
        <button class="pos-mobile-bar__jump" type="button" id="posMobileJump" aria-label="Review the current sale">
          <span class="pos-mobile-bar__icon">
            <i class="bi bi-bag-check" aria-hidden="true"></i>
            <span class="pos-mobile-bar__badge" id="posMobileCount">0</span>
          </span>
          <span class="pos-mobile-bar__body">
            <span class="pos-mobile-bar__label" id="posMobileLabel">Cart is empty</span>
            <span class="pos-mobile-bar__total text-tabular" id="posMobileTotal">&#8369;0.00</span>
          </span>
          <span class="pos-mobile-bar__cta">Review sale <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </button>
      </div>

      <section class="panel">
        <div class="panel__header">
          <div>
            <h2 class="panel__title"><i class="bi bi-clock-history" aria-hidden="true"></i> Recent sales</h2>
            <p class="panel__sub">Latest recorded transactions from this counter</p>
          </div>
          <a class="panel-link" href="transactions.php">Full journal <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="panel__body panel__body--flush">
          <div class="table-responsive">
            <table class="table table-app align-middle">
              <thead>
                <tr>
                  <th>Invoice</th>
                  <th>Date &amp; time</th>
                  <th>Customer</th>
                  <th>Order type</th>
                  <th>Cashier</th>
                  <th>Payment</th>
                  <th class="text-end">Total</th>
                  <th class="text-end">Receipt</th>
                </tr>
              </thead>
              <tbody id="cashierSalesBody"></tbody>
            </table>
          </div>
        </div>
      </section>
    </main>

    <div id="footerRoot"></div>
  </div>

  <!-- Weight selection modal (feeds) -->
  <div class="modal fade" id="weightModal" tabindex="-1" aria-labelledby="weightModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-3">
            <div class="modal-icon"><i class="bi bi-basket" aria-hidden="true"></i></div>
            <div>
              <h2 class="modal-title" id="weightModalTitle">Choose weight</h2>
              <div class="text-app-muted" style="font-size:.8rem">Sold by kilogram &middot; decimals allowed</div>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-app" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert-row mb-3" id="weightProduct">
            <img class="product-thumb" src="/guinhawa_system/assets/images/guinhawa-logo.png" alt="" width="46" height="46">
            <div class="alert-row__body">
              <span class="alert-row__name" id="weightName">&mdash;</span>
              <span class="alert-row__meta" id="weightMeta">&mdash;</span>
            </div>
          </div>

          <div class="form-section-title">Quick picks</div>
          <div class="weight-presets" id="weightPresets">
            <button class="weight-preset" type="button" data-weight="0.5">0.5 kg</button>
            <button class="weight-preset" type="button" data-weight="1">1 kg</button>
            <button class="weight-preset active" type="button" data-weight="1.5">1.5 kg</button>
            <button class="weight-preset" type="button" data-weight="2.5">2.5 kg</button>
          </div>

          <label class="form-label" for="weightInput">Custom weight (kg)</label>
          <input class="form-control" id="weightInput" type="number" min="0.1" step="0.1" value="1.5" inputmode="decimal">
          <div class="invalid-feedback" id="weightError">Enter a weight greater than zero and within available stock.</div>

          <div class="weight-summary mt-3">
            <span id="weightRate">&mdash; per kg</span>
            <strong class="text-tabular" id="weightTotal">&#8369;0.00</strong>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-app" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-brand" id="weightAdd">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Add to cart
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Receipt modal -->
  <div class="modal fade" id="posReceiptModal" tabindex="-1" aria-labelledby="posReceiptTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-3">
            <div class="modal-icon"><i class="bi bi-receipt" aria-hidden="true"></i></div>
            <div>
              <h2 class="modal-title" id="posReceiptTitle">Sale receipt</h2>
              <div class="text-app-muted" style="font-size:.8rem" id="posReceiptSub">&mdash;</div>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-app" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="posReceiptBody"></div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-outline-app" data-bs-dismiss="modal">New sale</button>
          <button type="button" class="btn btn-brand" id="posPrintReceipt">
            <i class="bi bi-printer" aria-hidden="true"></i> Print receipt
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="/guinhawa_system/shared/bootstrap5/js/bootstrap.bundle.min.js" defer></script>
  <script src="/guinhawa_system/shared/js/store.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/charts.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/app.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/cashier.js?v=cleanup-20261122-2" defer></script>
</body>
</html>
