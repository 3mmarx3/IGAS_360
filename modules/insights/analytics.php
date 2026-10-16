<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reports.view');
require_once __DIR__ . '/../../config/db.php';

$active_page = 'analytics';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Insights & Intelligence', 'Analytics & Performance'];

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

$stmt_prod_cur = $pdo->prepare("SELECT COALESCE(SUM(actual_output), 0) FROM shift_logs WHERE operational_date BETWEEN ? AND ?");
$stmt_prod_cur->execute([$cur_start, $cur_end]);
$production_vol = (float)$stmt_prod_cur->fetchColumn();

$stmt_prod_prev = $pdo->prepare("SELECT COALESCE(SUM(actual_output), 0) FROM shift_logs WHERE operational_date BETWEEN ? AND ?");
$stmt_prod_prev->execute([$prev_start, $prev_end]);
$production_last = (float)$stmt_prod_prev->fetchColumn();

$production_growth = 0;
if ($production_last > 0) {
  $production_growth = round((($production_vol - $production_last) / $production_last) * 100, 1);
} elseif ($production_vol > 0) {
  $production_growth = 100;
}

$stmt_yield = $pdo->prepare("SELECT AVG((actual_output / target_output) * 100) FROM shift_logs WHERE operational_date BETWEEN ? AND ? AND target_output > 0");
$stmt_yield->execute([$cur_start, $cur_end]);
$avg_yield = round((float)$stmt_yield->fetchColumn(), 1);

$stmt_fleet_tot = $pdo->query("SELECT COUNT(*) FROM vehicles");
$tot_vehicles = (int)($stmt_fleet_tot ? $stmt_fleet_tot->fetchColumn() : 0);
$stmt_fleet_act = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status != 'maintenance'");
$act_vehicles = (int)($stmt_fleet_act ? $stmt_fleet_act->fetchColumn() : 0);
$fleet_utilization = $tot_vehicles > 0 ? round(($act_vehicles / $tot_vehicles) * 100, 1) : 0;

$metrics = [
  'revenue_mtd' => $revenue_mtd,
  'revenue_growth' => $revenue_growth,
  'production_vol' => $production_vol,
  'production_growth' => $production_growth,
  'avg_yield' => $avg_yield,
  'fleet_utilization' => $fleet_utilization,
];

$stmt_top_prod = $pdo->prepare("
  SELECT poi.item_name, SUM(poi.line_total) as revenue 
  FROM purchase_order_items poi 
  JOIN purchase_orders po ON poi.purchase_order_id = po.id 
  WHERE po.order_date BETWEEN ? AND ? AND po.status != 'cancelled'
  GROUP BY poi.item_name 
  ORDER BY revenue DESC 
  LIMIT 5
");
$stmt_top_prod->execute([$cur_start, $cur_end]);
$top_current = $stmt_top_prod ? $stmt_top_prod->fetchAll(PDO::FETCH_ASSOC) : [];

$top_products = [];
$rank = 1;

$stmt_prev_prod = $pdo->prepare("
  SELECT COALESCE(SUM(poi.line_total), 0) 
  FROM purchase_order_items poi 
  JOIN purchase_orders po ON poi.purchase_order_id = po.id 
  WHERE poi.item_name = ? AND po.order_date BETWEEN ? AND ? AND po.status != 'cancelled'
");

foreach ($top_current as $tp) {
  $stmt_prev_prod->execute([$tp['item_name'], $prev_start, $prev_end]);
  $prev_rev = (float)$stmt_prev_prod->fetchColumn();
  $cur_rev = (float)$tp['revenue'];
  
  $growth = 0;
  if ($prev_rev > 0) {
    $growth = round((($cur_rev - $prev_rev) / $prev_rev) * 100, 1);
  } elseif ($cur_rev > 0) {
    $growth = 100;
  }
  
  $top_products[] = [
    'rank' => $rank++,
    'name' => $tp['item_name'],
    'revenue' => $cur_rev,
    'growth' => $growth,
    'trend' => $growth >= 0 ? 'up' : 'down'
  ];
}

$region_totals = [
  'Eastern Province (Jubail/Dammam)' => 0,
  'Western Province (Jeddah/Yanbu)' => 0,
  'Central Region (Riyadh)' => 0,
  'Northern/Southern Borders' => 0,
];
$total_regional_revenue = 0;

$stmt_regions = $pdo->prepare("
  SELECT p.city, SUM(i.amount) as total 
  FROM invoices i 
  JOIN partners p ON i.client_id = p.id 
  WHERE i.issue_date BETWEEN ? AND ? AND i.status IN ('paid', 'pending', 'partial')
  GROUP BY p.city
");
$stmt_regions->execute([$cur_start, $cur_end]);
$city_sales = $stmt_regions ? $stmt_regions->fetchAll(PDO::FETCH_ASSOC) : [];

foreach ($city_sales as $row) {
  $city = strtolower(trim($row['city'] ?? ''));
  $amt = (float)$row['total'];
  $total_regional_revenue += $amt;
  
  if (in_array($city, ['dammam', 'jubail', 'khobar', 'dhahran', 'al khobar'])) {
    $region_totals['Eastern Province (Jubail/Dammam)'] += $amt;
  } elseif (in_array($city, ['jeddah', 'yanbu', 'makkah', 'medina'])) {
    $region_totals['Western Province (Jeddah/Yanbu)'] += $amt;
  } elseif (in_array($city, ['riyadh', 'qassim', 'al qassim'])) {
    $region_totals['Central Region (Riyadh)'] += $amt;
  } else {
    $region_totals['Northern/Southern Borders'] += $amt;
  }
}

$regional_sales = [];
foreach ($region_totals as $region => $val) {
  $pct = $total_regional_revenue > 0 ? round(($val / $total_regional_revenue) * 100, 1) : 0;
  $regional_sales[] = [
    'region' => $region,
    'pct' => $pct,
    'value' => $val
  ];
}

usort($regional_sales, function($a, $b) {
  return $b['value'] <=> $a['value'];
});
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Analytics & Plant Performance | I-GAS Industrial" data-ar="التحليلات والأداء التشغيلي | I-GAS Industrial">Analytics & Plant Performance | I-GAS Industrial</title>
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
        <span data-en="MACRO TELEMETRY & STRATEGIC KPI INTELLIGENCE" data-ar="القياسات الكلية وذكاء مؤشرات الأداء">MACRO TELEMETRY & STRATEGIC KPI INTELLIGENCE</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="CYCLE: MTD CONSOLIDATED" data-ar="الدورة: مجمعة حتى اليوم">CYCLE: MTD CONSOLIDATED</span>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: EXEC-KPI-01</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6 max-w-7xl mx-auto w-full">

    <!-- Top Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs font-mono uppercase tracking-wider text-neutral-500" data-en="Executive Insights" data-ar="رؤى الإدارة التنفيذية">Executive Insights</span>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400 rtl:rotate-180"></i>
          <span class="text-xs font-mono uppercase tracking-wider text-white font-semibold" data-en="Analytics & Performance" data-ar="التحليلات والأداء">Analytics & Performance</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Plant-Wide Analytics & Performance" data-ar="التحليلات الشاملة ومؤشرات أداء المصنع">Plant-Wide Analytics & Performance</h1>
        <p class="text-xs text-slate-400 mt-1" data-en="Macro-level data intelligence covering commercial revenue streams, cryogenic yield, and fleet operational efficiency." data-ar="ذكاء بيانات استراتيجي يغطي تدفقات الإيرادات التجارية، كفاءة الإنتاج المبرّد، واستخدام الأسطول.">Macro-level data intelligence covering commercial revenue streams, cryogenic yield, and fleet operational efficiency.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#1e3a50] bg-[#0c1924] bg-[#112433] text-slate-300 text-xs font-mono rounded-xs">
          <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
          <span data-en="Current Month (MTD)" data-ar="الشهر الحالي (MTD)">Current Month (MTD)</span>
        </div>
        <?php if (has_permission('reports.export')): ?>
        <button id="exportDashboardBtn" class="btn-cta-orange px-4 py-2 text-xs font-mono uppercase tracking-wider font-semibold transition-colors flex items-center gap-2">
          <i data-lucide="download" class="w-4 h-4"></i>
          <span data-en="Export Dashboard" data-ar="تصدير لوحة القيادة">Export Dashboard</span>
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Metric Bento Cards (4-col) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- Gross Revenue -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Gross Invoiced Revenue" data-ar="إجمالي الإيرادات المفوترة">Gross Invoiced Revenue</span>
          <i data-lucide="banknote" class="w-4 h-4 text-slate-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= number_format($metrics['revenue_mtd']) ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <?php if ($metrics['revenue_growth'] >= 0): ?>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="trending-up" class="w-3 h-3"></i>+<?= $metrics['revenue_growth'] ?>%
          </span>
          <?php else: ?>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-rose-800 dark:border-rose-800/60 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="trending-down" class="w-3 h-3"></i><?= $metrics['revenue_growth'] ?>%
          </span>
          <?php endif; ?>
          <span class="text-[11px] font-mono text-neutral-400" data-en="vs last month" data-ar="مقارنة بالشهر السابق">vs last month</span>
        </div>
      </div>

      <!-- Production Volume -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Production Output" data-ar="حجم الإنتاج الفعلي">Production Output</span>
          <i data-lucide="boxes" class="w-4 h-4 text-slate-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= number_format($metrics['production_vol']) ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Units" data-ar="وحدة">Units</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <?php if ($metrics['production_growth'] >= 0): ?>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="trending-up" class="w-3 h-3"></i>+<?= $metrics['production_growth'] ?>%
          </span>
          <?php else: ?>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-rose-800 dark:border-rose-800/60 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="trending-down" class="w-3 h-3"></i><?= $metrics['production_growth'] ?>%
          </span>
          <?php endif; ?>
          <span class="text-[11px] font-mono text-neutral-400" data-en="vs last month" data-ar="مقارنة بالشهر السابق">vs last month</span>
        </div>
      </div>

      <!-- Plant Yield Efficiency -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Plant Yield Efficiency" data-ar="كفاءة إنتاج المصنع">Plant Yield Efficiency</span>
          <i data-lucide="gauge" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $metrics['avg_yield'] ?></span>
          <span class="text-xs font-mono text-neutral-500">%</span>
        </div>
        <div class="mt-3">
          <div class="w-full bg-[#09151f] h-1.5 rounded-full overflow-hidden border border-[#1e3a50]">
            <div class="bg-teal-500 h-full rounded-full transition-all duration-300" style="width: <?= min(100, max(0, $metrics['avg_yield'])) ?>%;"></div>
          </div>
        </div>
      </div>

      <!-- Fleet Utilization -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Active Fleet Ratio" data-ar="معدل جاهزية الأسطول">Active Fleet Ratio</span>
          <i data-lucide="truck" class="w-4 h-4 text-slate-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $metrics['fleet_utilization'] ?></span>
          <span class="text-xs font-mono text-neutral-500">%</span>
        </div>
        <div class="mt-3">
          <div class="w-full bg-[#09151f] h-1.5 rounded-full overflow-hidden border border-[#1e3a50]">
            <div class="bg-blue-600 dark:bg-blue-500 h-full rounded-full transition-all duration-300" style="width: <?= min(100, max(0, $metrics['fleet_utilization'])) ?>%;"></div>
          </div>
        </div>
      </div>

    </div>

    <!-- 2-Column Intelligence Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

      <!-- Top Products (2 cols) -->
      <div class="xl:col-span-2 bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-[#1a3346] flex items-center justify-between bg-[#09151f]">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
              <i data-lucide="flame" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Top Performing Products & Gases" data-ar="المنتجات والغازات الأعلى أداءً">Top Performing Products & Gases</span>
            </h2>
          </div>
          <a href="<?= app_url('modules/production/gases_mixtures.php') ?>" class="text-xs font-mono text-neutral-500 hover:text-white transition-colors flex items-center gap-1">
            <span data-en="Product Catalog" data-ar="كتالوج المنتجات">Product Catalog</span>
            <i data-lucide="arrow-right" class="w-3 h-3 rtl:rotate-180"></i>
          </a>
        </div>

        <div class="overflow-x-auto flex-1">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-5 py-3 font-semibold" data-en="Rank" data-ar="المرتبة">Rank</th>
                <th class="px-5 py-3 font-semibold" data-en="Product / Gas SKU" data-ar="المنتج / رمز الغاز">Product / Gas SKU</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Revenue (SAR)" data-ar="الإيراد (ريال)">Revenue (SAR)</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="MoM Growth" data-ar="النمو الشهري">MoM Growth</th>
                <th class="px-5 py-3 font-semibold text-center" data-en="Trend" data-ar="الاتجاه">Trend</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a] text-xs">
              <?php if (empty($top_products)): ?>
                <tr>
                  <td colspan="5" class="px-5 py-8 text-center text-xs font-mono text-neutral-400" data-en="No sales data available for this period." data-ar="لا توجد بيانات مبيعات متاحة لهذه الفترة.">No sales data available for this period.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($top_products as $p): ?>
                  <tr class="hover:bg-[#102332] transition-colors">
                    <td class="px-5 py-3.5 font-mono text-neutral-500">
                      #<?= $p['rank'] ?>
                    </td>
                    <td class="px-5 py-3.5 font-medium text-white">
                      <?= htmlspecialchars($p['name']) ?>
                    </td>
                    <td class="px-5 py-3.5 text-right rtl:text-left font-mono font-bold text-white whitespace-nowrap">
                      <?= number_format($p['revenue']) ?> SAR
                    </td>
                    <td class="px-5 py-3.5 text-right rtl:text-left font-mono font-medium whitespace-nowrap <?= $p['trend'] === 'up' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' ?>">
                      <?= $p['trend'] === 'up' ? '+' : '' ?><?= $p['growth'] ?>%
                    </td>
                    <td class="px-5 py-3.5 text-center">
                      <?php if ($p['trend'] === 'up'): ?>
                        <span class="inline-flex items-center text-emerald-600 dark:text-emerald-400">
                          <i data-lucide="trending-up" class="w-4 h-4"></i>
                        </span>
                      <?php else: ?>
                        <span class="inline-flex items-center text-rose-600 dark:text-rose-400">
                          <i data-lucide="trending-down" class="w-4 h-4"></i>
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

      <!-- Regional Distribution (1 col) -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-[#1a3346] flex items-center justify-between bg-[#09151f]">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
              <i data-lucide="map-pin" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Regional Distribution" data-ar="التوزيع الإقليمي">Regional Distribution</span>
            </h2>
          </div>
        </div>

        <div class="p-5 flex-1 flex flex-col justify-center space-y-5">
          <?php if (empty($regional_sales) || $total_regional_revenue == 0): ?>
            <p class="text-center text-xs font-mono text-neutral-400 py-6" data-en="No regional revenue data available." data-ar="لا توجد بيانات إقليمية متاحة.">No regional revenue data available.</p>
          <?php else: ?>
            <?php foreach ($regional_sales as $rs): ?>
              <div>
                <div class="flex justify-between items-end mb-1.5">
                  <div>
                    <p class="text-xs font-medium text-white"><?= htmlspecialchars($rs['region']) ?></p>
                    <p class="text-[11px] font-mono text-neutral-500 mt-0.5">SAR <?= number_format($rs['value']) ?></p>
                  </div>
                  <span class="text-xs font-mono font-bold text-white"><?= $rs['pct'] ?>%</span>
                </div>
                <div class="w-full bg-[#09151f] h-1.5 rounded-full overflow-hidden border border-[#1e3a50]">
                  <div class="bg-cyan-500 h-full rounded-full transition-all duration-300" style="width: <?= $rs['pct'] ?>%;"></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="px-5 py-3 border-t border-[#1a3346] bg-[#09151f] text-xs font-mono text-neutral-500 flex justify-between">
          <span data-en="TOTAL REGIONAL:" data-ar="الإجمالي الإقليمي:">TOTAL REGIONAL:</span>
          <span class="font-bold text-white">SAR <?= number_format($total_regional_revenue) ?></span>
        </div>
      </div>

    </div>

  </div>
</main>

<script>
  function applyLanguage(lang) {
    document.querySelectorAll('[data-en]').forEach(el => {
      const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.hasAttribute('placeholder')) {
        el.placeholder = transText;
      } else if (el.tagName === 'OPTION') {
        el.innerText = transText;
      } else {
        el.innerHTML = transText;
      }
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", function() {
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);

    const exportBtn = document.getElementById('exportDashboardBtn');
    if (exportBtn) {
      exportBtn.addEventListener('click', function() {
        let csvContent = "\uFEFF"; 

        csvContent += "Metric,Value\r\n";
        csvContent += '"Gross Revenue (MTD)","<?= number_format($metrics['revenue_mtd']) ?> SAR"\r\n';
        csvContent += '"Production Volume","<?= number_format($metrics['production_vol']) ?> Units"\r\n';
        csvContent += '"Plant Yield Efficiency","<?= $metrics['avg_yield'] ?>%"\r\n';
        csvContent += '"Fleet Utilization","<?= $metrics['fleet_utilization'] ?>%"\r\n';
        csvContent += "\r\n"; 

        csvContent += "Top Performing Products\r\n";
        csvContent += "Rank,Product,Revenue (SAR),MoM Growth (%)\r\n";
        <?php foreach ($top_products as $p): ?>
        csvContent += '"#<?= $p['rank'] ?>","<?= str_replace('"', '""', $p['name']) ?>","<?= number_format($p['revenue']) ?>","<?= $p['trend'] === 'up' ? '+' : '' ?><?= $p['growth'] ?>%"\r\n';
        <?php endforeach; ?>
        csvContent += "\r\n"; 

        csvContent += "Revenue Distribution by Region\r\n";
        csvContent += "Region,Revenue (SAR),Percentage\r\n";
        <?php foreach ($regional_sales as $rs): ?>
        csvContent += '"<?= str_replace('"', '""', $rs['region']) ?>","<?= number_format($rs['value']) ?>","<?= $rs['pct'] ?>%"\r\n';
        <?php endforeach; ?>

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", "Analytics_Dashboard_Export.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
    }
  });
</script>
</body>
</html>