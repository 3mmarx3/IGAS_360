<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('cylinders.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_permission('cylinders.manage');
  require_csrf();
  $sku_to_delete = $_POST['sku'] ?? '';
  if (!empty($sku_to_delete)) {
    try {
      $pdo->beginTransaction();
      
      $delCyl = $pdo->prepare("DELETE FROM cylinders WHERE sku = ?");
      $delCyl->execute([$sku_to_delete]);

      $delBatch = $pdo->prepare("DELETE FROM cylinder_batches WHERE sku = ?");
      $delBatch->execute([$sku_to_delete]);

      $pdo->commit();
      redirect(app_url('modules/production/cylinders_inventory.php'));
    } catch (Exception $e) {
      $pdo->rollBack();
    }
  }
}

$active_page = 'cylinders_inventory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Cylinders Inventory'];

$kpiStmt = $pdo->query("
  SELECT 
    COUNT(id) AS total_assets,
    COALESCE(SUM(CASE WHEN status IN ('in_plant_empty', 'in_plant_filled') THEN 1 ELSE 0 END), 0) AS in_plant,
    COALESCE(SUM(CASE WHEN status = 'with_client' THEN 1 ELSE 0 END), 0) AS with_clients,
    COALESCE(SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END), 0) AS maintenance
  FROM cylinders
");
$kpiData = $kpiStmt ? $kpiStmt->fetch(PDO::FETCH_ASSOC) : [];

$total_assets = (int)($kpiData['total_assets'] ?? 0);
$in_plant   = (int)($kpiData['in_plant'] ?? 0);
$with_clients = (int)($kpiData['with_clients'] ?? 0);
$maintenance = (int)($kpiData['maintenance'] ?? 0);

$in_plant_pct = $total_assets > 0 ? round(($in_plant / $total_assets) * 100) : 0;
$with_clients_pct = $total_assets > 0 ? round(($with_clients / $total_assets) * 100) : 0;

$cylStmt = $pdo->query("
  SELECT 
    cb.sku, 
    MAX(cb.gas_classification) as gas, 
    MAX(cb.volume) as size,
    COUNT(c.id) as total,
    COALESCE(SUM(CASE WHEN c.status IN ('in_plant_empty', 'in_plant_filled') THEN 1 ELSE 0 END), 0) as plant,
    COALESCE(SUM(CASE WHEN c.status = 'with_client' THEN 1 ELSE 0 END), 0) as clients,
    COALESCE(SUM(CASE WHEN c.status = 'maintenance' THEN 1 ELSE 0 END), 0) as maint,
    COALESCE(SUM(CASE WHEN c.status = 'scrapped' THEN 1 ELSE 0 END), 0) as scrapped
  FROM cylinder_batches cb
  LEFT JOIN cylinders c ON cb.id = c.batch_id
  GROUP BY cb.sku
  ORDER BY total DESC
");
$cylinders_data = $cylStmt ? $cylStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$cylinders_overview = [];
$cylinders_hydro = [];
$cylinders_scrapped = [];

foreach ($cylinders_data as $row) {
  $plant = (int)$row['plant'];
  $total = (int)$row['total'];
  $maint = (int)$row['maint'];
  $scrapped = (int)$row['scrapped'];
  
  $status = 'optimal';
  if ($total > 0 && ($plant / $total) <= 0.20) {
    $status = 'low';
  } elseif ($plant == 0 && $total > 0) {
    $status = 'low';
  }
  
  $base_item = [
    'sku'   => $row['sku'],
    'gas'   => $row['gas'],
    'size'   => $row['size'],
    'total'  => $total,
    'plant'  => $plant,
    'clients' => (int)$row['clients'],
    'maint'  => $maint,
    'scrapped' => $scrapped,
    'status'  => $status
  ];

  $cylinders_overview[] = $base_item;

  if ($maint > 0) {
    $hydro_item = $base_item;
    $hydro_item['count_subset'] = $maint;
    $cylinders_hydro[] = $hydro_item;
  }

  if ($scrapped > 0) {
    $scrap_item = $base_item;
    $scrap_item['count_subset'] = $scrapped;
    $cylinders_scrapped[] = $scrap_item;
  }
}

$statusStyles = [
  'optimal' => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label' => 'Healthy', 'label_ar' => 'سليم'],
  'low'   => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Low at Plant', 'label_ar' => 'منخفض بالمصنع'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Cylinders Inventory | I-GAS Enterprise" data-ar="مخزون الأسطوانات | I-GAS Enterprise">Cylinders Inventory | I-GAS Enterprise</title>
  
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
        <span data-en="SHIFT: B (14:00 - 22:00)" data-ar="الوردية: ب (14:00 - 22:00)">SHIFT: B (14:00 - 22:00)</span>
      </div>
      <div class="hidden sm:flex items-center gap-2">
        <span class="text-neutral-400 dark:text-neutral-500">REV:</span>
        <span class="text-slate-300 font-semibold">2.4.1</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl">

      <!-- Page Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#1a3346] pb-5">
        <div>
          <div class="flex items-center gap-2 text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
            <span data-en="PRODUCTION & ASSETS" data-ar="الإنتاج والأصول">PRODUCTION & ASSETS</span>
            <span>/</span>
            <span class="text-teal-400 dark:text-teal-400" data-en="CYLINDERS" data-ar="الأسطوانات">CYLINDERS</span>
          </div>
          <h1 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight" data-en="Cylinders Inventory" data-ar="مخزون الأسطوانات">
            Cylinders Inventory
          </h1>
          <p class="text-xs sm:text-sm text-slate-400 mt-1" data-en="Track physical cylinder assets, plant availability, client circulation, and hydro-testing schedules." data-ar="تتبع أصول الأسطوانات الفعلية، توفر المصنع، تداول العملاء، وجداول الاختبار الهيدروليكي.">
            Track physical cylinder assets, plant availability, client circulation, and hydro-testing schedules.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button type="button" onclick="exportCylindersData()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
            <i data-lucide="download" class="w-4 h-4"></i>
            <span data-en="Export CSV" data-ar="تصدير CSV">Export CSV</span>
          </button>
          <button type="button" onclick="openBarcodeModal()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
            <i data-lucide="scan-barcode" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Scan Barcode" data-ar="مسح الباركود">Scan Barcode</span>
          </button>
          <?php if (has_permission('cylinders.manage')): ?>
          <a href="<?= app_url('modules/production/new_cylinder.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span data-en="Register Batch" data-ar="تسجيل دفعة">Register Batch</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Total Assets Owned" data-ar="إجمالي الأصول المملوكة">Total Assets Owned</p>
            <i data-lucide="cylinder" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div class="mt-2 flex items-baseline justify-between">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= number_format($total_assets) ?></h3>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Registered company units" data-ar="وحدات الشركة المسجلة">Registered company units</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-emerald-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="In Plant (Ready/Empty)" data-ar="الموجودة بالمصنع (جاهزة/فارغة)">In Plant (Ready/Empty)</p>
            <i data-lucide="warehouse" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
          </div>
          <div class="mt-2 flex items-baseline justify-between">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= number_format($in_plant) ?></h3>
            <span class="text-xs font-mono px-1.5 py-0.5 rounded-xs bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20"><?= $in_plant_pct ?>%</span>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Available at plant yard" data-ar="متوفرة بفناء المصنع">Available at plant yard</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-cyan-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="With Clients" data-ar="مع العملاء (قيد التداول)">With Clients</p>
            <i data-lucide="truck" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
          </div>
          <div class="mt-2 flex items-baseline justify-between">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= number_format($with_clients) ?></h3>
            <span class="text-xs font-mono px-1.5 py-0.5 rounded-xs bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border border-cyan-500/20"><?= $with_clients_pct ?>%</span>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="In customer circulation" data-ar="قيد التداول لدى العملاء">In customer circulation</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-rose-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Maintenance & Scrapped" data-ar="الصيانة والخردة">Maintenance & Scrapped</p>
            <i data-lucide="wrench" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
          </div>
          <div class="mt-2 flex items-baseline justify-between">
            <h3 class="font-mono text-2xl font-bold text-rose-600 dark:text-rose-400 tabular-nums"><?= number_format($maintenance) ?></h3>
          </div>
          <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-2" data-en="Hydro-testing required" data-ar="يتطلب اختبار هيدروليكي">Hydro-testing required</p>
        </div>
      </div>

      <!-- Cylinders Registry Table Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs flex flex-col overflow-hidden">
        <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f]">
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            
            <!-- Tabs -->
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar" id="filterTabs">
              <button type="button" onclick="switchTab('overview', this)" class="tab-btn active px-3.5 py-2 text-xs font-mono uppercase tracking-wider border-b-2 border-teal-600 text-teal-400 dark:text-teal-400 font-semibold cursor-pointer transition-colors" data-tab="overview">
                <span data-en="Inventory Overview" data-ar="نظرة عامة على المخزون">Inventory Overview</span>
                <span class="ms-1.5 px-1.5 py-0.2 rounded-xs text-[10px] font-mono bg-[#112433] text-slate-300"><?= count($cylinders_overview) ?></span>
              </button>
              <button type="button" onclick="switchTab('hydro', this)" class="tab-btn px-3.5 py-2 text-xs font-mono uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer transition-colors" data-tab="hydro">
                <span data-en="Hydro-Testing" data-ar="الاختبار الهيدروليكي">Hydro-Testing</span>
                <span class="ms-1.5 px-1.5 py-0.2 rounded-xs text-[10px] font-mono bg-[#112433] text-slate-300"><?= count($cylinders_hydro) ?></span>
              </button>
              <button type="button" onclick="switchTab('scrapped', this)" class="tab-btn px-3.5 py-2 text-xs font-mono uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer transition-colors" data-tab="scrapped">
                <span data-en="Scrapped Units" data-ar="الوحدات التالفة">Scrapped Units</span>
                <span class="ms-1.5 px-1.5 py-0.2 rounded-xs text-[10px] font-mono bg-[#112433] text-slate-300"><?= count($cylinders_scrapped) ?></span>
              </button>
            </div>

            <!-- Search and Filter -->
            <div class="flex items-center gap-3 w-full lg:w-auto">
              <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search SKU or gas type..." data-en-placeholder="Search SKU or gas type..." data-ar-placeholder="البحث برمز SKU أو نوع الغاز..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
              <select id="sizeFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
                <option value="" data-en="All Sizes" data-ar="جميع الأحجام">All Sizes</option>
                <option value="50L">50L</option>
                <option value="40L">40L</option>
                <option value="10L">10L</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Overview Section -->
        <div id="overview_section" class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse text-xs" style="min-width: 900px;">
            <thead>
              <tr class="bg-[#102230]/75 bg-[#112433]/60 border-b border-[#1a3346] text-[11px] font-mono uppercase tracking-wider text-slate-400">
                <th class="px-5 py-3 font-semibold" data-en="Type / SKU" data-ar="النوع / SKU">Type / SKU</th>
                <th class="px-5 py-3 font-semibold" data-en="Gas & Volume" data-ar="الغاز والحجم">Gas & Volume</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Total Owned" data-ar="الإجمالي المملوك">Total Owned</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="In Plant" data-ar="في المصنع">In Plant</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="With Clients" data-ar="مع العملاء">With Clients</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Maintenance" data-ar="الصيانة">Maintenance</th>
                <th class="px-5 py-3 font-semibold" data-en="Status" data-ar="الحالة">Status</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" style="width: 140px;" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a]">
              <?php foreach ($cylinders_overview as $c): ?>
              <?php
                $statusObj = $statusStyles[$c['status']] ?? $statusStyles['optimal'];
                $plantTextColor = $c['status'] === 'low' ? 'text-rose-600 dark:text-rose-400' : 'text-white';
              ?>
              <tr class="hover:bg-[#102332] transition-colors cylinder-row" data-size="<?= htmlspecialchars($c['size'] ?? '') ?>">
                <td class="px-5 py-3 font-mono font-semibold sku-text">
                  <a href="<?= app_url('modules/production/cylinder_details.php?sku=' . urlencode($c['sku'] ?? '')) ?>" class="text-teal-400 dark:text-teal-400 hover:underline inline-flex items-center gap-1.5 whitespace-nowrap">
                    <?= htmlspecialchars($c['sku'] ?? '') ?>
                  </a>
                </td>
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2">
                    <div class="gas-text"><?= gas_chip($c['gas'] ?? 'O2', 'compact') ?></div>
                    <span class="text-white font-medium"><?= htmlspecialchars($c['gas'] ?? '') ?></span>
                    <span class="text-xs font-mono text-slate-400 size-text">(<?= htmlspecialchars($c['size'] ?? '') ?>)</span>
                  </div>
                </td>
                <td class="px-5 py-3 text-right rtl:text-left font-mono font-bold text-white tabular-nums"><?= number_format($c['total']) ?></td>
                <td class="whitespace-nowrap px-5 py-3 text-right rtl:text-left font-mono font-bold <?= $plantTextColor ?> tabular-nums"><?= number_format($c['plant']) ?></td>
                <td class="px-5 py-3 text-right rtl:text-left font-mono text-slate-400 tabular-nums"><?= number_format($c['clients']) ?></td>
                <td class="px-5 py-3 text-right rtl:text-left font-mono text-slate-400 tabular-nums"><?= number_format($c['maint']) ?></td>
                <td class="px-5 py-3">
                  <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-xs border text-[11px] font-medium <?= $statusObj['border'] ?> <?= $statusObj['bg'] ?> <?= $statusObj['text'] ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
                    <span data-en="<?= $statusObj['label'] ?>" data-ar="<?= $statusObj['label_ar'] ?>"><?= $statusObj['label'] ?></span>
                  </span>
                </td>
                <td class="px-5 py-3 text-right rtl:text-left">
                  <div class="flex items-center justify-end gap-1.5">
                    <a href="<?= app_url('modules/production/cylinder_details.php?sku=' . urlencode($c['sku'] ?? '')) ?>" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 cursor-pointer transition-colors inline-flex items-center" title="View Details">
                      <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                    </a>
                    <?php if (has_permission('cylinders.manage')): ?>
                    <form method="POST" action="" class="m-0 p-0 inline-flex" onsubmit="return confirmDelete();">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="sku" value="<?= htmlspecialchars($c['sku'] ?? '') ?>">
                      <button type="submit" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-rose-950/30 dark:hover:bg-rose-950/40 text-rose-600 dark:text-rose-400 cursor-pointer transition-colors" title="Delete Cylinder Batch">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Hydro Section -->
        <div id="hydro_section" class="overflow-x-auto custom-scrollbar flex-1 w-full hidden">
          <table class="w-full text-left rtl:text-right border-collapse text-xs" style="min-width: 800px;">
            <thead>
              <tr class="bg-[#102230]/75 bg-[#112433]/60 border-b border-[#1a3346] text-[11px] font-mono uppercase tracking-wider text-slate-400">
                <th class="px-5 py-3 font-semibold" data-en="Type / SKU" data-ar="النوع / SKU">Type / SKU</th>
                <th class="px-5 py-3 font-semibold" data-en="Gas & Volume" data-ar="الغاز والحجم">Gas & Volume</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="In Maintenance / Hydro" data-ar="تحت الصيانة / الاختبار">In Maintenance / Hydro</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" style="width: 140px;" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a]">
              <?php foreach ($cylinders_hydro as $c): ?>
              <tr class="hover:bg-[#102332] transition-colors cylinder-row" data-size="<?= htmlspecialchars($c['size'] ?? '') ?>">
                <td class="px-5 py-3 font-mono font-semibold sku-text">
                  <a href="<?= app_url('modules/production/cylinder_details.php?sku=' . urlencode($c['sku'] ?? '')) ?>" class="text-teal-400 dark:text-teal-400 hover:underline">
                    <?= htmlspecialchars($c['sku'] ?? '') ?>
                  </a>
                </td>
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2">
                    <div class="gas-text"><?= gas_chip($c['gas'] ?? 'O2', 'compact') ?></div>
                    <span class="text-white font-medium"><?= htmlspecialchars($c['gas'] ?? '') ?></span>
                    <span class="text-xs font-mono text-slate-400 size-text">(<?= htmlspecialchars($c['size'] ?? '') ?>)</span>
                  </div>
                </td>
                <td class="px-5 py-3 text-right rtl:text-left font-mono font-bold text-rose-600 dark:text-rose-400 tabular-nums"><?= number_format($c['count_subset']) ?></td>
                <td class="px-5 py-3 text-right rtl:text-left">
                  <a href="<?= app_url('modules/production/cylinder_details.php?sku=' . urlencode($c['sku'] ?? '')) ?>" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 cursor-pointer transition-colors inline-flex items-center" title="View Details">
                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($cylinders_hydro)): ?>
              <tr>
                <td colspan="4" class="text-center py-8 text-xs text-slate-400" data-en="No cylinders currently under hydro-testing." data-ar="لا توجد أسطوانات تحت الاختبار الهيدروليكي حالياً.">No cylinders currently under hydro-testing.</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Scrapped Section -->
        <div id="scrapped_section" class="overflow-x-auto custom-scrollbar flex-1 w-full hidden">
          <table class="w-full text-left rtl:text-right border-collapse text-xs" style="min-width: 800px;">
            <thead>
              <tr class="bg-[#102230]/75 bg-[#112433]/60 border-b border-[#1a3346] text-[11px] font-mono uppercase tracking-wider text-slate-400">
                <th class="px-5 py-3 font-semibold" data-en="Type / SKU" data-ar="النوع / SKU">Type / SKU</th>
                <th class="px-5 py-3 font-semibold" data-en="Gas & Volume" data-ar="الغاز والحجم">Gas & Volume</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Scrapped Units" data-ar="الوحدات التالفة">Scrapped Units</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" style="width: 140px;" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a]">
              <?php foreach ($cylinders_scrapped as $c): ?>
              <tr class="hover:bg-[#102332] transition-colors cylinder-row" data-size="<?= htmlspecialchars($c['size'] ?? '') ?>">
                <td class="px-5 py-3 font-mono font-semibold sku-text">
                  <a href="<?= app_url('modules/production/cylinder_details.php?sku=' . urlencode($c['sku'] ?? '')) ?>" class="text-teal-400 dark:text-teal-400 hover:underline">
                    <?= htmlspecialchars($c['sku'] ?? '') ?>
                  </a>
                </td>
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2">
                    <div class="gas-text"><?= gas_chip($c['gas'] ?? 'O2', 'compact') ?></div>
                    <span class="text-white font-medium"><?= htmlspecialchars($c['gas'] ?? '') ?></span>
                    <span class="text-xs font-mono text-slate-400 size-text">(<?= htmlspecialchars($c['size'] ?? '') ?>)</span>
                  </div>
                </td>
                <td class="px-5 py-3 text-right rtl:text-left font-mono font-bold text-rose-600 dark:text-rose-400 tabular-nums"><?= number_format($c['count_subset']) ?></td>
                <td class="px-5 py-3 text-right rtl:text-left">
                  <a href="<?= app_url('modules/production/cylinder_details.php?sku=' . urlencode($c['sku'] ?? '')) ?>" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 cursor-pointer transition-colors inline-flex items-center" title="View Details">
                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($cylinders_scrapped)): ?>
              <tr>
                <td colspan="4" class="text-center py-8 text-xs text-slate-400" data-en="No scrapped units recorded." data-ar="لا توجد وحدات تالفة مسجلة.">No scrapped units recorded.</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="px-5 py-3 border-t border-[#1a3346] bg-[#09151f] flex justify-between items-center text-xs font-mono text-slate-400">
          <span id="showingText" data-en="All physical cylinders recorded in database" data-ar="جميع الأسطوانات الفعلية المسجلة بقاعدة البيانات">All physical cylinders recorded in database</span>
        </div>
      </div>

    </div>
  </main>

  <!-- Barcode Lookup Modal -->
  <div id="barcodeModal" class="fixed inset-0 bg-[#0c1924]/70 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-[#0c1924] border border-[#1e3a50] rounded-xs max-w-md w-full p-6 space-y-4">
      <div class="flex justify-between items-center pb-3 border-b border-[#1a3346]">
        <div class="flex items-center gap-2">
          <i data-lucide="scan-barcode" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
          <h3 class="font-heading font-bold text-base text-white" data-en="Scan / Lookup Cylinder" data-ar="مسح / استعلام عن أسطوانة">Scan / Lookup Cylinder</h3>
        </div>
        <button type="button" onclick="closeBarcodeModal()" class="text-neutral-500 hover:text-white cursor-pointer">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <p class="text-xs text-slate-400" data-en="Scan the physical barcode using a handheld scanner or enter the Cylinder SKU / Serial Number below:" data-ar="امسح الباركود باستخدام القارئ اليدوي أو أدخل رمز الأسطوانة (SKU) أو الرقم التسلسلي أدناه:">
        Scan the physical barcode using a handheld scanner or enter the Cylinder SKU / Serial Number below:
      </p>
      <form onsubmit="handleBarcodeSubmit(event)" class="space-y-4">
        <div>
          <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Cylinder SKU or Barcode" data-ar="رمز الأسطوانة أو الباركود">Cylinder SKU or Barcode</label>
          <input type="text" id="barcodeInput" required placeholder="e.g. CYL-O2-50L-001" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500">
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" onclick="closeBarcodeModal()" class="px-3.5 py-1.5 text-xs font-medium text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700 rounded-xs transition-colors cursor-pointer" data-en="Cancel" data-ar="إلغاء">Cancel</button>
          <button type="submit" class="btn-cta-orange px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-white bg-amber-600 hover:bg-amber-500 border border-amber-500 rounded-xs transition-colors cursor-pointer" data-en="Lookup Asset" data-ar="استعلام عن الأصل">Lookup Asset</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    lucide.createIcons();
    let activeCurrentTab = 'overview';

    function switchTab(tabName, element) {
      document.querySelectorAll('#filterTabs .tab-btn').forEach(t => {
        t.classList.remove('active', 'border-teal-600', 'text-teal-400', 'dark:text-teal-400', 'font-semibold');
        t.classList.add('border-transparent', 'text-neutral-600', 'text-slate-400');
      });
      element.classList.add('active', 'border-teal-600', 'text-teal-400', 'dark:text-teal-400', 'font-semibold');
      element.classList.remove('border-transparent', 'text-neutral-600', 'text-slate-400');
      activeCurrentTab = tabName;
      
      document.getElementById('overview_section').classList.add('hidden');
      document.getElementById('hydro_section').classList.add('hidden');
      document.getElementById('scrapped_section').classList.add('hidden');

      const targetSection = document.getElementById(tabName + '_section');
      if (targetSection) {
        targetSection.classList.remove('hidden');
      }
      filterRows();
    }

    function filterRows() {
      const query = (document.getElementById('searchInput').value || '').toLowerCase();
      const selectedSize = document.getElementById('sizeFilter').value;
      const activeSection = document.getElementById(activeCurrentTab + '_section');
      if (!activeSection) return;

      const rows = activeSection.querySelectorAll('.cylinder-row');
      rows.forEach(row => {
        const skuText = row.querySelector('.sku-text') ? row.querySelector('.sku-text').innerText.toLowerCase() : '';
        const gasText = row.querySelector('.gas-text') ? row.querySelector('.gas-text').innerText.toLowerCase() : '';
        const sizeText = row.getAttribute('data-size') || '';

        const matchesSearch = !query || skuText.includes(query) || gasText.includes(query);
        const matchesSize = !selectedSize || sizeText.includes(selectedSize);

        if (matchesSearch && matchesSize) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    document.getElementById('searchInput').addEventListener('input', filterRows);
    document.getElementById('sizeFilter').addEventListener('change', filterRows);

    function exportCylindersData() {
      let activeSection = document.getElementById(activeCurrentTab + '_section');
      let table = activeSection.querySelector('table');
      let csv = [];
      let rows = table.querySelectorAll("tr");
      
      rows.forEach(row => {
        let cols = row.querySelectorAll("td, th");
        let data = [];
        for(let i = 0; i < cols.length - 1; i++) {
          data.push('"' + cols[i].innerText.replace(/"/g, '""').trim() + '"');
        }
        if (data.length > 0) {
          csv.push(data.join(","));
        }
      });

      let csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
      let downloadLink = document.createElement("a");
      downloadLink.download = "cylinders_" + activeCurrentTab + "_export_" + new Date().toISOString().slice(0,10) + ".csv";
      downloadLink.href = window.URL.createObjectURL(csvFile);
      downloadLink.style.display = "none";
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
    }

    function openBarcodeModal() {
      document.getElementById('barcodeModal').classList.remove('hidden');
      setTimeout(() => document.getElementById('barcodeInput').focus(), 50);
    }

    function closeBarcodeModal() {
      document.getElementById('barcodeModal').classList.add('hidden');
    }

    function handleBarcodeSubmit(e) {
      e.preventDefault();
      const sku = document.getElementById('barcodeInput').value.trim();
      if (sku) {
        window.location.href = '<?= app_url("modules/production/cylinder_details.php?sku=") ?>' + encodeURIComponent(sku);
      }
    }

    function confirmDelete() {
      const currentLang = document.documentElement.lang || 'en';
      return confirm(currentLang === 'ar' ? 'هل أنت متأكد أنك تريد حذف سجل هذه الأسطوانة؟' : 'Are you sure you want to delete this cylinder record?');
    }
  </script>
</body>
</html>