<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.view');

$active_page = 'liquid_tracking';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Liquid Reconciliation'];

$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$stmt_factors = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'factor_%'");
$db_factors = [];
if ($stmt_factors) {
  while ($row = $stmt_factors->fetch(PDO::FETCH_ASSOC)) {
    $db_factors[$row['setting_key']] = (float)$row['setting_value'];
  }
}

function getGasKey($name) {
  $n = mb_strtolower($name, 'UTF-8');
  if (strpos($n, 'oxy') !== false || strpos($n, 'أكسجين') !== false) return 'oxygen';
  if (strpos($n, 'nit') !== false || strpos($n, 'نيتروجين') !== false) return 'nitrogen';
  if (strpos($n, 'arg') !== false || strpos($n, 'أرجون') !== false) return 'argon';
  if (strpos($n, 'co2') !== false || strpos($n, 'carbon') !== false || strpos($n, 'كربون') !== false) return 'co2';
  return 'other';
}

function getFactorForGas($name, $pdo, &$db_factors) {
  $k = getGasKey($name);
  $setting_key = 'factor_' . $k;
  
  if (isset($db_factors[$setting_key])) {
    return $db_factors[$setting_key];
  }
  
  $defaults = [
    'oxygen'  => 7.30,
    'nitrogen' => 6.39,
    'argon'  => 9.12,
    'co2'   => 10.04
  ];
  
  $factor = $defaults[$k] ?? 1.00;
  
  if ($k !== 'other') {
    try {
      $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
      if ($isSqlite) {
        $ins = $pdo->prepare("INSERT OR REPLACE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
      } else {
        $ins = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
      }
      $ins->execute([$setting_key, (string)$factor]);
      $db_factors[$setting_key] = $factor;
    } catch (Exception $e) {}
  }
  
  return $factor;
}

function initializeYearData() {
  $d = [];
  for ($m = 1; $m <= 12; $m++) {
    $d[$m] = ['produced' => 0, 'liquid' => 0];
  }
  return $d;
}

$stmt_bg = $pdo->query("SELECT id, material_name FROM raw_materials WHERE category = 'Bulk Gas'");
$bulk_gas_rows = $stmt_bg ? $stmt_bg->fetchAll(PDO::FETCH_ASSOC) : [];
$bulk_gases = [];
$bulk_gas_ids = [];
foreach ($bulk_gas_rows as $bg_row) {
  $bulk_gases[] = $bg_row['material_name'];
  $bulk_gas_ids[$bg_row['material_name']] = (int)$bg_row['id'];
}

$reconciliation_data = [];
foreach ($bulk_gases as $gas) {
  $reconciliation_data[$gas] = initializeYearData();
}

$stmt_liquid = $pdo->prepare("
  SELECT MONTH(mt.transaction_date) as m, rm.material_name, SUM(mt.quantity) as total_liquid
  FROM material_transactions mt
  JOIN raw_materials rm ON mt.material_id = rm.id
  WHERE mt.type = 'in' AND rm.category = 'Bulk Gas' AND YEAR(mt.transaction_date) = ?
  GROUP BY m, rm.material_name
");
$stmt_liquid->execute([$selected_year]);
while ($row = $stmt_liquid->fetch(PDO::FETCH_ASSOC)) {
  $m = (int)$row['m'];
  $name = $row['material_name'];
  if (isset($reconciliation_data[$name]) && isset($reconciliation_data[$name][$m])) {
    $reconciliation_data[$name][$m]['liquid'] += (float)$row['total_liquid'];
  }
}

$stmt_produced = $pdo->prepare("
  SELECT MONTH(po.order_date) as m, poi.item_name, SUM(poi.qty) as total_produced
  FROM purchase_order_items poi
  JOIN purchase_orders po ON poi.purchase_order_id = po.id
  WHERE YEAR(po.order_date) = ? AND po.status NOT IN ('cancelled', 'draft')
  GROUP BY m, poi.item_name
");
$stmt_produced->execute([$selected_year]);
while ($row = $stmt_produced->fetch(PDO::FETCH_ASSOC)) {
  $m = (int)$row['m'];
  $po_item = $row['item_name'];
  $po_key = getGasKey($po_item);
  
  foreach ($bulk_gases as $gas) {
    if ($po_key !== 'other' && getGasKey($gas) === $po_key) {
      if (isset($reconciliation_data[$gas][$m])) {
        $reconciliation_data[$gas][$m]['produced'] += (float)$row['total_produced'];
      }
      break;
    }
  }
}

$months = [
  1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
  7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
];

$months_ar = [
  1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
  7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Liquid Reconciliation | I-GAS Enterprise" data-ar="مطابقة السوائل | I-GAS Enterprise">Liquid Reconciliation | I-GAS Enterprise</title>
  
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
        <span data-en="PLANT: JEDDAH MAIN FACILITY" data-ar="المحطة: منشأة جدة الرئيسية">PLANT: JEDDAH MAIN FACILITY</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="CRYO TANK FARM" data-ar="مزرعة صهاريج التبريد">CRYO TANK FARM</span>
      </div>
      <div class="hidden sm:flex items-center gap-2">
        <span class="text-neutral-400 dark:text-neutral-500">CYCLE:</span>
        <span class="text-slate-300 font-semibold font-mono"><?= $selected_year ?></span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl">

      <!-- Page Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#1a3346] pb-5">
        <div>
          <div class="flex items-center gap-2 text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
            <span data-en="PRODUCTION & INVENTORY" data-ar="الإنتاج والمخزون">PRODUCTION & INVENTORY</span>
            <span>/</span>
            <span class="text-teal-400 dark:text-teal-400" data-en="CRYOGENIC RECONCILIATION" data-ar="مطابقة السوائل">CRYOGENIC RECONCILIATION</span>
          </div>
          <h1 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight" data-en="Liquid Reconciliation" data-ar="مطابقة السوائل">
            Liquid Reconciliation
          </h1>
          
          <!-- Year Switcher Tabs -->
          <div class="flex items-center gap-4 mt-3">
            <?php for($y = (int)date('Y') - 2; $y <= (int)date('Y'); $y++): ?>
              <a href="?year=<?= $y ?>" class="px-3 py-1 text-xs font-mono uppercase tracking-wider border-b-2 transition-colors <?= $selected_year === $y ? 'border-teal-600 text-teal-400 dark:text-teal-400 font-semibold' : 'border-transparent text-slate-400 hover:text-white' ?>">
                <span data-en="Year <?= $y ?>" data-ar="سنة <?= $y ?>">Year <?= $y ?></span>
              </a>
            <?php endfor; ?>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <a href="<?= app_url('modules/production/liquid_transaction.php?type=out') ?>" class="px-3.5 py-2 text-xs font-medium text-slate-300 bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700 rounded-xs flex items-center gap-1.5 transition-colors">
            <i data-lucide="arrow-up-right" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
            <span data-en="Decant / Dispatch" data-ar="صرف / تفريغ">Decant / Dispatch</span>
          </a>
          <a href="<?= app_url('modules/production/liquid_transaction.php?type=in') ?>" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white bg-amber-600 hover:bg-amber-500 active:bg-amber-700 transition-colors border border-amber-500 rounded-xs">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span data-en="Receive Shipment" data-ar="استلام شحنة سائل">Receive Shipment</span>
          </a>
        </div>
      </div>

      <!-- Reconciliation Formula Explanation Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] p-5 rounded-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <i data-lucide="calculator" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-heading font-bold text-sm text-white" data-en="Produced vs. theoretical yield from liquid received (per-cylinder basis)" data-ar="الإنتاج الفعلي مقابل العائد النظري من السوائل المستلمة (على أساس الأسطوانات)">
                Produced vs. theoretical yield from liquid received (per-cylinder basis)
              </h3>
            </div>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed" data-en="Calculated cylinders = liquid received (kg) ÷ gas mass of one standard cylinder. Factors are dynamically loaded from System Settings." data-ar="الأسطوانات المحسوبة = السوائل المستلمة (كجم) ÷ كتلة الغاز لأسطوانة معيارية. يتم تحميل المعاملات ديناميكياً من إعدادات النظام.">
              Calculated cylinders = liquid received (kg) ÷ gas mass of one standard cylinder. Factors are dynamically loaded from System Settings.
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span class="px-2.5 py-1 rounded-xs text-[11px] font-mono font-medium bg-[#09151f] text-slate-200 border border-[#1e3a50] whitespace-nowrap">
              38 L @ 140 BAR G
            </span>
            <a href="<?= app_url('modules/insights/system_settings.php') ?>" class="text-teal-400 dark:text-teal-400 hover:underline text-xs font-mono whitespace-nowrap" data-en="Edit Factors &rarr;" data-ar="تعديل المعاملات &larr;">
              Edit Factors &rarr;
            </a>
          </div>
        </div>
      </div>

      <!-- Reconciliation Gas Tables Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php foreach ($reconciliation_data as $gas_name => $months_data): ?>
          <?php 
            $factor = getFactorForGas($gas_name, $pdo, $db_factors);
            $gas_tank_id = $bulk_gas_ids[$gas_name] ?? 0;
            $total_produced = 0;
            $total_liquid = 0;
            $total_calc = 0;
            $total_diff = 0;
          ?>
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs flex flex-col overflow-hidden border-s-4 border-s-[#f97316]">
            
            <!-- Gas Table Header -->
            <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center justify-between">
              <div class="flex items-center gap-2.5">
                <div><?= gas_chip($gas_name, 'badge') ?></div>
                <div>
                  <h3 class="font-heading font-bold text-sm text-white"><?= htmlspecialchars($gas_name) ?></h3>
                  <p class="text-[11px] font-mono text-slate-400"><?= number_format($factor, 2) ?> kg / cylinder</p>
                </div>
              </div>
              <?php if ($gas_tank_id > 0): ?>
                <a href="<?= app_url('modules/production/liquid_profile.php?id=' . urlencode($gas_tank_id)) ?>" class="px-2.5 py-1 text-xs font-mono text-slate-300 bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700 rounded-xs flex items-center gap-1.5 transition-colors">
                  <i data-lucide="gauge" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
                  <span>Tank Profile</span>
                </a>
              <?php endif; ?>
            </div>
            
            <!-- Table Content -->
            <div class="overflow-x-auto flex-1">
              <table class="w-full text-left rtl:text-right border-collapse text-xs" style="min-width: 480px;">
                <thead>
                  <tr class="bg-[#102230]/75 bg-[#112433]/60 border-b border-[#1a3346] text-[11px] font-mono uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-2.5 font-semibold" data-en="Month" data-ar="الشهر">Month</th>
                    <th class="px-4 py-2.5 font-semibold text-right rtl:text-left" data-en="Produced" data-ar="المنتج">Produced</th>
                    <th class="px-4 py-2.5 font-semibold text-right rtl:text-left" data-en="Liquid (kg)" data-ar="السائل (كجم)">Liquid (kg)</th>
                    <th class="px-4 py-2.5 font-semibold text-right rtl:text-left" data-en="Calc. cyls" data-ar="الأسطوانات المحسوبة">Calc. cyls</th>
                    <th class="px-4 py-2.5 font-semibold text-right rtl:text-left" data-en="Diff" data-ar="الفرق">Diff</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#152a3a] font-mono">
                  <?php foreach ($months as $m_num => $m_name): ?>
                    <?php 
                      $produced = $months_data[$m_num]['produced'];
                      $liquid = $months_data[$m_num]['liquid'];
                      $calc_cyls = ($liquid > 0 && $factor > 0) ? round($liquid / $factor) : 0;
                      $diff = $calc_cyls - $produced;
                      
                      $total_produced += $produced;
                      $total_liquid += $liquid;
                      $total_calc += $calc_cyls;
                      $total_diff += $diff;

                      $diff_color = 'text-neutral-400 dark:text-neutral-500';
                      $diff_prefix = '';
                      if ($diff > 0) {
                        $diff_color = 'text-rose-600 dark:text-rose-400 font-semibold';
                        $diff_prefix = '+';
                      } elseif ($diff < 0) {
                        $diff_color = 'text-emerald-600 dark:text-emerald-400 font-semibold';
                      }
                    ?>
                    <tr class="hover:bg-[#102332] transition-colors">
                      <td class="px-4 py-2.5 text-slate-400 font-sans">
                        <span data-en="<?= $m_name ?>" data-ar="<?= $months_ar[$m_num] ?>"><?= $m_name ?></span>
                      </td>
                      <td class="px-4 py-2.5 text-right rtl:text-left text-white tabular-nums"><?= number_format($produced) ?></td>
                      <td class="px-4 py-2.5 text-right rtl:text-left text-white tabular-nums"><?= number_format($liquid) ?></td>
                      <td class="px-4 py-2.5 text-right rtl:text-left text-white tabular-nums"><?= number_format($calc_cyls) ?></td>
                      <td class="px-4 py-2.5 text-right rtl:text-left <?= $diff_color ?> tabular-nums"><?= $diff_prefix . number_format($diff) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  
                  <?php 
                    $tot_diff_color = 'text-neutral-400 dark:text-neutral-500';
                    $tot_diff_prefix = '';
                    if ($total_diff > 0) {
                      $tot_diff_color = 'text-rose-600 dark:text-rose-400 font-bold';
                      $tot_diff_prefix = '+';
                    } elseif ($total_diff < 0) {
                      $tot_diff_color = 'text-emerald-600 dark:text-emerald-400 font-bold';
                    }
                  ?>
                  <tr class="bg-[#102230]/75 bg-[#112433]/80 border-t border-[#1e3a50] font-bold">
                    <td class="px-4 py-3 font-sans text-white" data-en="Total" data-ar="الإجمالي">Total</td>
                    <td class="px-4 py-3 text-right rtl:text-left text-white tabular-nums"><?= number_format($total_produced) ?></td>
                    <td class="px-4 py-3 text-right rtl:text-left text-white tabular-nums"><?= number_format($total_liquid) ?></td>
                    <td class="px-4 py-3 text-right rtl:text-left text-white tabular-nums"><?= number_format($total_calc) ?></td>
                    <td class="px-4 py-3 text-right rtl:text-left <?= $tot_diff_color ?> tabular-nums"><?= $tot_diff_prefix . number_format($total_diff) ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
        
        <?php if(empty($reconciliation_data)): ?>
          <div class="col-span-full bg-[#0c1924] border border-[#1a3346] rounded-xs p-8 text-center text-xs text-slate-400" data-en="No bulk gas materials found in inventory. Please add Bulk Gas items to begin reconciliation." data-ar="لم يتم العثور على مواد غاز سائب في المخزون. يرجى إضافة عناصر غاز سائب لبدء المطابقة.">
            No bulk gas materials found in inventory. Please add Bulk Gas items to begin reconciliation.
          </div>
        <?php endif; ?>
      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();
  </script>
</body>
</html>