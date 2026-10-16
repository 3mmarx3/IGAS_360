<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'reports.view');

$active_page = 'sales_reports';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Sales Reports & Analytics'];

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
  require_permission($pdo, 'reports.export');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="Sales_Report_' . $year . '_' . sprintf("%02d", $month) . '.csv"');
  
  $output = fopen('php://output', 'w');
  fputs($output, "\xEF\xBB\xBF"); 
  
  fputcsv($output, ['Order Number', 'Order Date', 'Client Name', 'Client Email', 'Client Phone', 'Sales Representative', 'Total Value (SAR)', 'Status']);

  $stmt_export = $pdo->prepare("
    SELECT 
      po.order_number, 
      po.order_date, 
      p.company_name, 
      p.email,
      p.phone,
      u.full_name as rep_name,
      po.total_value, 
      po.status
    FROM purchase_orders po
    LEFT JOIN partners p ON po.client_id = p.id
    LEFT JOIN users u ON po.sales_rep_id = u.id
    WHERE MONTH(po.order_date) = ? AND YEAR(po.order_date) = ?
    ORDER BY po.order_date DESC, po.id DESC
  ");
  $stmt_export->execute([$month, $year]);
  
  while ($row = $stmt_export->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
      $row['order_number'],
      $row['order_date'],
      $row['company_name'] ?? 'Unknown',
      $row['email'] ?? 'N/A',
      $row['phone'] ?? 'N/A',
      $row['rep_name'] ?? 'Unassigned',
      number_format($row['total_value'], 2, '.', ''),
      strtoupper($row['status'])
    ]);
  }
  fclose($output);
  exit;
}

$stmt_kpi = $pdo->prepare("
  SELECT 
    COALESCE(SUM(total_value), 0) AS kpi_sales,
    COUNT(id) AS kpi_orders,
    COUNT(DISTINCT client_id) AS kpi_clients
  FROM purchase_orders 
  WHERE status NOT IN ('cancelled', 'draft') 
   AND MONTH(order_date) = ? 
   AND YEAR(order_date) = ?
");
$stmt_kpi->execute([$month, $year]);
$kpi = $stmt_kpi->fetch(PDO::FETCH_ASSOC);

$kpi_sales  = (float)($kpi['kpi_sales'] ?? 0);
$kpi_orders = (int)($kpi['kpi_orders'] ?? 0);
$kpi_clients = (int)($kpi['kpi_clients'] ?? 0);
$kpi_avg   = $kpi_orders > 0 ? ($kpi_sales / $kpi_orders) : 0;

$stmt_reps = $pdo->prepare("
  SELECT 
    u.full_name, 
    COUNT(po.id) as order_count, 
    COALESCE(SUM(po.total_value), 0) as total_sales
  FROM purchase_orders po
  JOIN users u ON po.sales_rep_id = u.id
  WHERE po.status NOT IN ('cancelled', 'draft') AND MONTH(po.order_date) = ? AND YEAR(po.order_date) = ?
  GROUP BY u.id
  ORDER BY total_sales DESC
  LIMIT 5
");
$stmt_reps->execute([$month, $year]);
$top_reps = $stmt_reps->fetchAll(PDO::FETCH_ASSOC);
$max_rep_sales = !empty($top_reps) ? max(array_column($top_reps, 'total_sales')) : 1;

$stmt_clients = $pdo->prepare("
  SELECT 
    p.company_name, 
    p.email,
    p.phone,
    COUNT(po.id) as order_count, 
    COALESCE(SUM(po.total_value), 0) as total_sales
  FROM purchase_orders po
  JOIN partners p ON po.client_id = p.id
  WHERE po.status NOT IN ('cancelled', 'draft') AND MONTH(po.order_date) = ? AND YEAR(po.order_date) = ?
  GROUP BY p.id
  ORDER BY total_sales DESC
  LIMIT 5
");
$stmt_clients->execute([$month, $year]);
$top_clients = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);
$max_client_sales = !empty($top_clients) ? max(array_column($top_clients, 'total_sales')) : 1;

$stmt_recent = $pdo->prepare("
  SELECT 
    po.order_number, 
    po.order_date, 
    p.company_name, 
    p.email,
    p.phone,
    u.full_name as rep_name,
    po.total_value, 
    po.status
  FROM purchase_orders po
  LEFT JOIN partners p ON po.client_id = p.id
  LEFT JOIN users u ON po.sales_rep_id = u.id
  WHERE MONTH(po.order_date) = ? AND YEAR(po.order_date) = ?
  ORDER BY po.order_date DESC, po.id DESC
  LIMIT 100
");
$stmt_recent->execute([$month, $year]);
$recent_orders = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

$statusConfigs = [
  'draft'   => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-neutral-500', 'dot' => 'bg-neutral-400', 'label' => 'Draft', 'label_ar' => 'مسودة'],
  'processing' => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Processing', 'label_ar' => 'قيد المعالجة'],
  'in_transit' => ['border' => 'border-blue-500/30', 'bg' => 'bg-blue-500/10', 'text' => 'text-blue-700 dark:text-blue-400', 'dot' => 'bg-blue-500', 'label' => 'In Transit', 'label_ar' => 'قيد الشحن'],
  'delivered' => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'dot' => 'bg-teal-500', 'label' => 'Delivered', 'label_ar' => 'تم التوصيل'],
  'cancelled' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Cancelled', 'label_ar' => 'ملغي'],
];

$months_list = [
  1 => ['en' => 'January', 'ar' => 'يناير'], 
  2 => ['en' => 'February', 'ar' => 'فبراير'], 
  3 => ['en' => 'March', 'ar' => 'مارس'], 
  4 => ['en' => 'April', 'ar' => 'أبريل'], 
  5 => ['en' => 'May', 'ar' => 'مايو'], 
  6 => ['en' => 'June', 'ar' => 'يونيو'],
  7 => ['en' => 'July', 'ar' => 'يوليو'], 
  8 => ['en' => 'August', 'ar' => 'أغسطس'], 
  9 => ['en' => 'September', 'ar' => 'سبتمبر'], 
  10 => ['en' => 'October', 'ar' => 'أكتوبر'], 
  11 => ['en' => 'November', 'ar' => 'نوفمبر'], 
  12 => ['en' => 'December', 'ar' => 'ديسمبر']
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Reports & Analytics | I-GAS Enterprise</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  
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
        <span data-en="COMMERCIAL REVENUE INTELLIGENCE" data-ar="استخبارات الإيرادات التجارية">COMMERCIAL REVENUE INTELLIGENCE</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">PERIOD: <?= $months_list[$month]['en'] ?> <?= $year ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Page Title & Period Controls -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <span class="text-xs font-mono uppercase tracking-wider text-teal-600 dark:text-teal-400 font-semibold block mb-1" data-en="PERIODIC COMMERCIAL AUDIT" data-ar="التدقيق التجاري الدوري">PERIODIC COMMERCIAL AUDIT</span>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Sales Reports & Analytics" data-ar="تقارير المبيعات والتحليلات">
            Sales Reports & Analytics
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Analyze plant sales throughput, client distribution, and sales force realization." data-ar="تحليل إنتاجية مبيعات المصنع وتوزيع العملاء وتحقيق فريق المبيعات.">
            Analyze plant sales throughput, client distribution, and sales force realization.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <form method="GET" class="flex items-center gap-2 m-0 p-0">
            <select name="month" class="px-3 py-1.5 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" onchange="this.form.submit()">
              <?php foreach($months_list as $m_num => $m_data): ?>
                <option value="<?= $m_num ?>" <?= $month === $m_num ? 'selected' : '' ?> data-en="<?= $m_data['en'] ?>" data-ar="<?= $m_data['ar'] ?>"><?= $m_data['en'] ?></option>
              <?php endforeach; ?>
            </select>
            <select name="year" class="px-3 py-1.5 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" onchange="this.form.submit()">
              <?php for($y = date('Y')-2; $y <= date('Y'); $y++): ?>
                <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </form>
          <a href="?month=<?= $month ?>&year=<?= $year ?>&export=excel" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm">
            <i data-lucide="download" class="w-3.5 h-3.5"></i>
            <span data-en="Export Report" data-ar="تصدير التقرير">Export Report</span>
          </a>
        </div>
      </div>

      <!-- Bento KPI Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 block mb-2" data-en="MONTHLY REVENUE" data-ar="الإيراد الشهري">MONTHLY REVENUE</span>
          <div class="text-2xl font-bold font-mono text-white tabular-nums">
            <?= number_format($kpi_sales, 2) ?> <span class="text-xs font-normal text-neutral-500">SAR</span>
          </div>
          <span class="text-[11px] font-mono text-neutral-400 block mt-2" data-en="Gross invoiced order value" data-ar="إجمالي قيمة الطلبات المفوترة">Gross invoiced order value</span>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 block mb-2" data-en="PROCESSED ORDERS" data-ar="الطلبات المنفذة">PROCESSED ORDERS</span>
          <div class="text-2xl font-bold font-mono text-blue-700 dark:text-blue-400 tabular-nums">
            <?= number_format($kpi_orders) ?>
          </div>
          <span class="text-[11px] font-mono text-neutral-400 block mt-2" data-en="Fulfilled in selected period" data-ar="تم تنفيذها خلال الفترة">Fulfilled in selected period</span>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 block mb-2" data-en="ACTIVE PURCHASING CLIENTS" data-ar="العملاء المشترون">ACTIVE PURCHASING CLIENTS</span>
          <div class="text-2xl font-bold font-mono text-amber-400 dark:text-amber-400 tabular-nums">
            <?= number_format($kpi_clients) ?>
          </div>
          <span class="text-[11px] font-mono text-neutral-400 block mt-2" data-en="Distinct client accounts buying" data-ar="حسابات عملاء فريدة للشراء">Distinct client accounts buying</span>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 block mb-2" data-en="AVG ORDER VALUE" data-ar="متوسط قيمة الطلب">AVG ORDER VALUE</span>
          <div class="text-2xl font-bold font-mono text-teal-400 dark:text-teal-400 tabular-nums">
            <?= number_format($kpi_avg, 2) ?> <span class="text-xs font-normal text-neutral-500">SAR</span>
          </div>
          <span class="text-[11px] font-mono text-neutral-400 block mt-2" data-en="Mean revenue per transaction" data-ar="متوسط القيمة لكل معاملة">Mean revenue per transaction</span>
        </div>
      </div>

      <!-- Top Performers 2-Column Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Top Sales Representatives -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
          <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <i data-lucide="award" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="TOP SALES REPRESENTATIVES" data-ar="أفضل مندوبي المبيعات">TOP SALES REPRESENTATIVES</h3>
            </div>
          </div>

          <?php if(empty($top_reps)): ?>
            <div class="text-center py-8 text-xs font-mono text-neutral-400" data-en="No sales data recorded for this period." data-ar="لا توجد بيانات مبيعات لهذه الفترة.">
              No sales data recorded for this period.
            </div>
          <?php else: ?>
            <div class="space-y-4">
              <?php foreach($top_reps as $rep): ?>
                <?php $pct = ($rep['total_sales'] / $max_rep_sales) * 100; ?>
                <div class="font-mono text-xs">
                  <div class="flex justify-between items-center mb-1">
                    <span class="font-sans font-bold text-white"><?= htmlspecialchars($rep['full_name']) ?></span>
                    <span class="font-bold tabular-nums text-teal-400 dark:text-teal-400"><?= number_format($rep['total_sales'], 2) ?> SAR</span>
                  </div>
                  <div class="flex justify-between items-center text-[10px] text-neutral-400 mb-1.5">
                    <span><?= (int)$rep['order_count'] ?> <span data-en="orders executed" data-ar="أمر منفذ">orders executed</span></span>
                    <span><?= round($pct, 1) ?>%</span>
                  </div>
                  <div class="w-full bg-[#09151f] h-1.5 rounded-full overflow-hidden">
                    <div class="bg-teal-600 h-full rounded-full" style="width: <?= min(100, $pct) ?>%;"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Top Performing Clients -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
          <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <i data-lucide="building" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
              <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="TOP PURCHASING CLIENTS" data-ar="أكبر العملاء شراءً">TOP PURCHASING CLIENTS</h3>
            </div>
          </div>

          <?php if(empty($top_clients)): ?>
            <div class="text-center py-8 text-xs font-mono text-neutral-400" data-en="No client sales recorded for this period." data-ar="لا توجد مبيعات عملاء مسجلة لهذه الفترة.">
              No client sales recorded for this period.
            </div>
          <?php else: ?>
            <div class="space-y-4">
              <?php foreach($top_clients as $client): ?>
                <?php $pct = ($client['total_sales'] / $max_client_sales) * 100; ?>
                <div class="font-mono text-xs">
                  <div class="flex justify-between items-center mb-1">
                    <span class="font-sans font-bold text-white"><?= htmlspecialchars($client['company_name']) ?></span>
                    <span class="font-bold tabular-nums text-white"><?= number_format($client['total_sales'], 2) ?> SAR</span>
                  </div>
                  <div class="flex justify-between items-center text-[10px] text-neutral-400 mb-1.5">
                    <span><?= (int)$client['order_count'] ?> <span data-en="orders" data-ar="أمر">orders</span> · <?= htmlspecialchars($client['phone'] ?? 'N/A') ?></span>
                    <span><?= round($pct, 1) ?>%</span>
                  </div>
                  <div class="w-full bg-[#09151f] h-1.5 rounded-full overflow-hidden">
                    <div class="bg-blue-600 h-full rounded-full" style="width: <?= min(100, $pct) ?>%;"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- Recent Orders Transactions Table -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#1a3346] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i data-lucide="list-ordered" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="TRANSACTION LOG AUDIT" data-ar="سجل تدقيق المعاملات">TRANSACTION LOG AUDIT</h3>
          </div>
          <span class="text-xs font-mono text-neutral-500"><?= count($recent_orders) ?> <span data-en="TRANSACTIONS" data-ar="معاملة">TRANSACTIONS</span></span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-6 py-3.5 font-semibold" data-en="Order Number" data-ar="رقم الطلب">Order Number</th>
                <th class="px-6 py-3.5 font-semibold" data-en="Date" data-ar="التاريخ">Date</th>
                <th class="px-6 py-3.5 font-semibold" data-en="Client Account" data-ar="حساب العميل">Client Account</th>
                <th class="px-6 py-3.5 font-semibold" data-en="Sales Representative" data-ar="مندوب المبيعات">Sales Representative</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left" data-en="Value (SAR)" data-ar="القيمة (ريال)">Value (SAR)</th>
                <th class="px-6 py-3.5 font-semibold text-center" data-en="Status" data-ar="الحالة">Status</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-[#152a3a]">
              <?php if(empty($recent_orders)): ?>
              <tr>
                <td colspan="6" class="px-6 py-12 text-center text-xs font-mono text-neutral-400" data-en="No recent transactions found for this period." data-ar="لم يتم العثور على معاملات لهذه الفترة.">
                  No recent transactions found for this period.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($recent_orders as $o): ?>
                <?php $ss = $statusConfigs[$o['status']] ?? $statusConfigs['draft']; ?>
                <tr class="hover:bg-[#102332]/50 dark:hover:bg-neutral-800/40 transition-colors">
                  <td class="px-6 py-4 font-mono font-bold">
                    <a href="<?= app_url('modules/crm_sales/order_details.php?order_num=' . urlencode($o['order_number'])) ?>" class="text-teal-600 dark:text-teal-400 hover:underline">
                      <?= htmlspecialchars($o['order_number']) ?>
                    </a>
                  </td>
                  <td class="px-6 py-4 font-mono tabular-nums text-neutral-500 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($o['order_date']))) ?></td>
                  <td class="px-6 py-4 font-sans font-medium text-white whitespace-nowrap">
                    <?= htmlspecialchars($o['company_name'] ?? 'Unknown Client') ?>
                  </td>
                  <td class="px-6 py-4 font-sans text-slate-400 whitespace-nowrap">
                    <?= htmlspecialchars($o['rep_name'] ?? 'Unassigned') ?>
                  </td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-white tabular-nums whitespace-nowrap">
                    <?= number_format($o['total_value'], 2) ?>
                  </td>
                  <td class="px-6 py-4 text-center whitespace-nowrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-sm text-xs font-mono uppercase tracking-wider border whitespace-nowrap <?= $ss['border'] ?> <?= $ss['bg'] ?> <?= $ss['text'] ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?= $ss['dot'] ?>"></span>
                      <span data-en="<?= $ss['label'] ?>" data-ar="<?= $ss['label_ar'] ?>"><?= $ss['label'] ?></span>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();

    function applyLanguage(lang) {
      document.querySelectorAll('[data-en]').forEach(el => {
        const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
        el.innerText = transText;
      });
    }

    document.addEventListener("DOMContentLoaded", function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      applyLanguage(savedLang);
    });
  </script>
</body>
</html>