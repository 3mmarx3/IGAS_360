<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_authenticated();

if (($_SESSION['account_type'] ?? '') === 'client') {
  $logged_in_client_id = (int)($_SESSION['client_id'] ?? 0);
  if (!$logged_in_client_id) {
    redirect(app_url('auth/login.php'));
  }
} else {
  require_permission($pdo, 'orders.create');
  $logged_in_client_id = (int)($_REQUEST['client_id'] ?? ($_SESSION['client_id'] ?? 0));
  if (!$logged_in_client_id) {
    $stmtFirst = $pdo->query("SELECT id FROM partners WHERE partner_type = 'client' ORDER BY id ASC LIMIT 1");
    $logged_in_client_id = (int)$stmtFirst->fetchColumn();
  }
}

$stmt_client = $pdo->prepare("SELECT id, company_name, reference_id, address, city FROM partners WHERE id = ? AND partner_type = 'client'");
$stmt_client->execute([$logged_in_client_id]);
$current_client = $stmt_client->fetch(PDO::FETCH_ASSOC);

if (!$current_client) {
  http_response_code(404);
  die("Error: Client account not found or unauthorized access.");
}

// Fetch all clients if staff user for quick switching
$all_clients = [];
if (($_SESSION['account_type'] ?? '') !== 'client') {
  $stmt_all = $pdo->query("SELECT id, company_name, reference_id FROM partners WHERE partner_type = 'client' ORDER BY company_name ASC");
  $all_clients = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
}

// AJAX: Get quotation details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_quote_details') {
  header('Content-Type: application/json');
  $q_id = (int)($_GET['quote_id'] ?? 0);
  
  $stmt = $pdo->prepare("SELECT client_id, specs FROM quotations WHERE id = ? AND client_id = ?");
  $stmt->execute([$q_id, $logged_in_client_id]);
  $quote = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if($quote) {
    $stmt_items = $pdo->prepare("SELECT product_name, qty, unit, unit_price, line_total FROM quotation_items WHERE quotation_id = ?");
    $stmt_items->execute([$q_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_rm = $pdo->prepare("SELECT id, material_sku as code FROM raw_materials WHERE material_name = ?");
    $stmt_gm = $pdo->prepare("SELECT id, mix_id as code FROM gas_mixtures WHERE product_name = ?");
    
    foreach ($items as &$item) {
      $stmt_rm->execute([$item['product_name']]);
      if ($rm = $stmt_rm->fetch(PDO::FETCH_ASSOC)) {
        $item['db_id'] = $rm['id'];
        $item['code'] = $rm['code'];
      } else {
        $stmt_gm->execute([$item['product_name']]);
        if ($gm = $stmt_gm->fetch(PDO::FETCH_ASSOC)) {
          $item['db_id'] = $gm['id'];
          $item['code'] = $gm['code'];
        } else {
          $item['db_id'] = 0;
          $item['code'] = 'N/A';
        }
      }
    }
    
    echo json_encode(['quote' => $quote, 'items' => $items]);
  } else {
    echo json_encode(['error' => 'Unauthorized or invalid quotation.']);
  }
  exit;
}

$active_page = 'purchase_orders';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Purchase Orders', 'Call-off Order Dispatch'];

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $client_id = $logged_in_client_id;
  $quotation_id = !empty($_POST['quotation_id']) ? $_POST['quotation_id'] : null;
  $customer_po_number = trim($_POST['customer_po_number'] ?? '');
  $order_ref = $_POST['order_ref'] ?? ('OB-' . rand(10000, 99999));
  $order_date = $_POST['order_date'] ?? date('Y-m-d');
  $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
  $delivery_time = $_POST['delivery_time'] ?? '02:00';
  $delivery_location = trim($_POST['delivery_location'] ?? '');
  $payment_terms = $_POST['payment_terms'] ?? 'cod';
  $main_supplier = trim($_POST['main_supplier'] ?? '');
  
  $custom_mix_request = trim($_POST['custom_mix_request'] ?? '');
  $items = $_POST['items'] ?? [];

  $has_valid_items = false;
  $has_drawdown = false;

  foreach ($items as $item) {
    if (!empty($item['total_qty']) && (float)$item['total_qty'] > 0) {
      $has_valid_items = true;
      if (!empty($item['draw_qty']) && (float)$item['draw_qty'] > 0) {
        $has_drawdown = true;
      }
    }
  }

  if (empty($main_supplier)) {
    $error_msg = "Please select a fulfilling supplier for the shipment.";
  } elseif (!$has_valid_items && empty($custom_mix_request)) {
    $error_msg = "Please select at least one product with a valid quantity.";
  } else {
    try {
      $pdo->beginTransaction();

      $subtotal = 0.00;
      $prepared_items = [];

      foreach ($items as $item_id => $item_data) {
        $total_qty = (float)($item_data['total_qty'] ?? 0);
        $draw_qty = (float)($item_data['draw_qty'] ?? 0);
        $item_code = $item_data['code'] ?? 'N/A';
        $item_name = $item_data['name'] ?? '';
        $unit_price = (float)($item_data['unit_price'] ?? 0);
        $product_db_id = (int)($item_data['product_id'] ?? 0);

        if ($total_qty <= 0) continue;

        if ($draw_qty > $total_qty) {
          throw new Exception("Initial dispatch quantity cannot exceed the total ordered quantity.");
        }

        $line_total = $total_qty * $unit_price;
        $subtotal += $line_total;

        $prepared_items[] = [
          'product_id' => $product_db_id,
          'supplier_reference' => $main_supplier,
          'item_code' => $item_code,
          'item_name' => $item_name,
          'total_qty' => $total_qty,
          'draw_qty' => $draw_qty,
          'unit' => 'CYL',
          'unit_price' => $unit_price,
          'line_total' => $line_total
        ];
      }

      $vat = $subtotal * 0.15;
      $grand_total = $subtotal + $vat;

      $specs = 'Items: ' . count($prepared_items);
      if (!empty($custom_mix_request)) {
        $specs .= ' | Custom Mix: ' . $custom_mix_request;
      }

      $stmt_insert_po = $pdo->prepare("
        INSERT INTO purchase_orders (order_number, customer_po_number, quotation_id, client_id, supplier_reference, specs, order_date, delivery_address, delivery_date, delivery_time, payment_terms, status, total_value)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Received', ?)
      ");
      $stmt_insert_po->execute([
        $order_ref,
        $customer_po_number,
        $quotation_id,
        $client_id,
        $main_supplier,
        $specs,
        $order_date,
        $delivery_location,
        $delivery_date,
        $delivery_time,
        $payment_terms,
        $grand_total
      ]);

      $purchase_order_id = $pdo->lastInsertId();

      if (!empty($prepared_items)) {
        $stmt_item_insert = $pdo->prepare("
          INSERT INTO purchase_order_items (purchase_order_id, supplier_reference, product_id, item_name, item_code, qty, consumed_qty, unit, unit_price, line_total)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($prepared_items as $pi) {
          $stmt_item_insert->execute([
            $purchase_order_id,
            $pi['supplier_reference'],
            $pi['product_id'],
            $pi['item_name'],
            $pi['item_code'],
            $pi['total_qty'],
            $pi['draw_qty'],
            $pi['unit'],
            $pi['unit_price'],
            $pi['line_total']
          ]);
        }
      }

      $invoice_number = 'INV-' . date('Y') . '-' . rand(1000, 9999);
      $due_days = ($payment_terms === 'net60') ? 60 : (($payment_terms === 'net30') ? 30 : 7);
      $due_date = date('Y-m-d', strtotime($order_date . " + $due_days days"));
      
      $stmt_invoice = $pdo->prepare("
        INSERT INTO invoices (invoice_number, client_id, po_reference, issue_date, due_date, amount, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
      ");
      $stmt_invoice->execute([
        $invoice_number,
        $client_id,
        $order_ref,
        $order_date,
        $due_date,
        $grand_total
      ]);

      if ($has_drawdown && !empty($prepared_items)) {
        $release_number = 'REL-' . rand(10000, 99999);
        
        $stmt_rel = $pdo->prepare("
          INSERT INTO po_releases (release_number, po_reference, client_id, supplier_reference, release_date, delivery_date, delivery_location, status)
          VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt_rel->execute([
          $release_number,
          $order_ref,
          $client_id,
          $main_supplier,
          $order_date,
          $delivery_date,
          $delivery_location
        ]);

        $release_id = $pdo->lastInsertId();
        $stmt_rel_item = $pdo->prepare("
          INSERT INTO po_release_items (release_id, po_item_id, item_name, draw_qty, unit)
          VALUES (?, ?, ?, ?, ?)
        ");

        $stmt_fetch_poi = $pdo->prepare("SELECT id FROM purchase_order_items WHERE purchase_order_id = ? AND item_name = ? LIMIT 1");

        foreach ($prepared_items as $pi) {
          if ($pi['draw_qty'] > 0) {
            $stmt_fetch_poi->execute([$purchase_order_id, $pi['item_name']]);
            $poi_row = $stmt_fetch_poi->fetch(PDO::FETCH_ASSOC);
            $poi_id = $poi_row['id'] ?? 0;

            $stmt_rel_item->execute([
              $release_id,
              $poi_id,
              $pi['item_name'],
              $pi['draw_qty'],
              $pi['unit']
            ]);
          }
        }
      }

      $stmt_activity = $pdo->prepare("
        INSERT INTO client_activities (client_id, activity_text, activity_time, author)
        VALUES (?, ?, CURRENT_TIMESTAMP, ?)
      ");
      $activity_text = "Submitted Order {$order_ref} with total " . number_format($grand_total, 2) . " SAR";
      if ($has_drawdown) {
        $activity_text .= " including initial dispatch release";
      }
      $stmt_activity->execute([$client_id, $activity_text, $current_client['company_name']]);

      $stmt_update_client = $pdo->prepare("
        UPDATE partners
        SET balance_due = balance_due + ?, lifetime_value = lifetime_value + ?, last_order_date = ?
        WHERE id = ?
      ");
      $stmt_update_client->execute([$grand_total, $grand_total, $order_date, $client_id]);

      $pdo->commit();
      header("Location: " . app_url('modules/crm_sales/order_details.php?id=' . $purchase_order_id));
      exit;
    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $error_msg = $e->getMessage();
    }
  }
}

try {
  $stmt_suppliers = $pdo->query("
    SELECT reference_id, company_name 
    FROM partners 
    WHERE partner_type = 'supplier' AND status = 'approved'
    ORDER BY company_name ASC
  ");
  $suppliers = $stmt_suppliers->fetchAll(PDO::FETCH_ASSOC);

  $stmt_quotes = $pdo->prepare("SELECT id, quotation_number FROM quotations WHERE client_id = ? AND status IN ('sent', 'accepted') ORDER BY issue_date DESC");
  $stmt_quotes->execute([$logged_in_client_id]);
  $accepted_quotes = $stmt_quotes->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
  $suppliers = [];
  $accepted_quotes = [];
}

$order_ref = 'OB-' . rand(10000, 99999);
$selected_quote_ref = $_GET['quote_ref'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Call-off Order Dispatch | I-GAS Enterprise</title>
  
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
          <span data-en="SYS: NOMINAL" data-ar="النظام: مستقر">SYS: NOMINAL</span>
        </span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="CALL-OFF DISPATCH MODULE" data-ar="وحدة سحب وتوريد العقود">CALL-OFF DISPATCH MODULE</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">CLIENT: <?= htmlspecialchars($current_client['company_name']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Breadcrumb & Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#1a3346]">
        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-400 bg-[#0c1924] border border-[#1e3a50] rounded-sm hover:border-teal-500 dark:hover:border-teal-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
            <span data-en="Back to Orders" data-ar="العودة للطلبات">Back to Orders</span>
          </a>
          <span class="text-neutral-400 dark:text-neutral-600">/</span>
          <span class="text-xs font-mono text-slate-400 uppercase tracking-wider" data-en="Call-off Dispatch" data-ar="طلب سحب وتوريد">Call-off Dispatch</span>
        </div>

        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#0c1924] border border-[#1e3a50] rounded-sm hover:border-neutral-400 transition-colors">
            <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
          </a>
          <button type="submit" form="orderForm" class="btn-cta-orange inline-flex items-center gap-2 px-5 py-2 text-xs font-mono uppercase tracking-wider rounded-sm cursor-pointer">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span data-en="Submit Call-off Order" data-ar="تقديم أمر السحب">Submit Call-off Order</span>
          </button>
        </div>
      </div>

      <!-- Page Title Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <span class="text-xs font-mono uppercase tracking-wider text-teal-600 dark:text-teal-400 font-semibold block mb-1" data-en="CONTRACT CALL-OFF ORDER DISPATCH" data-ar="إرسالية سحب من العقد التجاري">CONTRACT CALL-OFF ORDER DISPATCH</span>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Create Call-off Order & Dispatch" data-ar="إنشاء طلب سحب وتوريد">
            Create Call-off Order & Dispatch
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Select an approved quotation to load line items, authorize drawdowns, and allocate logistics suppliers." data-ar="حدد عرض سعر معتمد لتحميل البنود، وتفويض الكميات وتعيين موردي الشحن.">
            Select an approved quotation to load line items, authorize drawdowns, and allocate logistics suppliers.
          </p>
        </div>
        <div class="text-right rtl:text-left font-mono">
          <span class="text-[10px] uppercase text-neutral-400 block" data-en="BATCH ORDER REF" data-ar="الرقم المرجعي للطلب">BATCH ORDER REF</span>
          <span class="text-lg font-bold text-white tabular-nums"><?= htmlspecialchars($order_ref) ?></span>
        </div>
      </div>

      <?php if ($error_msg): ?>
        <div class="p-4 rounded-sm bg-rose-950/30 dark:bg-rose-950/30 border border-rose-800 dark:border-rose-900/60 flex items-center gap-3 text-xs font-mono text-rose-400 dark:text-rose-400">
          <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
          <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
      <?php endif; ?>

      <form id="orderForm" action="" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="order_ref" value="<?= htmlspecialchars($order_ref) ?>">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

          <!-- Section 1: Top Identification (Full width span 3) -->
          <div class="lg:col-span-3 bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
            <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center gap-2">
              <i data-lucide="file-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="1. ORDER IDENTIFICATION & ALLOCATION" data-ar="1. تحديد الطلب وتخصيص المورد">1. ORDER IDENTIFICATION & ALLOCATION</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
              <div>
                <label class="block text-[11px] font-mono uppercase text-neutral-500 mb-1.5" data-en="Client Account" data-ar="حساب العميل">Client Account</label>
                <?php if (!empty($all_clients)): ?>
                  <select onchange="window.location.href='new_calloff.php?client_id=' + this.value" class="w-full px-3 py-2 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
                    <?php foreach ($all_clients as $c): ?>
                      <option value="<?= htmlspecialchars($c['id']) ?>" <?= $c['id'] == $logged_in_client_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['company_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <input type="text" class="w-full px-3 py-2 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-300 cursor-not-allowed" value="<?= htmlspecialchars($current_client['company_name']) ?>" disabled>
                <?php endif; ?>
              </div>

              <div>
                <label class="block text-[11px] font-mono uppercase text-neutral-500 mb-1.5" data-en="Linked Quotation" data-ar="عرض السعر المربوط">Linked Quotation</label>
                <select name="quotation_id" id="quoteSelect" class="w-full px-3 py-2 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
                  <option value="" data-en="Select Quotation..." data-ar="اختر عرض السعر...">Select Quotation...</option>
                  <?php foreach ($accepted_quotes as $q): ?>
                    <option value="<?= htmlspecialchars($q['id']) ?>" <?= $selected_quote_ref === $q['quotation_number'] ? 'selected' : '' ?>><?= htmlspecialchars($q['quotation_number']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-[11px] font-mono uppercase text-neutral-500 mb-1.5" data-en="Customer PO Number" data-ar="رقم أمر شراء العميل">Customer PO Number</label>
                <input type="text" name="customer_po_number" class="w-full px-3 py-2 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. PO-2026-0098">
              </div>

              <div>
                <label class="block text-[11px] font-mono uppercase text-neutral-500 mb-1.5" data-en="Order Ref Code" data-ar="كود مرجع الطلب">Order Ref Code</label>
                <input type="text" class="w-full px-3 py-2 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-300 tabular-nums cursor-not-allowed" value="<?= htmlspecialchars($order_ref) ?>" readonly>
              </div>

              <div>
                <label class="block text-[11px] font-mono uppercase text-neutral-500 mb-1.5" data-en="Fulfilling Supplier" data-ar="المورد المنفذ">Fulfilling Supplier</label>
                <select name="main_supplier" class="w-full px-3 py-2 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                  <option value="" selected disabled data-en="Select Supplier..." data-ar="اختر المورد...">Select Supplier...</option>
                  <?php foreach ($suppliers as $s): ?>
                    <option value="<?= htmlspecialchars($s['reference_id']) ?>"><?= htmlspecialchars($s['company_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <!-- Section 2: Products Selection Table (2 cols) -->
          <div class="lg:col-span-2 bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden flex flex-col justify-between">
            <div>
              <div class="px-6 py-4 border-b border-[#1a3346] flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <i data-lucide="package-search" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="2. SELECT QUOTED PRODUCTS & DRAW DOWNS" data-ar="2. اختيار منتجات العرض والكميات">2. SELECT QUOTED PRODUCTS & DRAW DOWNS</h3>
                </div>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-left rtl:text-right border-collapse" id="productsTable">
                  <thead>
                    <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                      <th class="px-4 py-3 text-center w-12" data-en="Pick" data-ar="اختيار">Pick</th>
                      <th class="px-4 py-3" data-en="Product Name" data-ar="اسم المنتج">Product Name</th>
                      <th class="px-4 py-3 text-right rtl:text-left" data-en="Price (SAR)" data-ar="السعر (ريال)">Price (SAR)</th>
                      <th class="px-4 py-3 text-right rtl:text-left w-32" data-en="Required Qty" data-ar="الكمية المطلوبة">Required Qty</th>
                      <th class="px-4 py-3 text-right rtl:text-left w-32" data-en="Initial Dispatch" data-ar="الصرف الأولي">Initial Dispatch</th>
                    </tr>
                  </thead>
                  <tbody id="orderItemsTableBody" class="text-xs font-mono divide-y divide-[#152a3a]">
                    <tr>
                      <td colspan="5" class="px-4 py-12 text-center text-xs font-mono text-neutral-400" data-en="Please select a quotation from Section 1 above to load products." data-ar="يرجى اختيار عرض سعر من القسم الأول لتحميل المنتجات.">
                        Please select a quotation from Section 1 above to load products.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="p-6 border-t border-[#1a3346] bg-[#09151f] bg-[#112433]/40">
              <label class="block text-[11px] font-mono uppercase text-neutral-500 mb-1.5 flex items-center gap-1.5">
                <i data-lucide="beaker" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Custom Gas Mixture Blend Request (Optional)" data-ar="طلب خلطة غاز مخصصة (اختياري)">Custom Gas Mixture Blend Request (Optional)</span>
              </label>
              <textarea name="custom_mix_request" rows="2" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="Specify gas components, purity ratio (e.g. 80% Ar / 20% CO2), and cylinder stamping requirements..."></textarea>
            </div>
          </div>

          <!-- Section 3: Logistics & Pricing Summary (1 col) -->
          <div class="space-y-6">

            <!-- Logistics Details -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
              <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="3. LOGISTICS DETAILS" data-ar="3. تفاصيل الخدمات اللوجستية">3. LOGISTICS DETAILS</h3>
              </div>

              <div class="space-y-4 text-xs font-mono">
                <div>
                  <label class="block text-[11px] uppercase text-neutral-500 mb-1" data-en="Order Date" data-ar="تاريخ الطلب">Order Date</label>
                  <input type="date" name="order_date" class="w-full px-3 py-2 bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 tabular-nums focus:outline-2 focus:outline-teal-500" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[11px] uppercase text-neutral-500 mb-1" data-en="Delivery Date" data-ar="تاريخ التسليم">Delivery Date</label>
                    <input type="date" name="delivery_date" class="w-full px-3 py-2 bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 tabular-nums focus:outline-2 focus:outline-teal-500">
                  </div>
                  <div>
                    <label class="block text-[11px] uppercase text-neutral-500 mb-1" data-en="Delivery Time" data-ar="وقت التسليم">Delivery Time</label>
                    <input type="time" name="delivery_time" class="w-full px-3 py-2 bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 tabular-nums focus:outline-2 focus:outline-teal-500" value="02:00">
                  </div>
                </div>
                <div>
                  <label class="block text-[11px] uppercase text-neutral-500 mb-1" data-en="Delivery Site / Location" data-ar="موقع التسليم">Delivery Site / Location</label>
                  <textarea name="delivery_location" rows="2" class="w-full px-3 py-2 font-sans bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="Enter plant coordinates or unloading bay..."><?= htmlspecialchars(trim(($current_client['address'] ?? '') . ' ' . ($current_client['city'] ?? ''))) ?></textarea>
                </div>
                <div>
                  <label class="block text-[11px] uppercase text-neutral-500 mb-1" data-en="Payment Terms" data-ar="شروط الدفع">Payment Terms</label>
                  <select name="payment_terms" class="w-full px-3 py-2 uppercase bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
                    <option value="net30">Net 30 Days</option>
                    <option value="net60">Net 60 Days</option>
                    <option value="cod">Cash on Delivery (COD)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Financial Summary -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
              <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <i data-lucide="calculator" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="ORDER COSTING VALUATION" data-ar="تقييم تكلفة الطلب">ORDER COSTING VALUATION</h3>
                </div>
              </div>

              <div class="space-y-3 text-xs font-mono mb-6">
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500" data-en="Subtotal (ex. VAT)" data-ar="المجموع الفرعي">Subtotal (ex. VAT)</span>
                  <span class="font-semibold text-white tabular-nums" id="disp_subtotal">0.00 SAR</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500" data-en="VAT (15%)" data-ar="ضريبة القيمة المضافة">VAT (15%)</span>
                  <span class="font-semibold text-white tabular-nums" id="disp_vat">0.00 SAR</span>
                </div>
                <div class="flex justify-between items-center pt-3 border-t border-[#1a3346]">
                  <span class="text-sm font-bold text-white" data-en="Grand Total" data-ar="المبلغ الإجمالي">Grand Total</span>
                  <span class="text-lg font-bold text-teal-400 dark:text-teal-400 tabular-nums" id="disp_total">0.00 SAR</span>
                </div>
              </div>

              <button type="submit" class="w-full btn-cta-orange py-2.5 rounded-sm text-xs font-mono uppercase tracking-wider font-bold cursor-pointer">
                <span data-en="Authorize & Submit Call-off" data-ar="اعتماد وتقديم أمر السحب">Authorize & Submit Call-off</span>
              </button>
            </div>

          </div>

        </div>
      </form>

    </div>
  </main>

  <script>
    lucide.createIcons();

    const orderForm = document.getElementById('orderForm');
    const quoteSelect = document.getElementById('quoteSelect');
    const tbody = document.getElementById('orderItemsTableBody');
    let selectedItems = {};

    if (quoteSelect) {
      quoteSelect.addEventListener('change', async function() {
        const qId = this.value;
        tbody.innerHTML = '';
        selectedItems = {};
        renderSummary();

        if (!qId) {
          tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-xs font-mono text-neutral-400">Please select a quotation from Section 1 above to load products.</td></tr>';
          return;
        }

        try {
          const res = await fetch(`?ajax=get_quote_details&quote_id=${qId}`);
          const data = await res.json();
          
          if (data.items && data.items.length > 0) {
            data.items.forEach((item, index) => {
              const uniqueId = `item_${index}`;
              const tr = document.createElement('tr');
              tr.className = 'hover:bg-[#102332]/50 dark:hover:bg-neutral-800/40 transition-colors';
              
              tr.innerHTML = `
                <td class="px-4 py-3 text-center">
                  <input type="checkbox" class="pick-item w-4 h-4 rounded-sm border-[#1e3a50] text-teal-600 cursor-pointer" data-id="${uniqueId}" data-product-id="${item.db_id}" data-code="${item.code}" data-name="${item.product_name}" data-price="${item.unit_price}" checked>
                </td>
                <td class="px-4 py-3 font-sans font-medium text-white">${item.product_name}</td>
                <td class="px-4 py-3 text-right rtl:text-left tabular-nums text-slate-200">${parseFloat(item.unit_price).toFixed(2)}</td>
                <td class="px-4 py-3 text-right rtl:text-left">
                  <input type="number" min="1" max="${item.qty}" step="any" class="total-qty w-full px-2.5 py-1 text-xs font-mono text-right bg-[#08131c] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500" data-id="${uniqueId}" value="${item.qty}" title="Max available from quote: ${item.qty}">
                </td>
                <td class="px-4 py-3 text-right rtl:text-left">
                  <input type="number" min="0" max="${item.qty}" step="any" class="draw-qty w-full px-2.5 py-1 text-xs font-mono text-right bg-[#08131c] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500" data-id="${uniqueId}" value="0">
                </td>
              `;
              tbody.appendChild(tr);
              
              selectedItems[uniqueId] = {
                product_id: item.db_id,
                code: item.code,
                name: item.product_name,
                total_qty: parseFloat(item.qty),
                draw_qty: 0,
                unit_price: parseFloat(item.unit_price)
              };
            });
            
            bindDynamicEvents();
            renderSummary();
          } else {
            tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-xs font-mono text-neutral-400">No products found in this quotation.</td></tr>';
          }
        } catch (err) {
          console.error("Failed to load quotation details", err);
        }
      });
      
      if (quoteSelect.value) {
        quoteSelect.dispatchEvent(new Event('change'));
      }
    }

    function bindDynamicEvents() {
      document.querySelectorAll('.pick-item').forEach(ch => {
        ch.addEventListener('change', (e) => {
          const id = e.target.dataset.id;
          const totalQtyInput = document.querySelector(`.total-qty[data-id="${id}"]`);
          const drawQtyInput = document.querySelector(`.draw-qty[data-id="${id}"]`);

          if (e.target.checked) {
            totalQtyInput.disabled = false;
            drawQtyInput.disabled = false;
            
            const totalQty = parseFloat(totalQtyInput.value) || 1;
            const drawQty = parseFloat(drawQtyInput.value) || 0;
            
            selectedItems[id] = {
              product_id: e.target.dataset.productId,
              code: e.target.dataset.code,
              name: e.target.dataset.name,
              total_qty: totalQty,
              draw_qty: drawQty,
              unit_price: parseFloat(e.target.dataset.price)
            };
          } else {
            totalQtyInput.disabled = true;
            drawQtyInput.disabled = true;
            delete selectedItems[id];
          }
          renderSummary();
        });
      });

      document.querySelectorAll('.total-qty').forEach(input => {
        input.addEventListener('input', (e) => {
          const id = e.target.dataset.id;
          if (selectedItems[id]) {
            let totalQty = parseFloat(e.target.value) || 0;
            const maxQty = parseFloat(e.target.max) || Infinity;
            
            if (totalQty > maxQty) {
              alert(`You cannot exceed the quoted quantity (${maxQty}).`);
              e.target.value = maxQty;
              totalQty = maxQty;
            }

            const drawInput = document.querySelector(`.draw-qty[data-id="${id}"]`);
            drawInput.max = totalQty;
            
            if(parseFloat(drawInput.value) > totalQty) {
              drawInput.value = totalQty;
            }
            
            selectedItems[id].total_qty = totalQty;
            selectedItems[id].draw_qty = parseFloat(drawInput.value) || 0;
            
            renderSummary();
          }
        });
      });

      document.querySelectorAll('.draw-qty').forEach(input => {
        input.addEventListener('input', (e) => {
          const id = e.target.dataset.id;
          if (selectedItems[id]) {
            let drawQty = parseFloat(e.target.value) || 0;
            const totalQty = selectedItems[id].total_qty;
            
            if(drawQty > totalQty) {
              e.target.value = totalQty;
              drawQty = totalQty;
            }
            
            selectedItems[id].draw_qty = drawQty;
          }
        });
      });
    }

    function renderSummary() {
      let subtotal = 0;

      Object.values(selectedItems).forEach(item => {
        const totalQty = parseFloat(item.total_qty) || 0;
        const unitPrice = parseFloat(item.unit_price) || 0;
        const line = totalQty * unitPrice;
        subtotal += line;
      });

      const vat = subtotal * 0.15;
      const total = subtotal + vat;

      document.getElementById('disp_subtotal').innerText = subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' SAR';
      document.getElementById('disp_vat').innerText = vat.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' SAR';
      document.getElementById('disp_total').innerText = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' SAR';
    }

    orderForm.addEventListener('submit', function(e) {
      document.querySelectorAll('input[name^="items"]').forEach(el => el.remove());

      Object.keys(selectedItems).forEach(id => {
        const item = selectedItems[id];
        ['product_id', 'total_qty', 'draw_qty', 'unit_price', 'code', 'name'].forEach(key => {
          const inp = document.createElement('input');
          inp.type = 'hidden';
          inp.name = `items[${id}][${key}]`;
          inp.value = item[key];
          orderForm.appendChild(inp);
        });
      });
    });

    function applyLanguage(lang) {
      document.querySelectorAll('[data-en]').forEach(el => {
        el.innerText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      });
      document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
    }

    document.addEventListener('DOMContentLoaded', () => {
      applyLanguage(localStorage.getItem('app_lang') || 'en');
    });
  </script>
</body>
</html>