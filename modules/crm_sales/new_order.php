<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'orders.create');

$active_page = 'purchase_orders';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Purchase Orders', 'Create Purchase Order'];

// Client selection & resolution
$selected_client_ref = $_GET['client_id'] ?? ($_POST['client_reference'] ?? '');

$client = null;
if (!empty($selected_client_ref)) {
  if (is_numeric($selected_client_ref)) {
    $stmt = $pdo->prepare("SELECT id, company_name, reference_id, sales_rep_id, address, city FROM partners WHERE id = ? AND partner_type = 'client'");
    $stmt->execute([(int)$selected_client_ref]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
  }
  if (!$client) {
    $stmt = $pdo->prepare("SELECT id, company_name, reference_id, sales_rep_id, address, city FROM partners WHERE reference_id = ? AND partner_type = 'client'");
    $stmt->execute([$selected_client_ref]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
  }
}

if (!$client) {
  $stmt_fallback = $pdo->query("SELECT id, company_name, reference_id, sales_rep_id, address, city FROM partners WHERE partner_type = 'client' ORDER BY id ASC LIMIT 1");
  $client = $stmt_fallback->fetch(PDO::FETCH_ASSOC);
}

$stmt_all_clients = $pdo->query("SELECT id, company_name, reference_id FROM partners WHERE partner_type = 'client' ORDER BY company_name ASC");
$all_clients = $stmt_all_clients->fetchAll(PDO::FETCH_ASSOC);

$client_id_internal = $client['id'] ?? 0;
$client_name = $client['company_name'] ?? 'Unknown Client';
$client_reps = explode(',', $client['sales_rep_id'] ?? '');

$success_msg = '';
$error_msg = '';

try {
  $stmt_quote_items = $pdo->prepare("
    SELECT qi.id as quote_item_id, qi.product_name as item_name, qi.unit_price, qi.unit, qi.qty as total_qty, qi.consumed_qty, q.quotation_number, q.sales_rep_id
    FROM quotation_items qi
    JOIN quotations q ON qi.quotation_id = q.id
    WHERE q.client_id = ? AND q.status = 'accepted' AND (qi.qty - qi.consumed_qty) > 0
    ORDER BY q.issue_date ASC
  ");
  $stmt_quote_items->execute([$client_id_internal]);
  $available_quote_items = $stmt_quote_items->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
  $available_quote_items = [];
}

$stmt_reps = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('Sales Rep', 'Sales Manager', 'Admin') AND status = 'Active' ORDER BY full_name ASC");
$sales_reps = $stmt_reps->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $order_number = $_POST['order_ref'] ?? ('PO-' . rand(10000, 99999));
  $order_date = $_POST['order_date'] ?? date('Y-m-d');
  $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
  $delivery_location = trim($_POST['delivery_address'] ?? '');
  
  $sales_rep_raw = $_POST['sales_rep_id'] ?? [];
  $sales_rep_id = is_array($sales_rep_raw) ? implode(',', $sales_rep_raw) : $sales_rep_raw;
  
  $items = $_POST['items'] ?? [];

  $has_valid_items = false;
  foreach ($items as $item) {
    if (!empty($item['draw_qty']) && (float)$item['draw_qty'] > 0) {
      $has_valid_items = true;
      break;
    }
  }

  if (empty($sales_rep_id)) {
    $error_msg = "Please select at least one sales representative.";
  } elseif (!$has_valid_items) {
    $error_msg = "Please specify a valid quantity for at least one product.";
  } else {
    try {
      $pdo->beginTransaction();

      $subtotal = 0.00;
      $prepared_items = [];

      foreach ($items as $quote_item_id => $item_data) {
        $draw_qty = (float)($item_data['draw_qty'] ?? 0);
        $remaining_qty = (float)($item_data['remaining_qty'] ?? 0);
        
        if ($draw_qty <= 0) continue;

        if ($remaining_qty > 0 && $draw_qty > $remaining_qty) {
          throw new Exception("Order quantity for " . htmlspecialchars($item_data['name']) . " exceeds the remaining quotation balance.");
        }

        $unit_price = (float)($item_data['price'] ?? 0);
        $line_total = $draw_qty * $unit_price;
        $subtotal += $line_total;

        $prepared_items[] = [
          'quote_item_id' => $quote_item_id,
          'quote_ref' => $item_data['quote_ref'] ?? 'DIRECT',
          'item_name' => $item_data['name'],
          'draw_qty' => $draw_qty,
          'unit' => $item_data['unit'] ?? 'CYL',
          'unit_price' => $unit_price,
          'line_total' => $line_total
        ];
      }

      $vat = $subtotal * 0.15;
      $grand_total = $subtotal + $vat;

      $stmt_insert = $pdo->prepare("
        INSERT INTO purchase_orders (order_number, client_id, sales_rep_id, order_date, delivery_date, delivery_address, status, production_status, total_value)
        VALUES (?, ?, ?, ?, ?, ?, 'draft', 'pending', ?)
      ");
      $stmt_insert->execute([
        $order_number,
        $client_id_internal,
        $sales_rep_id,
        $order_date,
        $delivery_date,
        $delivery_location,
        $grand_total
      ]);

      $po_id = $pdo->lastInsertId();

      $stmt_item_insert = $pdo->prepare("
        INSERT INTO purchase_order_items (purchase_order_id, item_name, qty, unit_price, line_total, unit)
        VALUES (?, ?, ?, ?, ?, ?)
      ");
      
      $stmt_update_quote_item = $pdo->prepare("
        UPDATE quotation_items 
        SET consumed_qty = consumed_qty + ? 
        WHERE id = ?
      ");

      foreach ($prepared_items as $pi) {
        $stmt_item_insert->execute([
          $po_id,
          $pi['item_name'],
          $pi['draw_qty'],
          $pi['unit_price'],
          $pi['line_total'],
          $pi['unit']
        ]);
        
        if (is_numeric($pi['quote_item_id']) && (int)$pi['quote_item_id'] > 0) {
          $stmt_update_quote_item->execute([
            $pi['draw_qty'],
            $pi['quote_item_id']
          ]);
        }
      }

      $stmt_activity = $pdo->prepare("
        INSERT INTO client_activities (client_id, activity_text, activity_time, author)
        VALUES (?, ?, CURRENT_TIMESTAMP, 'System')
      ");
      $stmt_activity->execute([
        $client_id_internal,
        "Created new Purchase Order {$order_number} valued at " . number_format($grand_total, 2) . " SAR"
      ]);

      $pdo->commit();

      header("Location: " . app_url('modules/crm_sales/order_details.php?id=' . $po_id));
      exit;
    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $error_msg = $e->getMessage();
    }
  }
}

$order_ref = 'PO-' . rand(10000, 99999);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Purchase Order | I-GAS Enterprise</title>
  
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
        <span data-en="PURCHASE ORDER BOOKING" data-ar="حجز أمر الشراء">PURCHASE ORDER BOOKING</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">ASSIGNMENT: <?= htmlspecialchars($client['reference_id'] ?? 'N/A') ?></span>
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
          <span class="text-xs font-mono text-slate-400 uppercase tracking-wider" data-en="Create Order" data-ar="إنشاء طلب">Create Order</span>
        </div>

        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#0c1924] border border-[#1e3a50] rounded-sm hover:border-neutral-400 transition-colors">
            <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
          </a>
          <button type="submit" form="orderForm" class="btn-cta-orange inline-flex items-center gap-2 px-5 py-2 text-xs font-mono uppercase tracking-wider rounded-sm cursor-pointer">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span data-en="Confirm & Create Order" data-ar="تأكيد وإنشاء الطلب">Confirm & Create Order</span>
          </button>
        </div>
      </div>

      <!-- Header Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <span class="text-xs font-mono uppercase tracking-wider text-teal-600 dark:text-teal-400 font-semibold block mb-1" data-en="COMMERCIAL TRANSACTION DISPATCH" data-ar="إرسال المعاملة التجارية">COMMERCIAL TRANSACTION DISPATCH</span>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Create Purchase Order" data-ar="إنشاء أمر شراء جديد">
            Create Purchase Order
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Draw quantities from accepted client quotations and assign logistics routes." data-ar="سحب الكميات من عروض الأسعار المقبولة وتعيين مسار التوصيل.">
            Draw quantities from accepted client quotations and assign logistics routes.
          </p>
        </div>
        <div class="text-right rtl:text-left font-mono">
          <span class="text-[10px] uppercase text-neutral-400 block" data-en="GENERATED ORDER REF" data-ar="الرقم المرجعي للطلب">GENERATED ORDER REF</span>
          <span class="text-lg font-bold text-white tabular-nums"><?= htmlspecialchars($order_ref) ?></span>
        </div>
      </div>

      <?php if ($error_msg): ?>
        <div class="p-4 rounded-sm bg-rose-950/30 dark:bg-rose-950/30 border border-rose-800 dark:border-rose-900/60 flex items-center gap-3 text-xs font-mono text-rose-400 dark:text-rose-400">
          <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
          <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
      <?php endif; ?>

      <form id="orderForm" action="" method="POST" onsubmit="return validateForm()">
        <?= csrf_field() ?>
        <input type="hidden" name="client_reference" value="<?= htmlspecialchars($client['reference_id'] ?? '') ?>">
        <input type="hidden" name="order_ref" value="<?= htmlspecialchars($order_ref) ?>">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

          <!-- Left: Account & Quote Selection (2 cols) -->
          <div class="lg:col-span-2 space-y-6">
            
            <!-- Account & Sales Reps Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
              <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <i data-lucide="briefcase" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="1. CLIENT ACCOUNT & SALES REPS" data-ar="1. حساب العميل وممثلو المبيعات">1. CLIENT ACCOUNT & SALES REPS</h3>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label class="block text-[11px] font-mono uppercase text-neutral-500 mb-1.5" data-en="Client Account" data-ar="حساب العميل">Client Account</label>
                  <select onchange="window.location.href='new_order.php?client_id=' + this.value" class="w-full px-3 py-2 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 focus:outline-offset-1">
                    <?php foreach ($all_clients as $c): ?>
                      <option value="<?= htmlspecialchars($c['id']) ?>" <?= $c['id'] == $client_id_internal ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['reference_id']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-[11px] font-mono uppercase text-neutral-500 mb-1.5" data-en="Purchase Order Reference" data-ar="مرجع أمر الشراء">Purchase Order Reference</label>
                  <input type="text" class="w-full px-3 py-2 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-300 tabular-nums cursor-not-allowed" value="<?= htmlspecialchars($order_ref) ?>" readonly>
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-mono uppercase text-neutral-500 mb-1.5" data-en="Sales Representative Commission Assignment" data-ar="تعيين عمولة ممثلي المبيعات">Sales Representative Commission Assignment</label>
                <div class="border border-[#1a3346] rounded-sm p-3 max-h-36 overflow-y-auto bg-[#09151f] bg-[#112433]/40">
                  <?php if(empty($sales_reps)): ?>
                    <span class="text-xs font-mono text-neutral-400">No active sales reps found.</span>
                  <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      <?php foreach($sales_reps as $rep): ?>
                        <label class="flex items-center gap-2 p-1.5 rounded-sm hover:bg-[#102332] dark:hover:bg-neutral-800 cursor-pointer text-xs font-mono transition-colors">
                          <input type="checkbox" name="sales_rep_id[]" value="<?= htmlspecialchars($rep['id']) ?>" class="w-4 h-4 rounded-sm border-[#1e3a50] text-teal-600 focus:ring-0 focus:outline-none" <?= in_array($rep['id'], $client_reps) ? 'checked' : '' ?>>
                          <span class="font-medium text-slate-200"><?= htmlspecialchars($rep['full_name']) ?></span> 
                          <span class="text-[10px] text-neutral-400">(<?= htmlspecialchars($rep['role']) ?>)</span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
                <p class="text-[10px] font-mono text-neutral-400 mt-1" data-en="Select one or more sales representatives responsible for this commercial supply agreement." data-ar="اختر ممثل أو أكثر من مسؤولي المبيعات لهذا الاتفاق التجاري.">Select one or more sales representatives responsible for this commercial supply agreement.</p>
              </div>
            </div>

            <!-- Quotation Items Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
              <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <i data-lucide="package-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="2. ACCEPTED QUOTATION LINE ITEMS" data-ar="2. بنود عروض الأسعار المقبولة">2. ACCEPTED QUOTATION LINE ITEMS</h3>
                </div>
                <span class="text-xs font-mono text-neutral-500"><?= count($available_quote_items) ?> <span data-en="ACTIVE QUOTE ITEMS" data-ar="بند متاح">ACTIVE QUOTE ITEMS</span></span>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-left rtl:text-right border-collapse">
                  <thead>
                    <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                      <th class="px-4 py-3 text-center w-12" data-en="Pick" data-ar="اختيار">Pick</th>
                      <th class="px-4 py-3" data-en="Quote Ref" data-ar="رقم العرض">Quote Ref</th>
                      <th class="px-4 py-3" data-en="Product Specification" data-ar="مواصفات المنتج">Product Specification</th>
                      <th class="px-4 py-3 text-right rtl:text-left" data-en="Price (SAR)" data-ar="السعر (ريال)">Price (SAR)</th>
                      <th class="px-4 py-3 text-right rtl:text-left" data-en="Balance" data-ar="الرصيد المتبقي">Balance</th>
                      <th class="px-4 py-3 text-right rtl:text-left w-32" data-en="Draw Qty" data-ar="كمية السحب">Draw Qty</th>
                    </tr>
                  </thead>
                  <tbody class="text-xs font-mono divide-y divide-[#152a3a]">
                    <?php if(empty($available_quote_items)): ?>
                    <tr>
                      <td colspan="6" class="px-4 py-10 text-center text-xs font-mono text-neutral-400">
                        <div class="flex flex-col items-center justify-center gap-2">
                          <i data-lucide="file-x" class="w-6 h-6 text-neutral-400"></i>
                          <span data-en="No active accepted quotations with remaining balances found for this client." data-ar="لا توجد عروض أسعار مقبولة ونشطة بأرصدة متبقية لهذا العميل.">
                            No active accepted quotations with remaining balances found for this client.
                          </span>
                          <a href="<?= app_url('modules/crm_sales/new_quotation.php?client_id=' . (int)$client_id_internal) ?>" class="text-teal-600 dark:text-teal-400 hover:underline mt-1">
                            &rarr; <span data-en="Create New Quotation" data-ar="إنشاء عرض سعر جديد">Create New Quotation</span>
                          </a>
                        </div>
                      </td>
                    </tr>
                    <?php else: ?>
                      <?php foreach ($available_quote_items as $q): ?>
                      <?php 
                        $remaining = $q['total_qty'] - $q['consumed_qty']; 
                        $pct = $q['total_qty'] > 0 ? ($q['consumed_qty'] / $q['total_qty']) * 100 : 0;
                      ?>
                      <tr class="hover:bg-[#102332]/50 dark:hover:bg-neutral-800/40 transition-colors">
                        <td class="px-4 py-3 text-center">
                          <input type="checkbox" class="pick-item w-4 h-4 rounded-sm border-[#1e3a50] text-teal-600 cursor-pointer"
                              data-id="<?= $q['quote_item_id'] ?>" 
                              data-quote="<?= htmlspecialchars($q['quotation_number']) ?>" 
                              data-name="<?= htmlspecialchars($q['item_name']) ?>" 
                              data-price="<?= $q['unit_price'] ?>"
                              data-unit="<?= htmlspecialchars($q['unit']) ?>"
                              data-remaining="<?= $remaining ?>">
                        </td>
                        <td class="px-4 py-3 text-neutral-500"><?= htmlspecialchars($q['quotation_number']) ?></td>
                        <td class="px-4 py-3 font-sans font-medium text-white"><?= htmlspecialchars($q['item_name']) ?></td>
                        <td class="px-4 py-3 text-right rtl:text-left tabular-nums text-slate-200"><?= number_format($q['unit_price'], 2) ?></td>
                        <td class="px-4 py-3 text-right rtl:text-left">
                          <div class="font-bold tabular-nums text-white">
                            <?= number_format($remaining, 2) ?> <span class="text-[10px] text-neutral-400 font-normal"><?= htmlspecialchars($q['unit']) ?></span>
                          </div>
                          <div class="w-full bg-[#132636] h-1.5 rounded-full mt-1 overflow-hidden">
                            <div class="bg-teal-600 h-full rounded-full" style="width: <?= min(100, $pct) ?>%;"></div>
                          </div>
                        </td>
                        <td class="px-4 py-3 text-right rtl:text-left">
                          <input type="number" min="0" max="<?= $remaining ?>" step="any" class="item-qty w-full px-2.5 py-1.5 text-xs font-mono text-right bg-[#112433] border border-[#1e3a50] rounded-sm text-white disabled:bg-[#102230] dark:disabled:bg-[#0c1924] disabled:text-neutral-400 disabled:cursor-not-allowed" data-id="<?= $q['quote_item_id'] ?>" value="0" disabled>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>

          <!-- Right: Logistics & Costing (1 col) -->
          <div class="space-y-6">
            
            <!-- Logistics Details Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
              <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="3. LOGISTICS DISPATCH" data-ar="3. التوزيع والخدمات اللوجستية">3. LOGISTICS DISPATCH</h3>
              </div>

              <div class="space-y-4 text-xs font-mono">
                <div>
                  <label class="block text-[11px] uppercase text-neutral-500 mb-1" data-en="Order Date" data-ar="تاريخ الطلب">Order Date</label>
                  <input type="date" name="order_date" class="w-full px-3 py-2 bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 tabular-nums focus:outline-2 focus:outline-teal-500" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                  <label class="block text-[11px] uppercase text-neutral-500 mb-1" data-en="Expected Delivery Date" data-ar="تاريخ التسليم المتوقع">Expected Delivery Date</label>
                  <input type="date" name="delivery_date" class="w-full px-3 py-2 bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 tabular-nums focus:outline-2 focus:outline-teal-500">
                </div>
                <div>
                  <label class="block text-[11px] uppercase text-neutral-500 mb-1" data-en="Delivery Address / Facility" data-ar="عنوان التوصيل / المنشأة">Delivery Address / Facility</label>
                  <textarea name="delivery_address" rows="3" class="w-full px-3 py-2 font-sans bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="Enter plant coordinates or delivery address..."><?= htmlspecialchars(trim(($client['address'] ?? '') . ' ' . ($client['city'] ?? ''))) ?></textarea>
                </div>
              </div>
            </div>

            <!-- Order Costing Summary -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
              <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <i data-lucide="calculator" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="FINANCIAL SUMMARY" data-ar="الملخص المالي">FINANCIAL SUMMARY</h3>
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
                  <span class="text-sm font-bold text-white" data-en="Total Order Value" data-ar="إجمالي قيمة الطلب">Total Order Value</span>
                  <span class="text-lg font-bold text-teal-400 dark:text-teal-400 tabular-nums" id="disp_total">0.00 SAR</span>
                </div>
              </div>

              <button type="submit" class="w-full btn-cta-orange py-2.5 rounded-sm text-xs font-mono uppercase tracking-wider font-bold cursor-pointer">
                <span data-en="Authorize & Book Order" data-ar="اعتماد وحجز الطلب">Authorize & Book Order</span>
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
    let selectedItems = {};

    document.querySelectorAll('.pick-item').forEach(ch => {
      ch.addEventListener('change', (e) => {
        const id = e.target.dataset.id;
        const price = parseFloat(e.target.dataset.price) || 0;
        const name = e.target.dataset.name;
        const quote_ref = e.target.dataset.quote;
        const unit = e.target.dataset.unit;
        const remaining = parseFloat(e.target.dataset.remaining) || 0;
        
        const qtyInput = document.querySelector(`.item-qty[data-id="${id}"]`);

        if (e.target.checked) {
          qtyInput.disabled = false;
          if(qtyInput.value == 0) qtyInput.value = 1;
          let currentVal = parseFloat(qtyInput.value) || 1;
          if(currentVal > remaining) currentVal = remaining;
          qtyInput.value = currentVal;

          selectedItems[id] = {
            quote_ref: quote_ref,
            name: name,
            unit: unit,
            remaining_qty: remaining,
            draw_qty: currentVal,
            price: price
          };
        } else {
          qtyInput.disabled = true;
          qtyInput.value = 0;
          delete selectedItems[id];
        }
        renderSummary();
        bindItemInputs();
      });
    });

    document.querySelectorAll('.item-qty').forEach(input => {
      input.addEventListener('input', (e) => {
        const id = e.target.dataset.id;
        if (selectedItems[id]) {
          let val = parseFloat(e.target.value) || 0;
          const remaining = selectedItems[id].remaining_qty;
          
          if(val > remaining) {
            val = remaining;
            e.target.value = val;
          }
          
          selectedItems[id].draw_qty = val;
          renderSummary();
          bindItemInputs();
        }
      });
    });

    function bindItemInputs() {
      document.querySelectorAll('input[name^="items"]').forEach(el => el.remove());

      Object.keys(selectedItems).forEach(id => {
        const item = selectedItems[id];
        ['quote_ref', 'name', 'unit', 'remaining_qty', 'draw_qty', 'price'].forEach(key => {
          const inp = document.createElement('input');
          inp.type = 'hidden';
          inp.name = `items[${id}][${key}]`;
          inp.value = item[key];
          orderForm.appendChild(inp);
        });
      });
    }

    function renderSummary() {
      let subtotal = 0;

      Object.values(selectedItems).forEach(item => {
        const qty = parseFloat(item.draw_qty) || 0;
        const unitPrice = parseFloat(item.price) || 0;
        const line = qty * unitPrice;
        subtotal += line;
      });

      const vat = subtotal * 0.15;
      const total = subtotal + vat;

      document.getElementById('disp_subtotal').innerText = subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' SAR';
      document.getElementById('disp_vat').innerText = vat.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' SAR';
      document.getElementById('disp_total').innerText = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' SAR';
    }

    function validateForm() {
      const checkboxes = document.querySelectorAll('input[name="sales_rep_id[]"]');
      let isChecked = false;
      for (let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) {
          isChecked = true;
          break;
        }
      }
      if (!isChecked && checkboxes.length > 0) {
        alert('Please select at least one sales representative.');
        return false;
      }
      if (Object.keys(selectedItems).length === 0) {
        alert('Please select and specify quantity for at least one item from the quotations.');
        return false;
      }
      return true;
    }

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