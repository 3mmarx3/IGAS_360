<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('cylinders.view');

$active_page = 'cylinders_inventory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Cylinders Inventory', 'Cylinder Specs'];

$sku = isset($_GET['sku']) ? trim($_GET['sku']) : trim($_GET['id'] ?? '');
if (empty($sku) || is_numeric($sku)) {
  $firstSku = $pdo->query("SELECT sku FROM cylinder_batches ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstSku) {
    $sku = $firstSku;
  } else {
    redirect(app_url('modules/production/cylinders_inventory.php'));
  }
}

$stmt = $pdo->prepare("
  SELECT 
    sku, 
    gas_classification, 
    volume, 
    working_pressure, 
    test_pressure, 
    build_material, 
    valve_spec, 
    color_coding, 
    MAX(initial_test_date) as last_hydro, 
    MIN(next_test_due) as next_hydro 
  FROM cylinder_batches 
  WHERE sku = :sku 
  GROUP BY sku, gas_classification, volume, working_pressure, test_pressure, build_material, valve_spec, color_coding 
  LIMIT 1
");
$stmt->execute(['sku' => $sku]);
$batchInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$statStmt = $pdo->prepare("
  SELECT 
    COUNT(id) as total, 
    SUM(CASE WHEN status IN ('in_plant_empty', 'in_plant_filled') THEN 1 ELSE 0 END) as plant, 
    SUM(CASE WHEN status = 'with_client' THEN 1 ELSE 0 END) as clients, 
    SUM(CASE WHEN status IN ('maintenance', 'scrapped') THEN 1 ELSE 0 END) as maint 
  FROM cylinders 
  WHERE sku = :sku
");
$statStmt->execute(['sku' => $sku]);
$stats = $statStmt->fetch(PDO::FETCH_ASSOC);

if (!$batchInfo) {
  $batchInfo = [
    'sku' => $sku,
    'gas_classification' => 'N/A',
    'volume' => 'N/A',
    'working_pressure' => '0',
    'test_pressure' => '0',
    'build_material' => 'N/A',
    'valve_spec' => 'N/A',
    'color_coding' => 'N/A',
    'last_hydro' => 'N/A',
    'next_hydro' => 'N/A'
  ];
}

$total = (int)($stats['total'] ?? 0);
$plant = (int)($stats['plant'] ?? 0);

$status = 'optimal';
if ($total > 0 && ($plant / $total) <= 0.20) {
  $status = 'low';
} elseif ($plant == 0 && $total > 0) {
  $status = 'low';
}

$cylinder = [
  'sku' => $batchInfo['sku'],
  'gas' => $batchInfo['gas_classification'],
  'size' => $batchInfo['volume'],
  'total' => $total,
  'plant' => $plant,
  'clients' => (int)($stats['clients'] ?? 0),
  'maint' => (int)($stats['maint'] ?? 0),
  'status' => $status,
  'working_pressure' => $batchInfo['working_pressure'] . ' Bar',
  'test_pressure' => $batchInfo['test_pressure'] . ' Bar',
  'material' => $batchInfo['build_material'],
  'valve_type' => $batchInfo['valve_spec'],
  'color_code' => $batchInfo['color_coding'],
  'last_hydro' => $batchInfo['last_hydro'],
  'next_hydro' => $batchInfo['next_hydro']
];

$logStmt = $pdo->prepare("
  SELECT 
    c.status,
    COUNT(c.id) as qty,
    MAX(c.created_at) as date,
    p.company_name as client_name
  FROM cylinders c
  LEFT JOIN partners p ON c.current_client_id = p.id
  WHERE c.sku = :sku
  GROUP BY c.status, p.company_name, DATE(c.created_at)
  ORDER BY date DESC
  LIMIT 20
");
$logStmt->execute(['sku' => $sku]);
$rawLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

$logs = [];
foreach ($rawLogs as $index => $row) {
  $event_en = 'Unknown';
  $event_ar = 'غير معروف';
  $ref = 'System';
  
  switch($row['status']) {
    case 'in_plant_empty':
    case 'in_plant_filled':
      $event_en = 'In Plant';
      $event_ar = 'في المصنع';
      $ref = 'Production Line / Plant';
      break;
    case 'with_client':
      $event_en = 'Dispatch';
      $event_ar = 'إرسال للعميل';
      $ref = $row['client_name'] ? $row['client_name'] : 'Unknown Client';
      break;
    case 'maintenance':
      $event_en = 'Maintenance';
      $event_ar = 'صيانة / فحص';
      $ref = 'Sent for Hydro-testing / Maint.';
      break;
    case 'scrapped':
      $event_en = 'Scrapped';
      $event_ar = 'إتلاف / خردة';
      $ref = 'Out of Service';
      break;
  }

  $logs[] = [
    'id' => 'TRX-' . (8100 - $index),
    'date' => date('Y-m-d H:i', strtotime($row['date'])),
    'event_en' => $event_en,
    'event_ar' => $event_ar,
    'qty' => $row['qty'],
    'ref' => $ref,
    'user' => 'System'
  ];
}

$statusStyles = [
  'optimal' => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'Healthy Inventory', 'label_ar' => 'مخزون سليم'],
  'low'   => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'Low at Plant', 'label_ar' => 'منخفض بالمصنع'],
];

$eventStyles = [
  'Dispatch'  => ['border' => 'border-cyan-500/30', 'bg' => 'bg-cyan-500/10', 'text' => 'text-cyan-700 dark:text-cyan-400'],
  'In Plant'  => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400'],
  'Maintenance'=> ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400'],
  'Scrapped'  => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-slate-400'],
  'Unknown'  => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-slate-400']
];

$cs = $statusStyles[$cylinder['status']] ?? $statusStyles['optimal'];
$plant_pct = $cylinder['total'] > 0 ? round(($cylinder['plant'] / $cylinder['total']) * 100) : 0;
$with_clients_pct = $cylinder['total'] > 0 ? round(($cylinder['clients'] / $cylinder['total']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($cylinder['sku']) ?> | I-GAS Enterprise</title>
  
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
        <span class="text-white font-semibold"><?= htmlspecialchars($cylinder['sku']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-2">
        <span class="text-neutral-400 dark:text-neutral-500">REV:</span>
        <span class="text-slate-300 font-semibold">2.4.1</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl">

      <!-- Back Link -->
      <div>
        <a href="<?= app_url('modules/production/cylinders_inventory.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-white transition-colors">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          <span data-en="Back to Cylinders Inventory" data-ar="العودة لمخزون الأسطوانات">Back to Cylinders Inventory</span>
        </a>
      </div>

      <!-- Page Header Card -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#1a3346] pb-5">
        <div class="flex items-start sm:items-center gap-4">
          <div class="w-12 h-12 bg-[#0c1924] bg-[#112433] border border-[#1e3a50] rounded-xs flex items-center justify-center flex-shrink-0">
            <i data-lucide="cylinder" class="w-6 h-6 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-2.5 mb-1">
              <h1 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight font-mono">
                <?= htmlspecialchars($cylinder['sku']) ?>
              </h1>
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs border text-[11px] font-medium <?= $cs['border'] ?> <?= $cs['bg'] ?> <?= $cs['text'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $cs['dot'] ?>"></span>
                <span data-en="<?= $cs['label_en'] ?>" data-ar="<?= $cs['label_ar'] ?>"><?= $cs['label_en'] ?></span>
              </span>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-400">
              <div><?= gas_chip($cylinder['gas'] ?? 'O2', 'compact') ?></div>
              <span class="font-medium text-white"><?= htmlspecialchars($cylinder['gas']) ?></span>
              <span>·</span>
              <span class="font-mono"><?= htmlspecialchars($cylinder['size']) ?></span>
              <span data-en="Cylinders" data-ar="أسطوانات">Cylinders</span>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button type="button" onclick="window.print()" class="px-3.5 py-2 text-xs font-medium text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700 rounded-xs flex items-center gap-1.5 transition-colors cursor-pointer">
            <i data-lucide="download" class="w-4 h-4"></i>
            <span data-en="Export Data" data-ar="تصدير البيانات">Export Data</span>
          </button>
          <a href="<?= app_url('modules/production/new_cylinder.php') ?>" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white bg-amber-600 hover:bg-amber-500 active:bg-amber-700 transition-colors border border-amber-500 rounded-xs">
            <i data-lucide="scan-barcode" class="w-4 h-4"></i>
            <span data-en="Scan Movement" data-ar="مسح حركة">Scan Movement</span>
          </a>
        </div>
      </div>

      <!-- Bento Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Total Assets Owned" data-ar="إجمالي الأصول المملوكة">Total Assets Owned</p>
            <i data-lucide="cylinder" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div class="mt-2">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= number_format($cylinder['total']) ?></h3>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Units in company registry" data-ar="وحدة مسجلة بالنظام">Units in company registry</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-emerald-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="In Plant (Ready/Empty)" data-ar="بالمصنع (جاهزة/فارغة)">In Plant (Ready/Empty)</p>
            <i data-lucide="warehouse" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
          </div>
          <div class="mt-2 flex items-baseline justify-between">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= number_format($cylinder['plant']) ?></h3>
            <span class="text-xs font-mono px-1.5 py-0.5 rounded-xs bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20"><?= $plant_pct ?>%</span>
          </div>
          <div class="mt-2.5 h-1.5 w-full bg-[#112433] rounded-xs overflow-hidden">
            <div class="h-full bg-emerald-600 dark:bg-emerald-500" style="width: <?= min(100, $plant_pct) ?>%;"></div>
          </div>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-cyan-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="With Clients" data-ar="مع العملاء">With Clients</p>
            <i data-lucide="truck" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
          </div>
          <div class="mt-2 flex items-baseline justify-between">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= number_format($cylinder['clients']) ?></h3>
            <span class="text-xs font-mono px-1.5 py-0.5 rounded-xs bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border border-cyan-500/20"><?= $with_clients_pct ?>%</span>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Active circulation" data-ar="في دورة العمل">Active circulation</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-rose-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Maintenance / Scrapped" data-ar="صيانة / خردة">Maintenance / Scrapped</p>
            <i data-lucide="wrench" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
          </div>
          <div class="mt-2">
            <h3 class="font-mono text-2xl font-bold text-rose-600 dark:text-rose-400 tabular-nums"><?= number_format($cylinder['maint']) ?></h3>
          </div>
          <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-2" data-en="Out of service" data-ar="خارج الخدمة">Out of service</p>
        </div>
      </div>

      <!-- Content Grid: Movement Logs (2 cols) & Specs (1 col) -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <!-- Movement Logs Table -->
        <div class="xl:col-span-2 bg-[#0c1924] border border-[#1a3346] rounded-xs flex flex-col overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex justify-between items-center">
            <div class="flex items-center gap-2">
              <i data-lucide="history" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-heading font-bold text-sm text-white" data-en="Recent Movement Logs" data-ar="سجل الحركات الأخيرة">Recent Movement Logs</h3>
            </div>
            <span class="text-xs font-mono text-slate-400"><?= count($logs) ?> <span data-en="records" data-ar="سجل">records</span></span>
          </div>
          <div class="overflow-x-auto flex-1">
            <table class="w-full text-left rtl:text-right border-collapse text-xs" style="min-width: 600px;">
              <thead>
                <tr class="bg-[#102230]/75 bg-[#112433]/60 border-b border-[#1a3346] text-[11px] font-mono uppercase tracking-wider text-slate-400">
                  <th class="px-5 py-3 font-semibold" data-en="Log ID" data-ar="رقم السجل">Log ID</th>
                  <th class="px-4 py-3 font-semibold" data-en="Date & Time" data-ar="التاريخ والوقت">Date & Time</th>
                  <th class="px-4 py-3 font-semibold" data-en="Event Type" data-ar="نوع الحركة">Event Type</th>
                  <th class="px-4 py-3 font-semibold text-right rtl:text-left" data-en="Qty" data-ar="الكمية">Qty</th>
                  <th class="px-4 py-3 font-semibold" data-en="Reference / Client" data-ar="المرجع / العميل">Reference / Client</th>
                  <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Logged By" data-ar="بواسطة">Logged By</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#152a3a]">
                <?php if (!empty($logs)): ?>
                  <?php foreach ($logs as $l): ?>
                  <?php $es = $eventStyles[$l['event_en']] ?? $eventStyles['Unknown']; ?>
                  <tr class="hover:bg-[#102332] transition-colors">
                    <td class="px-5 py-3 font-mono font-medium text-white"><?= htmlspecialchars($l['id']) ?></td>
                    <td class="px-4 py-3 font-mono text-slate-400 text-[11px]"><?= htmlspecialchars($l['date']) ?></td>
                    <td class="px-4 py-3">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-xs border text-[11px] font-medium <?= $es['border'] ?> <?= $es['bg'] ?> <?= $es['text'] ?>" data-en="<?= htmlspecialchars($l['event_en']) ?>" data-ar="<?= htmlspecialchars($l['event_ar']) ?>">
                        <?= htmlspecialchars($l['event_en']) ?>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-right rtl:text-left font-mono font-bold text-white tabular-nums"><?= number_format($l['qty']) ?></td>
                    <td class="px-4 py-3 text-slate-300"><?= htmlspecialchars($l['ref']) ?></td>
                    <td class="px-5 py-3 text-right rtl:text-left font-mono text-slate-400 text-[11px]"><?= htmlspecialchars($l['user']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center py-8 text-xs text-slate-400" data-en="No recorded logs for these cylinders." data-ar="لا توجد حركات مسجلة لهذه الأسطوانات.">No recorded logs for these cylinders.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right Column: Technical Specs & Compliance -->
        <div class="space-y-6">
          
          <!-- Technical Specs Card -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
              <i data-lucide="cpu" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-heading font-bold text-sm text-white" data-en="Technical Specifications" data-ar="المواصفات الفنية">Technical Specifications</h3>
            </div>
            <div class="p-5 space-y-4 text-xs">
              <div class="border-b border-neutral-100 border-[#1a3346]/80 pb-3">
                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Material Build" data-ar="مادة الصنع">Material Build</p>
                <p class="font-medium text-white text-sm"><?= htmlspecialchars($cylinder['material']) ?></p>
              </div>
              <div class="border-b border-neutral-100 border-[#1a3346]/80 pb-3">
                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Working / Test Pressure" data-ar="ضغط التشغيل / الاختبار">Working / Test Pressure</p>
                <p class="font-mono font-medium text-white"><?= htmlspecialchars($cylinder['working_pressure']) ?> / <?= htmlspecialchars($cylinder['test_pressure']) ?></p>
              </div>
              <div class="border-b border-neutral-100 border-[#1a3346]/80 pb-3">
                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Valve Specification" data-ar="مواصفات الصمام">Valve Specification</p>
                <p class="font-mono font-medium text-white"><?= htmlspecialchars($cylinder['valve_type']) ?></p>
              </div>
              <div>
                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Color Coding Standard" data-ar="معيار ترميز الألوان">Color Coding Standard</p>
                <p class="font-medium text-white"><?= htmlspecialchars($cylinder['color_code']) ?></p>
              </div>
            </div>
          </div>

          <!-- Compliance & Testing Card -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
              <i data-lucide="shield-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-heading font-bold text-sm text-white" data-en="Compliance & Testing" data-ar="المطابقة والاختبار">Compliance & Testing</h3>
            </div>
            <div class="p-5 space-y-4 text-xs">
              <div class="border-b border-neutral-100 border-[#1a3346]/80 pb-3">
                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Last Hydro-Test Batch" data-ar="آخر فحص هيدروليكي">Last Hydro-Test Batch</p>
                <p class="font-mono font-medium text-white"><?= htmlspecialchars($cylinder['last_hydro']) ?></p>
              </div>
              <div class="pb-1">
                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Next Hydro-Test Due" data-ar="موعد الفحص القادم">Next Hydro-Test Due</p>
                <p class="font-mono font-bold text-white text-sm"><?= htmlspecialchars($cylinder['next_hydro']) ?></p>
              </div>
              <div class="pt-2">
                <a href="<?= app_url('modules/production/new_cylinder.php') ?>" class="w-full inline-flex justify-center items-center gap-2 px-3 py-2 text-xs font-medium text-slate-300 bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700 rounded-xs transition-colors cursor-pointer">
                  <i data-lucide="calendar" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
                  <span data-en="Schedule Hydro-Test" data-ar="جدولة فحص هيدروليكي">Schedule Hydro-Test</span>
                </a>
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