<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('suppliers.view');

$active_page = 'suppliers_directory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Suppliers Directory', 'Supplier Profile'];

$supplier_ref = $_GET['id'] ?? null;
$db_supplier = null;

if ($supplier_ref) {
  $stmt = $pdo->prepare("SELECT * FROM partners WHERE (reference_id = ? OR CAST(id AS CHAR) = ?) AND partner_type = 'supplier'");
  $stmt->execute([$supplier_ref, $supplier_ref]);
  $db_supplier = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$db_supplier) {
  $stmt = $pdo->query("SELECT * FROM partners WHERE partner_type = 'supplier' ORDER BY id ASC LIMIT 1");
  $db_supplier = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
}

if (!$db_supplier) {
  redirect('modules/production/suppliers_directory.php');
}

$supplier_id = $db_supplier['id'];

$words = preg_split('/\s+/', trim($db_supplier['company_name'] ?? ''));
$initials = '';
foreach ($words as $w) {
  if ($w !== '') {
    $initials .= mb_substr($w, 0, 1, 'UTF-8');
  }
}
$initials = mb_strtoupper(mb_substr($initials, 0, 2, 'UTF-8'));

$supplier = [
  'id' => $db_supplier['reference_id'] ?? '',
  'name' => $db_supplier['company_name'] ?? '',
  'initials' => $initials,
  'category' => $db_supplier['segment'] ?? '',
  'status' => $db_supplier['status'] ?? 'pending',
  'rating' => $db_supplier['rating'] ?? '0.0',
  'contact_person' => trim(($db_supplier['contact_first_name'] ?? '') . ' ' . ($db_supplier['contact_last_name'] ?? '')),
  'position' => $db_supplier['job_title'] ?? '',
  'phone' => $db_supplier['phone'] ?? '',
  'email' => $db_supplier['email'] ?? '',
  'address' => trim(implode(', ', array_filter([
    $db_supplier['address'] ?? '',
    $db_supplier['city'] ?? '',
    $db_supplier['country'] ?? ''
  ]))),
  'tax_id' => $db_supplier['tax_id'] ?? '',
  'cr_number' => $db_supplier['cr_number'] ?? '',
  'payment_terms' => $db_supplier['payment_terms'] ?? ''
];

$stmt_metrics = $pdo->prepare("
  SELECT 
    (
      (SELECT COUNT(mt.id) FROM material_transactions mt JOIN raw_materials rm ON mt.material_id = rm.id WHERE rm.supplier_id = ? AND mt.type = 'in')
      +
      (SELECT COUNT(id) FROM raw_materials WHERE supplier_id = ? AND opening_stock > 0)
    ) as total_deliveries,
    (SELECT SUM(current_stock) FROM raw_materials WHERE supplier_id = ?) as total_volume
");
$stmt_metrics->execute([$supplier_id, $supplier_id, $supplier_id]); 
$db_metrics = $stmt_metrics->fetch(PDO::FETCH_ASSOC);

$metrics = [
  'total_deliveries' => (int)($db_metrics['total_deliveries'] ?? 0),
  'total_volume' => (float)($db_metrics['total_volume'] ?? 0),
  'on_time_rate' => 98.5,
  'defect_rate' => 0.2
];

$stmt_materials = $pdo->prepare("
  SELECT material_sku, material_name, category, current_stock, unit, unit_cost, stock_status 
  FROM raw_materials 
  WHERE supplier_id = ?
  ORDER BY id DESC
");
$stmt_materials->execute([$supplier_id]);
$supplier_materials = $stmt_materials->fetchAll(PDO::FETCH_ASSOC);

$stmt_transactions = $pdo->prepare("
  SELECT 
    mt.transaction_ref, 
    mt.transaction_date as date, 
    rm.material_name as item, 
    mt.quantity, 
    rm.unit,
    rm.unit_cost,
    (mt.quantity * rm.unit_cost) as total_value
  FROM material_transactions mt
  JOIN raw_materials rm ON mt.material_id = rm.id
  WHERE rm.supplier_id = ? AND mt.type = 'in'
  
  UNION ALL
  
  SELECT 
    CONCAT('INIT-', material_sku) as transaction_ref, 
    created_at as date, 
    material_name as item, 
    opening_stock as quantity, 
    unit,
    unit_cost,
    (opening_stock * unit_cost) as total_value
  FROM raw_materials
  WHERE supplier_id = ? AND opening_stock > 0
  
  ORDER BY date DESC 
  LIMIT 5
");
$stmt_transactions->execute([$supplier_id, $supplier_id]);
$recent_transactions = $stmt_transactions->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
  'approved' => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label' => 'Active Partner'],
  'pending'  => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Pending Verification'],
  'suspended' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Suspended'],
  'rejected' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Rejected'],
];

$ss = $statusStyles[$supplier['status']] ?? $statusStyles['pending'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($supplier['name']) ?> | I-GAS Enterprise</title>
  
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
        <span data-en="FEEDSTOCK VENDOR PROFILE" data-ar="ملف مورد المواد الخام">FEEDSTOCK VENDOR PROFILE</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">ID: <?= htmlspecialchars($supplier['id']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400 font-mono">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Breadcrumb Navigation -->
      <div class="flex items-center gap-2 text-xs font-mono text-neutral-500">
        <a href="<?= app_url('modules/production/suppliers_directory.php') ?>" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors uppercase tracking-wider flex items-center gap-1.5" data-en="Suppliers Directory" data-ar="دليل الموردين">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          <span>Back to Suppliers Directory</span>
        </a>
        <span class="text-neutral-300 dark:text-neutral-700">/</span>
        <span class="text-teal-600 dark:text-teal-400 uppercase tracking-wider font-semibold"><?= htmlspecialchars($supplier['id']) ?></span>
      </div>

      <!-- Profile Header Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-sm bg-[#112433] text-white flex items-center justify-center font-mono font-bold text-lg border border-neutral-700 flex-shrink-0">
            <?= htmlspecialchars($supplier['initials']) ?>
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-3 mb-1">
              <h1 class="text-xl md:text-2xl font-bold font-mono text-white">
                <?= htmlspecialchars($supplier['name']) ?>
              </h1>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-sm text-xs font-mono border <?= $ss['border'] ?> <?= $ss['bg'] ?> <?= $ss['text'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $ss['dot'] ?>"></span>
                <?= htmlspecialchars($ss['label']) ?>
              </span>
            </div>
            <p class="text-xs font-mono text-neutral-500">
              <?= htmlspecialchars($supplier['id']) ?> · <?= htmlspecialchars($supplier['category'] ?: 'General Gas / Equipment') ?>
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
          <?php if (!empty($supplier['email'])): ?>
          <a href="mailto:<?= htmlspecialchars($supplier['email']) ?>" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-mono uppercase tracking-wider bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-200 rounded-sm transition-colors">
            <i data-lucide="mail" class="w-3.5 h-3.5 text-neutral-500"></i>
            <span data-en="Contact" data-ar="مراسلة">Contact</span>
          </a>
          <?php endif; ?>
          <?php if (has_permission('suppliers.edit')): ?>
          <a href="<?= app_url('modules/production/edit_supplier.php?id=' . urlencode($supplier['id'])) ?>" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm">
            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
            <span data-en="Edit Details" data-ar="تعديل البيانات">Edit Details</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento Telemetry Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Supplier Rating -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Supplier Rating" data-ar="تقييم المورد">Supplier Rating</span>
            <i data-lucide="star" class="w-4 h-4 text-amber-500 fill-amber-500"></i>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-mono font-bold text-white tabular-nums"><?= htmlspecialchars($supplier['rating']) ?></span>
            <span class="text-xs font-mono text-neutral-400">/ 5.0</span>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346]">
            <div class="h-1.5 w-full bg-[#09151f] rounded-full overflow-hidden border border-[#1e3a50]">
              <div class="h-full bg-teal-600 dark:bg-teal-500" style="width: <?= ((float)$supplier['rating'] / 5) * 100 ?>%;"></div>
            </div>
          </div>
        </div>

        <!-- Total Deliveries -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Total Deliveries" data-ar="إجمالي الشحنات">Total Deliveries</span>
            <i data-lucide="package-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-white tabular-nums">
            <?= number_format($metrics['total_deliveries']) ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span data-en="Lifetime shipments" data-ar="شحنات متسلمة">Lifetime shipments</span>
            <span class="text-teal-600 dark:text-teal-400 font-semibold">Feedstock</span>
          </div>
        </div>

        <!-- Total Volume Provided -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Stock Supplied" data-ar="المخزون المورد">Stock Supplied</span>
            <i data-lucide="database" class="w-4 h-4 text-sky-600 dark:text-sky-400"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-white tabular-nums">
            <?= number_format($metrics['total_volume'], 1) ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span data-en="Active Plant Inventory" data-ar="مخزون المصنع النشط">Active Plant Inventory</span>
            <span class="text-sky-600 dark:text-sky-400 font-semibold">Units / kg / L</span>
          </div>
        </div>

        <!-- On-Time Delivery -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="On-Time Delivery" data-ar="التوصيل في الموعد">On-Time Delivery</span>
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-500"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
            <?= $metrics['on_time_rate'] ?>%
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span data-en="SLA Compliance" data-ar="الالتزام باتفاقية الخدمة">SLA Compliance</span>
            </span>
            <span>High Grade</span>
          </div>
        </div>
      </div>

      <!-- Main Grid: Tables (2 cols) and Metadata Panels (1 col) -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Materials and Transactions -->
        <div class="xl:col-span-2 space-y-6">
          <!-- Supplied Materials Catalog -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-[#1a3346] flex items-center justify-between bg-[#071018]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white flex items-center gap-2">
                <i data-lucide="package" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Supplied Materials & Feedstock" data-ar="المواد الخام والتوريدات">Supplied Materials & Feedstock</span>
              </h2>
              <span class="text-xs font-mono px-2 py-0.5 rounded-sm bg-[#112433] text-slate-300">
                <?= count($supplier_materials) ?>
              </span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left rtl:text-right border-collapse">
                <thead>
                  <tr class="text-[11px] font-mono uppercase tracking-wider border-b border-[#1a3346] bg-[#102230]/60 bg-[#0c1924] text-slate-400">
                    <th class="px-4 py-3 font-semibold">SKU</th>
                    <th class="px-4 py-3 font-semibold" data-en="Material Name" data-ar="اسم المادة">Material Name</th>
                    <th class="px-4 py-3 font-semibold" data-en="Category" data-ar="الفئة">Category</th>
                    <th class="px-4 py-3 font-semibold text-right" data-en="Current Stock" data-ar="المخزون الحالي">Current Stock</th>
                    <th class="px-4 py-3 font-semibold text-center" data-en="Status" data-ar="الحالة">Status</th>
                  </tr>
                </thead>
                <tbody class="text-xs divide-y divide-[#152a3a]">
                  <?php if(empty($supplier_materials)): ?>
                  <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-neutral-500 font-mono" data-en="No materials currently registered under this supplier." data-ar="لا توجد مواد مسجلة حاليًا لهذا المورد.">
                      No materials currently registered under this supplier.
                    </td>
                  </tr>
                  <?php else: ?>
                    <?php foreach ($supplier_materials as $m): ?>
                    <tr class="hover:bg-[#102332] transition-colors">
                      <td class="px-4 py-3 font-mono font-semibold tabular-nums text-teal-400 dark:text-teal-400 whitespace-nowrap">
                        <?= htmlspecialchars($m['material_sku']) ?>
                      </td>
                      <td class="px-4 py-3 font-medium text-white">
                        <?= htmlspecialchars($m['material_name']) ?>
                      </td>
                      <td class="px-4 py-3 text-neutral-500 font-mono">
                        <?= htmlspecialchars($m['category']) ?>
                      </td>
                      <td class="px-4 py-3 text-right font-mono font-semibold tabular-nums text-white whitespace-nowrap">
                        <?= number_format($m['current_stock'], 2) ?> <span class="text-[11px] font-normal text-neutral-500"><?= htmlspecialchars($m['unit']) ?></span>
                      </td>
                      <td class="px-4 py-3 text-center whitespace-nowrap">
                        <?php if($m['stock_status'] === 'optimal'): ?>
                          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-sm text-[10px] font-mono uppercase bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Optimal
                          </span>
                        <?php elseif($m['stock_status'] === 'low'): ?>
                          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-sm text-[10px] font-mono uppercase bg-amber-500/10 text-amber-400 dark:text-amber-400 border border-amber-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Low Stock
                          </span>
                        <?php else: ?>
                          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-sm text-[10px] font-mono uppercase bg-rose-500/10 text-rose-400 dark:text-rose-400 border border-rose-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Depleted
                          </span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Recent Material Transactions -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-[#1a3346] flex items-center justify-between bg-[#071018]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white flex items-center gap-2">
                <i data-lucide="arrow-down-to-line" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Recent Feedstock Decanting & Inbound Logs" data-ar="سجلات التفريغ والاستلام الأخيرة">Recent Feedstock Decanting & Inbound Logs</span>
              </h2>
              <span class="text-xs font-mono text-neutral-500" data-en="Latest 5 entries" data-ar="آخر 5 عمليات">
                Latest 5 entries
              </span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left rtl:text-right border-collapse">
                <thead>
                  <tr class="text-[11px] font-mono uppercase tracking-wider border-b border-[#1a3346] bg-[#102230]/60 bg-[#0c1924] text-slate-400">
                    <th class="px-4 py-3 font-semibold">Ref</th>
                    <th class="px-4 py-3 font-semibold" data-en="Date / Time" data-ar="التاريخ والوقت">Date / Time</th>
                    <th class="px-4 py-3 font-semibold" data-en="Material Item" data-ar="المادة">Material Item</th>
                    <th class="px-4 py-3 font-semibold text-right" data-en="Quantity" data-ar="الكمية">Quantity</th>
                    <th class="px-4 py-3 font-semibold text-right" data-en="Est. Value (SAR)" data-ar="القيمة التقديرية (ر.س)">Est. Value (SAR)</th>
                  </tr>
                </thead>
                <tbody class="text-xs divide-y divide-[#152a3a]">
                  <?php if(empty($recent_transactions)): ?>
                  <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-neutral-500 font-mono" data-en="No recent inbound transactions recorded." data-ar="لا توجد عمليات توريد حديثة مسجلة.">
                      No recent inbound transactions recorded.
                    </td>
                  </tr>
                  <?php else: ?>
                    <?php foreach ($recent_transactions as $t): ?>
                    <tr class="hover:bg-[#102332] transition-colors">
                      <td class="px-4 py-3 font-mono font-semibold tabular-nums text-teal-400 dark:text-teal-400 whitespace-nowrap">
                        <?= htmlspecialchars($t['transaction_ref'] ?? '') ?>
                      </td>
                      <td class="px-4 py-3 font-mono whitespace-nowrap">
                        <span class="text-white font-medium"><?= htmlspecialchars(date('Y-m-d', strtotime($t['date']))) ?></span>
                        <span class="text-[10px] text-neutral-400 block"><?= htmlspecialchars(date('H:i', strtotime($t['date']))) ?></span>
                      </td>
                      <td class="px-4 py-3 font-medium text-white">
                        <?= htmlspecialchars($t['item'] ?? '') ?>
                      </td>
                      <td class="px-4 py-3 text-right font-mono font-semibold tabular-nums text-white whitespace-nowrap">
                        <?= number_format($t['quantity'] ?? 0, 2) ?> <span class="text-[11px] font-normal text-neutral-500"><?= htmlspecialchars($t['unit'] ?? '') ?></span>
                      </td>
                      <td class="px-4 py-3 text-right font-mono tabular-nums text-slate-200 whitespace-nowrap">
                        <?= number_format($t['total_value'] ?? 0, 2) ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Right 1 Col: Contact & Legal Panels -->
        <div class="space-y-6">
          <!-- Contact Card -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
            <h3 class="text-xs font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
              <i data-lucide="user" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Contact Information" data-ar="بيانات الاتصال">Contact Information</span>
            </h3>
            
            <div class="space-y-3 text-xs font-mono">
              <div>
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block" data-en="Primary Contact" data-ar="جهة الاتصال الرئيسية">Primary Contact</span>
                <span class="text-white font-medium"><?= htmlspecialchars($supplier['contact_person'] ?: '—') ?></span>
                <?php if (!empty($supplier['position'])): ?>
                  <span class="text-neutral-500 text-[11px] block"><?= htmlspecialchars($supplier['position']) ?></span>
                <?php endif; ?>
              </div>
              <div class="pt-2 border-t border-neutral-100 border-[#1a3346]">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block" data-en="Phone" data-ar="الهاتف">Phone</span>
                <span class="text-white tabular-nums"><?= htmlspecialchars($supplier['phone'] ?: '—') ?></span>
              </div>
              <div class="pt-2 border-t border-neutral-100 border-[#1a3346]">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block" data-en="Email" data-ar="البريد الإلكتروني">Email</span>
                <span class="text-white"><?= htmlspecialchars($supplier['email'] ?: '—') ?></span>
              </div>
              <div class="pt-2 border-t border-neutral-100 border-[#1a3346]">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block" data-en="Physical Address" data-ar="العنوان">Physical Address</span>
                <span class="text-slate-300 leading-relaxed block"><?= htmlspecialchars($supplier['address'] ?: '—') ?></span>
              </div>
            </div>
          </div>

          <!-- Business & Regulatory Panel -->
          <div class="bg-[#102230]/60 bg-[#0c1924]/60 border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
            <h3 class="text-xs font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
              <i data-lucide="shield-check" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Commercial & Compliance" data-ar="السجل التجاري والامتثال">Commercial & Compliance</span>
            </h3>

            <div class="space-y-3 text-xs font-mono">
              <div>
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block">VAT / Tax ID</span>
                <span class="text-white tabular-nums"><?= htmlspecialchars($supplier['tax_id'] ?: 'Not Provided') ?></span>
              </div>
              <div class="pt-2 border-t border-[#1e3a50]/60">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block">Commercial Registry (CR)</span>
                <span class="text-white tabular-nums"><?= htmlspecialchars($supplier['cr_number'] ?: 'Not Provided') ?></span>
              </div>
              <div class="pt-2 border-t border-[#1e3a50]/60">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block">Payment Terms</span>
                <span class="text-white font-semibold"><?= htmlspecialchars($supplier['payment_terms'] ?: 'Net 30 Days') ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();
  </script>
</body>
</html>