<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'reports.view');

$cur_start = date('Y-m-01');
$cur_end  = date('Y-m-t');

// KPIs
$stmt_total_dispatches = $pdo->prepare("SELECT COUNT(*) FROM dispatches WHERE dispatch_date BETWEEN ? AND ?");
$stmt_total_dispatches->execute([$cur_start, $cur_end]);
$total_dispatches = (int)$stmt_total_dispatches->fetchColumn();

$stmt_total_distance = $pdo->prepare("SELECT COALESCE(SUM(distance), 0) FROM dispatches WHERE dispatch_date BETWEEN ? AND ?");
$stmt_total_distance->execute([$cur_start, $cur_end]);
$total_distance = (int)$stmt_total_distance->fetchColumn();

$stmt_active_vehicles = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available' OR status = 'in_transit'");
$active_vehicles = (int)$stmt_active_vehicles->fetchColumn();

$stmt_delivered = $pdo->prepare("SELECT COUNT(*) FROM dispatches WHERE status = 'delivered' AND dispatch_date BETWEEN ? AND ?");
$stmt_delivered->execute([$cur_start, $cur_end]);
$delivered_count = (int)$stmt_delivered->fetchColumn();

$delivery_rate = $total_dispatches > 0 ? round(($delivered_count / $total_dispatches) * 100, 1) : 0;

// Main Data
$stmt_dispatches = $pdo->query("
  SELECT d.manifest_id, d.dispatch_date, d.eta_time, d.order_ref, d.destination, d.status, d.distance,
      v.plate_number, v.make_model,
      dr.full_name as driver_name
  FROM dispatches d
  LEFT JOIN vehicles v ON d.vehicle_id = v.fleet_id
  LEFT JOIN drivers dr ON d.driver_id = dr.driver_id
  ORDER BY d.dispatch_date DESC, d.eta_time DESC
  LIMIT 150
");
$dispatches = $stmt_dispatches ? $stmt_dispatches->fetchAll(PDO::FETCH_ASSOC) : [];

$active_page = 'logistics_reports';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Operations', 'Logistics Reports'];

$statusStyles = [
  'delivered'  => ['bg' => 'bg-[#0a2618]', 'text' => 'text-[#34d399]', 'border' => 'border-emerald-800/60', 'dot' => 'bg-emerald-500', 'label_en' => 'Delivered', 'label_ar' => 'تم التسليم'],
  'in_transit' => ['bg' => 'bg-[#0d2a38]', 'text' => 'text-[#38bdf8]', 'border' => 'border-cyan-800/60', 'dot' => 'bg-cyan-400', 'label_en' => 'In Transit', 'label_ar' => 'في الطريق'],
  'dispatched' => ['bg' => 'bg-[#2a1e0d]', 'text' => 'text-[#fbbf24]', 'border' => 'border-amber-800/60', 'dot' => 'bg-amber-400', 'label_en' => 'Dispatched', 'label_ar' => 'تم الإرسال'],
  'scheduled'  => ['bg' => 'bg-[#102230]', 'text' => 'text-slate-400', 'border' => 'border-[#1e3a50]', 'dot' => 'bg-slate-500', 'label_en' => 'Scheduled', 'label_ar' => 'مجدول'],
  'cancelled'  => ['bg' => 'bg-[#2a0d14]', 'text' => 'text-[#f87171]', 'border' => 'border-rose-800/60', 'dot' => 'bg-rose-500', 'label_en' => 'Cancelled', 'label_ar' => 'ملغي']
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Logistics & Fleet Reports | I-GAS Control Room" data-ar="تقارير اللوجستيات وحركة الأسطول | غرفة التحكم I-GAS">Logistics & Fleet Reports | I-GAS Control Room</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="../../assets/css/main.css">
  <link rel="stylesheet" href="../../assets/css/theme.css">
  <script>
    (function() {
      const savedTheme = localStorage.getItem('theme') || 'dark';
      if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
      const savedLang = localStorage.getItem('app_lang') || 'en';
      document.documentElement.lang = savedLang;
      document.documentElement.dir = savedLang === 'ar' ? 'rtl' : 'ltr';
    })();
  </script>
  <style>
    *, *::before, *::after {
      box-shadow: none !important;
      text-shadow: none !important;
      filter: none !important;
    }
    .font-oxanium { font-family: 'Oxanium', 'Reem Kufi', sans-serif; }
    .font-mono { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/30 selection:text-white">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

  <!-- Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0 text-slate-400">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-200">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span data-en="LOGISTICS & DISPATCH ANALYTICS SUITE" data-ar="منظومة تحليلات اللوجستيات والتوزيع">LOGISTICS & DISPATCH ANALYTICS SUITE</span>
      </span>
      <span class="text-[#1a3346]">|</span>
      <span class="text-slate-400">
        <span data-en="PERIOD:" data-ar="الفترة:">PERIOD:</span> <strong class="text-white"><?= date('M Y') ?></strong>
      </span>
      <span class="text-[#1a3346]">|</span>
      <span class="text-slate-400">
        <span data-en="TOTAL MANIFESTS:" data-ar="إجمالي البيانات:">TOTAL MANIFESTS:</span> <strong class="text-cyan-400 font-mono"><?= $total_dispatches ?></strong>
      </span>
    </div>
    <div class="flex items-center gap-3 text-slate-500 text-xs">
      <span class="tag-plate whitespace-nowrap">LOG-REP-01</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">

    <!-- Header Row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-[#1a3346]">
      <div>
        <div class="flex items-center gap-2 mb-1.5">
          <span class="tag-plate whitespace-nowrap">LOG-REP-01</span>
          <span class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Fleet Telemetry & Operational Intelligence" data-ar="مراقبة الأسطول والتقارير التشغيلية">Fleet Telemetry & Operational Intelligence</span>
        </div>
        <h1 class="text-2xl font-bold font-heading tracking-tight text-white flex items-center gap-2">
          <i data-lucide="bar-chart-3" class="w-6 h-6 text-cyan-400"></i>
          <span data-en="Logistics & Fleet Reports" data-ar="تقارير حركة الأسطول والتوزيع">Logistics & Fleet Reports</span>
        </h1>
        <p class="text-xs sm:text-sm text-slate-400 font-mono mt-1" data-en="Monitor fleet dispatches, delivery performance, kilometer telemetry, and active transport manifests." data-ar="مراقبة رحلات التوزيع، أداء تسليم الغازات، الكيلومترات المقطوعة وسجلات النقل النشطة.">
          Monitor fleet dispatches, delivery performance, kilometer telemetry, and active transport manifests.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <button type="button" onclick="window.print()" class="btn-secondary h-9 px-3.5 py-2 rounded-md border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 text-xs font-semibold uppercase tracking-wider inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
          <i data-lucide="printer" class="w-4 h-4 text-slate-400"></i>
          <span data-en="Print Summary" data-ar="طباعة الملخص">Print Summary</span>
        </button>
        <button type="button" id="exportExcelBtn" class="btn-cta-orange h-9 px-3.5 py-2 rounded-md text-xs font-semibold uppercase tracking-wider inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
          <i data-lucide="download" class="w-4 h-4"></i>
          <span data-en="Export Dispatch CSV" data-ar="تصدير كملف CSV">Export Dispatch CSV</span>
        </button>
      </div>
    </div>

    <!-- 4 KPI Telemetry Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- KPI 1: Total Dispatches MTD -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] rounded-sm p-4 flex flex-col justify-between min-h-[140px] hover:border-[#234257] transition-colors">
        <div class="text-[10px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Total Dispatches (MTD)" data-ar="إجمالي الإرساليات (الشهر الحالي)">
          Total Dispatches (MTD)
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= number_format($total_dispatches) ?>
          </span>
          <span class="text-[10px] font-mono text-cyan-400 px-1.5 py-0.5 rounded-xs bg-[#0d2a38] border border-cyan-800/60">RUNS LOGGED</span>
        </div>
        <div class="mt-2 text-[11px] font-mono text-slate-400" data-en="Authorized manifests in billing period" data-ar="الرحلات المعتمدة خلال الشهر الجاري">
          Authorized manifests in billing period
        </div>
      </div>

      <!-- KPI 2: Total Distance Covered -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] rounded-sm p-4 flex flex-col justify-between min-h-[140px] hover:border-[#234257] transition-colors">
        <div class="text-[10px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Total Distance Covered" data-ar="إجمالي المسافات المقطوعة">
          Total Distance Covered
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= number_format($total_distance) ?> <span class="text-xs font-normal text-slate-400 font-mono">KM</span>
          </span>
          <span class="text-[10px] font-mono text-cyan-400 px-1.5 py-0.5 rounded-xs bg-[#0d2a38] border border-cyan-800/60">TELEMETRY</span>
        </div>
        <div class="mt-2 text-[11px] font-mono text-slate-400" data-en="Aggregated round-trip haulage" data-ar="مجموع مسارات النقل ذهاباً وإياباً">
          Aggregated round-trip haulage
        </div>
      </div>

      <!-- KPI 3: Available Vehicles -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] rounded-sm p-4 flex flex-col justify-between min-h-[140px] hover:border-[#234257] transition-colors">
        <div class="text-[10px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Fleet Readiness" data-ar="جاهزية الأسطول">
          Fleet Readiness
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= $active_vehicles ?> <span class="text-xs font-normal text-slate-400 font-mono">UNITS</span>
          </span>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-mono uppercase rounded-xs bg-[#0a2618] text-[#34d399] border border-emerald-800/60">
            <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
            <span data-en="Online" data-ar="متاح">Online</span>
          </span>
        </div>
        <div class="mt-2 text-[11px] font-mono text-slate-400" data-en="Carriers available or actively in transit" data-ar="صهاريج وشاحنات متاحة أو في مسار الخدمة">
          Carriers available or actively in transit
        </div>
      </div>

      <!-- KPI 4: Delivery Success Rate -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] rounded-sm p-4 flex flex-col justify-between min-h-[140px] hover:border-[#234257] transition-colors">
        <div class="text-[10px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Delivery Success Rate" data-ar="معدل نجاح التسليم">
          Delivery Success Rate
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-[#34d399]">
            <?= $delivery_rate ?>%
          </span>
          <span class="text-[10px] font-mono text-slate-400"><?= $delivered_count ?> COMPLETED</span>
        </div>
        <div class="mt-2 w-full bg-[#09151f] border border-[#1a3346] h-1.5 rounded-xs overflow-hidden">
          <div class="bg-[#2dd4bf] h-full" style="width: <?= $delivery_rate ?>%;"></div>
        </div>
      </div>

    </div>

    <!-- Registry & Data Table Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden flex flex-col">
      
      <!-- Controls Header: Title + Search -->
      <div class="px-5 py-3.5 border-b border-[#1a3346] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-[#0c1924]">
        <div class="flex items-center gap-2">
          <span class="w-1.5 h-3 bg-cyan-400 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="Dispatch Manifest Ledger" data-ar="سجل بيانات إرساليات التوزيع">
            Dispatch Manifest Ledger
          </h2>
          <span class="px-1.5 py-0.5 rounded-xs bg-[#09151f] border border-[#1a3346] text-[10px] font-mono text-slate-400">
            <?= count($dispatches) ?> records
          </span>
        </div>

        <!-- Search Box -->
        <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search manifest, destination, driver..." data-en-placeholder="Search manifest, destination, driver..." data-ar-placeholder="ابحث عن البيان، الوجهة، السائق..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-start text-xs border-collapse" id="logisticsTable">
          <thead>
            <tr class="border-b border-[#1a3346] bg-[#09151f] text-slate-400 font-mono uppercase text-[10px] tracking-wider">
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Manifest ID / Order" data-ar="رقم البيان / الطلب">Manifest ID / Order</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Dispatch Date & ETA" data-ar="تاريخ الإرسال والموعد">Dispatch Date & ETA</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Hazmat Driver & Unit" data-ar="السائق والشاحنة">Hazmat Driver & Unit</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Destination" data-ar="الوجهة">Destination</th>
              <th class="py-2.5 px-3 text-end font-semibold" data-en="Distance (KM)" data-ar="المسافة (كم)">Distance (KM)</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Status" data-ar="الحالة">Status</th>
              <th class="py-2.5 px-3 text-end font-semibold no-export" data-en="Actions" data-ar="الإجراءات">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] text-xs font-mono text-slate-200">
            <?php if (empty($dispatches)): ?>
              <tr class="empty-state">
                <td colspan="7" class="py-8 text-center text-slate-400 font-mono text-xs" data-en="No logistics records found." data-ar="لم يتم العثور على سجلات لوجستية.">
                  No logistics records found.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($dispatches as $d): ?>
                <?php 
                  $stat = strtolower($d['status'] ?? 'scheduled');
                  $style = $statusStyles[$stat] ?? $statusStyles['dispatched'];
                ?>
                <tr class="filterable-row hover:bg-[#102332] transition-colors" data-search="<?= strtolower(htmlspecialchars(($d['manifest_id'] ?? '') . ' ' . ($d['order_ref'] ?? '') . ' ' . ($d['destination'] ?? '') . ' ' . ($d['driver_name'] ?? '') . ' ' . ($d['plate_number'] ?? ''))) ?>">
                  <td class="py-2.5 px-3">
                    <div class="font-mono font-bold text-cyan-400">
                      <?= htmlspecialchars($d['manifest_id']) ?>
                    </div>
                    <div class="text-[11px] font-mono text-slate-400">
                      PO: <?= htmlspecialchars($d['order_ref']) ?>
                    </div>
                  </td>
                  <td class="py-2.5 px-3">
                    <div class="font-mono text-slate-200 font-medium">
                      <?= htmlspecialchars(date('Y-m-d', strtotime($d['dispatch_date']))) ?>
                    </div>
                    <div class="text-[11px] font-mono text-slate-400">
                      ETA: <?= htmlspecialchars(date('H:i', strtotime($d['eta_time']))) ?>
                    </div>
                  </td>
                  <td class="py-2.5 px-3">
                    <div class="font-medium text-slate-100">
                      <?= htmlspecialchars($d['driver_name'] ?? 'Unassigned Driver') ?>
                    </div>
                    <div class="text-[11px] font-mono text-slate-400">
                      <?= htmlspecialchars($d['plate_number'] ?? 'N/A') ?> <?= !empty($d['make_model']) ? '(' . htmlspecialchars($d['make_model']) . ')' : '' ?>
                    </div>
                  </td>
                  <td class="py-2.5 px-3 text-slate-300 max-w-xs truncate" title="<?= htmlspecialchars($d['destination']) ?>">
                    <?= htmlspecialchars($d['destination']) ?>
                  </td>
                  <td class="py-2.5 px-3 text-end font-mono text-white font-medium">
                    <?= number_format((int)$d['distance']) ?>
                  </td>
                  <td class="py-2.5 px-3">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] font-mono uppercase tracking-wider rounded-xs border <?= $style['bg'] ?> <?= $style['text'] ?> <?= $style['border'] ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?= $style['dot'] ?>"></span>
                      <span data-en="<?= $style['label_en'] ?>" data-ar="<?= $style['label_ar'] ?>"><?= $style['label_en'] ?></span>
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-end no-export">
                    <a href="dispatch_details.php?id=<?= urlencode($d['manifest_id']) ?>" class="btn-table-action inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-mono w-auto h-7 whitespace-nowrap" title="View Waybill">
                      <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                      <span class="hidden sm:inline" data-en="Dossier" data-ar="الملف">Dossier</span>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            <tr id="noResultsRow" style="display: none;">
              <td colspan="7" class="py-8 text-center text-slate-400 font-mono text-xs" data-en="No dispatches found matching your search." data-ar="لم يتم العثور على إرساليات مطابقة للبحث.">
                No dispatches found matching your search.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Footer -->
      <div class="px-5 py-3.5 border-t border-[#1a3346] bg-[#09151f] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs font-mono text-slate-400">
        <div>
          <span data-en="Showing" data-ar="عرض">Showing</span> <span id="visibleCount" class="font-semibold text-white"><?= count($dispatches) ?></span> <span data-en="dispatch manifests" data-ar="بيانات إرسال">dispatch manifests</span>
        </div>
        <div class="flex items-center gap-1">
          <button type="button" class="px-2 py-1 bg-[#112433] text-slate-200 border border-[#234257] rounded-xs hover:bg-[#173042] transition-colors cursor-pointer">
            <i data-lucide="chevron-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          </button>
          <span class="px-2 py-1 bg-[#f97316] text-white rounded-xs font-mono font-semibold">1</span>
          <button type="button" class="px-2 py-1 bg-[#112433] text-slate-200 border border-[#234257] rounded-xs hover:bg-[#173042] transition-colors cursor-pointer">
            <i data-lucide="chevron-right" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          </button>
        </div>
      </div>

    </div>

  </div>
</main>

<script>
  lucide.createIcons();

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

    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('.filterable-row');
    const visibleCountElem = document.getElementById('visibleCount');
    const noResultsRow = document.getElementById('noResultsRow');

    if(searchInput) {
      searchInput.addEventListener('input', function() {
        const query = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(row => {
          const searchData = row.getAttribute('data-search') || '';
          if (!query || searchData.includes(query)) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        });
        
        if (visibleCountElem) visibleCountElem.innerText = visibleCount;
        if (noResultsRow) {
          noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
        }
      });
    }

    // CSV Export
    const exportBtn = document.getElementById('exportExcelBtn');
    if(exportBtn) {
      exportBtn.addEventListener('click', function() {
        let csvContent = "\uFEFF"; 
        const table = document.getElementById("logisticsTable");
        const trs = table.querySelectorAll("tr");

        trs.forEach(function(row) {
          if (row.style.display === 'none') return;
          
          let rowData = [];
          const cols = row.querySelectorAll("th, td");
          
          cols.forEach(function(col) {
            if (col.classList.contains('no-export')) return;
            
            let text = col.innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/"/g, '""');
            text = text.trim();
            rowData.push('"' + text + '"');
          });
          
          if (rowData.length > 0) {
            csvContent += rowData.join(",") + "\r\n";
          }
        });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", "IGAS_Logistics_Dispatch_Report_" + (new Date().toISOString().slice(0, 10)) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
    }
  });
</script>
</body>
</html>