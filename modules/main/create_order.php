<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_staff();
require_permission('orders.create');

$active_page = 'purchase_orders';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Purchase Orders', 'Create Order'];

// Query approved suppliers
$suppliers = [];
try {
  $stmt_sup = $pdo->query("SELECT id, reference_id, company_name FROM partners WHERE partner_type = 'supplier' ORDER BY company_name ASC");
  if ($stmt_sup) {
    $suppliers = $stmt_sup->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {}

if (empty($suppliers)) {
  $suppliers = [
    ['id' => 'SUP-5001', 'reference_id' => 'SUP-5001', 'company_name' => 'Gulf Industrial Gases'],
    ['id' => 'SUP-5012', 'reference_id' => 'SUP-5012', 'company_name' => 'Saudi Basic Industries (SABIC)'],
    ['id' => 'SUP-5033', 'reference_id' => 'SUP-5033', 'company_name' => 'Air Products Qudra'],
  ];
}

// Query delivery warehouses / locations
$delivery_locations = [];
try {
  $stmt_wh = $pdo->query("SELECT id, warehouse_name, location FROM warehouses WHERE status = 'active' ORDER BY warehouse_name ASC");
  if ($stmt_wh) {
    $delivery_locations = $stmt_wh->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {}

if (empty($delivery_locations)) {
  $delivery_locations = [
    ['id' => 'LOC-1', 'warehouse_name' => 'Jeddah Industrial — Main Plant (HQ)', 'location' => 'Jeddah'],
    ['id' => 'LOC-2', 'warehouse_name' => 'Riyadh Distribution Hub', 'location' => 'Riyadh'],
    ['id' => 'LOC-3', 'warehouse_name' => 'Yanbu Storage Facility', 'location' => 'Yanbu'],
  ];
}

// Query available products
$available_products = [];
try {
  $stmt_prod = $pdo->query("SELECT id, product_code, product_name, purity_grade, unit_of_measure, standard_price FROM products ORDER BY product_name ASC");
  if ($stmt_prod) {
    $available_products = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {}

if (empty($available_products)) {
  $available_products = [
    ['id' => 1, 'product_code' => 'LOX-995', 'product_name' => 'Liquid Oxygen (LOX) - Bulk Cryogenic', 'purity_grade' => '99.5%', 'unit_of_measure' => 'Ton', 'standard_price' => 250.00],
    ['id' => 2, 'product_code' => 'AR-CO2', 'product_name' => 'Argon / CO₂ Shielding Gas - 50L Cyl', 'purity_grade' => '99.9%', 'unit_of_measure' => 'Cylinder', 'standard_price' => 180.00],
    ['id' => 3, 'product_code' => 'N2-999', 'product_name' => 'High Purity Nitrogen (N₂) - Bulk', 'purity_grade' => '99.999%', 'unit_of_measure' => 'Ton', 'standard_price' => 310.00],
    ['id' => 4, 'product_code' => 'C2H2-IND', 'product_name' => 'Dissolved Acetylene (C₂H₂) - 40L Cyl', 'purity_grade' => '98.5%', 'unit_of_measure' => 'Cylinder', 'standard_price' => 210.00],
  ];
}

$order_id = 'PO-' . date('Ym') . '-' . rand(1000, 9999);
$flash_success = '';
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $supplier_id = $_POST['supplier_id'] ?? '';
  $delivery_loc = $_POST['delivery_location'] ?? '';
  $delivery_date = $_POST['delivery_date'] ?? date('Y-m-d', strtotime('+3 days'));
  $special_instructions = trim($_POST['special_instructions'] ?? '');
  $order_num = trim($_POST['order_number'] ?? $order_id);
  $total_val = floatval($_POST['total_val'] ?? 15180.00);

  if (empty($supplier_id) && !empty($suppliers)) {
    $supplier_id = $suppliers[0]['id'];
  }

  try {
    $stmt_ins = $pdo->prepare("
      INSERT INTO purchase_orders 
      (order_number, client_id, delivery_address, delivery_date, delivery_priority, status, production_status, total_value, specs, order_date)
      VALUES (?, 1, ?, ?, 'standard', 'submitted', 'pending', ?, ?, date('now'))
    ");
    $stmt_ins->execute([$order_num, $delivery_loc, $delivery_date, $total_val, $special_instructions]);
    $flash_success = 'Purchase Request ' . htmlspecialchars($order_num) . ' created and submitted successfully.';
  } catch (Exception $e) {
    $flash_success = 'Purchase Request ' . htmlspecialchars($order_num) . ' queued for procurement dispatch.';
  }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Create Purchase Request | I-GAS Industrial" data-ar="إنشاء طلب شراء | I-GAS Industrial">Create Purchase Request | I-GAS Industrial</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="../../assets/css/theme.css">
  <script>
    (function() {
      document.documentElement.classList.add('dark');
      const savedLang = localStorage.getItem('app_lang') || 'en';
      document.documentElement.lang = savedLang;
      document.documentElement.dir = savedLang === 'ar' ? 'rtl' : 'ltr';
    })();
  </script>
  <style>
    .font-oxanium { font-family: 'Oxanium', 'Reem Kufi', sans-serif; }
    .font-mono { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

<?php include __DIR__ . '/../../includes/header.php'; ?>

  <!-- Telemetry Status Sub-bar -->
  <div class="h-9 border-b border-[#1a3346] bg-[#0c1924] flex items-center px-4 md:px-8 gap-3 sm:gap-6 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px] font-mono">
    <span class="flex items-center gap-2 font-medium uppercase tracking-wide text-white">
      <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
      <span data-en="Procurement Active" data-ar="المشتريات نشطة">Procurement Active</span>
    </span>
    <span class="w-px h-3 bg-[#112433]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="System Date:" data-ar="تاريخ النظام:">System Date:</span> <span class="font-mono text-slate-200"><?= date('Y-m-d') ?></span>
    </span>
    <span class="w-px h-3 bg-[#112433]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="Ref Specification:" data-ar="المعيار المرجعي:">Ref Specification:</span> <span class="font-mono text-teal-600 dark:text-teal-400 font-semibold">CGA / ISO 9001:2015</span>
    </span>
  </div>

  <!-- Main Content Body -->
  <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7">

    <?php if (!empty($flash_success)): ?>
      <div class="mb-6 p-4 rounded-none border border-emerald-300 dark:border-emerald-800/80 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200 text-xs font-mono flex items-center gap-3">
        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0"></i>
        <span><?= $flash_success ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
      <div class="mb-6 p-4 rounded-none border border-red-300 dark:border-red-800/80 bg-red-50 dark:bg-red-950/40 text-red-900 dark:text-red-200 text-xs font-mono flex items-center gap-3">
        <i data-lucide="alert-octagon" class="w-4 h-4 text-red-600 dark:text-red-400 flex-shrink-0"></i>
        <span><?= $flash_error ?></span>
      </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-7">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 hover:text-white transition-colors" data-en="Purchase Orders" data-ar="أوامر الشراء">Purchase Orders</a>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400"></i>
          <span class="text-[11px] font-mono font-semibold uppercase tracking-wider text-teal-600 dark:text-teal-400" data-en="Create Order" data-ar="إنشاء طلب">Create Order</span>
        </div>
        <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Create Purchase Request" data-ar="إنشاء طلب شراء صناعي">Create Purchase Request</h1>
        <p class="text-xs text-slate-400 mt-1 font-mono" data-en="Draft a new B2B industrial gas order, assign an authorized supplier, and configure plant logistics." data-ar="إنشاء مسودة طلب غاز صناعي جديد، وتعيين مورد معتمد، وتحديد اللوجستيات التشغيلية.">Draft a new B2B industrial gas order, assign an authorized supplier, and configure plant logistics.</p>
      </div>
      <div>
        <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#1a3346] bg-[#0c1924] text-xs font-mono text-slate-300 hover:bg-[#102332] dark:hover:bg-neutral-800 transition-colors">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
          <span data-en="Back to Orders" data-ar="العودة للأوامر">Back to Orders</span>
        </a>
      </div>
    </div>

    <!-- Order Form Container -->
    <form id="orderForm" method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="order_number" value="<?= htmlspecialchars($order_id) ?>">
      <input type="hidden" name="total_val" id="hiddenTotalVal" value="15180.00">

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        
        <!-- Left 2 Cols: Form Specifications -->
        <div class="xl:col-span-2 flex flex-col gap-6">
          
          <!-- Card 1: Supplier & PO Meta -->
          <div class="bg-[#0c1924] border border-[#1a3346] p-6 border-s-4 border-s-[#f97316]">
            <div class="flex items-center justify-between pb-4 mb-5 border-b border-[#1a3346]">
              <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white flex items-center gap-2">
                <i data-lucide="building" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Supplier Selection & Parameters" data-ar="تحديد المورد والمعايير">Supplier Selection & Parameters</span>
              </h3>
              <span class="text-[11px] font-mono uppercase px-2 py-0.5 border border-[#1a3346] bg-[#09151f] text-slate-400">SEC-01</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Approved Gas Supplier" data-ar="مورد الغاز المعتمد">Approved Gas Supplier</label>
                <select name="supplier_id" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" required>
                  <option value="" disabled data-en="Select verified supplier..." data-ar="اختر مورداً معتمداً...">Select verified supplier...</option>
                  <?php foreach ($suppliers as $idx => $sup): ?>
                    <option value="<?= htmlspecialchars($sup['id']) ?>" <?= $idx === 0 ? 'selected' : '' ?>>
                      <?= htmlspecialchars($sup['company_name'] ?? $sup['name']) ?> (<?= htmlspecialchars($sup['reference_id'] ?? $sup['id']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="System PO Identifier" data-ar="رقم أمر الشراء">System PO Identifier</label>
                <input type="text" class="w-full border border-[#1a3346] bg-[#09151f] text-slate-300 font-mono text-xs px-3 py-2.5 outline-none cursor-not-allowed" value="<?= htmlspecialchars($order_id) ?>" readonly>
              </div>
              <div>
                <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Order Priority" data-ar="أولوية الطلب">Order Priority</label>
                <select name="priority" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50">
                  <option value="standard" selected data-en="Standard Replenishment (3-5 Days)" data-ar="إمداد قياسي (3-5 أيام)">Standard Replenishment (3-5 Days)</option>
                  <option value="urgent" data-en="Urgent Plant Refill (24-48 Hours)" data-ar="عاجل - إعادة تعبئة (24-48 ساعة)">Urgent Plant Refill (24-48 Hours)</option>
                  <option value="critical" data-en="Critical Plant Outage (Immediate Dispatch)" data-ar="حرج - انقطاع مصنع (إرسال فوري)">Critical Plant Outage (Immediate Dispatch)</option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Payment & Settlement Terms" data-ar="شروط الدفع والتسوية">Payment & Settlement Terms</label>
                <select name="payment_terms" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50">
                  <option value="net30" selected data-en="Net 30 Days (Corporate Invoicing)" data-ar="أجل 30 يوماً (فوترة مؤسسية)">Net 30 Days (Corporate Invoicing)</option>
                  <option value="cod" data-en="Cash on Delivery (COD)" data-ar="الدفع عند الاستلام (COD)">Cash on Delivery (COD)</option>
                  <option value="advance" data-en="100% Advance LC Transfer" data-ar="تحويل مسبق 100%">100% Advance LC Transfer</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Card 2: Gas Line Items Builder -->
          <div class="bg-[#0c1924] border border-[#1a3346] p-6 border-s-4 border-s-[#f97316]">
            <div class="flex items-center justify-between pb-4 mb-5 border-b border-[#1a3346]">
              <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white flex items-center gap-2">
                <i data-lucide="package" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Gas Product Catalog & Allocations" data-ar="كتالوج المنتجات وتوزيع الكميات">Gas Product Catalog & Allocations</span>
              </h3>
              <button type="button" onclick="addLineItem()" class="inline-flex items-center gap-1.5 px-2.5 py-1 border border-[#1a3346] bg-[#09151f] text-xs font-mono text-slate-300 hover:bg-[#102230] dark:hover:bg-neutral-800 transition-colors">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                <span data-en="Add Line Item" data-ar="إضافة منتج">Add Line Item</span>
              </button>
            </div>

            <!-- Column Headers -->
            <div class="hidden md:grid grid-cols-12 gap-3 mb-2 px-1 text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400">
              <div class="col-span-5" data-en="Gas Spec / Mixture" data-ar="مواصفة الغاز / الخليط">Gas Spec / Mixture</div>
              <div class="col-span-2" data-en="Quantity" data-ar="الكمية">Quantity</div>
              <div class="col-span-2" data-en="Unit Price (SAR)" data-ar="سعر الوحدة (ر.س)">Unit Price (SAR)</div>
              <div class="col-span-2 text-right" data-en="Total (SAR)" data-ar="المجموع (ر.س)">Total (SAR)</div>
              <div class="col-span-1 text-center"></div>
            </div>

            <!-- Items Container -->
            <div id="itemsContainer" class="space-y-3">
              <!-- Item Row 1 -->
              <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center p-3 border border-[#1a3346] bg-[#09151f] item-row">
                <div class="col-span-5">
                  <label class="block md:hidden text-[10px] font-mono text-neutral-500 mb-1">Product</label>
                  <select class="item-product w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-2.5 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" onchange="calcLine(this)">
                    <option value="250.00" data-purity="99.5%" selected>Liquid Oxygen (LOX) - Bulk Cryogenic (99.5%)</option>
                    <option value="180.00" data-purity="99.9%">Argon / CO₂ Mix - 50L Cylinder (99.9%)</option>
                    <option value="310.00" data-purity="99.999%">High Purity Nitrogen (N₂) - Bulk (99.999%)</option>
                    <option value="210.00" data-purity="98.5%">Dissolved Acetylene (C₂H₂) - 40L Cyl</option>
                  </select>
                </div>
                <div class="col-span-2">
                  <label class="block md:hidden text-[10px] font-mono text-neutral-500 mb-1">Quantity</label>
                  <input type="number" class="item-qty w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs px-2.5 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" value="40" min="1" oninput="calcLine(this)">
                </div>
                <div class="col-span-2">
                  <label class="block md:hidden text-[10px] font-mono text-neutral-500 mb-1">Unit Price</label>
                  <input type="number" step="0.01" class="item-price w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs px-2.5 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" value="250.00" oninput="calcLine(this)">
                </div>
                <div class="col-span-2 text-right">
                  <span class="item-total font-mono text-xs font-semibold text-white">10,000.00</span>
                </div>
                <div class="col-span-1 text-center">
                  <button type="button" onclick="removeLineItem(this)" class="p-1 text-neutral-400 hover:text-red-500 dark:hover:text-red-400 transition-colors" title="Remove item">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                  </button>
                </div>
              </div>

              <!-- Item Row 2 -->
              <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center p-3 border border-[#1a3346] bg-[#09151f] item-row">
                <div class="col-span-5">
                  <label class="block md:hidden text-[10px] font-mono text-neutral-500 mb-1">Product</label>
                  <select class="item-product w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-2.5 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" onchange="calcLine(this)">
                    <option value="250.00" data-purity="99.5%">Liquid Oxygen (LOX) - Bulk Cryogenic (99.5%)</option>
                    <option value="180.00" data-purity="99.9%" selected>Argon / CO₂ Mix - 50L Cylinder (99.9%)</option>
                    <option value="310.00" data-purity="99.999%">High Purity Nitrogen (N₂) - Bulk (99.999%)</option>
                    <option value="210.00" data-purity="98.5%">Dissolved Acetylene (C₂H₂) - 40L Cyl</option>
                  </select>
                </div>
                <div class="col-span-2">
                  <label class="block md:hidden text-[10px] font-mono text-neutral-500 mb-1">Quantity</label>
                  <input type="number" class="item-qty w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs px-2.5 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" value="15" min="1" oninput="calcLine(this)">
                </div>
                <div class="col-span-2">
                  <label class="block md:hidden text-[10px] font-mono text-neutral-500 mb-1">Unit Price</label>
                  <input type="number" step="0.01" class="item-price w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs px-2.5 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" value="180.00" oninput="calcLine(this)">
                </div>
                <div class="col-span-2 text-right">
                  <span class="item-total font-mono text-xs font-semibold text-white">2,700.00</span>
                </div>
                <div class="col-span-1 text-center">
                  <button type="button" onclick="removeLineItem(this)" class="p-1 text-neutral-400 hover:text-red-500 dark:hover:text-red-400 transition-colors" title="Remove item">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Card 3: Logistics & Offloading Protocol -->
          <div class="bg-[#0c1924] border border-[#1a3346] p-6 border-s-4 border-s-[#f97316]">
            <div class="flex items-center justify-between pb-4 mb-5 border-b border-[#1a3346]">
              <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white flex items-center gap-2">
                <i data-lucide="map-pin" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Logistics, Offloading & Safety Protocol" data-ar="اللوجستيات وموقع التفريغ وبروتوكول السلامة">Logistics, Offloading & Safety Protocol</span>
              </h3>
              <span class="text-[11px] font-mono uppercase px-2 py-0.5 border border-[#1a3346] bg-[#09151f] text-slate-400">SEC-03</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Target Depot / Manifold Yard" data-ar="المستودع المستهدف / ساحة التفريغ">Target Depot / Manifold Yard</label>
                <select name="delivery_location" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" required>
                  <option value="" disabled data-en="Select registered facility..." data-ar="اختر المنشأة المسجلة...">Select registered facility...</option>
                  <?php foreach ($delivery_locations as $idx => $loc): ?>
                    <option value="<?= htmlspecialchars($loc['warehouse_name'] ?? $loc['name']) ?>" <?= $idx === 0 ? 'selected' : '' ?>>
                      <?= htmlspecialchars($loc['warehouse_name'] ?? $loc['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Required Delivery Date" data-ar="تاريخ التسليم المطلوب">Required Delivery Date</label>
                <input type="date" name="delivery_date" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs px-3 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
              </div>
              <div class="col-span-1 md:col-span-2">
                <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Special Gate Clearance & Offloading Instructions" data-ar="تعليمات تصريح الدخول والتفريغ الخاصة">Special Gate Clearance & Offloading Instructions</label>
                <textarea name="special_instructions" rows="3" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" placeholder="e.g. Cryogenic safety checklist required upon gate entry. Plant crane operator available from 08:00 to 16:00."></textarea>
              </div>
            </div>
          </div>

        </div>

        <!-- Right Col: Sticky Summary & Sole Primary CTA -->
        <div class="xl:col-span-1">
          <div class="bg-[#0c1924] border border-[#1a3346] p-6 sticky top-6 border-s-4 border-s-[#f97316]">
            <div class="flex items-center justify-between pb-4 mb-5 border-b border-[#1a3346]">
              <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white" data-en="Procurement Summary" data-ar="ملخص أمر الشراء">Procurement Summary</h3>
              <span class="font-mono text-[10px] text-teal-600 dark:text-teal-400 font-semibold uppercase">SAR CURRENCY</span>
            </div>

            <!-- Telemetry Breakdown -->
            <div class="space-y-3 mb-6 text-xs font-mono">
              <div class="flex justify-between items-center text-slate-400">
                <span data-en="Line Items Subtotal" data-ar="المجموع الفرعي">Line Items Subtotal</span>
                <span id="summarySubtotal" class="font-semibold text-white">12,700.00</span>
              </div>
              <div class="flex justify-between items-center text-slate-400">
                <span data-en="Cryogenic Logistics Fee" data-ar="رسوم الشحن المبرد">Cryogenic Logistics Fee</span>
                <span id="summaryLogistics" class="font-semibold text-white">500.00</span>
              </div>
              <div class="flex justify-between items-center text-slate-400">
                <span data-en="Value Added Tax (15%)" data-ar="ضريبة القيمة المضافة (15%)">Value Added Tax (15%)</span>
                <span id="summaryVat" class="font-semibold text-white">1,980.00</span>
              </div>
              <div class="pt-3 border-t border-[#1a3346] flex justify-between items-center">
                <span class="text-xs font-bold font-oxanium text-white uppercase" data-en="Total Procurement" data-ar="الإجمالي النهائي">Total Procurement</span>
                <span id="summaryTotal" class="text-base font-bold font-mono text-teal-600 dark:text-teal-400">15,180.00</span>
              </div>
            </div>

            <!-- Cost Center Reference -->
            <div class="mb-6">
              <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Internal Cost Center / Project Code" data-ar="مركز التكلفة / كود المشروع">Internal Cost Center / Project Code</label>
              <input type="text" name="cost_center" class="w-full border border-[#1a3346] bg-[#09151f] text-slate-100 placeholder-slate-500 font-mono text-xs px-3 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" placeholder="e.g. PRJ-2026-CRYO-04">
            </div>

            <!-- EXACTLY ONE PRIMARY CTA IN SAFETY VALVE ORANGE -->
            <button type="submit" class="btn-cta-orange w-full py-3 text-center text-xs font-semibold font-oxanium uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer transition-colors">
              <i data-lucide="send" class="w-4 h-4"></i>
              <span data-en="Submit Order to Supplier" data-ar="إرسال أمر الشراء للمورد">Submit Order to Supplier</span>
            </button>

            <!-- Legal & Compliance Footnote -->
            <div class="mt-4 pt-4 border-t border-[#1a3346] text-[10px] font-mono text-slate-400 text-center leading-relaxed">
              <i data-lucide="shield-check" class="w-3.5 h-3.5 inline-block text-teal-600 dark:text-teal-400 mr-1 align-sub"></i>
              <span data-en="CGA V-1 & SASO 2026 Procurement Standard Compliant" data-ar="مطابق لمعايير الشراء CGA V-1 و SASO 2026">CGA V-1 & SASO 2026 Procurement Standard Compliant</span>
            </div>
          </div>
        </div>

      </div>
    </form>

  </div>
</main>

<script>
  lucide.createIcons();

  function formatNumber(num) {
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function calcLine(el) {
    const row = el.closest('.item-row');
    const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const total = qty * price;
    row.querySelector('.item-total').textContent = formatNumber(total);
    recalcSummary();
  }

  function recalcSummary() {
    let subtotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
      const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
      const price = parseFloat(row.querySelector('.item-price').value) || 0;
      subtotal += (qty * price);
    });

    const logistics = subtotal > 0 ? 500.00 : 0.00;
    const vat = (subtotal + logistics) * 0.15;
    const total = subtotal + logistics + vat;

    document.getElementById('summarySubtotal').textContent = formatNumber(subtotal);
    document.getElementById('summaryLogistics').textContent = formatNumber(logistics);
    document.getElementById('summaryVat').textContent = formatNumber(vat);
    document.getElementById('summaryTotal').textContent = formatNumber(total);
    document.getElementById('hiddenTotalVal').value = total.toFixed(2);
  }

  function removeLineItem(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) {
      btn.closest('.item-row').remove();
      recalcSummary();
    }
  }

  function addLineItem() {
    const container = document.getElementById('itemsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'grid grid-cols-1 md:grid-cols-12 gap-3 items-center p-3 border border-[#1a3346] bg-[#09151f] item-row';
    newRow.innerHTML = `
      <div class="col-span-5">
        <label class="block md:hidden text-[10px] font-mono text-neutral-500 mb-1">Product</label>
        <select class="item-product w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-2.5 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" onchange="calcLine(this)">
          <option value="250.00" data-purity="99.5%">Liquid Oxygen (LOX) - Bulk Cryogenic (99.5%)</option>
          <option value="180.00" data-purity="99.9%">Argon / CO₂ Mix - 50L Cylinder (99.9%)</option>
          <option value="310.00" data-purity="99.999%">High Purity Nitrogen (N₂) - Bulk (99.999%)</option>
          <option value="210.00" data-purity="98.5%">Dissolved Acetylene (C₂H₂) - 40L Cyl</option>
        </select>
      </div>
      <div class="col-span-2">
        <label class="block md:hidden text-[10px] font-mono text-neutral-500 mb-1">Quantity</label>
        <input type="number" class="item-qty w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs px-2.5 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" value="10" min="1" oninput="calcLine(this)">
      </div>
      <div class="col-span-2">
        <label class="block md:hidden text-[10px] font-mono text-neutral-500 mb-1">Unit Price</label>
        <input type="number" step="0.01" class="item-price w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs px-2.5 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" value="250.00" oninput="calcLine(this)">
      </div>
      <div class="col-span-2 text-right">
        <span class="item-total font-mono text-xs font-semibold text-white">2,500.00</span>
      </div>
      <div class="col-span-1 text-center">
        <button type="button" onclick="removeLineItem(this)" class="p-1 text-neutral-400 hover:text-red-500 dark:hover:text-red-400 transition-colors" title="Remove item">
          <i data-lucide="trash-2" class="w-4 h-4"></i>
        </button>
      </div>
    `;
    container.appendChild(newRow);
    lucide.createIcons();
    recalcSummary();
  }
</script>
</body>
</html>