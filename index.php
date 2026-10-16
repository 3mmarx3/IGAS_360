<?php
require_once __DIR__ . '/includes/auth.php';
require_staff();
require_once __DIR__ . '/config/db.php';

$active_page = 'dashboard';
$base_url  = './';
$breadcrumb = ['I-GAS', 'Operations', 'Command Center'];

$cur_month = date('m');
$cur_year = date('Y');
$cur_start = date('Y-m-01');
$cur_end = date('Y-m-t');
$prev_start = date('Y-m-01', strtotime('-1 month'));
$prev_end = date('Y-m-t', strtotime('-1 month'));

$stmt_rev_cur = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE issue_date BETWEEN ? AND ? AND status IN ('paid', 'pending', 'partial')");
$stmt_rev_cur->execute([$cur_start, $cur_end]);
$revenue_mtd = (float)$stmt_rev_cur->fetchColumn();

$stmt_rev_prev = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE issue_date BETWEEN ? AND ? AND status IN ('paid', 'pending', 'partial')");
$stmt_rev_prev->execute([$prev_start, $prev_end]);
$revenue_last = (float)$stmt_rev_prev->fetchColumn();

$revenue_growth = 0;
if ($revenue_last > 0) {
  $revenue_growth = round((($revenue_mtd - $revenue_last) / $revenue_last) * 100, 1);
} elseif ($revenue_mtd > 0) {
  $revenue_growth = 100;
}

$stmt_cyl_tot = $pdo->query("SELECT COUNT(*) FROM cylinders");
$total_cylinders = (int)$stmt_cyl_tot->fetchColumn();

$stmt_cyl = $pdo->query("SELECT COUNT(*) FROM cylinders WHERE status = 'in_plant'");
$active_cylinders = (int)$stmt_cyl->fetchColumn();

$cyl_pct = $total_cylinders > 0 ? round(($active_cylinders / $total_cylinders) * 100) : 0;

$stmt_fleet = $pdo->query("SELECT status, COUNT(*) FROM vehicles GROUP BY status");
$fleet_data = $stmt_fleet->fetchAll(PDO::FETCH_KEY_PAIR);
$total_fleet = array_sum($fleet_data) ?: 0;
$active_fleet = ($fleet_data['available'] ?? 0) + ($fleet_data['in_transit'] ?? 0);
$fleet_pct = $total_fleet > 0 ? round(($active_fleet / $total_fleet) * 100) : 0;

$maint_fleet = $fleet_data['maintenance'] ?? 0;
$loading_fleet = $fleet_data['loading'] ?? 0;
$on_route_fleet = $fleet_data['in_transit'] ?? 0;

$stmt_overdue = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM invoices WHERE status = 'overdue'");
$overdue_data = $stmt_overdue->fetch(PDO::FETCH_ASSOC);
$overdue_val = (float)$overdue_data['total'];
$overdue_count = (int)$overdue_data['count'];

$stmt_orders = $pdo->query("
  SELECT po.id, po.order_number, po.specs, po.status, po.total_value, po.order_date,
      p.company_name, p.contact_first_name, p.contact_last_name
  FROM purchase_orders po
  LEFT JOIN partners p ON po.client_id = p.id
  ORDER BY po.order_date DESC, po.id DESC 
  LIMIT 10
");
$manifest_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

$stmt_order_counts = $pdo->query("SELECT status, COUNT(*) FROM purchase_orders GROUP BY status");
$order_status_counts = $stmt_order_counts->fetchAll(PDO::FETCH_KEY_PAIR);
$total_orders = array_sum($order_status_counts);

$processing_count = $order_status_counts['processing'] ?? 0;
$in_transit_count = $order_status_counts['in_transit'] ?? 0;
$delivered_count = $order_status_counts['delivered'] ?? 0;
$draft_count   = $order_status_counts['draft'] ?? 0;

$stmt_activities = $pdo->query("
  SELECT ca.activity_text, ca.activity_time, ca.author, p.company_name
  FROM client_activities ca
  LEFT JOIN partners p ON ca.client_id = p.id
  ORDER BY ca.activity_time DESC 
  LIMIT 6
");
$activities = $stmt_activities->fetchAll(PDO::FETCH_ASSOC);

function time_elapsed_string($datetime, $full = false) {
  $now = new DateTime;
  $ago = new DateTime($datetime);
  $diff = $now->diff($ago);
  if ($diff->d == 0) {
    if ($diff->h == 0) {
      if ($diff->i == 0) return "Just now";
      return $diff->i . " mins ago";
    }
    return "Today · " . $ago->format('H:i');
  } elseif ($diff->d == 1) {
    return "Yesterday · " . $ago->format('H:i');
  } else {
    return $ago->format('D · H:i');
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Command Center | I-GAS Enterprise</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="<?= app_url('assets/css/main.css') ?>">
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    (function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      document.documentElement.lang = savedLang;
      document.documentElement.dir = savedLang === 'ar' ? 'rtl' : 'ltr';
      if (savedLang === 'ar') {
        document.documentElement.classList.add('arabic-mode');
      } else {
        document.documentElement.classList.remove('arabic-mode');
      }
      void document.documentElement.offsetHeight;
      requestAnimationFrame(function() {
        document.documentElement.classList.add('layout-ready');
      });
    })();
  </script>
  <style>
    *, *::before, *::after {
      box-shadow: none !important;
      text-shadow: none !important;
      filter: none !important;
    }
    html { font-size: 16px; visibility: hidden; opacity: 0; }
    html.layout-ready { visibility: visible; opacity: 1; transition: opacity 0.2s ease-in-out; }
    html.arabic-mode body { font-family: 'Cairo', 'IBM Plex Sans', sans-serif !important; }
    body {
      font-family: 'IBM Plex Sans', sans-serif;
      background-color: var(--surface-0);
      color: var(--ink);
      font-feature-settings: "tnum" 1;
    }
    .mono { font-family: 'IBM Plex Mono', monospace; letter-spacing: 0; }
    .num { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #1a3346; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #234257; }
    a, button { -webkit-tap-highlight-color: transparent; }

    .tab-item { position: relative; transition: color 0.15s ease; cursor: pointer; padding-bottom: 11px; user-select: none; }
    .tab-item::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: transparent; transition: background 0.15s ease; }
    .tab-item.active { color: #ffffff; font-weight: 600; }
    .tab-item.active::after { background: #f97316; }
    .tab-item:not(.active) { color: #94a3b8; }
    .tab-item:not(.active):hover { color: #ffffff; }

    .timeline-rail { position: relative; }
    .timeline-rail::before { content: ''; position: absolute; left: 19.5px; rtl:right: 19.5px; rtl:left: auto; top: 4px; bottom: 4px; width: 1px; background: #152a3a; }
    .timeline-dot { width: 7px; height: 7px; border-radius: 50%; border: 2px solid #234257; background: #0c1924; flex-shrink: 0; position: relative; z-index: 1; }
    .timeline-dot.current { border-color: #f97316; background: #f97316; }

    @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] text-slate-100 font-sans">

<?php include __DIR__ . '/includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 bg-[#071018] overflow-hidden">

  <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Telemetry Status Bar -->
    <div class="h-9 border-b border-[#152938] bg-[#060e14] flex items-center px-4 md:px-8 gap-3 sm:gap-6 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px] font-mono text-slate-400">
      <span class="flex items-center gap-2 font-medium uppercase tracking-wide text-slate-200 flex-shrink-0">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span data-en="System Nominal" data-ar="النظام يعمل بشكل طبيعي">System Nominal</span>
      </span>
      <span class="w-px h-3 flex-shrink-0 bg-[#152938]"></span>
      <span class="uppercase tracking-wide flex-shrink-0" data-en="Plant — Jeddah Industrial" data-ar="مصنع — جدة الصناعية">Plant — Jeddah Industrial</span>
      <span class="w-px h-3 flex-shrink-0 bg-[#152938]"></span>
      <span class="uppercase tracking-wide flex-shrink-0 text-cyan-400" data-en="Shift B · 14:00–22:00" data-ar="الوردية ب · 14:00–22:00">Shift B · 14:00–22:00</span>
      <span class="ml-auto rtl:mr-auto rtl:ml-0 uppercase tracking-wide flex-shrink-0 text-slate-500">v2.4.1 · P&ID CORE</span>
    </div>

    <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7 space-y-6">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <div class="flex items-center gap-2 mb-1.5">
            <span class="tag-plate whitespace-nowrap">CMD-CTRL-01</span>
            <span class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Executive Operations & Facility Telemetry" data-ar="العمليات التنفيذية ومراقبة المنشآت">Executive Operations & Facility Telemetry</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white font-heading" data-en="Executive Dashboard" data-ar="لوحة التحكم التنفيذية">Executive Dashboard</h1>
          <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-2xl leading-relaxed font-mono" data-en="Real-time operational visibility across production facilities, logistics fleet, order fulfillment, and cash collections." data-ar="مراقبة العمليات في الوقت الفعلي عبر مرافق الإنتاج وأسطول النقل وتنفيذ الطلبات والتحصيلات.">Real-time operational visibility across production facilities, logistics fleet, order fulfillment, and cash collections.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 shrink-0">
          <?php if (has_permission('reports.export') || is_admin()): ?>
          <button onclick="exportDashboardReport()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-mono font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
            <i data-lucide="download" class="w-4 h-4 text-slate-400"></i>
            <span data-en="Export Report" data-ar="تصدير التقرير">Export Report</span>
          </button>
          <?php endif; ?>
          <?php if (has_permission('orders.create') || is_admin()): ?>
          <a href="<?= app_url('modules/crm_sales/new_order.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span data-en="New Order" data-ar="طلب جديد">New Order</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento-Style Responsive KPI Grid (Design #3 Deep Industrial Navy & Slate Blue) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- KPI 1: Revenue MTD -->
        <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] rounded-sm p-4 sm:p-5 flex flex-col justify-between min-h-[160px] transition-colors hover:border-[#234257]">
          <div>
            <div class="flex items-center justify-between mb-3">
              <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400" data-en="Revenue — MTD" data-ar="الإيرادات — الشهر الحالي">Revenue — MTD</span>
              <div class="w-8 h-8 rounded-sm bg-[#102230] border border-[#1e3a50] flex items-center justify-center text-cyan-400">
                <i data-lucide="wallet" class="w-4 h-4"></i>
              </div>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
              <?php if (has_permission('finance.view') || is_admin()): ?>
              <h3 class="text-2xl sm:text-3xl font-bold tracking-tight text-white font-mono num"><?= number_format($revenue_mtd) ?></h3>
              <span class="text-xs font-mono text-slate-400" data-en="SAR" data-ar="ريال">SAR</span>
              <?php else: ?>
              <h3 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-500 font-mono num">••••••</h3>
              <?php endif; ?>
            </div>
          </div>
          <div class="pt-3 border-t border-[#152a3a] flex items-center justify-between text-xs font-mono">
            <?php if (has_permission('finance.view') || is_admin()): ?>
              <?php if($revenue_growth >= 0): ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-xs bg-[#0d2a38] text-[#2dd4bf] border border-cyan-800/60 font-mono text-[11px] font-semibold">
                <i data-lucide="arrow-up-right" class="w-3 h-3"></i>+<?= $revenue_growth ?>%
              </span>
              <?php else: ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-xs bg-[#2a0d14] text-[#f87171] border border-rose-800/60 font-mono text-[11px] font-semibold">
                <i data-lucide="arrow-down-right" class="w-3 h-3"></i><?= $revenue_growth ?>%
              </span>
              <?php endif; ?>
              <span class="text-slate-400 text-[11px]" data-en="vs last month" data-ar="مقارنة بالسابق">vs last month</span>
            <?php else: ?>
              <span class="text-xs text-slate-500" data-en="Restricted" data-ar="مقيد">Restricted</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- KPI 2: Active Cylinders -->
        <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] rounded-sm p-4 sm:p-5 flex flex-col justify-between min-h-[160px] transition-colors hover:border-[#234257]">
          <div>
            <div class="flex items-center justify-between mb-3">
              <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400" data-en="Plant Cylinders Active" data-ar="الأسطوانات قيد المعالجة">Plant Cylinders Active</span>
              <div class="w-8 h-8 rounded-sm bg-[#102230] border border-[#1e3a50] flex items-center justify-center text-cyan-400">
                <i data-lucide="cylinder" class="w-4 h-4"></i>
              </div>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
              <h3 class="text-2xl sm:text-3xl font-bold tracking-tight text-white font-mono num"><?= number_format($active_cylinders) ?></h3>
              <span class="text-xs font-mono text-slate-400">/ <?= number_format($total_cylinders) ?></span>
            </div>
          </div>
          <div>
            <div class="h-1.5 w-full bg-[#09151f] border border-[#1a3346] rounded-xs overflow-hidden mb-2">
              <div class="bg-[#2dd4bf] h-full transition-all duration-500" style="width: <?= $cyl_pct ?>%;"></div>
            </div>
            <div class="flex justify-between items-center text-xs font-mono text-slate-400">
              <span data-en="Facility Capacity" data-ar="سعة المنشأة">Facility Capacity</span>
              <span class="font-semibold text-slate-200 num"><?= $cyl_pct ?>%</span>
            </div>
          </div>
        </div>

        <!-- KPI 3: Fleet Status -->
        <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] rounded-sm p-4 sm:p-5 flex flex-col justify-between min-h-[160px] transition-colors hover:border-[#234257]">
          <div>
            <div class="flex items-center justify-between mb-3">
              <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400" data-en="Fleet Utilization" data-ar="جاهزية الأسطول">Fleet Utilization</span>
              <div class="w-8 h-8 rounded-sm bg-[#102230] border border-[#1e3a50] flex items-center justify-center text-orange-400">
                <i data-lucide="truck" class="w-4 h-4"></i>
              </div>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
              <h3 class="text-2xl sm:text-3xl font-bold tracking-tight text-white font-mono num"><?= $active_fleet ?></h3>
              <span class="text-xs font-mono text-slate-400">/ <?= $total_fleet ?> <span data-en="units" data-ar="مركبة">units</span></span>
            </div>
          </div>
          <div>
            <div class="h-1.5 w-full bg-[#09151f] border border-[#1a3346] rounded-xs overflow-hidden mb-2">
              <div class="bg-[#38bdf8] h-full transition-all duration-500" style="width: <?= $fleet_pct ?>%"></div>
            </div>
            <div class="flex justify-between text-[11px] font-mono text-slate-400">
              <span><span data-en="Transit" data-ar="شحن">Transit</span>: <b class="text-white"><?= $on_route_fleet ?></b></span>
              <span><span data-en="Load" data-ar="تحميل">Load</span>: <b class="text-white"><?= $loading_fleet ?></b></span>
              <span><span data-en="Maint" data-ar="صيانة">Maint</span>: <b class="text-white"><?= $maint_fleet ?></b></span>
            </div>
          </div>
        </div>

        <!-- KPI 4: Pending Invoices -->
        <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] rounded-sm p-4 sm:p-5 flex flex-col justify-between min-h-[160px] transition-colors hover:border-[#234257]">
          <div>
            <div class="flex items-center justify-between mb-3">
              <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400" data-en="Receivables Overdue" data-ar="الذمم المستحقة المتأخرة">Receivables Overdue</span>
              <div class="w-8 h-8 rounded-sm bg-[#102230] border border-[#1e3a50] flex items-center justify-center text-orange-400">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
              </div>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
              <?php if (has_permission('finance.view') || is_admin()): ?>
              <h3 class="text-2xl sm:text-3xl font-bold tracking-tight text-white font-mono num"><?= number_format($overdue_val) ?></h3>
              <span class="text-xs font-mono text-slate-400" data-en="SAR" data-ar="ريال">SAR</span>
              <?php else: ?>
              <h3 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-500 font-mono num">••••••</h3>
              <?php endif; ?>
            </div>
          </div>
          <div class="pt-3 border-t border-[#152a3a] flex items-center justify-between text-xs font-mono">
            <?php if (has_permission('finance.view') || is_admin()): ?>
              <?php if($overdue_count > 0): ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-xs bg-[#2a170d] text-[#f97316] border border-orange-800/60 font-mono text-[11px] font-semibold">
                <i data-lucide="alert-circle" class="w-3 h-3"></i><?= $overdue_count ?> <span data-en="pending" data-ar="متأخرة">pending</span>
              </span>
              <?php else: ?>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-xs bg-[#0a2618] text-[#34d399] border border-emerald-800/60 font-mono text-[11px] font-semibold">
                <i data-lucide="check-circle" class="w-3 h-3"></i><span data-en="All clear" data-ar="الكل مسدد">All clear</span>
              </span>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-xs text-slate-500" data-en="Restricted" data-ar="مقيد">Restricted</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Main Content: Orders Master Table & Activity Feed -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Orders Table Card -->
        <div class="xl:col-span-2 bg-[#0c1924] border border-[#1a3346] rounded-sm flex flex-col overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] flex flex-col gap-3.5 bg-[#0c1924]">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <h3 class="text-sm sm:text-base font-bold tracking-tight text-white font-mono uppercase" data-en="Recent Purchase Orders" data-ar="أحدث أوامر الشراء">Recent Purchase Orders</h3>
                <p class="text-xs text-slate-400 mt-0.5 font-mono" data-en="Active client procurement orders flowing through the fulfillment pipeline." data-ar="أوامر شراء العملاء النشطة المتدفقة عبر مسار التنفيذ.">Active client procurement orders flowing through the fulfillment pipeline.</p>
              </div>
              <div class="flex items-center gap-2.5">
                <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="orderSearchInput" onkeyup="filterOrdersTableSearch()" placeholder="Search order or client..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
                <?php if (has_permission('orders.view') || is_admin()): ?>
                <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
                  <span data-en="View All" data-ar="عرض الكل">View All</span>
                  <i data-lucide="arrow-right" class="w-3.5 h-3.5 rtl:rotate-180"></i>
                </a>
                <?php endif; ?>
              </div>
            </div>

            <!-- Interactive Tabs for Pipeline Filtering -->
            <div class="flex items-center gap-5 text-xs font-mono font-medium border-b border-[#1a3346] -mb-4 overflow-x-auto">
              <button type="button" onclick="filterByStatus('all')" id="tab-all" class="tab-item active text-white pb-3 flex items-center gap-2 cursor-pointer">
                <span data-en="All Orders" data-ar="كل الطلبات">All Orders</span>
                <span class="px-1.5 py-0.5 rounded-xs bg-[#102230] border border-[#1e3a50] text-[10px] text-cyan-400 font-mono"><?= $total_orders ?></span>
              </button>
              <button type="button" onclick="filterByStatus('processing')" id="tab-processing" class="tab-item text-slate-400 hover:text-white pb-3 flex items-center gap-2 cursor-pointer">
                <span data-en="Processing" data-ar="قيد المعالجة">Processing</span>
                <span class="px-1.5 py-0.5 rounded-xs bg-[#102230] border border-[#1e3a50] text-[10px] text-slate-300 font-mono"><?= $processing_count ?></span>
              </button>
              <button type="button" onclick="filterByStatus('in_transit')" id="tab-in_transit" class="tab-item text-slate-400 hover:text-white pb-3 flex items-center gap-2 cursor-pointer">
                <span data-en="In Transit" data-ar="قيد الشحن">In Transit</span>
                <span class="px-1.5 py-0.5 rounded-xs bg-[#102230] border border-[#1e3a50] text-[10px] text-slate-300 font-mono"><?= $in_transit_count ?></span>
              </button>
              <button type="button" onclick="filterByStatus('delivered')" id="tab-delivered" class="tab-item text-slate-400 hover:text-white pb-3 flex items-center gap-2 cursor-pointer">
                <span data-en="Delivered" data-ar="تم التوصيل">Delivered</span>
                <span class="px-1.5 py-0.5 rounded-xs bg-[#102230] border border-[#1e3a50] text-[10px] text-slate-300 font-mono"><?= $delivered_count ?></span>
              </button>
              <button type="button" onclick="filterByStatus('draft')" id="tab-draft" class="tab-item text-slate-400 hover:text-white pb-3 flex items-center gap-2 cursor-pointer">
                <span data-en="Draft" data-ar="مسودة">Draft</span>
                <span class="px-1.5 py-0.5 rounded-xs bg-[#102230] border border-[#1e3a50] text-[10px] text-slate-300 font-mono"><?= $draft_count ?></span>
              </button>
            </div>
          </div>

          <div class="overflow-x-auto flex-1">
            <table class="w-full text-left rtl:text-right border-collapse" id="ordersTable">
              <thead>
                <tr class="text-[10px] font-mono uppercase tracking-wider text-slate-400 bg-[#09151f] border-b border-[#1a3346]">
                  <th class="px-5 py-3 font-semibold" data-en="Order Number" data-ar="رقم الطلب">Order Number</th>
                  <th class="px-5 py-3 font-semibold" data-en="Client Company" data-ar="الشركة / العميل">Client Company</th>
                  <th class="px-5 py-3 font-semibold" data-en="Gas Specs" data-ar="المواصفات">Gas Specs</th>
                  <th class="px-5 py-3 font-semibold" data-en="Status" data-ar="الحالة">Status</th>
                  <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Value (SAR)" data-ar="القيمة (ريال)">Value (SAR)</th>
                  <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Actions" data-ar="الإجراءات">Actions</th>
                </tr>
              </thead>
              <tbody class="text-xs font-mono divide-y divide-[#152a3a]">
                <?php if(empty($manifest_orders)): ?>
                <tr>
                  <td colspan="6" class="text-center py-10 text-slate-400 font-mono text-xs" data-en="No recent purchase orders found." data-ar="لم يتم العثور على طلبات حديثة.">No recent purchase orders found.</td>
                </tr>
                <?php else: ?>
                  <?php foreach($manifest_orders as $ord): ?>
                  <?php
                    $initials_client = strtoupper(substr($ord['company_name'] ?? 'CL', 0, 2));
                    $ordStatus = $ord['status'] ?? 'draft';
                    
                    $statusConfig = [
                      'processing' => ['bg' => 'bg-[#0d2a38]', 'text' => 'text-[#38bdf8]', 'border' => 'border-cyan-800/60', 'dot' => '#38bdf8', 'label' => 'Processing', 'label_ar' => 'قيد المعالجة'],
                      'in_transit' => ['bg' => 'bg-[#2a1e0d]', 'text' => 'text-[#fbbf24]', 'border' => 'border-amber-800/60', 'dot' => '#f59e0b', 'label' => 'In Transit', 'label_ar' => 'قيد الشحن'],
                      'delivered' => ['bg' => 'bg-[#0a2618]', 'text' => 'text-[#34d399]', 'border' => 'border-emerald-800/60', 'dot' => '#10b981', 'label' => 'Delivered', 'label_ar' => 'تم التوصيل'],
                      'draft'   => ['bg' => 'bg-[#102230]', 'text' => 'text-slate-400', 'border' => 'border-[#1e3a50]', 'dot' => '#94a3b8', 'label' => 'Draft', 'label_ar' => 'مسودة'],
                      'cancelled' => ['bg' => 'bg-[#2a0d14]', 'text' => 'text-[#f87171]', 'border' => 'border-rose-800/60', 'dot' => '#ef4444', 'label' => 'Cancelled', 'label_ar' => 'ملغي']
                    ];
                    
                    $s = $statusConfig[$ordStatus] ?? $statusConfig['draft'];
                  ?>
                  <tr class="order-row transition-colors hover:bg-[#102332]" data-status="<?= htmlspecialchars($ordStatus) ?>">
                    <td class="px-5 py-3 font-mono font-semibold text-cyan-400 text-xs">
                      <?= htmlspecialchars($ord['order_number']) ?>
                    </td>
                    <td class="px-5 py-3">
                      <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-xs bg-[#08131c] border border-[#1e3a50] text-cyan-400 font-bold text-[11px] flex items-center justify-center flex-shrink-0"><?= $initials_client ?></span>
                        <div class="flex flex-col min-w-0">
                          <span class="font-medium text-slate-100 text-xs truncate hover:text-cyan-400"><?= htmlspecialchars($ord['company_name'] ?? 'Unknown Client') ?></span>
                          <span class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars(trim(($ord['contact_first_name'] ?? '') . ' ' . ($ord['contact_last_name'] ?? ''))) ?></span>
                        </div>
                      </div>
                    </td>
                    <td class="px-5 py-3 text-xs font-mono text-slate-300">
                      <?php
                        $specs = strlen($ord['specs'] ?? '') > 28 ? substr($ord['specs'], 0, 28) . '...' : ($ord['specs'] ?? 'Industrial Standard');
                        echo htmlspecialchars($specs);
                      ?>
                    </td>
                    <td class="px-5 py-3">
                      <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs border text-[11px] font-mono <?= $s['bg'] ?> <?= $s['text'] ?> <?= $s['border'] ?>">
                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $s['dot'] ?>"></span>
                        <span data-en="<?= $s['label'] ?>" data-ar="<?= $s['label_ar'] ?>"><?= $s['label'] ?></span>
                      </span>
                    </td>
                    <td class="px-5 py-3 text-right rtl:text-left font-bold font-mono text-white text-xs">
                      <?= (has_permission('finance.view') || is_admin()) ? number_format((float)($ord['total_value'] ?? 0), 2) : '—' ?>
                    </td>
                    <td class="px-5 py-3 text-right rtl:text-left">
                      <div class="flex items-center justify-end gap-1.5">
                        <?php if (has_permission('orders.view') || is_admin()): ?>
                        <a href="<?= app_url('modules/crm_sales/view_order.php') ?>?order_num=<?= urlencode($ord['order_number']) ?>" class="p-1.5 rounded-xs border border-[#1a3346] bg-[#08131c] hover:bg-[#102332] text-slate-400 hover:text-white transition-colors" title="View Order Details">
                          <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        </a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="px-5 py-3 border-t border-[#1a3346] flex flex-col sm:flex-row justify-between items-center gap-3 bg-[#09151f] text-xs font-mono text-slate-400">
            <span><span data-en="Showing latest " data-ar="عرض أحدث ">Showing latest </span><strong class="text-white"><?= count($manifest_orders) ?></strong> <span data-en="orders out of " data-ar="طلبات من إجمالي ">orders out of </span><strong class="text-white"><?= $total_orders ?></strong></span>
            <div class="flex items-center gap-2">
              <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="text-xs font-semibold text-cyan-400 hover:text-cyan-300 flex items-center gap-1 transition-colors">
                <span data-en="Open Full Registry" data-ar="فتح السجل الكامل">Open Full Registry</span>
                <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
              </a>
            </div>
          </div>
        </div>

        <!-- Activity Feed Card -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm flex flex-col overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] flex justify-between items-center bg-[#0c1924]">
            <div>
              <h3 class="text-sm sm:text-base font-bold tracking-tight text-white font-mono uppercase" data-en="Live Activity Stream" data-ar="سجل الأنشطة الحية">Live Activity Stream</h3>
              <p class="text-xs text-slate-400 mt-0.5 font-mono" data-en="Real-time audit log of customer interactions." data-ar="سجل فوري لتفاعلات العملاء.">Real-time audit log of customer interactions.</p>
            </div>
            <a href="<?= app_url('modules/crm_sales/client_activity.php') ?>" class="p-1.5 rounded-xs border border-[#1a3346] bg-[#08131c] hover:bg-[#102332] text-slate-400 hover:text-white transition-colors" title="View Full Activity Log">
              <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
            </a>
          </div>
          <div class="p-5 timeline-rail flex-1 overflow-y-auto space-y-5">
            <?php if(empty($activities)): ?>
              <p class="text-center text-xs text-slate-400 font-mono py-8" data-en="No recent activities recorded." data-ar="لا توجد أنشطة حديثة مسجلة.">No recent activities recorded.</p>
            <?php else: ?>
              <?php foreach($activities as $index => $act): ?>
              <div class="flex gap-3.5 relative">
                <div class="timeline-dot <?= $index === 0 ? 'current' : '' ?> mt-1.5"></div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs sm:text-sm font-medium text-slate-200 leading-snug font-sans"><?= htmlspecialchars($act['activity_text']) ?></p>
                  <div class="flex items-center gap-2 mt-1 font-mono">
                    <span class="text-xs text-slate-400 font-medium"><?= htmlspecialchars($act['company_name'] ?? 'General') ?></span>
                    <span class="text-slate-600">·</span>
                    <span class="text-[11px] text-slate-500">
                      <?= time_elapsed_string($act['activity_time']) ?>
                    </span>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="border-t border-[#1a3346] p-3 bg-[#09151f]">
            <?php if (has_permission('clients.view') || is_admin()): ?>
            <a href="<?= app_url('modules/crm_sales/client_activity.php') ?>" class="btn-secondary w-full block text-center text-xs font-mono font-semibold py-2 rounded-sm" data-en="View Complete Activity Timeline" data-ar="عرض السجل الزمني الكامل">View Complete Activity Timeline</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Quick Action Command Cards (Design #3 Deep Industrial Navy) -->
      <div>
        <h4 class="text-xs font-mono font-semibold uppercase tracking-wider text-slate-400 mb-3" data-en="Quick Operations Access" data-ar="الوصول السريع للعمليات">Quick Operations Access</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <?php if (has_permission('quotes.create') || is_admin()): ?>
          <a href="<?= app_url('modules/crm_sales/new_quotation.php') ?>" class="bg-[#0c1924] hover:bg-[#102332] border border-[#1a3346] hover:border-orange-500/50 rounded-sm p-4 flex items-center gap-3.5 transition-all group border-s-4 border-s-[#f97316]">
            <div class="w-11 h-11 rounded-sm bg-[#102230] group-hover:bg-[#152e42] group-hover:border-orange-500/50 transition-colors flex items-center justify-center flex-shrink-0 text-cyan-400 group-hover:text-orange-400 border border-[#1e3a50]">
              <i data-lucide="file-plus-2" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0">
              <p class="text-sm font-bold text-white group-hover:text-orange-400 transition-colors truncate" data-en="Create Quotation" data-ar="إنشاء عرض سعر">Create Quotation</p>
              <p class="text-xs text-slate-400 truncate mt-0.5 font-mono" data-en="Draft custom gas proposal" data-ar="مسودة مقترح أسعار جديد">Draft custom gas proposal</p>
            </div>
          </a>
          <?php endif; ?>

          <?php if (has_permission('production.create') || is_admin()): ?>
          <a href="<?= app_url('modules/production/new_shift_log.php') ?>" class="bg-[#0c1924] hover:bg-[#102332] border border-[#1a3346] hover:border-orange-500/50 rounded-sm p-4 flex items-center gap-3.5 transition-all group border-s-4 border-s-[#f97316]">
            <div class="w-11 h-11 rounded-sm bg-[#102230] group-hover:bg-[#152e42] group-hover:border-orange-500/50 transition-colors flex items-center justify-center flex-shrink-0 text-cyan-400 group-hover:text-orange-400 border border-[#1e3a50]">
              <i data-lucide="factory" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0">
              <p class="text-sm font-bold text-white group-hover:text-orange-400 transition-colors truncate" data-en="Log Production Shift" data-ar="تسجيل وردية الإنتاج">Log Production Shift</p>
              <p class="text-xs text-slate-400 truncate mt-0.5 font-mono" data-en="Daily compressor & gas log" data-ar="سجل الضاغط والغاز اليومي">Daily compressor & gas log</p>
            </div>
          </a>
          <?php endif; ?>

          <?php if (has_permission('dispatch.create') || has_permission('logistics.view') || is_admin()): ?>
          <a href="<?= app_url('modules/logistics_fleet/new_dispatch.php') ?>" class="bg-[#0c1924] hover:bg-[#102332] border border-[#1a3346] hover:border-orange-500/50 rounded-sm p-4 flex items-center gap-3.5 transition-all group border-s-4 border-s-[#f97316]">
            <div class="w-11 h-11 rounded-sm bg-[#102230] group-hover:bg-[#152e42] group-hover:border-orange-500/50 transition-colors flex items-center justify-center flex-shrink-0 text-cyan-400 group-hover:text-orange-400 border border-[#1e3a50]">
              <i data-lucide="truck" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0">
              <p class="text-sm font-bold text-white group-hover:text-orange-400 transition-colors truncate" data-en="Dispatch Fleet Manifest" data-ar="إرسال بيان الأسطول">Dispatch Fleet Manifest</p>
              <p class="text-xs text-slate-400 truncate mt-0.5 font-mono" data-en="Assign vehicle routes & orders" data-ar="تخصيص مسارات المركبات والطلبات">Assign vehicle routes & orders</p>
            </div>
          </a>
          <?php endif; ?>

          <?php if (has_permission('clients.view') || is_admin()): ?>
          <a href="<?= app_url('modules/crm_sales/clients_directory.php') ?>" class="bg-[#0c1924] hover:bg-[#102332] border border-[#1a3346] hover:border-orange-500/50 rounded-sm p-4 flex items-center gap-3.5 transition-all group border-s-4 border-s-[#f97316]">
            <div class="w-11 h-11 rounded-sm bg-[#102230] group-hover:bg-[#152e42] group-hover:border-orange-500/50 transition-colors flex items-center justify-center flex-shrink-0 text-cyan-400 group-hover:text-orange-400 border border-[#1e3a50]">
              <i data-lucide="users" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0">
              <p class="text-sm font-bold text-white group-hover:text-orange-400 transition-colors truncate" data-en="Enterprise Accounts" data-ar="دليل حسابات الشركات">Enterprise Accounts</p>
              <p class="text-xs text-slate-400 truncate mt-0.5 font-mono" data-en="Directory, credit limits & tanks" data-ar="الدليل، الحدود الائتمانية والخزانات">Directory, credit limits & tanks</p>
            </div>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <script>
    lucide.createIcons();

    // Interactive status filter for Recent Orders table
    function filterByStatus(status) {
      // Update active tab styles
      document.querySelectorAll('.tab-item').forEach(el => {
        el.classList.remove('active', 'text-white', 'font-semibold');
        el.classList.add('text-slate-400');
      });
      const activeTab = document.getElementById('tab-' + status);
      if (activeTab) {
        activeTab.classList.add('active', 'text-white', 'font-semibold');
        activeTab.classList.remove('text-slate-400');
      }

      // Filter rows
      const rows = document.querySelectorAll('.order-row');
      rows.forEach(row => {
        if (status === 'all' || row.getAttribute('data-status') === status) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    // Live instant table search filter
    function filterOrdersTableSearch() {
      const query = (document.getElementById('orderSearchInput')?.value || '').toLowerCase().trim();
      const rows = document.querySelectorAll('.order-row');
      rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (!query || text.includes(query)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    function exportDashboardReport() {
      let csv = [];
      let rows = document.querySelectorAll("#ordersTable tr");
      
      rows.forEach(row => {
        let cols = row.querySelectorAll("td, th");
        let data = [];
        for(let i = 0; i < cols.length - 1; i++) {
          data.push('"' + cols[i].innerText.replace(/"/g, '""').trim() + '"');
        }
        csv.push(data.join(","));
      });

      let csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
      let downloadLink = document.createElement("a");
      downloadLink.download = "dashboard_orders_export_" + new Date().toISOString().slice(0,10) + ".csv";
      downloadLink.href = window.URL.createObjectURL(csvFile);
      downloadLink.style.display = "none";
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
    }
  </script>
</body>
</html>