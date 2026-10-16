<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'warehouse.view');

$warehouse_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$warehouse_id) {
  $firstWh = (int)$pdo->query("SELECT id FROM warehouses ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstWh > 0) {
    $warehouse_id = $firstWh;
  } else {
    redirect(app_url('modules/warehouse/warehouses.php'));
  }
}

$active_page = 'inventory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Warehouse & Inventory', 'Warehouse Profile'];

$stmt = $pdo->prepare("
  SELECT w.*, p.company_name as client_name 
  FROM warehouses w 
  LEFT JOIN partners p ON w.client_id = p.id 
  WHERE w.id = ?
");
$stmt->execute([$warehouse_id]);
$warehouse = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$warehouse) {
  redirect(app_url('modules/warehouse/warehouses.php'));
}

if (($warehouse['warehouse_type'] ?? '') === 'client' && !empty($warehouse['client_id'])) {
  check_record_ownership($pdo, 'partners', $warehouse['client_id']);
}

$inv_stmt = $pdo->prepare("SELECT * FROM warehouse_inventory WHERE warehouse_id = ? ORDER BY id DESC");
$inv_stmt->execute([$warehouse_id]);
$inventory = $inv_stmt->fetchAll(PDO::FETCH_ASSOC);

// Transactions log for this warehouse
$trans_stmt = $pdo->prepare("
  SELECT wt.*, wi.item_name, wi.sku 
  FROM warehouse_transactions wt
  JOIN warehouse_inventory wi ON wt.inventory_id = wi.id
  WHERE wt.warehouse_id = ?
  ORDER BY wt.transaction_date DESC
  LIMIT 50
");
$trans_stmt->execute([$warehouse_id]);
$transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_skus = count($inventory);
$total_qty = 0;
foreach ($inventory as $i) { 
  $total_qty += (float)($i['quantity_on_hand'] ?? 0); 
}

$working_pressure = "150 Bar"; 

$isInternal = ($warehouse['warehouse_type'] ?? 'internal') === 'internal';
$typeStyles = [
  'internal' => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'border' => 'border-emerald-300 dark:border-emerald-800', 'dot' => 'bg-emerald-500', 'label_en' => 'Internal Plant Depot', 'label_ar' => 'مستودع مصنع داخلي'],
  'client'  => ['bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'border' => 'border-amber-300 dark:border-amber-800', 'dot' => 'bg-amber-500', 'label_en' => 'Client Custody Yard', 'label_ar' => 'عهدة عميل / موقع خارجي'],
];
$ts = $typeStyles[$warehouse['warehouse_type'] ?? 'internal'] ?? $typeStyles['internal'];

$isActive = strtolower($warehouse['status'] ?? 'active') === 'active';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Warehouse Profile: <?= htmlspecialchars($warehouse['warehouse_name']) ?> | I-GAS Control Room" data-ar="ملف المستودع: <?= htmlspecialchars($warehouse['warehouse_name']) ?> | غرفة التحكم I-GAS">Warehouse Profile: <?= htmlspecialchars($warehouse['warehouse_name']) ?> | I-GAS Control Room</title>
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

  <!-- Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-400">
        <span class="w-2 h-2 rounded-full bg-teal-500"></span>
        <span data-en="DEPOT MANIFOLD PROFILE & BAY TELEMETRY" data-ar="بيانات المستودع ومراقبة ضغط الأسطوانات">DEPOT MANIFOLD PROFILE & BAY TELEMETRY</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="FACILITY CODE:" data-ar="رمز المستودع:">FACILITY CODE:</span> <strong class="text-white font-mono"><?= htmlspecialchars($warehouse['warehouse_code'] ?? ($warehouse['code'] ?? ('WH-' . $warehouse['id']))) ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="WORKING PRESSURE:" data-ar="ضغط العمل:">WORKING PRESSURE:</span> <strong class="text-white font-mono"><?= $working_pressure ?></strong>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: YARD-OPS</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">

    <!-- Header Row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
          <a href="warehouses.php" class="hover:text-white transition-colors" data-en="Warehouses Directory" data-ar="دليل المستودعات">Warehouses Directory</a>
          <span>/</span>
          <span class="text-white font-semibold font-mono"><?= htmlspecialchars($warehouse['warehouse_code'] ?? ('WH-' . $warehouse['id'])) ?></span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white">
            <?= htmlspecialchars($warehouse['warehouse_name']) ?>
          </h1>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-mono uppercase tracking-wider rounded-xs border <?= $ts['bg'] ?> <?= $ts['text'] ?> <?= $ts['border'] ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $ts['dot'] ?>"></span>
            <span data-en="<?= $ts['label_en'] ?>" data-ar="<?= $ts['label_ar'] ?>"><?= $ts['label_en'] ?></span>
          </span>
          <span class="text-xs font-mono text-neutral-500">
            • <span data-en="Location:" data-ar="الموقع:">Location:</span> <strong class="text-white"><?= htmlspecialchars($warehouse['location'] ?? 'Jeddah Industrial Hub') ?></strong>
          </span>
          <?php if (!empty($warehouse['client_name'])): ?>
          <span class="text-xs font-mono text-neutral-500">
            • <span data-en="Client Link:" data-ar="العميل:">Client Link:</span> 
            <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . (int)$warehouse['client_id']) ?>" class="text-teal-600 dark:text-teal-400 font-medium hover:underline">
              <?= htmlspecialchars($warehouse['client_name']) ?>
            </a>
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <a href="warehouses.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700/50 rounded-xs transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
          <span data-en="Back to Directory" data-ar="العودة للدليل">Back to Directory</span>
        </a>
        <a href="withdraw.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700/50 rounded-xs transition-colors">
          <i data-lucide="arrow-right-left" class="w-4 h-4"></i>
          <span data-en="Transfer Stock" data-ar="تحويل رصيد">Transfer Stock</span>
        </a>
        <?php if (has_permission($pdo, 'warehouse.manage')): ?>
        <a href="<?= app_url('modules/warehouse/new_warehouse_item.php?warehouse_id=' . $warehouse['id']) ?>" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-1.5 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span data-en="Add Stock Item" data-ar="إضافة صنف للمستودع">Add Stock Item</span>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- 4 KPI Telemetry Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- KPI 1: Total SKUs -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Total Items (SKU)" data-ar="إجمالي الأصناف الفريدة">
          Total Items (SKU)
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= number_format($total_skus) ?>
          </span>
          <span class="text-[10px] font-mono text-neutral-500">CATALOG ITEMS</span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Gas types, manifold fittings & valves" data-ar="أنواع الغازات ومحابس ووصلات الشبكة">
          Gas types, manifold fittings & valves
        </div>
      </div>

      <!-- KPI 2: Total Quantity on Hand -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Total Quantity on Hand" data-ar="إجمالي الكميات الفعلية">
          Total Quantity on Hand
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= number_format($total_qty, 1) ?>
          </span>
          <span class="text-[10px] font-mono text-teal-600 dark:text-teal-400">UNITS / CYL</span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Total stored physical units" data-ar="إجمالي الأسطوانات والأجزاء المخزنة">
          Total stored physical units
        </div>
      </div>

      <!-- KPI 3: Rated Pressure Limit -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Rated Pressure Limit" data-ar="أقصى ضغط تشغيلي">
          Rated Pressure Limit
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= $working_pressure ?>
          </span>
          <span class="text-[10px] font-mono text-neutral-500">ASME SEC VIII</span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Safety manifold manifold limit" data-ar="الحد الأقصى المسموح به لشبكة التعبئة">
          Safety manifold manifold limit
        </div>
      </div>

      <!-- KPI 4: Operational State -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Operational Status" data-ar="حالة التشغيل">
          Operational Status
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono uppercase <?= $isActive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' ?>">
            <?= htmlspecialchars($warehouse['status'] ?? 'Active') ?>
          </span>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-mono uppercase rounded-xs <?= $isActive ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800' : 'bg-[#09151f] text-slate-300' ?>">
            <span class="w-1 h-1 rounded-full <?= $isActive ? 'bg-emerald-500' : 'bg-neutral-500' ?>"></span>
            <span data-en="Nominal" data-ar="طبيعي">Nominal</span>
          </span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Yard safety inspection current" data-ar="شهادة السلامة سارية المفعول">
          Yard safety inspection current
        </div>
      </div>

    </div>

    <!-- Ledger & Operations Tabbed Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
      
      <!-- Underline Tabs Header -->
      <div class="p-4 border-b border-[#1a3346] flex items-center justify-between">
        <div class="flex items-center gap-6 text-xs font-mono">
          <button type="button" class="tab-btn active pb-2 -mb-4 border-b-2 border-neutral-900 dark:border-neutral-100 font-semibold text-white flex items-center gap-1.5 cursor-pointer" onclick="switchTab('ledger', this)">
            <span data-en="Inventory Ledger" data-ar="سجل الأرصدة والمخزون">Inventory Ledger</span>
            <span class="px-1.5 py-0.2 rounded-xs bg-[#112433] text-[10px]"><?= $total_skus ?></span>
          </button>
          <button type="button" class="tab-btn pb-2 -mb-4 border-b-2 border-transparent text-neutral-500 hover:text-white flex items-center gap-1.5 cursor-pointer transition-colors" onclick="switchTab('operations', this)">
            <span data-en="Warehouse Operations Log" data-ar="سجل الحركات والتحويلات">Warehouse Operations Log</span>
            <span class="px-1.5 py-0.2 rounded-xs bg-[#112433] text-[10px]"><?= count($transactions) ?></span>
          </button>
        </div>

        <div class="text-xs font-mono text-neutral-500">
          <span data-en="Bay:" data-ar="المسار:">Bay:</span> <strong class="text-white font-mono">ALL-SECTORS</strong>
        </div>
      </div>

      <!-- Tab 1: Inventory Ledger -->
      <div id="ledger_section" class="overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="border-b border-[#1a3346] bg-[#09151f] text-neutral-500 font-mono uppercase text-[11px] tracking-wider">
              <th class="py-3 px-4 text-start font-medium" data-en="SKU Code" data-ar="رمز الصنف">SKU Code</th>
              <th class="py-3 px-4 text-start font-medium" data-en="Item Name / Gas Spec" data-ar="اسم الصنف / مواصفة الغاز">Item Name / Gas Spec</th>
              <th class="py-3 px-4 text-start font-medium" data-en="Category" data-ar="التصنيف">Category</th>
              <th class="py-3 px-4 text-end font-medium" data-en="Quantity on Hand" data-ar="الكمية المتوفرة">Quantity on Hand</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a]">
            <?php if (empty($inventory)): ?>
              <tr class="empty-state">
                <td colspan="4" class="py-8 text-center text-neutral-500 font-mono text-xs" data-en="No items found in this warehouse depot." data-ar="لا توجد أصناف مسجلة في هذا المستودع حالياً.">
                  No items found in this warehouse depot.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($inventory as $inv): ?>
                <tr class="hover:bg-[#102332] transition-colors">
                  <td class="py-3 px-4 font-mono font-medium text-slate-400">
                    <?= htmlspecialchars($inv['sku']) ?>
                  </td>
                  <td class="py-3 px-4 font-medium text-white">
                    <?= htmlspecialchars($inv['item_name']) ?>
                  </td>
                  <td class="py-3 px-4 text-slate-400">
                    <?= htmlspecialchars($inv['item_category'] ?? ($inv['category'] ?? 'General Inventory')) ?>
                  </td>
                  <td class="py-3 px-4 text-end font-mono font-bold text-white">
                    <?= number_format((float)$inv['quantity_on_hand'], 2) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Tab 2: Operations Log -->
      <div id="operations_section" class="overflow-x-auto" style="display: none;">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="border-b border-[#1a3346] bg-[#09151f] text-neutral-500 font-mono uppercase text-[11px] tracking-wider">
              <th class="py-3 px-4 text-start font-medium" data-en="Date & Time" data-ar="التاريخ والوقت">Date & Time</th>
              <th class="py-3 px-4 text-start font-medium" data-en="Action Type" data-ar="نوع الحركة">Action Type</th>
              <th class="py-3 px-4 text-start font-medium" data-en="Item SKU / Name" data-ar="الصنف / الكود">Item SKU / Name</th>
              <th class="py-3 px-4 text-end font-medium" data-en="Quantity" data-ar="الكمية">Quantity</th>
              <th class="py-3 px-4 text-start font-medium" data-en="Reference Doc" data-ar="المستند المرجعي">Reference Doc</th>
              <th class="py-3 px-4 text-start font-medium" data-en="Notes" data-ar="ملاحظات">Notes</th>
              <th class="py-3 px-4 text-end font-medium" data-en="Logged By" data-ar="بواسطة">Logged By</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a]">
            <?php if (empty($transactions)): ?>
              <tr class="empty-state">
                <td colspan="7" class="py-8 text-center text-neutral-500 font-mono text-xs" data-en="No operations recorded for this warehouse yet." data-ar="لا توجد حركات مسجلة لهذا المستودع حتى الآن.">
                  No operations recorded for this warehouse yet.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($transactions as $tx): ?>
                <?php 
                  $txType = strtolower($tx['transaction_type'] ?? 'in');
                  $txStyle = 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-800';
                  if ($txType === 'out') {
                    $txStyle = 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border-rose-800 dark:border-rose-800';
                  } elseif ($txType === 'transfer') {
                    $txStyle = 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-300 dark:border-sky-800';
                  } elseif ($txType === 'adjustment') {
                    $txStyle = 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-300 dark:border-amber-800';
                  }
                ?>
                <tr class="hover:bg-[#102332] transition-colors">
                  <td class="py-3 px-4 font-mono text-slate-400">
                    <?= htmlspecialchars($tx['transaction_date']) ?>
                  </td>
                  <td class="py-3 px-4">
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-mono uppercase tracking-wider rounded-xs border <?= $txStyle ?>">
                      <?= strtoupper(htmlspecialchars($txType)) ?>
                    </span>
                  </td>
                  <td class="py-3 px-4">
                    <div class="font-medium text-white">
                      <?= htmlspecialchars($tx['item_name']) ?>
                    </div>
                    <div class="text-[11px] font-mono text-neutral-500">
                      <?= htmlspecialchars($tx['sku']) ?>
                    </div>
                  </td>
                  <td class="py-3 px-4 text-end font-mono font-bold text-white">
                    <?= number_format((float)$tx['quantity'], 2) ?>
                  </td>
                  <td class="py-3 px-4 font-mono text-slate-300">
                    <?= htmlspecialchars($tx['reference_doc'] ?: '—') ?>
                  </td>
                  <td class="py-3 px-4 text-slate-400 max-w-xs truncate" title="<?= htmlspecialchars($tx['notes'] ?? '') ?>">
                    <?= htmlspecialchars($tx['notes'] ?: '—') ?>
                  </td>
                  <td class="py-3 px-4 text-end font-mono text-neutral-500">
                    <?= htmlspecialchars($tx['logged_by'] ?: 'System') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Table Footer -->
      <div class="p-4 border-t border-[#1a3346] bg-[#071018] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs font-mono text-neutral-500">
        <div>
          <span data-en="Facility Node:" data-ar="معرف المنشأة:">Facility Node:</span> <strong class="text-white font-mono"><?= htmlspecialchars($warehouse['warehouse_code'] ?? ('WH-' . $warehouse['id'])) ?></strong>
        </div>
        <div>
          <span data-en="Total Stored Units:" data-ar="إجمالي الوحدات:">Total Stored Units:</span> <strong class="text-white font-mono"><?= number_format($total_qty, 1) ?></strong>
        </div>
      </div>

    </div>

  </div>
</main>

<script>
  lucide.createIcons();

  function switchTab(tabName, element) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.classList.remove('active', 'border-[#234257]', 'dark:border-neutral-100', 'text-white', 'text-white', 'font-semibold');
      btn.classList.add('border-transparent', 'text-neutral-500');
    });
    element.classList.add('active', 'border-[#234257]', 'dark:border-neutral-100', 'text-white', 'text-white', 'font-semibold');
    element.classList.remove('border-transparent', 'text-neutral-500');
    
    const ledgerSection = document.getElementById('ledger_section');
    const operationsSection = document.getElementById('operations_section');

    if (tabName === 'ledger') {
      if (ledgerSection) ledgerSection.style.display = 'block';
      if (operationsSection) operationsSection.style.display = 'none';
    } else if (tabName === 'operations') {
      if (ledgerSection) ledgerSection.style.display = 'none';
      if (operationsSection) operationsSection.style.display = 'block';
    }
  }

  function applyLanguage(lang) {
    document.querySelectorAll('[data-en]').forEach(el => {
      const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.hasAttribute('placeholder')) {
        el.placeholder = transText;
      } else if (el.tagName === 'OPTION') {
        el.innerText = transText;
      } else {
        el.innerText = transText;
      }
    });
    document.querySelectorAll('[data-en-placeholder]').forEach(el => {
      el.placeholder = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", function() {
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);
  });
</script>
</body>
</html>