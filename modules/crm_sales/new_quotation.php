<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'quotations.create');

$all_products = [];
try {
  $stmt_mixes = $pdo->query("SELECT product_name, selling_price, volume as current_stock FROM gas_mixtures");
  if ($stmt_mixes) {
    foreach($stmt_mixes->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $all_products[$row['product_name']] = [
        'price' => $row['selling_price'],
        'unit' => 'Cylinders',
        'stock' => $row['current_stock'] ?: 0
      ];
    }
  }

  $stmt_raw = $pdo->query("SELECT material_name as product_name, unit_cost as selling_price, unit, current_stock FROM raw_materials");
  if ($stmt_raw) {
    foreach($stmt_raw->fetchAll(PDO::FETCH_ASSOC) as $row) {
      if(!isset($all_products[$row['product_name']])) {
        $all_products[$row['product_name']] = [
          'price' => $row['selling_price'],
          'unit' => $row['unit'],
          'stock' => $row['current_stock'] ?: 0
        ];
      }
    }
  }

  $stmt_cylinders = $pdo->query("SELECT sku as product_name, selling_price, quantity as current_stock FROM cylinder_batches");
  if($stmt_cylinders) {
    foreach($stmt_cylinders->fetchAll(PDO::FETCH_ASSOC) as $row) {
      if(!isset($all_products[$row['product_name']])) {
        $all_products[$row['product_name']] = [
          'price' => $row['selling_price'] ?: 0,
          'unit' => 'Units',
          'stock' => $row['current_stock'] ?: 0
        ];
      }
    }
  }
} catch (PDOException $e) { }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();

  $client_id = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;

  $sales_rep_raw = $_POST['sales_rep_id'] ?? [];
  $sales_rep_id = is_array($sales_rep_raw) ? implode(',', array_map('intval', $sales_rep_raw)) : (int)$sales_rep_raw;

  $quotation_number = trim($_POST['quotation_number'] ?? '');
  $specs = trim($_POST['specs'] ?? '');
  $company_name_doc = trim($_POST['company_name_doc'] ?? '');
  $delivery_mechanism = trim($_POST['delivery_mechanism'] ?? '');
  $delivery_address = trim($_POST['delivery_address'] ?? '');

  $validity = (int)($_POST['validity'] ?? 30);
  $payment_terms = trim($_POST['payment_terms'] ?? '');
  $delivery_fee = isset($_POST['free_delivery']) ? 0.00 : (float)($_POST['delivery_fee'] ?? 0.00);
  $vat_percentage = (float)($_POST['vat_percentage'] ?? 15.00);

  $issue_date = date('Y-m-d');
  $expiry_date = date('Y-m-d', strtotime("+$validity days"));
  $status = isset($_POST['send_quotation']) ? 'sent' : 'draft';

  $notes = "Delivery: $delivery_mechanism | Address: $delivery_address | Terms: $payment_terms";

  $logo_path = null;
  $error_message = "";

  // Secure File Upload Handling
  if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    $tmpName = $_FILES['company_logo']['tmp_name'];
    $fileSize = $_FILES['company_logo']['size'];
    $origName = $_FILES['company_logo']['name'];
    $fileExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if ($fileSize > $maxSize) {
      $error_message = "File upload error: Company logo must not exceed 2MB.";
    } elseif (!in_array($fileExt, $allowedExts, true)) {
      $error_message = "File upload error: Only JPG, PNG, WEBP, and SVG image formats are permitted.";
    } else {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mimeType = finfo_file($finfo, $tmpName);
      finfo_close($finfo);

      if (!in_array($mimeType, $allowedMimes, true)) {
        $error_message = "File upload error: Detected invalid image MIME type ($mimeType).";
      } else {
        $upload_dir = __DIR__ . '/../../uploads/';
        if (!is_dir($upload_dir)) {
          mkdir($upload_dir, 0755, true);
        }
        $safeName = 'logo_' . bin2hex(random_bytes(12)) . '.' . $fileExt;
        if (move_uploaded_file($tmpName, $upload_dir . $safeName)) {
          $logo_path = 'uploads/' . $safeName;
        } else {
          $error_message = "File upload error: Failed to save uploaded file.";
        }
      }
    }
  }

  $product_names = $_POST['product_name'] ?? [];
  $qtys = $_POST['qty'] ?? [];
  $units = $_POST['unit'] ?? [];
  $unit_prices = $_POST['unit_price'] ?? [];

  $subtotal = 0;
  $valid_items = [];

  foreach ($product_names as $index => $product_name) {
    $clean_name = trim($product_name);
    if (!empty($clean_name)) {
      $qty = (float)($qtys[$index] ?? 0);
      $price = (float)($unit_prices[$index] ?? 0);
      $unit = trim($units[$index] ?? '');
      $line_total = $qty * price;

      $subtotal += $line_total;
      $valid_items[] = [
        'name' => $clean_name,
        'qty' => $qty,
        'unit' => $unit,
        'price' => $price,
        'total' => $line_total
      ];
    }
  }

  if (empty($error_message)) {
    $vat_amount = $subtotal * ($vat_percentage / 100);
    $total_value = $subtotal + $vat_amount + $delivery_fee;

    if (empty($client_id)) {
      $error_message = "Validation Error: Please select a client.";
    } elseif (empty($valid_items)) {
      $error_message = "Validation Error: Please add at least one valid product line.";
    } else {
      try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO quotations (quotation_number, client_id, sales_rep_id, specs, company_name, company_logo, issue_date, expiry_date, status, subtotal, vat_percentage, vat_amount, delivery_fee, total_value, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$quotation_number, $client_id, $sales_rep_id, $specs, $company_name_doc, $logo_path, $issue_date, $expiry_date, $status, $subtotal, $vat_percentage, $vat_amount, $delivery_fee, $total_value, $notes]);
        $quotation_id = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("INSERT INTO quotation_items (quotation_id, product_name, qty, unit, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)");

        $stmt_check_rm = $pdo->prepare("SELECT id, current_stock FROM raw_materials WHERE material_name = ? LIMIT 1");
        $stmt_upd_rm = $pdo->prepare("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ?");
        $stmt_tx = $pdo->prepare("INSERT INTO material_transactions (material_id, transaction_ref, type, quantity, source_ref, logged_by, transaction_date) VALUES (?, ?, 'out', ?, ?, ?, CURRENT_TIMESTAMP)");

        foreach ($valid_items as $item) {
          $stmtItem->execute([$quotation_id, $item['name'], $item['qty'], $item['unit'], $item['price'], $item['total']]);

          $stmt_check_rm->execute([$item['name']]);
          $rm = $stmt_check_rm->fetch(PDO::FETCH_ASSOC);

          if ($rm) {
            $stmt_upd_rm->execute([$item['qty'], $rm['id']]);
            $txRef = 'SAL-' . rand(100000, 999999);
            $stmt_tx->execute([
              $rm['id'],
              $txRef,
              $item['qty'],
              $quotation_number,
              $_SESSION['user_name'] ?? 'Staff'
            ]);
          }
        }

        $pdo->commit();
        redirect('modules/crm_sales/quotations.php');
        exit;
      } catch (Exception $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        $error_message = "System Error: Unable to record quotation.";
        error_log("Quotation save failure: " . $e->getMessage());
      }
    }
  }
}

$stmt_clients = $pdo->prepare("SELECT id, company_name, sales_rep_id FROM partners WHERE partner_type = 'client' ORDER BY company_name ASC");
$stmt_clients->execute();
$clients = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);

$stmt_reps = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('Sales Rep', 'sales_rep', 'Sales Manager', 'sales_manager', 'Admin', 'admin') AND status = 'Active' ORDER BY full_name ASC");
$sales_reps = $stmt_reps ? $stmt_reps->fetchAll(PDO::FETCH_ASSOC) : [];

$active_page = 'quotations';
$breadcrumb = ['I-GAS', 'Sales & Quotations', 'New Quotation Builder'];
$new_quote_id = 'QT-' . date('Y') . '-' . str_pad((string)rand(100, 999), 3, '0', STR_PAD_LEFT);

$target_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$target_rep_id = isset($_GET['rep_id']) ? (int)$_GET['rep_id'] : 0;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Commercial Quotation Builder | I-GAS Enterprise</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

  <?php include __DIR__ . '/../../includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <!-- Telemetry Status Bar -->
    <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 sm:px-6 flex items-center justify-between text-xs font-mono text-slate-400">
      <div class="flex items-center gap-4 overflow-x-auto no-scrollbar">
        <span class="flex items-center gap-1.5 font-medium text-teal-400 dark:text-teal-400">
          <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
          <span data-en="BUILDER ACTIVE" data-ar="منشئ العروض نشط">BUILDER ACTIVE</span>
        </span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="COMMERCIAL PROPOSAL" data-ar="عرض سعر تجاري">COMMERCIAL PROPOSAL</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">REF: <?= htmlspecialchars($new_quote_id) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">SLA: ISO-14175 / EN-10204</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">
      <?php if (!empty($error_message)): ?>
        <div class="p-4 rounded-md border border-rose-800 dark:border-rose-900/60 bg-rose-950/30 dark:bg-rose-950/30 text-rose-300 dark:text-rose-300 text-xs flex items-center gap-2">
          <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600"></i>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
      <?php endif; ?>

      <form action="" method="POST" id="quotationForm" enctype="multipart/form-data" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="quotation_number" value="<?= htmlspecialchars($new_quote_id) ?>">

        <!-- Header & Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 mb-1.5">
              <a href="<?= app_url('modules/crm_sales/quotations.php') ?>" class="text-xs font-mono text-neutral-500 hover:text-teal-600 dark:hover:text-teal-400 transition-colors inline-flex items-center gap-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
                <span data-en="Quotations Register" data-ar="سجل عروض الأسعار">Quotations Register</span>
              </a>
              <span class="text-neutral-300 dark:text-neutral-700">/</span>
              <span class="text-xs font-mono text-neutral-400" data-en="New Quote" data-ar="عرض جديد">New Quote</span>
            </div>
            <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Build Commercial Gas Quotation" data-ar="إنشاء عرض أسعار غازات صناعية">
              Build Commercial Gas Quotation
            </h1>
          </div>

          <div class="flex items-center gap-3">
            <a href="<?= app_url('modules/crm_sales/quotations.php') ?>" class="px-4 py-2 text-xs font-medium border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 rounded-md transition-colors">
              <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
            </a>
            <button type="submit" name="save_draft" class="px-4 py-2 text-xs font-medium border border-[#1e3a50] hover:border-neutral-900 dark:hover:border-white rounded-md text-slate-200 transition-colors inline-flex items-center gap-2">
              <i data-lucide="save" class="w-3.5 h-3.5"></i>
              <span data-en="Save Draft" data-ar="حفظ كمسودة">Save Draft</span>
            </button>
            <!-- Single Primary CTA: Safety Valve Orange -->
            <button type="submit" name="send_quotation" class="btn-cta-orange px-5 py-2 text-xs font-semibold rounded-md inline-flex items-center gap-2 transition-all">
              <i data-lucide="send" class="w-4 h-4"></i>
              <span data-en="Save &amp; Issue Document" data-ar="حفظ وإصدار المستند">Save &amp; Issue Document</span>
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main Left Column (2 cols) -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Section 1: Line Items Table -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md border-s-4 border-s-[#f97316] overflow-hidden">
              <div class="px-6 py-4 border-b border-[#1a3346] flex items-center justify-between">
                <div>
                  <h2 class="text-sm font-semibold text-white" data-en="1. Product Line Items & Unit Pricing" data-ar="1. بنود المنتجات والأسعار الفردية">
                    1. Product Line Items & Unit Pricing
                  </h2>
                  <p class="text-xs text-neutral-500" data-en="Select industrial gas mixtures, pure gases, or cylinder batches." data-ar="اختر مخاليط الغازات الصناعية أو الغازات النقية أو دفعات الأسطوانات.">
                    Select industrial gas mixtures, pure gases, or cylinder batches.
                  </p>
                </div>
              </div>

              <datalist id="products_list">
                <?php foreach($all_products as $name => $data): ?>
                  <option value="<?= htmlspecialchars($name) ?>"></option>
                <?php endforeach; ?>
              </datalist>

              <div class="overflow-x-auto">
                <table class="w-full text-start text-xs border-collapse" id="productsTable">
                  <thead>
                    <tr class="bg-[#09151f] bg-[#112433]/75 border-b border-[#1a3346] text-neutral-500 font-mono uppercase">
                      <th class="py-3 px-6 text-start" style="width: 40%;" data-en="Product / Gas Spec" data-ar="المنتج ومواصفة الغاز">Product / Gas Spec</th>
                      <th class="py-3 px-3 text-end" style="width: 15%;" data-en="Qty" data-ar="الكمية">Qty</th>
                      <th class="py-3 px-3 text-start" style="width: 15%;" data-en="Unit" data-ar="الوحدة">Unit</th>
                      <th class="py-3 px-3 text-end" style="width: 15%;" data-en="Unit Price (SAR)" data-ar="سعر الوحدة (ر.س)">Unit Price (SAR)</th>
                      <th class="py-3 px-6 text-end" style="width: 15%;" data-en="Total (SAR)" data-ar="الإجمالي">Total (SAR)</th>
                      <th style="width: 5%;"></th>
                    </tr>
                  </thead>
                  <tbody id="itemsContainer" class="divide-y divide-[#152a3a] dark:divide-neutral-800 font-mono">
                  </tbody>
                </table>
              </div>

              <div class="p-4 border-t border-[#1a3346] bg-[#09151f] flex justify-between items-center">
                <button type="button" id="addRowBtn" class="px-3 py-1.5 text-xs font-semibold rounded border border-[#1e3a50] hover:border-neutral-900 dark:hover:border-white text-slate-200 transition-colors inline-flex items-center gap-1.5">
                  <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                  <span data-en="Add Line Item" data-ar="إضافة بند جديد">Add Line Item</span>
                </button>
                <span class="text-xs font-mono text-neutral-500" data-en="Live tabular inventory pricing" data-ar="تسعير مخزني فوري">Live tabular inventory pricing</span>
              </div>
            </div>

            <!-- Section 2: Branding & Custom Header -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-semibold text-white mb-1" data-en="2. Corporate Branding on Proposal" data-ar="2. هوية المنشأة على عرض السعر">
                2. Corporate Branding on Proposal
              </h2>
              <p class="text-xs text-neutral-500 mb-5" data-en="Optional custom trade entity name and header badge on formal A4 proposal." data-ar="اسم المنشأة التجاري المخصص وشعار الترويسة على المستند الرسمي.">
                Optional custom trade entity name and header badge on formal A4 proposal.
              </p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Document Title / Entity Name" data-ar="اسم المنشأة في المستند">Document Title / Entity Name</label>
                  <input type="text" name="company_name_doc" placeholder="e.g. I-GAS Systems Industrial Division" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-600">
                </div>
                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Header Logo (PNG / SVG)" data-ar="شعار الترويسة">Header Logo (PNG / SVG)</label>
                  <input type="file" name="company_logo" accept="image/*" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-1.5 text-xs text-white focus:outline-none">
                </div>
              </div>
            </div>

            <!-- Section 3: Delivery Mechanism & Logistics -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-semibold text-white mb-1" data-en="3. Logistics & Special Technical Specifications" data-ar="3. اللوجستيات والمواصفات الفنية">
                3. Logistics & Special Technical Specifications
              </h2>
              <p class="text-xs text-neutral-500 mb-5" data-en="Transport dispatch route, destination plant coordinates, and purity criteria." data-ar="مسار التوصيل وإحداثيات المصنع ومعايير النقاوة المطلوبة.">
                Transport dispatch route, destination plant coordinates, and purity criteria.
              </p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-4">
                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Delivery Mechanism" data-ar="آلية التوصيل">Delivery Mechanism</label>
                  <select name="delivery_mechanism" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                    <option value="I-GAS Cryo Fleet Dispatch" data-en="I-GAS Cryo Fleet Dispatch" data-ar="إرسال عبر أسطول النقل المبرد">I-GAS Cryo Fleet Dispatch</option>
                    <option value="Customer Bulk Tanker Pickup" data-en="Customer Bulk Tanker Pickup" data-ar="استلام العميل بصاروف ناقلته">Customer Bulk Tanker Pickup</option>
                    <option value="Dedicated Cylinder Pallet Delivery" data-en="Dedicated Cylinder Pallet Delivery" data-ar="توصيل منصات أسطوانات مخصصة">Dedicated Cylinder Pallet Delivery</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Destination Site Coordinates / Address" data-ar="عنوان موقع الوجهة أو الإحداثيات">Destination Site Coordinates / Address</label>
                  <input type="text" name="delivery_address" placeholder="e.g. Industrial Valley, Yanbu Plant Bay 4" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-600">
                </div>
              </div>

              <div>
                <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Technical Specifications & Notes" data-ar="المواصفات الفنية والملاحظات">Technical Specifications & Notes</label>
                <textarea name="specs" rows="3" placeholder="e.g. Grade 5.0 High Purity 99.999% certified. Cylinder valve outlet BS-341 No. 3." class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600"></textarea>
              </div>
            </div>
          </div>

          <!-- Right Column (1 col): Client & Commercials -->
          <div class="space-y-6">
            <!-- Client Account & Representative Assignment -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h3 class="text-sm font-semibold text-white mb-4" data-en="Client Assignment" data-ar="تعيين العميل">
                Client Assignment
              </h3>

              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Quotation Reference" data-ar="الرقم المرجعي">Quotation Reference</label>
                  <input type="text" value="<?= htmlspecialchars($new_quote_id) ?>" disabled class="w-full bg-[#09151f] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono font-bold text-white cursor-not-allowed">
                </div>

                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Client Commercial Account *" data-ar="الحساب التجاري للعميل *">Client Commercial Account *</label>
                  <select name="client_id" id="clientSelect" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                    <option value="" disabled <?= $target_client_id === 0 ? 'selected' : '' ?>>-- Select registered client --</option>
                    <?php foreach($clients as $c): ?>
                      <option value="<?= htmlspecialchars($c['id']) ?>" data-rep="<?= htmlspecialchars($c['sales_rep_id'] ?? '') ?>" <?= $target_client_id === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['company_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Sales Representatives" data-ar="مناديب المبيعات">Sales Representatives</label>
                  <div class="border border-[#1e3a50] rounded p-2.5 max-h-36 overflow-y-auto space-y-1.5 text-xs font-mono">
                    <?php if (empty($sales_reps)): ?>
                      <span class="text-neutral-400">No sales reps found.</span>
                    <?php else: ?>
                      <?php foreach ($sales_reps as $rep): ?>
                        <label class="flex items-center gap-2 hover:text-teal-600 cursor-pointer">
                          <input type="checkbox" name="sales_rep_id[]" value="<?= (int)$rep['id'] ?>" <?= $target_rep_id === (int)$rep['id'] ? 'checked' : '' ?> class="rep-checkbox rounded border-neutral-300">
                          <span><?= htmlspecialchars($rep['full_name']) ?></span>
                        </label>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Commercial Terms & Surcharges -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h3 class="text-sm font-semibold text-white mb-4" data-en="Terms & Financial Parameters" data-ar="الشروط والمعايير المالية">
                Terms & Financial Parameters
              </h3>

              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Proposal Validity (Days) *" data-ar="صلاحية العرض (أيام) *">Proposal Validity (Days) *</label>
                  <input type="number" name="validity" value="30" min="1" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                </div>

                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Settlement Terms *" data-ar="شروط السداد *">Settlement Terms *</label>
                  <input type="text" name="payment_terms" value="Net 30 Days" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                </div>

                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5" data-en="Delivery Freight Fee (SAR)" data-ar="رسوم التوصيل والنقل (ر.س)">Delivery Freight Fee (SAR)</label>
                  <div class="flex items-center gap-3">
                    <input type="number" name="delivery_fee" id="delivery_fee_input" value="0.00" step="0.01" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                    <label class="flex items-center gap-1.5 text-xs font-mono whitespace-nowrap cursor-pointer">
                      <input type="checkbox" name="free_delivery" id="free_delivery_check">
                      <span data-en="Free" data-ar="مجاني">Free</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Live Calculation Summary -->
            <div class="bg-[#09151f]/80 border border-[#1e3a50] rounded-md p-5 space-y-3">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-500" data-en="Live Pricing Summary" data-ar="ملخص التسعير الحي">
                Live Pricing Summary
              </h4>
              <div class="space-y-2 text-xs font-mono">
                <div class="flex justify-between py-1 border-b border-[#1e3a50]">
                  <span class="text-neutral-500" data-en="Subtotal:" data-ar="المجموع الفرعي:">Subtotal:</span>
                  <span id="ui_subtotal" class="font-bold text-white">0.00 SAR</span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-[#1e3a50]">
                  <div class="flex items-center gap-1.5">
                    <span class="text-neutral-500">VAT:</span>
                    <input type="number" name="vat_percentage" id="vat_percentage_input" value="15.00" step="0.01" class="w-12 px-1 py-0.5 text-center text-[11px] font-mono border rounded bg-[#08131c] text-white">%
                  </div>
                  <span id="ui_vat" class="font-bold text-white">0.00 SAR</span>
                </div>
                <div class="flex justify-between py-1 border-b border-[#1e3a50]">
                  <span class="text-neutral-500" data-en="Freight:" data-ar="الشحن:">Freight:</span>
                  <span id="ui_delivery" class="font-bold text-white">0.00 SAR</span>
                </div>
                <div class="flex justify-between items-center pt-2">
                  <span class="font-bold text-sm text-white uppercase" data-en="Net Total:" data-ar="الإجمالي:">Net Total:</span>
                  <span id="ui_total" class="text-lg font-bold font-mono text-teal-400 dark:text-teal-400">0.00 SAR</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </main>

  <!-- Dynamic Row Template -->
  <template id="rowTemplate">
    <tr class="item-row hover:bg-[#102332]/50 dark:hover:bg-neutral-800/40 transition-colors">
      <td class="py-3 px-6">
        <input type="text" name="product_name[]" list="products_list" placeholder="Select or type gas specification..." required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-2.5 py-1.5 text-xs font-mono text-white product-input focus:outline-none focus:border-teal-600">
        <div class="stock-info text-[10px] font-mono text-neutral-500 mt-1 hidden">
          <span>Stock: </span><span class="stock-qty font-bold text-slate-200"></span> <span class="stock-unit"></span>
        </div>
      </td>
      <td class="py-3 px-3 text-end">
        <input type="number" name="qty[]" step="0.01" value="1" required class="w-20 bg-[#08131c] border border-[#1e3a50] rounded px-2 py-1.5 text-xs font-mono text-end text-white qty-input focus:outline-none focus:border-teal-600">
      </td>
      <td class="py-3 px-3">
        <input type="text" name="unit[]" value="Cylinders" required class="w-24 bg-[#08131c] border border-[#1e3a50] rounded px-2 py-1.5 text-xs font-mono text-white unit-input focus:outline-none focus:border-teal-600">
      </td>
      <td class="py-3 px-3 text-end">
        <input type="number" name="unit_price[]" step="0.01" value="0.00" required class="w-24 bg-[#08131c] border border-[#1e3a50] rounded px-2 py-1.5 text-xs font-mono text-end text-white price-input focus:outline-none focus:border-teal-600">
      </td>
      <td class="py-3 px-6 text-end font-bold text-white line-total-text">
        0.00
      </td>
      <td class="py-3 px-3 text-center">
        <button type="button" class="p-1 rounded text-neutral-400 hover:text-rose-600 transition-colors remove-btn" title="Remove row">
          <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
        </button>
      </td>
    </tr>
  </template>

  <script>
    lucide.createIcons();

    const productData = <?= json_encode($all_products, JSON_UNESCAPED_UNICODE) ?>;
    const itemsContainer = document.getElementById('itemsContainer');
    const addRowBtn = document.getElementById('addRowBtn');
    const rowTemplate = document.getElementById('rowTemplate');

    const uiSubtotal = document.getElementById('ui_subtotal');
    const uiVat = document.getElementById('ui_vat');
    const uiDelivery = document.getElementById('ui_delivery');
    const uiTotal = document.getElementById('ui_total');

    const deliveryInput = document.getElementById('delivery_fee_input');
    const freeDeliveryCheck = document.getElementById('free_delivery_check');
    const vatInput = document.getElementById('vat_percentage_input');

    function calculateTotals() {
      let subtotal = 0;
      const rows = itemsContainer.querySelectorAll('.item-row');

      rows.forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const lineTotal = qty * price;

        row.querySelector('.line-total-text').textContent = lineTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        subtotal += lineTotal;
      });

      const vatRate = parseFloat(vatInput.value) || 0;
      const vat = subtotal * (vatRate / 100);
      const deliveryFee = freeDeliveryCheck.checked ? 0 : (parseFloat(deliveryInput.value) || 0);
      const total = subtotal + vat + deliveryFee;

      uiSubtotal.textContent = subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' SAR';
      uiVat.textContent = vat.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' SAR';
      uiDelivery.textContent = deliveryFee.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' SAR';
      uiTotal.textContent = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' SAR';
    }

    function addNewRow() {
      const clone = rowTemplate.content.cloneNode(true);
      itemsContainer.appendChild(clone);
      lucide.createIcons();
    }

    addNewRow();

    addRowBtn.addEventListener('click', addNewRow);

    itemsContainer.addEventListener('input', function(e) {
      if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) {
        calculateTotals();
      }
    });

    itemsContainer.addEventListener('change', function(e) {
      if (e.target.classList.contains('product-input')) {
        const val = e.target.value;
        const row = e.target.closest('.item-row');

        if (productData.hasOwnProperty(val)) {
          row.querySelector('.price-input').value = parseFloat(productData[val].price).toFixed(2);
          row.querySelector('.unit-input').value = productData[val].unit;

          const stockInfo = row.querySelector('.stock-info');
          if (stockInfo) {
            stockInfo.classList.remove('hidden');
            stockInfo.querySelector('.stock-qty').textContent = parseFloat(productData[val].stock).toFixed(2);
            stockInfo.querySelector('.stock-unit').textContent = productData[val].unit;
          }
          calculateTotals();
        } else {
          const stockInfo = row.querySelector('.stock-info');
          if (stockInfo) stockInfo.classList.add('hidden');
        }
      }
    });

    itemsContainer.addEventListener('click', function(e) {
      const btn = e.target.closest('.remove-btn');
      if (btn) {
        if (itemsContainer.querySelectorAll('.item-row').length > 1) {
          btn.closest('.item-row').remove();
          calculateTotals();
        } else {
          alert('You must retain at least one line item.');
        }
      }
    });

    freeDeliveryCheck.addEventListener('change', function() {
      if (this.checked) {
        deliveryInput.value = '0.00';
        deliveryInput.disabled = true;
      } else {
        deliveryInput.disabled = false;
      }
      calculateTotals();
    });

    deliveryInput.addEventListener('input', calculateTotals);
    vatInput.addEventListener('input', calculateTotals);
  </script>
</body>
</html>