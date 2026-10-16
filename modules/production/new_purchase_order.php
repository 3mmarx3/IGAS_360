<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('orders.create');

$active_page = 'raw_materials';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Procurement', 'Purchase Orders', 'New Order'];

$material_row_id = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;
$pre_supplier_ref = '';
$pre_material_name = '';
$pre_material_unit = '';
$pre_material_code = '';
$pre_unit_price = 0.00;

if ($material_row_id > 0) {
  $stmt = $pdo->prepare("
    SELECT 
      rm.material_name, rm.unit, rm.material_sku, rm.unit_cost,
      p.reference_id
    FROM raw_materials rm
    JOIN partners p ON rm.supplier_id = p.id
    WHERE rm.id = :id
    LIMIT 1
  ");
  $stmt->execute(['id' => $material_row_id]);
  $matRow = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($matRow) {
    $pre_supplier_ref = $matRow['reference_id'];
    $pre_material_name = $matRow['material_name'];
    $pre_material_unit = $matRow['unit'];
    $pre_material_code = $matRow['material_sku'];
    $pre_unit_price  = (float)$matRow['unit_cost'];
  }
}

$suppliersStmt = $pdo->query("SELECT reference_id, company_name FROM partners WHERE partner_type = 'supplier' AND status = 'approved' ORDER BY company_name ASC");
$suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  require_csrf();
  $supplier_reference = trim($_POST['supplier_reference'] ?? '');
  $order_date     = $_POST['order_date'] ?? date('Y-m-d');
  $delivery_date   = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
  $delivery_priority = $_POST['delivery_priority'] ?? 'standard';
  $payment_terms   = $_POST['payment_terms'] ?? 'cod';
  $specs       = $_POST['specs'] ?? '';
  $delivery_address  = $_POST['delivery_address'] ?? '';

  $checkSupplier = $pdo->prepare("SELECT reference_id FROM partners WHERE reference_id = ? AND partner_type = 'supplier' LIMIT 1");
  $checkSupplier->execute([$supplier_reference]);
  if (!$checkSupplier->fetch()) {
    $error_message = "Selected supplier is not verified. Please choose a valid supplier.";
  } else {
    $order_number = 'ORD-' . rand(7000, 7999);
    $status = 'draft';
    $total_value = 0;

    try {
      $pdo->beginTransaction();

      $poStmt = $pdo->prepare("
        INSERT INTO purchase_orders 
        (order_number, client_id, supplier_reference, specs, order_date, delivery_address, delivery_date, delivery_priority, payment_terms, status, total_value) 
        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $poStmt->execute([
        $order_number, $supplier_reference, $specs, $order_date,
        $delivery_address, $delivery_date, $delivery_priority, $payment_terms, $status, $total_value
      ]);

      $po_id = $pdo->lastInsertId();

      if (isset($_POST['items']) && is_array($_POST['items'])) {
        $itemStmt = $pdo->prepare("
          INSERT INTO purchase_order_items 
          (purchase_order_id, supplier_reference, product_id, item_name, item_code, qty, unit, unit_price, line_total) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($_POST['items'] as $item) {
          $qty    = (float)$item['qty'];
          $price   = (float)$item['price'];
          $line_total = $qty * $price;
          $total_value += $line_total;
          $item_code = trim($item['code']);

          $matStmt = $pdo->prepare("SELECT id FROM raw_materials WHERE material_sku = ? LIMIT 1");
          $matStmt->execute([$item_code]);
          $matRow = $matStmt->fetch(PDO::FETCH_ASSOC);
          $actual_product_id = $matRow ? $matRow['id'] : 0;

          $itemStmt->execute([
            $po_id,
            $supplier_reference,
            $actual_product_id,
            $item['name'],
            $item_code,
            $qty,
            $item['unit'],
            $price,
            $line_total
          ]);

          // Update stock immediately when order placed
          if ($actual_product_id > 0) {
            $updateStock = $pdo->prepare("UPDATE raw_materials SET current_stock = current_stock + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStock->execute([$qty, $actual_product_id]);

            $trx_ref = 'TRX-' . rand(10000, 99999);
            $logTrx = $pdo->prepare("
              INSERT INTO material_transactions (material_id, transaction_ref, type, quantity, source_ref, logged_by, transaction_date)
              VALUES (?, ?, 'in', ?, ?, 'System', CURRENT_TIMESTAMP)
            ");
            $logTrx->execute([$actual_product_id, $trx_ref, $qty, $order_number]);
          }
        }
      }

      $updateTotalStmt = $pdo->prepare("UPDATE purchase_orders SET total_value = ? WHERE id = ?");
      $updateTotalStmt->execute([$total_value, $po_id]);

      $pdo->commit();

      redirect('modules/production/raw_materials.php?ordered=1');

    } catch (Exception $e) {
      $pdo->rollBack();
      $error_message = 'Unable to create purchase order. Please check item details and quantities.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="New Purchase Order | I-GAS Enterprise" data-ar="أمر شراء جديد | I-GAS Enterprise">New Purchase Order | I-GAS Enterprise</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  
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
        <span data-en="FEEDSTOCK PROCUREMENT DISPATCH" data-ar="أوامر شراء وتوريد المواد الخام">FEEDSTOCK PROCUREMENT DISPATCH</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">ENTRY: <?= date('Y-m-d') ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400 font-mono">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Breadcrumb Navigation & Header Actions -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <div class="flex items-center gap-2 mb-1.5 text-xs font-mono text-neutral-500">
            <a href="<?= app_url('modules/production/raw_materials.php') ?>" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors uppercase tracking-wider flex items-center gap-1.5" data-en="Raw Materials" data-ar="المواد الخام">
              <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
              <span>Back to Raw Materials</span>
            </a>
            <span class="text-neutral-300 dark:text-neutral-700">/</span>
            <span class="text-teal-600 dark:text-teal-400 uppercase tracking-wider font-semibold" data-en="New Purchase Order" data-ar="أمر شراء جديد">New Purchase Order</span>
          </div>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Create Supplier Purchase Order" data-ar="إنشاء أمر شراء مورد">
            Create Supplier Purchase Order
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Reorder liquid nitrogen, oxygen, argon feedstock, or cylinder equipment directly from certified partners." data-ar="إعادة طلب النيتروجين والأكسجين والأرجون السائل أو معدات الأسطوانات مباشرة من الشركاء المعتمدين.">
            Reorder liquid nitrogen, oxygen, argon feedstock, or cylinder equipment directly from certified partners.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a href="<?= app_url('modules/production/raw_materials.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-mono uppercase tracking-wider bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-200 rounded-sm">
            <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
          </a>
        </div>
      </div>

      <?php if (isset($error_message)): ?>
      <div class="p-3.5 border border-rose-800 dark:border-rose-900 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 rounded-sm flex items-center gap-3 text-xs font-mono">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <span><?= htmlspecialchars($error_message) ?></span>
      </div>
      <?php endif; ?>

      <form action="" method="POST" id="po-master-form">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
          <!-- Left 2 Cols: Core Order Parameters & Items Table -->
          <div class="xl:col-span-2 space-y-6">
            <!-- Core Parameters Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316] space-y-4">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="1. Order Header & Feedstock Supplier" data-ar="1. رأس الطلب ومورد المواد الخام">1. Order Header & Feedstock Supplier</span>
              </h2>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-1">
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Primary Supplier *" data-ar="المورد الأساسي *">
                    Primary Supplier *
                  </label>
                  <select name="supplier_reference" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                    <option value="" disabled <?= empty($pre_supplier_ref) ? 'selected' : '' ?> data-en="Choose Supplier..." data-ar="اختر المورد...">Choose Supplier...</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= htmlspecialchars($s['reference_id']) ?>" <?= $pre_supplier_ref === $s['reference_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['company_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Issue Date *" data-ar="تاريخ الإصدار *">
                    Issue Date *
                  </label>
                  <input type="date" name="order_date" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Target Delivery Date" data-ar="تاريخ التوصيل المستهدف">
                    Target Delivery Date
                  </label>
                  <input type="date" name="delivery_date" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                </div>
              </div>
            </div>

            <!-- Line Items Configuration Table -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden border-s-4 border-s-[#f97316]">
              <div class="p-4 sm:p-5 border-b border-[#1a3346] flex items-center justify-between bg-[#071018]">
                <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white flex items-center gap-2">
                  <i data-lucide="layers" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <span data-en="2. Feedstock Line Items" data-ar="2. بنود المواد الخام">2. Feedstock Line Items</span>
                </h2>
                <button type="button" onclick="appendItemRow()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-wider bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-200 rounded-sm transition-colors">
                  <i data-lucide="plus" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
                  <span data-en="Add Line" data-ar="إضافة بند">Add Line</span>
                </button>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-left rtl:text-right border-collapse" id="items-data-table">
                  <thead>
                    <tr class="text-[11px] font-mono uppercase tracking-wider border-b border-[#1a3346] bg-[#102230]/60 bg-[#0c1924] text-slate-400">
                      <th class="px-4 py-3 font-semibold w-40">SKU / Code</th>
                      <th class="px-4 py-3 font-semibold" data-en="Description / Material" data-ar="المادة / الوصف">Description / Material</th>
                      <th class="px-4 py-3 font-semibold text-right w-28" data-en="Quantity" data-ar="الكمية">Quantity</th>
                      <th class="px-4 py-3 font-semibold text-center w-24" data-en="Unit" data-ar="الوحدة">Unit</th>
                      <th class="px-4 py-3 font-semibold text-right w-32" data-en="Unit Price" data-ar="سعر الوحدة">Unit Price</th>
                      <th class="px-4 py-3 text-center w-12"></th>
                    </tr>
                  </thead>
                  <tbody class="text-xs divide-y divide-[#152a3a]">
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Right 1 Col: Logistics & Summary -->
          <div class="space-y-6">
            <!-- Logistics & Specs Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="3. Logistics & Terms" data-ar="3. اللوجستيات والشروط">3. Logistics & Terms</span>
              </h2>

              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Delivery Priority" data-ar="أولوية التوصيل">
                    Delivery Priority
                  </label>
                  <select name="delivery_priority" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
                    <option value="standard" selected data-en="Standard Delivery" data-ar="توصيل عادي">Standard Delivery</option>
                    <option value="urgent" data-en="Urgent Tanker Dispatch" data-ar="إرسال صهريج عاجل">Urgent Tanker Dispatch</option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Payment Terms" data-ar="شروط الدفع">
                    Payment Terms
                  </label>
                  <select name="payment_terms" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
                    <option value="cod" selected>Cash On Delivery (COD)</option>
                    <option value="net30">Net 30 Days</option>
                    <option value="net60">Net 60 Days</option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Destination Tank / Bay" data-ar="الخزان أو الموقع المستهدف">
                    Destination Tank / Bay
                  </label>
                  <input type="text" name="delivery_address" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. Jeddah Cryo Tank Farm, Vessel T-102">
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Technical Specifications / CoA" data-ar="المواصفات الفنية وشهادة التحليل">
                    Technical Specifications / CoA
                  </label>
                  <textarea name="specs" rows="3" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 resize-none" placeholder="Purity grade ≥ 99.999%, decanting pressure max 16 bar..."></textarea>
                </div>
              </div>
            </div>

            <!-- Financial Summary Card (Single Orange CTA) -->
            <div class="bg-[#102230]/70 bg-[#0c1924]/70 border border-[#1a3346] rounded-sm p-5 space-y-5 border-s-4 border-s-[#f97316]">
              <div>
                <span class="text-xs font-mono text-neutral-500 uppercase tracking-wider block mb-1" data-en="Financial Aggregate Summary" data-ar="الملخص المالي الإجمالي">
                  Financial Aggregate Summary
                </span>
                <div class="text-3xl font-mono font-bold text-white tabular-nums">
                  <span class="text-base font-normal text-neutral-500">SAR</span>
                  <span id="po-grand-total">0.00</span>
                </div>
                <span class="text-[11px] font-mono text-neutral-400 block mt-1" data-en="Excludes VAT (15% applied upon invoicing)" data-ar="غير شامل ضريبة القيمة المضافة">Excludes VAT (15% applied upon invoicing)</span>
              </div>

              <div class="space-y-2 pt-3 border-t border-[#1e3a50]/60">
                <button type="submit" class="btn-cta-orange w-full py-2.5 px-4 text-xs font-mono uppercase tracking-wider rounded-sm flex items-center justify-center gap-2">
                  <i data-lucide="file-check" class="w-4 h-4"></i>
                  <span data-en="Commit Purchase Order" data-ar="اعتماد أمر الشراء">Commit Purchase Order</span>
                </button>
                <a href="<?= app_url('modules/production/raw_materials.php') ?>" class="w-full block py-2 px-4 text-xs font-mono uppercase tracking-wider bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-300 rounded-sm text-center transition-colors">
                  <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </form>

    </div>
  </main>

  <script>
    lucide.createIcons();

    let incrementalRowIndex = 0;

    function recalculateSystemTotals() {
      let aggregateSum = 0;
      const rows = document.querySelectorAll('#items-data-table tbody tr');
      
      rows.forEach(r => {
        const qEl = r.querySelector('.qty-field');
        const pEl = r.querySelector('.price-field');
        
        const qtyValue = parseFloat(qEl ? qEl.value : 0) || 0;
        const priceValue = parseFloat(pEl ? pEl.value : 0) || 0;
        aggregateSum += (qtyValue * priceValue);
      });

      const totalEl = document.getElementById('po-grand-total');
      if (totalEl) {
        totalEl.textContent = aggregateSum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
    }

    function appendItemRow(cVal = '', nVal = '', uVal = '', qVal = 1, pVal = 0) {
      if (!cVal) {
        cVal = 'SKU-' + Math.floor(100000 + Math.random() * 900000);
      }

      const tbody = document.querySelector('#items-data-table tbody');
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-[#102332] transition-colors';
      
      tr.innerHTML = `
        <td class="px-4 py-2">
          <input type="text" name="items[${incrementalRowIndex}][code]" value="${cVal}" class="w-full px-2 py-1 text-xs font-mono tabular-nums bg-[#09151f] border border-[#1e3a50] rounded-sm text-slate-400" placeholder="SKU-CODE" required readonly tabindex="-1">
        </td>
        <td class="px-4 py-2">
          <input type="text" name="items[${incrementalRowIndex}][name]" value="${nVal}" class="w-full px-2 py-1 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 font-medium" placeholder="Material item description" required>
        </td>
        <td class="px-4 py-2">
          <input type="number" step="0.01" name="items[${incrementalRowIndex}][qty]" value="${qVal}" class="w-full px-2 py-1 text-xs font-mono tabular-nums text-right bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 qty-field" required oninput="recalculateSystemTotals()">
        </td>
        <td class="px-4 py-2">
          <input type="text" name="items[${incrementalRowIndex}][unit]" value="${uVal}" class="w-full px-2 py-1 text-xs font-mono text-center bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="kg/L" required>
        </td>
        <td class="px-4 py-2">
          <input type="number" step="0.01" name="items[${incrementalRowIndex}][price]" value="${pVal}" class="w-full px-2 py-1 text-xs font-mono tabular-nums text-right bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 price-field" required oninput="recalculateSystemTotals()">
        </td>
        <td class="px-4 py-2 text-center whitespace-nowrap">
          <button type="button" onclick="this.closest('tr').remove(); recalculateSystemTotals();" class="p-1 text-neutral-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors rounded-sm" title="Remove line">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </td>
      `;
      
      tbody.appendChild(tr);
      lucide.createIcons({ root: tr });
      incrementalRowIndex++;
      recalculateSystemTotals();
    }

    document.addEventListener('DOMContentLoaded', () => {
      const pName = <?= json_encode($pre_material_name) ?>;
      const pCode = <?= json_encode($pre_material_code) ?>;
      const pUnit = <?= json_encode($pre_material_unit) ?>;
      const pPrice = <?= json_encode($pre_unit_price) ?>;
      
      if (pName) {
        appendItemRow(pCode, pName, pUnit, 1, pPrice);
      } else {
        appendItemRow('', '', '', 1, 0);
      }
    });
  </script>
</body>
</html>