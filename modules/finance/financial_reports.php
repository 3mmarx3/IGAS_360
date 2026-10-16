<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../../includes/auth.php';
require_permission('finance.view');
require_once __DIR__ . '/../../config/db.php';

$active_page = 'financial_reports';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Finance & Accounts', 'Financial Reports'];

$cur_year = date('Y');

// Total revenue from paid invoices
$stmt_rev = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'paid'");
$total_revenue = (float)($stmt_rev ? $stmt_rev->fetchColumn() : 0);

// Total receivables from pending/overdue invoices
$stmt_rec = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status IN ('pending', 'partial', 'overdue')");
$total_receivables = (float)($stmt_rec ? $stmt_rec->fetchColumn() : 0);

// Total operational expenses (vehicle logs + maintenance)
$stmt_fuel = $pdo->query("SELECT COALESCE(SUM(event_cost), 0) FROM vehicle_logs");
$total_fuel = (float)($stmt_fuel ? $stmt_fuel->fetchColumn() : 0);

$stmt_maint = $pdo->query("SELECT COALESCE(SUM(estimated_cost), 0) FROM maintenance_tickets");
$total_maint = (float)($stmt_maint ? $stmt_maint->fetchColumn() : 0);

$total_expenses = $total_fuel + $total_maint;
$net_balance = $total_revenue - $total_expenses;

// Aggregate unified ledger transactions
$transactions = [];

$inv_stmt = $pdo->query("
  SELECT i.invoice_number as ref, i.issue_date as date, p.company_name as party, i.amount, 'Inflow' as direction, 'Invoice Payment' as category
  FROM invoices i
  LEFT JOIN partners p ON i.client_id = p.id
  WHERE i.status = 'paid'
");
if ($inv_stmt) {
  while ($row = $inv_stmt->fetch(PDO::FETCH_ASSOC)) {
    $transactions[] = $row;
  }
}

$maint_stmt = $pdo->query("
  SELECT ticket_id as ref, scheduled_date as date, workshop as party, estimated_cost as amount, 'Outflow' as direction, 'Maintenance Expense' as category
  FROM maintenance_tickets
  WHERE estimated_cost > 0
");
if ($maint_stmt) {
  while ($row = $maint_stmt->fetch(PDO::FETCH_ASSOC)) {
    $transactions[] = $row;
  }
}

$fuel_stmt = $pdo->query("
  SELECT fleet_id as ref, event_date as date, logged_by as party, event_cost as amount, 'Outflow' as direction, 'Fleet Operations / Fuel' as category
  FROM vehicle_logs
  WHERE event_cost > 0
");
if ($fuel_stmt) {
  while ($row = $fuel_stmt->fetch(PDO::FETCH_ASSOC)) {
    $transactions[] = $row;
  }
}

usort($transactions, function($a, $b) {
  return strtotime($b['date']) - strtotime($a['date']);
});
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Financial Reports & Master Ledger | I-GAS Industrial" data-ar="التقارير المالية والسجل الرئيسي | I-GAS Industrial">Financial Reports & Master Ledger | I-GAS Industrial</title>
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
        <span data-en="MASTER FINANCIAL STATEMENT & AUDIT TRAIL" data-ar="القوائم المالية الرئيسية وسجل التدقيق">MASTER FINANCIAL STATEMENT & AUDIT TRAIL</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="REPORTING CURRENCY: SAR" data-ar="عملة القياس: ريال سعودي">REPORTING CURRENCY: SAR</span>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: FIN-AUDIT-02</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6 max-w-7xl mx-auto w-full">

    <!-- Top Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs font-mono uppercase tracking-wider text-neutral-500" data-en="Financial Intelligence" data-ar="الذكاء المالي">Financial Intelligence</span>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400 rtl:rotate-180"></i>
          <span class="text-xs font-mono uppercase tracking-wider text-white font-semibold" data-en="Financial Reports" data-ar="التقارير المالية">Financial Reports</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Master Financial Statement & Ledger" data-ar="القوائم المالية وسجل العمليات الشامل">Master Financial Statement & Ledger</h1>
        <p class="text-xs text-slate-400 mt-1" data-en="Comprehensive audit of all monetary transactions, revenue inflows, and operational fleet expenses." data-ar="تدقيق شامل لجميع المعاملات النقدية، تدفقات الإيرادات، ومصروفات التشغيل والصيانة.">Comprehensive audit of all monetary transactions, revenue inflows, and operational fleet expenses.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <a href="<?= app_url('modules/finance/cash_flow.php') ?>" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
          <i data-lucide="activity" class="w-3.5 h-3.5"></i>
          <span data-en="Cash Flow Statement" data-ar="كشف التدفق النقدي">Cash Flow Statement</span>
        </a>
        <?php if (has_permission('finance.view') || has_permission('reports.export')): ?>
        <button onclick="exportFinancialReport()" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
          <i data-lucide="download" class="w-4 h-4"></i>
          <span data-en="Export Full Report" data-ar="تصدير التقرير الكامل">Export Full Report</span>
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Metric Bento Cards (4-col) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- Revenue -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Total Collected Revenue" data-ar="إجمالي الإيرادات المحصلة">Total Collected Revenue</span>
          <i data-lucide="arrow-down-left" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= number_format($total_revenue, 2) ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="check" class="w-3 h-3"></i>
            <span data-en="Paid Invoices" data-ar="فواتير مدفوعة">Paid Invoices</span>
          </span>
        </div>
      </div>

      <!-- Expenses -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Total Expenses" data-ar="إجمالي المصروفات">Total Expenses</span>
          <i data-lucide="arrow-up-right" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= number_format($total_expenses, 2) ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-rose-800 dark:border-rose-800/60 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="wrench" class="w-3 h-3"></i>
            <span data-en="Maint. & Fuel" data-ar="الصيانة والوقود">Maint. & Fuel</span>
          </span>
        </div>
      </div>

      <!-- Net Balance -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Net Financial Balance" data-ar="الصافي المالي">Net Financial Balance</span>
          <i data-lucide="<?= $net_balance >= 0 ? 'activity' : 'alert-circle' ?>" class="w-4 h-4 <?= $net_balance >= 0 ? 'text-teal-600 dark:text-teal-400' : 'text-amber-600 dark:text-amber-400' ?>"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight <?= $net_balance >= 0 ? 'text-white' : 'text-rose-600 dark:text-rose-400' ?>">
            <?= number_format($net_balance, 2) ?>
          </span>
          <span class="text-xs font-mono text-neutral-500" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-[#1e3a50] bg-[#09151f] text-slate-300 text-[11px] font-mono rounded-xs">
            <i data-lucide="scale" class="w-3 h-3"></i>
            <span data-en="Net Position" data-ar="المركز الصافي">Net Position</span>
          </span>
        </div>
      </div>

      <!-- Receivables -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Pending Receivables" data-ar="المستحقات المعلقة">Pending Receivables</span>
          <i data-lucide="wallet" class="w-4 h-4 text-slate-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= number_format($total_receivables, 2) ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-amber-800 dark:border-amber-800/60 bg-amber-950/30 dark:bg-amber-950/40 text-amber-400 dark:text-amber-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="clock" class="w-3 h-3"></i>
            <span data-en="Unpaid Invoices" data-ar="فواتير غير مسددة">Unpaid Invoices</span>
          </span>
        </div>
      </div>

    </div>

    <!-- Master Financial Ledger Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
      <div class="p-5 border-b border-[#1a3346] flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-[#071018]/60 dark:bg-neutral-850">
        <div class="flex items-center gap-2">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="database" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Master Financial Ledger" data-ar="السجل المالي الرئيسي">Master Financial Ledger</span>
            <span class="ml-1.5 px-2 py-0.5 text-[11px] font-mono border border-[#1e3a50] bg-[#09151f] text-slate-300 rounded-xs">
              <span id="visibleCount"><?= count($transactions) ?></span>
            </span>
          </h2>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search reference or party..." data-en-placeholder="Search reference or party..." data-ar-placeholder="البحث بالمرجع أو الطرف..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
          <select id="directionFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
            <option value="" data-en="All Directions" data-ar="جميع الاتجاهات">All Directions</option>
            <option value="Inflow" data-en="Inflow (Revenue)" data-ar="داخل (إيراد)">Inflow (Revenue)</option>
            <option value="Outflow" data-en="Outflow (Expense)" data-ar="خارج (مصروف)">Outflow (Expense)</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-left rtl:text-right border-collapse" id="financialReportTable">
          <thead>
            <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
              <th class="px-5 py-3 font-semibold" data-en="Reference" data-ar="المرجع">Reference</th>
              <th class="px-5 py-3 font-semibold" data-en="Category" data-ar="الفئة">Category</th>
              <th class="px-5 py-3 font-semibold" data-en="Party / Counterparty" data-ar="الطرف / المورد">Party / Counterparty</th>
              <th class="px-5 py-3 font-semibold" data-en="Date" data-ar="التاريخ">Date</th>
              <th class="px-5 py-3 font-semibold text-center" data-en="Flow Direction" data-ar="نوع التدفق">Flow Direction</th>
              <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Amount (SAR)" data-ar="المبلغ (ريال)">Amount (SAR)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] text-xs">
            <?php if (empty($transactions)): ?>
              <tr>
                <td colspan="6" class="px-5 py-8 text-center text-xs font-mono text-neutral-400" data-en="No financial transactions recorded." data-ar="لا توجد معاملات مالية مسجلة.">No financial transactions recorded.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($transactions as $tx): ?>
                <?php
                  $isInflow = $tx['direction'] === 'Inflow';
                  $amountColor = $isInflow ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';
                  $prefix = $isInflow ? '+' : '-';
                  $pillBorder = $isInflow ? 'border-emerald-200 dark:border-emerald-800/60' : 'border-rose-800 dark:border-rose-800/60';
                  $pillBg = $isInflow ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400' : 'bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400';
                ?>
                <tr class="hover:bg-[#071018] dark:hover:bg-neutral-800/40 transition-colors tx-row" data-direction="<?= htmlspecialchars($tx['direction']) ?>">
                  <td class="px-5 py-3.5 font-mono font-medium text-white ref-text">
                    <?= htmlspecialchars($tx['ref']) ?>
                  </td>
                  <td class="px-5 py-3.5 font-mono text-slate-400 category-text">
                    <?= htmlspecialchars($tx['category']) ?>
                  </td>
                  <td class="px-5 py-3.5 font-medium text-slate-200 party-text">
                    <?= htmlspecialchars($tx['party'] ?? 'N/A') ?>
                  </td>
                  <td class="px-5 py-3.5 font-mono text-neutral-500 whitespace-nowrap">
                    <?= htmlspecialchars(date('Y-m-d', strtotime($tx['date']))) ?>
                  </td>
                  <td class="px-5 py-3.5 text-center">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 border <?= $pillBorder ?> <?= $pillBg ?> text-[11px] font-mono rounded-xs">
                      <?= htmlspecialchars($tx['direction']) ?>
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-5 py-3.5 text-right rtl:text-left font-mono font-bold <?= $amountColor ?> whitespace-nowrap">
                    <?= $prefix . number_format($tx['amount'], 2) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            <tr id="emptyStateRow" style="display: none;">
              <td colspan="6" class="px-5 py-8 text-center text-xs font-mono text-neutral-400" data-en="No transactions found matching your search." data-ar="لم يتم العثور على معاملات تطابق بحثك.">
                No transactions found matching your search.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="px-5 py-3 border-t border-[#1a3346] bg-[#071018]/60 dark:bg-neutral-850 flex items-center justify-between text-xs font-mono text-neutral-500">
        <span data-en="Verified Financial Record Set" data-ar="مجموعة قيود مالية موثقة">Verified Financial Record Set</span>
        <span>SEC: GAAP-ALIGNED</span>
      </div>
    </div>

  </div>
</main>

<script>
  function applyLanguage(lang) {
    document.querySelectorAll('[data-en]').forEach(el => {
      const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      el.innerText = transText;
    });
    document.querySelectorAll('input[data-en-placeholder]').forEach(el => {
      const ph = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
      el.placeholder = ph;
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);

    const searchInput = document.getElementById('searchInput');
    const directionFilter = document.getElementById('directionFilter');

    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (directionFilter) directionFilter.addEventListener('change', filterRows);
  });

  function filterRows() {
    const searchInput = document.getElementById('searchInput');
    const directionFilter = document.getElementById('directionFilter');
    const rows = document.querySelectorAll('.tx-row');

    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const selectedDirection = directionFilter ? directionFilter.value : '';
    let visibleCount = 0;

    rows.forEach(row => {
      const refText = row.querySelector('.ref-text') ? row.querySelector('.ref-text').innerText.toLowerCase() : '';
      const categoryText = row.querySelector('.category-text') ? row.querySelector('.category-text').innerText.toLowerCase() : '';
      const partyText = row.querySelector('.party-text') ? row.querySelector('.party-text').innerText.toLowerCase() : '';
      const rowDirection = row.getAttribute('data-direction');

      const matchesSearch = refText.includes(query) || categoryText.includes(query) || partyText.includes(query);
      const matchesDirection = !selectedDirection || rowDirection === selectedDirection;

      if (matchesSearch && matchesDirection) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    const visibleCountEl = document.getElementById('visibleCount');
    if (visibleCountEl) visibleCountEl.textContent = visibleCount;

    const emptyRow = document.getElementById('emptyStateRow');
    if (emptyRow) {
      emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
    }
  }

  function exportFinancialReport() {
    let csv = [];
    let rows = document.querySelectorAll("#financialReportTable tr");
    
    rows.forEach(row => {
      if (row.style.display === 'none') return;
      let cols = row.querySelectorAll("td, th");
      let data = [];
      cols.forEach(col => data.push('"' + col.innerText.replace(/"/g, '""').trim() + '"'));
      csv.push(data.join(","));
    });

    let csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    let downloadLink = document.createElement("a");
    downloadLink.download = "financial_reports_ledger_" + new Date().toISOString().slice(0,10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
  }
</script>
</body>
</html>