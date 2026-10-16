<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../../includes/auth.php';
require_permission('finance.view');
require_once __DIR__ . '/../../config/db.php';

$active_page = 'cash_flow';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Finance & Accounts', 'Cash Flow & Liquidity'];

$cur_month = date('m');
$cur_year = date('Y');

$stmt_rev = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'paid' AND MONTH(issue_date) = ? AND YEAR(issue_date) = ?");
$stmt_rev->execute([$cur_month, $cur_year]);
$revenue_mtd = (float)$stmt_rev->fetchColumn();

$stmt_rec = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status IN ('pending', 'partial', 'overdue')");
$receivables = (float)($stmt_rec ? $stmt_rec->fetchColumn() : 0);

$stmt_fuel = $pdo->prepare("SELECT COALESCE(SUM(event_cost), 0) FROM vehicle_logs WHERE MONTH(event_date) = ? AND YEAR(event_date) = ?");
$stmt_fuel->execute([$cur_month, $cur_year]);
$fuel_cost = (float)$stmt_fuel->fetchColumn();

$stmt_maint = $pdo->prepare("SELECT COALESCE(SUM(estimated_cost), 0) FROM maintenance_tickets WHERE MONTH(scheduled_date) = ? AND YEAR(scheduled_date) = ?");
$stmt_maint->execute([$cur_month, $cur_year]);
$maint_cost = (float)$stmt_maint->fetchColumn();

$expenses_mtd = $fuel_cost + $maint_cost;
$net_cashflow = $revenue_mtd - $expenses_mtd;

$stmt_inflow = $pdo->query("
  SELECT i.id as invoice_id, i.invoice_number as ref, i.client_id, i.amount, i.issue_date as date, p.company_name as entity, 'Invoice Payment' as type
  FROM invoices i
  LEFT JOIN partners p ON i.client_id = p.id
  WHERE i.status = 'paid'
  ORDER BY i.issue_date DESC LIMIT 8
");
$inflows = $stmt_inflow ? $stmt_inflow->fetchAll(PDO::FETCH_ASSOC) : [];

$stmt_outflow_m = $pdo->query("
  SELECT id as out_id, ticket_id as ref, estimated_cost as amount, scheduled_date as date, workshop as entity, 'Maintenance' as type, 'maintenance' as source
  FROM maintenance_tickets
  WHERE estimated_cost > 0
  ORDER BY scheduled_date DESC LIMIT 4
");
$outflows_m = $stmt_outflow_m ? $stmt_outflow_m->fetchAll(PDO::FETCH_ASSOC) : [];

$stmt_outflow_f = $pdo->query("
  SELECT id as out_id, fleet_id as ref, event_cost as amount, event_date as date, logged_by as entity, 'Fuel / Operations' as type, 'fuel' as source
  FROM vehicle_logs
  WHERE event_cost > 0
  ORDER BY event_date DESC LIMIT 4
");
$outflows_f = $stmt_outflow_f ? $stmt_outflow_f->fetchAll(PDO::FETCH_ASSOC) : [];

$outflows = array_merge($outflows_m, $outflows_f);
usort($outflows, function($a, $b) {
  return strtotime($b['date']) - strtotime($a['date']);
});
$outflows = array_slice($outflows, 0, 8);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Cash Flow & Liquidity | I-GAS Industrial" data-ar="التدفق النقدي والسيولة | I-GAS Industrial">Cash Flow & Liquidity | I-GAS Industrial</title>
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
        <span data-en="TREASURY LEDGER & WORKING CAPITAL MONITOR" data-ar="سجل الخزينة ومراقبة رأس المال العامل">TREASURY LEDGER & WORKING CAPITAL MONITOR</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="AUDIT STATUS: RECONCILED" data-ar="حالة التدقيق: مطابق">AUDIT STATUS: RECONCILED</span>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: FIN-CF-01</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6 max-w-7xl mx-auto w-full">

    <!-- Header Banner & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs font-mono uppercase tracking-wider text-neutral-500" data-en="Financial Operations" data-ar="العمليات المالية">Financial Operations</span>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400 rtl:rotate-180"></i>
          <span class="text-xs font-mono uppercase tracking-wider text-white font-semibold" data-en="Cash Flow & Liquidity" data-ar="التدفق النقدي والسيولة">Cash Flow & Liquidity</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Cash Flow & Working Capital" data-ar="التدفق النقدي ورأس المال العامل">Cash Flow & Working Capital</h1>
        <p class="text-xs text-slate-400 mt-1" data-en="Real-time visibility into customer collections, recurring receivables, and operational fleet expenses." data-ar="مراقبة السيولة في الوقت الفعلي، تحصيلات العملاء، الذمم المدينة، ومصروفات الأسطول التشغيلية.">Real-time visibility into customer collections, recurring receivables, and operational fleet expenses.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <?php if (has_permission('finance.view') || has_permission('reports.export')): ?>
        <button onclick="exportCashFlowData()" class="border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 px-4 py-2 text-xs font-mono uppercase tracking-wider font-medium transition-colors flex items-center gap-2">
          <i data-lucide="download" class="w-3.5 h-3.5"></i>
          <span data-en="Export Statement" data-ar="تصدير الكشف">Export Statement</span>
        </button>
        <?php endif; ?>
        <a href="<?= app_url('modules/crm_sales/collections_invoices.php') ?>" class="btn-cta-orange px-4 py-2 text-xs font-mono uppercase tracking-wider font-semibold transition-colors flex items-center gap-2">
          <i data-lucide="receipt" class="w-4 h-4"></i>
          <span data-en="Invoices & Collections" data-ar="الفواتير والتحصيلات">Invoices & Collections</span>
        </a>
      </div>
    </div>

    <!-- Metric Bento Cards (4-col) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- Inflow MTD -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Total Inflow (MTD)" data-ar="إجمالي التدفق الداخلي (الشهر الحالي)">Total Inflow (MTD)</span>
          <i data-lucide="arrow-down-left" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= number_format($revenue_mtd, 2) ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="check" class="w-3 h-3"></i>
            <span data-en="Settled Invoices" data-ar="فواتير محصلة">Settled Invoices</span>
          </span>
        </div>
      </div>

      <!-- Outflow MTD -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Total Outflow (MTD)" data-ar="إجمالي التدفق الخارجي (الشهر الحالي)">Total Outflow (MTD)</span>
          <i data-lucide="arrow-up-right" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= number_format($expenses_mtd, 2) ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-rose-800 dark:border-rose-800/60 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="wrench" class="w-3 h-3"></i>
            <span data-en="Fleet & Maintenance" data-ar="الأسطول والصيانة">Fleet & Maintenance</span>
          </span>
        </div>
      </div>

      <!-- Net Cash Flow -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Net Operational Margin" data-ar="صافي التدفق التشغيلي">Net Operational Margin</span>
          <i data-lucide="<?= $net_cashflow >= 0 ? 'trending-up' : 'trending-down' ?>" class="w-4 h-4 <?= $net_cashflow >= 0 ? 'text-teal-600 dark:text-teal-400' : 'text-amber-600 dark:text-amber-400' ?>"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight <?= $net_cashflow >= 0 ? 'text-teal-400 dark:text-teal-400' : 'text-amber-400 dark:text-amber-400' ?>">
            <?= number_format($net_cashflow, 2) ?>
          </span>
          <span class="text-xs font-mono text-neutral-500" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <?php if ($net_cashflow >= 0): ?>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-teal-800 dark:border-teal-800/60 bg-teal-950/30 dark:bg-teal-950/40 text-teal-400 dark:text-teal-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="plus" class="w-3 h-3"></i>
            <span data-en="Positive Surplus" data-ar="فائض نقدي">Positive Surplus</span>
          </span>
          <?php else: ?>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-amber-800 dark:border-amber-800/60 bg-amber-950/30 dark:bg-amber-950/40 text-amber-400 dark:text-amber-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="alert-triangle" class="w-3 h-3"></i>
            <span data-en="Operating Deficit" data-ar="عجز تشغيلي">Operating Deficit</span>
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Accounts Receivable -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Accounts Receivable" data-ar="حسابات الذمم المدينة">Accounts Receivable</span>
          <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= number_format($receivables, 2) ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-[#1e3a50] bg-[#09151f] text-slate-300 text-[11px] font-mono rounded-xs">
            <i data-lucide="file-text" class="w-3 h-3"></i>
            <span data-en="Pending & Overdue" data-ar="معلق ومستحق">Pending & Overdue</span>
          </span>
        </div>
      </div>

    </div>

    <!-- Ledger Dual Tables (Inflows vs Outflows) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Recent Inflows Table -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-[#1a3346] flex items-center justify-between bg-[#071018]/60 dark:bg-neutral-850">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-1.5">
              <i data-lucide="arrow-down-left" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
              <span data-en="Recent Cash Inflow" data-ar="التدفقات النقدية الداخلة الأخيرة">Recent Cash Inflow</span>
            </h2>
          </div>
          <a href="<?= app_url('modules/crm_sales/collections_invoices.php') ?>" class="text-xs font-mono text-neutral-500 hover:text-white transition-colors flex items-center gap-1">
            <span data-en="All Invoices" data-ar="جميع الفواتير">All Invoices</span>
            <i data-lucide="arrow-right" class="w-3 h-3 rtl:rotate-180"></i>
          </a>
        </div>

        <div class="overflow-x-auto flex-1">
          <table class="w-full text-left rtl:text-right border-collapse" id="inflowTable">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-5 py-3 font-semibold" data-en="Reference" data-ar="المرجع">Reference</th>
                <th class="px-5 py-3 font-semibold" data-en="Client Account" data-ar="حساب العميل">Client Account</th>
                <th class="px-5 py-3 font-semibold" data-en="Date" data-ar="التاريخ">Date</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Amount (SAR)" data-ar="المبلغ (ريال)">Amount (SAR)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a] text-xs">
              <?php if (empty($inflows)): ?>
                <tr>
                  <td colspan="4" class="px-5 py-8 text-center text-xs font-mono text-neutral-400" data-en="No recent inflows recorded." data-ar="لا توجد تدفقات داخلية مسجلة.">No recent inflows recorded.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($inflows as $in): ?>
                  <tr class="hover:bg-[#071018] dark:hover:bg-neutral-800/40 transition-colors">
                    <td class="px-5 py-3.5">
                      <a href="<?= app_url('modules/crm_sales/view_invoice.php?id=' . (int)$in['invoice_id']) ?>" class="font-mono font-medium text-white hover:text-teal-600 dark:hover:text-teal-400">
                        <?= htmlspecialchars($in['ref']) ?>
                      </a>
                      <span class="block text-[11px] font-mono text-neutral-400"><?= htmlspecialchars($in['type']) ?></span>
                    </td>
                    <td class="px-5 py-3.5 font-medium text-slate-200">
                      <?php if (!empty($in['client_id'])): ?>
                        <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . (int)$in['client_id']) ?>" class="hover:text-teal-600 dark:hover:text-teal-400">
                          <?= htmlspecialchars($in['entity'] ?? 'Counterparty') ?>
                        </a>
                      <?php else: ?>
                        <?= htmlspecialchars($in['entity'] ?? 'Counterparty') ?>
                      <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 font-mono text-neutral-500 whitespace-nowrap">
                      <?= htmlspecialchars(date('Y-m-d', strtotime($in['date']))) ?>
                    </td>
                    <td class="px-5 py-3.5 text-right rtl:text-left font-mono font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                      +<?= number_format($in['amount'], 2) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Outflows Table -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-[#1a3346] flex items-center justify-between bg-[#071018]/60 dark:bg-neutral-850">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-1.5">
              <i data-lucide="arrow-up-right" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
              <span data-en="Recent Cash Outflow" data-ar="التدفقات النقدية الخارجة الأخيرة">Recent Cash Outflow</span>
            </h2>
          </div>
          <a href="<?= app_url('modules/logistics_fleet/maintenance_fuel.php') ?>" class="text-xs font-mono text-neutral-500 hover:text-white transition-colors flex items-center gap-1">
            <span data-en="Fleet & Maint" data-ar="الأسطول والصيانة">Fleet & Maint</span>
            <i data-lucide="arrow-right" class="w-3 h-3 rtl:rotate-180"></i>
          </a>
        </div>

        <div class="overflow-x-auto flex-1">
          <table class="w-full text-left rtl:text-right border-collapse" id="outflowTable">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-5 py-3 font-semibold" data-en="Reference" data-ar="المرجع">Reference</th>
                <th class="px-5 py-3 font-semibold" data-en="Vendor / Asset" data-ar="المورد / الأصل">Vendor / Asset</th>
                <th class="px-5 py-3 font-semibold" data-en="Date" data-ar="التاريخ">Date</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Amount (SAR)" data-ar="المبلغ (ريال)">Amount (SAR)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a] text-xs">
              <?php if (empty($outflows)): ?>
                <tr>
                  <td colspan="4" class="px-5 py-8 text-center text-xs font-mono text-neutral-400" data-en="No recent outflows recorded." data-ar="لا توجد تدفقات خارجية مسجلة.">No recent outflows recorded.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($outflows as $out): ?>
                  <tr class="hover:bg-[#071018] dark:hover:bg-neutral-800/40 transition-colors">
                    <td class="px-5 py-3.5">
                      <?php if (($out['source'] ?? '') === 'maintenance'): ?>
                        <a href="<?= app_url('modules/logistics_fleet/maintenance_details.php?id=' . urlencode($out['ref'])) ?>" class="font-mono font-medium text-white hover:text-teal-600 dark:hover:text-teal-400">
                          <?= htmlspecialchars($out['ref']) ?>
                        </a>
                      <?php else: ?>
                        <a href="<?= app_url('modules/logistics_fleet/maintenance_fuel.php?fleet_id=' . urlencode($out['ref'])) ?>" class="font-mono font-medium text-white hover:text-teal-600 dark:hover:text-teal-400">
                          <?= htmlspecialchars($out['ref']) ?>
                        </a>
                      <?php endif; ?>
                      <span class="block text-[11px] font-mono text-neutral-400"><?= htmlspecialchars($out['type']) ?></span>
                    </td>
                    <td class="px-5 py-3.5 font-medium text-slate-200">
                      <?= htmlspecialchars($out['entity'] ?? 'Direct Vendor') ?>
                    </td>
                    <td class="px-5 py-3.5 font-mono text-neutral-500 whitespace-nowrap">
                      <?= htmlspecialchars(date('Y-m-d', strtotime($out['date']))) ?>
                    </td>
                    <td class="px-5 py-3.5 text-right rtl:text-left font-mono font-bold text-rose-600 dark:text-rose-400 whitespace-nowrap">
                      -<?= number_format($out['amount'], 2) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
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
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);
  });

  function exportCashFlowData() {
    let csv = [];
    csv.push('"--- CASH INFLOWS ---"');
    let inflowRows = document.querySelectorAll("#inflowTable tr");
    inflowRows.forEach(row => {
      let cols = row.querySelectorAll("td, th");
      let data = [];
      cols.forEach(col => data.push('"' + col.innerText.replace(/"/g, '""').trim() + '"'));
      csv.push(data.join(","));
    });

    csv.push('""');
    csv.push('"--- CASH OUTFLOWS ---"');
    let outflowRows = document.querySelectorAll("#outflowTable tr");
    outflowRows.forEach(row => {
      let cols = row.querySelectorAll("td, th");
      let data = [];
      cols.forEach(col => data.push('"' + col.innerText.replace(/"/g, '""').trim() + '"'));
      csv.push(data.join(","));
    });

    let csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    let downloadLink = document.createElement("a");
    downloadLink.download = "cash_flow_statement_" + new Date().toISOString().slice(0,10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
  }
</script>
</body>
</html>