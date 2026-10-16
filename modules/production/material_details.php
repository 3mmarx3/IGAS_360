<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('materials.view');

$active_page = 'raw_materials';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Raw Materials', 'Material Details'];

$material_row_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($material_row_id <= 0) {
  $firstMat = (int)$pdo->query("SELECT id FROM raw_materials ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstMat > 0) {
    $material_row_id = $firstMat;
  } else {
    redirect(app_url('modules/production/raw_materials.php'));
  }
}

$stmt = $pdo->prepare("
  SELECT
    rm.*,
    p.id AS partner_id,
    p.reference_id AS supplier_ref,
    p.company_name AS supplier_name
  FROM raw_materials rm
  LEFT JOIN partners p ON rm.supplier_id = p.id
  WHERE rm.id = :id
  LIMIT 1
");
$stmt->execute(['id' => $material_row_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  redirect(app_url('modules/production/raw_materials.php'));
}

// Automatic synchronization: deduct unlogged sales from stock
try {
  $syncStmt = $pdo->prepare("
    SELECT qi.qty, q.quotation_number, q.created_at
    FROM quotation_items qi
    JOIN quotations q ON qi.quotation_id = q.id
    WHERE qi.product_name = :m_name AND q.status = 'accepted'
  ");
  $syncStmt->execute(['m_name' => $row['material_name']]);
  $unlogged_sales = $syncStmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($unlogged_sales as $sale) {
    $checkTx = $pdo->prepare("SELECT id FROM material_transactions WHERE material_id = :mid AND source_ref = :ref");
    $checkTx->execute(['mid' => $material_row_id, 'ref' => $sale['quotation_number']]);
    if (!$checkTx->fetch()) {
      $txRef = 'SAL-' . rand(10000, 99999);
      $insTx = $pdo->prepare("
        INSERT INTO material_transactions 
        (material_id, transaction_ref, type, quantity, source_ref, logged_by, transaction_date) 
        VALUES (:mid, :tref, 'out', :qty, :ref, 'Sales System', :tdate)
      ");
      $insTx->execute([
        'mid' => $material_row_id,
        'tref' => $txRef,
        'qty' => $sale['qty'],
        'ref' => $sale['quotation_number'],
        'tdate' => $sale['created_at']
      ]);
      
      $updStock = $pdo->prepare("UPDATE raw_materials SET current_stock = current_stock - :qty WHERE id = :mid");
      $updStock->execute(['qty' => $sale['qty'], 'mid' => $material_row_id]);
      
      $row['current_stock'] -= $sale['qty'];
    }
  }
} catch (PDOException $e) {}

$currentStock = (float)$row['current_stock'];
$threshold  = (float)$row['safety_stock_threshold'];
$maxCapacity = (float)$row['max_capacity'];

if ($currentStock <= 0) {
  $statusKey = 'out';
} elseif ($currentStock <= $threshold) {
  $statusKey = 'low';
} else {
  $statusKey = 'optimal';
}

$totalValue = $currentStock * (float)$row['unit_cost'];

$leadTime_en = $row['lead_time_days'] !== null ? ((int)$row['lead_time_days'] . ' Day' . ((int)$row['lead_time_days'] === 1 ? '' : 's')) : '—';
$leadTime_ar = $row['lead_time_days'] !== null ? ((int)$row['lead_time_days'] . ' يوم') : '—';

$material = [
  'id'      => $row['material_sku'],
  'name'     => $row['material_name'],
  'category'   => $row['category'],
  'unit'     => $row['unit'],
  'stock'     => $currentStock,
  'capacity'   => $maxCapacity,
  'threshold'   => $threshold,
  'unit_cost'   => (float)$row['unit_cost'],
  'total_value'  => $totalValue,
  'supplier_id'  => $row['supplier_ref'],
  'supplier_db_id'=> $row['partner_id'],
  'supplier_name' => $row['supplier_name'],
  'lead_time_en' => $leadTime_en,
  'lead_time_ar' => $leadTime_ar,
  'location'   => $row['warehouse_zone'] ?: '—',
  'status'    => $statusKey,
  'last_updated' => !empty($row['updated_at']) ? date('Y-m-d H:i', strtotime($row['updated_at'])) : '—',
];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  require_permission('reports.export');
  $exportStmt = $pdo->prepare("
    SELECT transaction_ref AS id, transaction_date AS date, type, quantity AS qty, source_ref AS ref, logged_by AS user
    FROM material_transactions
    WHERE material_id = :id OR material_id = :sku
    ORDER BY transaction_date DESC
  ");
  $exportStmt->execute(['id' => $material_row_id, 'sku' => $row['material_sku']]);
  $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

  if (isset($row['opening_stock']) && (float)$row['opening_stock'] > 0) {
    $hasInit = false;
    foreach ($exportData as $t) {
      if ($t['id'] === 'INIT-' . $material['id']) {
        $hasInit = true;
        break;
      }
    }
    if (!$hasInit) {
      $initDate = !empty($row['created_at']) ? $row['created_at'] : date('Y-m-d H:i:s');
      $exportData[] = [
        'id'  => 'INIT-' . $material['id'],
        'date' => $initDate,
        'type' => 'in',
        'qty' => (float)$row['opening_stock'],
        'ref' => 'Initial Stock Entry',
        'user' => 'System'
      ];
      usort($exportData, function($a, $b) {
        return strtotime($b['date']) <=> strtotime($a['date']);
      });
    }
  }

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="Log_' . $material['id'] . '_' . date('Ymd') . '.csv"');
  
  $output = fopen('php://output', 'w');
  fputs($output, "\xEF\xBB\xBF"); 
  fputcsv($output, ['Transaction ID', 'Date & Time', 'Type', 'Quantity', 'Context / Destination', 'Logged By']);

  foreach ($exportData as $t) {
    $context = 'Unknown';
    if ($t['type'] === 'out') {
      if (strpos($t['ref'], 'GAS-') === 0 || strpos($t['ref'], 'MIX-') === 0) {
        $context = 'Gas Mixture Production';
      } else {
        $context = 'Client Dispatch / Sales';
      }
    } else {
      if ($t['id'] === 'INIT-' . $material['id']) {
        $context = 'Initial Stock Entry';
      } elseif (strpos($t['ref'], 'Restored') !== false) {
        $context = 'Restored from Mix/Order';
      } else {
        $context = 'Stock Replenishment';
      }
    }
    $sign = $t['type'] === 'out' ? '-' : '+';
    fputcsv($output, [
      $t['id'],
      date('d M Y H:i:s', strtotime($t['date'])),
      strtoupper($t['type']),
      $sign . number_format((float)$t['qty'], 2),
      $context . ' (Ref: ' . ($t['ref'] ?? 'N/A') . ')',
      $t['user']
    ]);
  }
  fclose($output);
  exit;
}

$thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
$avgConsumptionStmt = $pdo->prepare("
  SELECT AVG(quantity) AS avg_qty
  FROM material_transactions
  WHERE (material_id = :id OR material_id = :sku) AND type = 'out'
    AND transaction_date >= :t_limit
");
$avgConsumptionStmt->execute([
  'id' => $material_row_id,
  'sku' => $row['material_sku'],
  't_limit' => $thirty_days_ago
]);
$avgRow = $avgConsumptionStmt->fetch(PDO::FETCH_ASSOC);
$avgConsumption = $avgRow && $avgRow['avg_qty'] !== null ? round((float)$avgRow['avg_qty']) : 0;
$material['avg_consumption'] = $avgConsumption;

$stmt_usage = $pdo->prepare("
  SELECT 
    SUM(CASE WHEN source_ref LIKE 'GAS-%' OR source_ref LIKE 'MIX-%' THEN quantity ELSE 0 END) as total_mixed,
    SUM(CASE WHEN source_ref NOT LIKE 'GAS-%' AND source_ref NOT LIKE 'MIX-%' THEN quantity ELSE 0 END) as total_sold
  FROM material_transactions
  WHERE (material_id = :id OR material_id = :sku) AND type = 'out'
");
$stmt_usage->execute(['id' => $material_row_id, 'sku' => $row['material_sku']]);
$usage_data = $stmt_usage->fetch(PDO::FETCH_ASSOC);

$lifetime_mixed = (float)($usage_data['total_mixed'] ?? 0);
$lifetime_sold = (float)($usage_data['total_sold'] ?? 0);

$txStmt = $pdo->prepare("
  SELECT transaction_ref AS id, transaction_date AS date, type, quantity AS qty, source_ref AS ref, logged_by AS user
  FROM material_transactions
  WHERE material_id = :id OR material_id = :sku
  ORDER BY transaction_date DESC
  LIMIT 50
");
$txStmt->execute(['id' => $material_row_id, 'sku' => $row['material_sku']]);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($row['opening_stock']) && (float)$row['opening_stock'] > 0) {
  $hasInit = false;
  foreach ($transactions as $t) {
    if ($t['id'] === 'INIT-' . $material['id']) {
      $hasInit = true;
      break;
    }
  }
  if (!$hasInit) {
    $initDate = !empty($row['created_at']) ? $row['created_at'] : date('Y-m-d H:i:s');
    $transactions[] = [
      'id'  => 'INIT-' . $material['id'],
      'date' => $initDate,
      'type' => 'in',
      'qty' => (float)$row['opening_stock'],
      'ref' => 'Initial Stock Entry',
      'user' => 'System'
    ];
    
    usort($transactions, function($a, $b) {
      return strtotime($b['date']) <=> strtotime($a['date']);
    });
  }
}

$statusStyles = [
  'optimal' => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'In Stock / Optimal', 'label_ar' => 'متوفر / مثالي'],
  'low'   => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'Low Stock Alert', 'label_ar' => 'تنبيه نقص المخزون'],
  'out'   => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label_en' => 'Out of Stock', 'label_ar' => 'نفد المخزون'],
];

$typeStyles = [
  'in' => ['color' => 'text-emerald-700 dark:text-emerald-400', 'sign' => '+'],
  'out' => ['color' => 'text-rose-400 dark:text-rose-400', 'sign' => '-'],
];

$ms = $statusStyles[$material['status']];
$stock_percentage = $material['capacity'] > 0
  ? min(100, ($material['stock'] / $material['capacity']) * 100)
  : 0;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($material['name']) ?> | I-GAS Enterprise</title>
  
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
        <span data-en="RAW FEEDSTOCK TELEMETRY & AUDIT" data-ar="قياسات وتدقيق مخزون المادة الخام">RAW FEEDSTOCK TELEMETRY & AUDIT</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">SKU: <?= htmlspecialchars($material['id']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400 font-mono">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Breadcrumb Navigation & Top Action -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center gap-2 text-xs font-mono text-neutral-500">
          <a href="<?= app_url('modules/production/raw_materials.php') ?>" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors uppercase tracking-wider flex items-center gap-1.5" data-en="Back to Inventory" data-ar="العودة إلى المخزون">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
            <span>Back to Inventory Registry</span>
          </a>
          <span class="text-neutral-300 dark:text-neutral-700">/</span>
          <span class="text-teal-600 dark:text-teal-400 uppercase tracking-wider font-semibold"><?= htmlspecialchars($material['id']) ?></span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <?php if (has_permission('orders.create')): ?>
          <a href="<?= app_url('modules/production/new_purchase_order.php?material_id=' . urlencode($material_row_id)) ?>" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm">
            <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
            <span data-en="Reorder Material" data-ar="إعادة طلب المادة">Reorder Material</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Material Profile Header Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-sm bg-[#112433] text-white flex items-center justify-center font-mono font-bold text-lg border border-neutral-700 flex-shrink-0">
            <i data-lucide="boxes" class="w-7 h-7 text-teal-400"></i>
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-3 mb-1">
              <h1 class="text-xl md:text-2xl font-bold font-mono text-white">
                <?= htmlspecialchars($material['name']) ?>
              </h1>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-sm text-xs font-mono border <?= $ms['border'] ?> <?= $ms['bg'] ?> <?= $ms['text'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $ms['dot'] ?>"></span>
                <span data-en="<?= $ms['label_en'] ?>" data-ar="<?= $ms['label_ar'] ?>"><?= $ms['label_en'] ?></span>
              </span>
            </div>
            <p class="text-xs font-mono text-neutral-500">
              <?= htmlspecialchars($material['id']) ?> · <?= htmlspecialchars($material['category']) ?> · <?= htmlspecialchars($material['location']) ?>
            </p>
          </div>
        </div>
      </div>

      <!-- Bento Telemetry Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Current Stock Level -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Current Stock Level" data-ar="مستوى المخزون الحالي">Current Stock Level</span>
            <i data-lucide="gauge" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-mono font-bold text-white tabular-nums"><?= number_format($material['stock']) ?></span>
            <span class="text-xs font-mono text-neutral-400"><?= htmlspecialchars($material['unit']) ?></span>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346]">
            <div class="h-1.5 w-full bg-[#09151f] rounded-full overflow-hidden border border-[#1e3a50]">
              <div class="h-full bg-teal-600 dark:bg-teal-500" style="width: <?= $stock_percentage ?>%;"></div>
            </div>
            <div class="flex justify-between text-[10px] font-mono text-neutral-400 mt-1">
              <span>0</span>
              <span>Max: <?= number_format($material['capacity']) ?></span>
            </div>
          </div>
        </div>

        <!-- Total Valuation -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Inventory Value" data-ar="قيمة المخزون">Inventory Value</span>
            <i data-lucide="badge-dollar-sign" class="w-4 h-4 text-emerald-600 dark:text-emerald-500"></i>
          </div>
          <div class="flex items-baseline gap-1.5">
            <span class="text-2xl font-mono font-bold text-white tabular-nums"><?= number_format($material['total_value'], 2) ?></span>
            <span class="text-xs font-mono text-neutral-500">SAR</span>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] text-[11px] font-mono text-neutral-500">
            <span>@ <?= number_format($material['unit_cost'], 2) ?> SAR / <?= htmlspecialchars($material['unit']) ?></span>
          </div>
        </div>

        <!-- Safety Threshold -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Safety Threshold" data-ar="حد الأمان">Safety Threshold</span>
            <i data-lucide="shield-alert" class="w-4 h-4 text-amber-600 dark:text-amber-500"></i>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-mono font-bold text-amber-400 dark:text-amber-400 tabular-nums"><?= number_format($material['threshold']) ?></span>
            <span class="text-xs font-mono text-neutral-400"><?= htmlspecialchars($material['unit']) ?></span>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span class="inline-flex items-center gap-1 text-teal-600 dark:text-teal-400">
              <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
              <span data-en="Min Stock Alert" data-ar="حد أدنى">Min Stock Alert</span>
            </span>
            <span>Buffer</span>
          </div>
        </div>

        <!-- Daily Burn Rate -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Est. Daily Burn" data-ar="الاستهلاك اليومي">Est. Daily Burn</span>
            <i data-lucide="flame" class="w-4 h-4 text-rose-600 dark:text-rose-500"></i>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-mono font-bold text-white tabular-nums"><?= number_format($material['avg_consumption']) ?></span>
            <span class="text-xs font-mono text-neutral-400"><?= htmlspecialchars($material['unit']) ?>/d</span>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] text-[11px] font-mono text-neutral-500">
            <?php if ($material['avg_consumption'] > 0): ?>
              <span>≈ <strong class="text-white tabular-nums"><?= round($material['stock'] / $material['avg_consumption']) ?></strong> days buffer</span>
            <?php else: ?>
              <span>Stable reserves</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Main Layout: Movement Ledger (2 cols) and Technical Panels (1 col) -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Movement Ledger Table -->
        <div class="xl:col-span-2 space-y-6">
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-[#1a3346] flex items-center justify-between bg-[#071018]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Usage & Decanting Ledger" data-ar="سجل الاستخدام والتفريغ">Usage & Decanting Ledger</span>
              </h2>
              <a href="?id=<?= urlencode($material_row_id) ?>&export=csv" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-wider bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-200 rounded-sm transition-colors">
                <i data-lucide="download" class="w-3.5 h-3.5 text-neutral-500"></i>
                <span data-en="Export CSV" data-ar="تصدير CSV">Export CSV</span>
              </a>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left rtl:text-right border-collapse">
                <thead>
                  <tr class="text-[11px] font-mono uppercase tracking-wider border-b border-[#1a3346] bg-[#102230]/60 bg-[#0c1924] text-slate-400">
                    <th class="px-4 py-3 font-semibold w-32">Transaction Ref</th>
                    <th class="px-4 py-3 font-semibold" data-en="Date & Time" data-ar="التاريخ والوقت">Date & Time</th>
                    <th class="px-4 py-3 font-semibold text-right" data-en="Quantity" data-ar="الكمية">Quantity</th>
                    <th class="px-4 py-3 font-semibold" data-en="Context / Batch" data-ar="السياق / الدفعة">Context / Batch</th>
                    <th class="px-4 py-3 font-semibold text-right rtl:text-left" data-en="Logged By" data-ar="المسجل">Logged By</th>
                  </tr>
                </thead>
                <tbody class="text-xs divide-y divide-[#152a3a]">
                  <?php if (empty($transactions)): ?>
                  <tr>
                    <td colspan="5" class="text-center py-10 text-neutral-500 font-mono" data-en="No transactions recorded for this material." data-ar="لا توجد حركات مسجلة لهذه المادة.">
                      No transactions recorded for this material.
                    </td>
                  </tr>
                  <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                    <?php 
                      $ts = $typeStyles[$t['type']]; 
                      
                      $context_en = 'Unknown';
                      $badgeClass = '';
                      if ($t['type'] === 'out') {
                        if (strpos($t['ref'], 'GAS-') === 0 || strpos($t['ref'], 'MIX-') === 0) {
                          $context_en = 'Gas Mixture Production';
                          $badgeClass = 'bg-teal-500/10 text-teal-400 dark:text-teal-400 border-teal-500/30';
                        } else {
                          $context_en = 'Sales / Dispatch Order';
                          $badgeClass = 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30';
                        }
                      } else {
                        if ($t['id'] === 'INIT-' . $material['id']) {
                          $context_en = 'Initial Stock Entry';
                          $badgeClass = 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30';
                        } elseif (strpos($t['ref'], 'Restored') !== false) {
                          $context_en = 'Restored Feedstock';
                          $badgeClass = 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30';
                        } else {
                          $context_en = 'Tanker Inbound Delivery';
                          $badgeClass = 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30';
                        }
                      }
                    ?>
                    <tr class="hover:bg-[#102332] transition-colors">
                      <td class="px-4 py-3 font-mono font-semibold tabular-nums text-teal-400 dark:text-teal-400 whitespace-nowrap">
                        <?= htmlspecialchars($t['id']) ?>
                      </td>
                      <td class="px-4 py-3 font-mono whitespace-nowrap">
                        <span class="text-white font-medium"><?= htmlspecialchars(date('Y-m-d', strtotime($t['date']))) ?></span>
                        <span class="text-[10px] text-neutral-400 block"><?= htmlspecialchars(date('H:i', strtotime($t['date']))) ?></span>
                      </td>
                      <td class="px-4 py-3 text-right font-mono font-semibold tabular-nums whitespace-nowrap <?= $ts['color'] ?>">
                        <?= $ts['sign'] ?> <?= number_format($t['qty']) ?>
                      </td>
                      <td class="px-4 py-3">
                        <span class="inline-block px-2 py-0.5 text-[10px] font-mono uppercase tracking-wider rounded-sm border <?= $badgeClass ?>">
                          <?= $context_en ?>
                        </span>
                        <span class="text-[11px] font-mono text-neutral-400 block mt-0.5">Ref: <?= htmlspecialchars($t['ref'] ?? 'N/A') ?></span>
                      </td>
                      <td class="px-4 py-3 text-right rtl:text-left font-mono text-neutral-500 whitespace-nowrap">
                        <?= htmlspecialchars($t['user']) ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Right 1 Col: Specs & Procurement Details -->
        <div class="space-y-6">
          <!-- Usage Summary Card -->
          <div class="bg-[#102230]/60 bg-[#0c1924]/60 border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
            <h3 class="text-xs font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
              <i data-lucide="pie-chart" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Usage Distribution" data-ar="توزيع الاستخدام">Usage Distribution</span>
            </h3>

            <div class="space-y-3 text-xs font-mono">
              <div>
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block" data-en="Plant Gas Mixtures" data-ar="خلطات المصنع">Plant Gas Mixtures</span>
                <span class="text-teal-400 dark:text-teal-400 font-bold tabular-nums text-sm"><?= number_format($lifetime_mixed, 2) ?> <?= htmlspecialchars($material['unit']) ?></span>
              </div>
              <div class="pt-2 border-t border-[#1e3a50]/60">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block" data-en="Direct Commercial Sales" data-ar="المبيعات التجارية المباشرة">Direct Commercial Sales</span>
                <span class="text-amber-400 dark:text-amber-400 font-bold tabular-nums text-sm"><?= number_format($lifetime_sold, 2) ?> <?= htmlspecialchars($material['unit']) ?></span>
              </div>
              <div class="pt-2 border-t border-[#1e3a50]/60">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block" data-en="Total Lifetime Outbound" data-ar="إجمالي الخارج">Total Lifetime Outbound</span>
                <span class="text-white font-bold tabular-nums text-sm"><?= number_format($lifetime_mixed + $lifetime_sold, 2) ?> <?= htmlspecialchars($material['unit']) ?></span>
              </div>
            </div>
          </div>

          <!-- Material Physical Specifications -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
            <h3 class="text-xs font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
              <i data-lucide="sliders" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Storage Properties" data-ar="خصائص التخزين">Storage Properties</span>
            </h3>

            <div class="space-y-3 text-xs font-mono">
              <div>
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block">Category</span>
                <span class="text-white font-medium"><?= htmlspecialchars($material['category']) ?></span>
              </div>
              <div class="pt-2 border-t border-neutral-100 border-[#1a3346]">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block">Unit of Measure</span>
                <span class="text-white font-medium"><?= htmlspecialchars($material['unit']) ?></span>
              </div>
              <div class="pt-2 border-t border-neutral-100 border-[#1a3346]">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block">Max Storage Capacity</span>
                <span class="text-white font-medium tabular-nums"><?= number_format($material['capacity']) ?> <?= htmlspecialchars($material['unit']) ?></span>
              </div>
              <div class="pt-2 border-t border-neutral-100 border-[#1a3346]">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block">Storage Zone</span>
                <span class="text-white font-medium"><?= htmlspecialchars($material['location']) ?></span>
              </div>
            </div>
          </div>

          <!-- Supplier & Procurement Links -->
          <div class="bg-[#102230]/60 bg-[#0c1924]/60 border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
            <div class="flex justify-between items-center pb-3 border-b border-[#1a3346]">
              <h3 class="text-xs font-mono font-bold uppercase tracking-wider text-white flex items-center gap-2">
                <i data-lucide="truck" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Procurement Vendor" data-ar="مورد التوريد">Procurement Vendor</span>
              </h3>
              <a href="<?= app_url('modules/production/supplier_profile.php?id=' . urlencode($material['supplier_id'])) ?>" class="text-[11px] font-mono text-teal-600 dark:text-teal-400 hover:underline">
                Profile &rarr;
              </a>
            </div>

            <div class="space-y-3 text-xs font-mono">
              <div>
                <span class="text-white font-semibold"><?= htmlspecialchars($material['supplier_name']) ?></span>
                <span class="text-neutral-400 block text-[11px]"><?= htmlspecialchars($material['supplier_id']) ?></span>
              </div>
              <div class="pt-2 border-t border-[#1e3a50]/60">
                <span class="text-[10px] text-neutral-400 uppercase tracking-wider block">Standard Lead Time</span>
                <span class="text-white font-semibold"><?= $material['lead_time_en'] ?></span>
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