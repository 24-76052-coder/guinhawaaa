/* ==========================================================================
   GUINHAWA — Products catalog
   ========================================================================== */

(function (window, document) {
  "use strict";

  var Store = window.GuinhawaStore;
  var UI = window.GuinhawaUI;
  if (!Store || !UI) return;

  var $ = function (id) {
    return document.getElementById(id);
  };

  var IMAGES = [
    { file: "feed-sack.svg", label: "Feed sack" },
    { file: "egg-tray.svg", label: "Egg tray" },
    { file: "chick.svg", label: "Chick" },
    { file: "hen.svg", label: "Hen" },
    { file: "vitamins.svg", label: "Vitamins" },
    { file: "drinker.svg", label: "Drinker" },
    { file: "feeder.svg", label: "Feeder" },
    { file: "rice-hull.svg", label: "Rice hull" },
    { file: "generic.svg", label: "Generic" },
  ];

  var CATEGORY_IMAGE = {
    Accessories: "drinker.svg",
    "Cat Food": "feed-sack.svg",
    "Dog Food": "feed-sack.svg",
    Feeds: "feed-sack.svg",
    Medicine: "vitamins.svg",
  };

  var state = {
    search: UI.param("q"),
    category: "",
    stock: "",
    status: "",
  };

  var editingId = null;
  var imageTouched = false;
  var session = Store.getSession();
  var canManage = !session || session.role !== "Cashier";

  /* ------------------------------- Rendering ------------------------------ */

  function allProducts() {
    return (Store.data.products || []).concat(Store.data.archivedProducts || []);
  }

  /** Categories come from the catalog itself, never from a hardcoded list. */
  function categoryOptions() {
    var set = {};
    allProducts().forEach(function (product) {
      if (product.category) set[product.category] = true;
    });
    return Object.keys(set).sort(function (a, b) {
      return a.localeCompare(b);
    });
  }

  function supplierName(product) {
    var supplier = Store.findSupplier(product.supplierId);
    return supplier ? supplier.name : "Not linked";
  }

  function filteredProducts() {
    var q = state.search.trim().toLowerCase();
    return Store.data.products.filter(function (product) {
      if (state.category && product.category !== state.category) return false;
      if (state.status && product.status !== state.status) return false;
      if (state.stock && Store.stockState(product).key !== state.stock) return false;
      if (!q) return true;
      var haystack = [
        product.name,
        product.sku,
        product.category,
        product.description,
        supplierName(product),
      ]
        .join(" ")
        .toLowerCase();
      return haystack.indexOf(q) > -1;
    });
  }

  function renderStats() {
    var products = Store.data.products;
    var categories = {};
    products.forEach(function (p) {
      categories[p.category] = true;
    });
    $("statProductCount").textContent = Store.number(products.length);
    $("statCategoryCount").textContent = Store.number(Object.keys(categories).length);
    $("statRetailValue").textContent = Store.money(Store.retailValue());
    $("statNeedsReorder").textContent = Store.number(Store.lowStockCount());
  }

  function renderChips() {
    var host = $("categoryChips");
    if (!host) return;

    var counts = {};
    Store.data.products.forEach(function (product) {
      counts[product.category] = (counts[product.category] || 0) + 1;
    });

    var categories = categoryOptions();
    if (state.category && categories.indexOf(state.category) === -1) {
      state.category = "";
    }

    var chips = [
      '<button class="category-chip' + (state.category ? "" : " is-active") + '" type="button" data-category="" aria-pressed="' + (state.category ? "false" : "true") + '">' +
        '<i class="bi bi-grid-fill" aria-hidden="true"></i> All products' +
        '<span class="category-chip__count">' + Store.number(Store.data.products.length) + "</span>" +
        "</button>",
    ];

    categories.forEach(function (category) {
      var active = state.category === category;
      chips.push(
        '<button class="category-chip' + (active ? " is-active" : "") + '" type="button" data-category="' + UI.escape(category) + '" aria-pressed="' + (active ? "true" : "false") + '">' +
          UI.escape(category) +
          '<span class="category-chip__count">' + Store.number(counts[category] || 0) + "</span>" +
          "</button>"
      );
    });

    host.innerHTML = chips.join("");
  }

  function renderTable() {
    var tbody = $("productsBody");
    var rows = filteredProducts();

    if (!rows.length) {
      tbody.innerHTML = UI.emptyRow(
        8,
        "bi-box-seam",
        "No products match",
        "Adjust the search or filters, or add a new product to the catalog."
      );
    } else {
      tbody.innerHTML = rows
        .map(function (product) {
          var stock = Store.stockState(product);
          var statusVariant = product.status === "Active" ? "success" : "neutral";
          return (
            '<tr data-id="' + UI.escape(product.id) + '">' +
            "<td>" +
            '<div class="product-cell">' +
            '<img class="product-thumb" src="' + Store.productImage(product) + '" alt="" width="42" height="42">' +
            "<div>" +
            '<span class="cell-strong">' + UI.escape(product.name) + "</span>" +
            '<span class="cell-sub">' + UI.escape(product.sku) + " &middot; " + UI.escape(product.unit) + "</span>" +
            "</div></div></td>" +
            "<td>" + UI.escape(product.category) + "</td>" +
            '<td class="text-end text-tabular">' + Store.money(product.price) + "</td>" +
            '<td class="text-end text-tabular text-app-muted">' + Store.money(product.cost) + "</td>" +
            '<td data-sort-value="' + Number(product.qty) + '">' +
            '<span class="qty ' + (stock.key === "ok" ? "" : "qty--" + stock.key) + '">' +
            Store.number(product.qty) + " " + UI.escape(Store.unitLabel(product.unit, product.qty)) + "</span>" +
            '<div class="mt-1">' + UI.statusBadge(stock.label, stock.badge) + "</div>" +
            '<span class="cell-sub mt-1">' +
            (product.expiry ? "Expires " + UI.escape(Store.formatDate(product.expiry)) : "No expiry") +
            "</span>" +
            "</td>" +
            '<td class="text-truncate" style="max-width:170px">' + UI.escape(supplierName(product)) + "</td>" +
            "<td>" + UI.statusBadge(product.status, statusVariant) + "</td>" +
            '<td class="text-end text-nowrap">' +
            (canManage
              ? '<button class="btn btn-icon" type="button" data-edit="' + UI.escape(product.id) + '" title="Edit ' + UI.escape(product.name) + '" aria-label="Edit product"><i class="bi bi-pencil"></i></button> ' +
                '<a class="btn btn-icon btn-icon--success" href="' + UI.areaHref("inventory.php?panel=in&product=" + encodeURIComponent(product.id)) + '" title="Stock in" aria-label="Stock in"><i class="bi bi-box-arrow-in-down"></i></a> ' +
                '<button class="btn btn-icon btn-icon--danger" type="button" data-delete="' + UI.escape(product.id) + '" title="Delete product" aria-label="Delete product"><i class="bi bi-trash3"></i></button>'
              : '<span class="cell-sub">View only</span>') +
            "</td></tr>"
          );
        })
        .join("");
    }

    $("productResultCount").textContent =
      Store.number(rows.length) + (rows.length === 1 ? " product" : " products");
    $("productsFoot").textContent =
      "Showing " + Store.number(rows.length) + " of " + Store.number(Store.data.products.length) + " products";
  }

  function renderAll() {
    renderStats();
    renderChips();
    renderTable();
  }

  /* --------------------------------- Modal -------------------------------- */

  function buildImagePicker() {
    var host = $("imagePicker");
    host.innerHTML = IMAGES.map(function (image, index) {
      return (
        '<label class="image-choice">' +
        '<input type="radio" name="productImage" value="' + image.file + '"' + (index === IMAGES.length - 1 ? " checked" : "") + ">" +
        '<span class="image-choice__box">' +
        '<img src="/guinhawa_system/shared/assets/products/' + image.file + '" alt="">' +
        "<span>" + UI.escape(image.label) + "</span>" +
        "</span></label>"
      );
    }).join("");
  }

  function fillSelect(select, values, selected) {
    select.innerHTML = values
      .map(function (value) {
        return '<option value="' + UI.escape(value) + '"' + (value === selected ? " selected" : "") + ">" + UI.escape(value) + "</option>";
      })
      .join("");
  }

  function selectImage(file) {
    var input = document.querySelector('#imagePicker input[value="' + file + '"]');
    if (input) input.checked = true;
  }

  function syncUnitSteps() {
    var isKg = $("productUnit").value === "kg";
    ["productQty", "productMinStock"].forEach(function (id) {
      var field = $(id);
      if (!field) return;
      field.step = isKg ? "0.1" : "1";
    });
    if ($("qtyHelp")) {
      $("qtyHelp").textContent = editingId
        ? "Stock is adjusted from Inventory so every movement is logged."
        : isKg
          ? "Opening stock in kilograms. Decimals are allowed."
          : "Opening stock for this new product.";
    }
  }

  function openModal(productId) {
    editingId = productId || null;
    imageTouched = false;
    var form = $("productForm");
    form.reset();
    form.classList.remove("was-validated");
    $("productQty").disabled = false;

    fillSelect($("productCategory"), categoryOptions(), "Feeds");
    // Supplier select needs supplier ids as option values.
    $("productSupplier").innerHTML =
      '<option value="">No supplier linked</option>' +
      Store.data.suppliers
        .map(function (supplier) {
          return '<option value="' + UI.escape(supplier.id) + '">' + UI.escape(supplier.name) + "</option>";
        })
        .join("");

    if (productId) {
      var product = Store.findProduct(productId);
      if (!product) return;
      $("productModalTitle").textContent = "Edit product";
      $("productName").value = product.name;
      $("productSku").value = product.sku;
      fillSelect($("productCategory"), categoryOptions(), product.category);
      $("productUnit").value = product.unit;
      $("productCost").value = product.cost;
      $("productPrice").value = product.price;
      $("productMinStock").value = product.minStock;
      $("productQty").value = product.qty;
      $("productQty").disabled = true;
      $("qtyHelp").textContent = "Stock is adjusted from Inventory so every movement is logged.";
      $("productSupplier").value = product.supplierId || "";
      $("productStatus").value = product.status || "Active";
      $("productExpiry").value = product.expiry || "";
      $("productDescription").value = product.description || "";
      selectImage(product.image || "generic.svg");
      imageTouched = true;
    } else {
      $("productModalTitle").textContent = "Add product";
      $("productQty").value = 0;
      $("qtyHelp").textContent = "Opening stock for this new product.";
      $("productStatus").value = "Active";
      selectImage("generic.svg");
    }

    syncUnitSteps();
    bootstrap.Modal.getOrCreateInstance($("productModal")).show();
  }

  function validateForm() {
    var form = $("productForm");
    var valid = form.checkValidity();

    var name = $("productName").value.trim();
    var sku = $("productSku").value.trim();
    var editingProduct = editingId ? Store.findProduct(editingId) : null;
    var skuTaken = Store.data.products.some(function (product) {
      if (product.id === editingId) return false;
      if (editingProduct && product.productId === editingProduct.productId) return false;
      return product.sku.toLowerCase() === sku.toLowerCase();
    });

    if (!name) valid = false;
    if (!sku || skuTaken) valid = false;
    if (Number($("productCost").value) < 0) valid = false;
    if (Number($("productPrice").value) < 0) valid = false;
    if (Number($("productMinStock").value) < 0) valid = false;
    if (Number($("productQty").value) < 0) valid = false;

    form.classList.add("was-validated");

    if (skuTaken) {
      $("productSku").setCustomValidity("This SKU is already used.");
      $("productSku").classList.add("is-invalid");
      UI.toast("That SKU is already assigned to another product.", {
        type: "warning",
        title: "Duplicate SKU",
      });
    } else {
      $("productSku").setCustomValidity("");
      $("productSku").classList.remove("is-invalid");
    }

    return valid;
  }

  function collectForm() {
    var chosen = document.querySelector('#imagePicker input[name="productImage"]:checked');
    var isKg = $("productUnit").value === "kg";
    var whole = function (value) {
      var number = Math.max(0, Number(value) || 0);
      return isKg ? Store.roundQty(number) : Math.floor(number);
    };
    return {
      name: $("productName").value.trim(),
      sku: $("productSku").value.trim().toUpperCase(),
      category: $("productCategory").value,
      unit: $("productUnit").value,
      cost: Number($("productCost").value) || 0,
      price: Number($("productPrice").value) || 0,
      minStock: whole($("productMinStock").value),
      qty: whole($("productQty").value),
      supplierId: $("productSupplier").value,
      status: $("productStatus").value,
      expiry: $("productExpiry").value || "",
      sellByWeight: isKg,
      description: $("productDescription").value.trim(),
      image: chosen ? chosen.value : "generic.svg",
    };
  }

  function saveProduct(event) {
    event.preventDefault();
    if (!validateForm()) return;

    var values = collectForm();
    var button = $("saveProductBtn");
    button.disabled = true;

    var result = Store.saveProduct(values, editingId);
    button.disabled = false;

    if (!result.ok) {
      UI.toast(result.message, { type: "danger", title: "Product not saved" });
      return;
    }

    if (editingId) {
      UI.toast(values.name + " was updated.", { title: "Product saved" });
    } else {
      UI.toast(values.name + " was added to the catalog.", { title: "Product created" });
    }

    bootstrap.Modal.getOrCreateInstance($("productModal")).hide();
    renderAll();
    UI.refreshBadges();
  }

  function deleteProduct(id) {
    var product = Store.findProduct(id);
    if (!product) return;
    var linkedSales = Store.data.transactions.filter(function (txn) {
      return txn.items.some(function (item) {
        return item.productId === id;
      });
    }).length;

    UI.confirm({
      title: "Archive product?",
      message:
        "Remove " + product.name + " from the catalog? " +
        (linkedSales
          ? linkedSales + " past sale(s) keep their recorded name and price."
          : "This cannot be undone from this page."),
      confirmLabel: "Archive product",
      icon: "bi-trash3",
    }).then(function (confirmed) {
      if (!confirmed) return;
      var result = Store.archiveProduct(id);
      if (!result.ok) {
        UI.toast(result.message, { type: "danger", title: "Product not archived" });
        return;
      }
      renderAll();
      UI.refreshBadges();
      UI.toast(product.name + " was archived.", {
        type: "warning",
        title: "Product archived",
      });
    });
  }

  /* --------------------------------- Export ------------------------------- */

  function exportProducts() {
    var rows = filteredProducts().map(function (product) {
      return [
        product.name,
        product.sku,
        product.category,
        product.unit,
        product.cost.toFixed(2),
        product.price.toFixed(2),
        product.qty,
        product.minStock,
        Store.stockState(product).label,
        product.status,
        supplierName(product),
      ];
    });

    UI.exportCSV(
      "guinhawa-products-" + Store.todayISO() + ".csv",
      ["Product", "SKU", "Category", "Unit", "Cost", "Selling price", "Stock", "Minimum stock", "Stock status", "Catalog status", "Supplier"],
      rows
    );
    UI.toast("Product CSV exported.", { type: "info", title: "Export ready" });
  }

  /* --------------------------------- Events ------------------------------- */

  function bindEvents() {
    var debouncedRender = UI.debounce(function () {
      renderTable();
    }, 160);

    $("productSearch").addEventListener("input", function (event) {
      state.search = event.target.value;
      debouncedRender();
    });

    if ($("categoryChips")) {
      $("categoryChips").addEventListener("click", function (event) {
        var chip = event.target.closest("[data-category]");
        if (!chip) return;
        state.category = chip.getAttribute("data-category") || "";
        renderChips();
        renderTable();
      });
    }

    $("stockFilter").addEventListener("change", function (event) {
      state.stock = event.target.value;
      renderTable();
    });

    $("statusFilter").addEventListener("change", function (event) {
      state.status = event.target.value;
      renderTable();
    });

    $("clearFilters").addEventListener("click", function () {
      state = { search: "", category: "", stock: "", status: "" };
      $("productSearch").value = "";
      $("stockFilter").value = "";
      $("statusFilter").value = "";
      renderChips();
      renderTable();
    });

    $("addProductBtn").addEventListener("click", function () {
      openModal(null);
    });

    $("exportProducts").addEventListener("click", exportProducts);

    $("productForm").addEventListener("submit", saveProduct);

    $("productCategory").addEventListener("change", function (event) {
      if (editingId || imageTouched) return;
      selectImage(CATEGORY_IMAGE[event.target.value] || "generic.svg");
    });

    $("productUnit").addEventListener("change", syncUnitSteps);

    $("imagePicker").addEventListener("change", function () {
      imageTouched = true;
    });

    $("productsBody").addEventListener("click", function (event) {
      var editButton = event.target.closest("[data-edit]");
      if (editButton) {
        openModal(editButton.getAttribute("data-edit"));
        return;
      }
      var deleteButton = event.target.closest("[data-delete]");
      if (deleteButton) {
        deleteProduct(deleteButton.getAttribute("data-delete"));
      }
    });

    $("productModal").addEventListener("hidden.bs.modal", function () {
      editingId = null;
      $("productForm").classList.remove("was-validated");
    });
  }

  /* ---------------------------------- Init -------------------------------- */

  function init() {
    buildImagePicker();
    renderAll();
    bindEvents();

    if (!canManage) {
      var addButton = $("addProductBtn");
      if (addButton) addButton.classList.add("d-none");
    }

    $("productSearch").value = state.search;

    if (canManage && UI.param("new") === "1") {
      window.setTimeout(function () {
        openModal(null);
      }, 200);
    }

    if (state.search) {
      // Re-apply so the initial query is highlighted in the count.
      renderTable();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
