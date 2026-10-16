<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'quotations.view');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_csrf();
  require_permission($pdo, 'quotations.delete');
  $delete_id = filter_var($_POST['quotation_id'] ?? null, FILTER_VALIDATE_INT);
  if ($delete_id) {
    $stmt_del = $pdo->prepare("DELETE FROM quotations WHERE id = ?");
    $stmt_del->execute([$delete_id]);
  }
  redirect(app_url('modules/crm_sales/quotations.php'));
}

$active_page = 'quotations';
$breadcrumb = ['I-GAS', 'Sales & Quotations', 'All Quotations'];

$isRep = has_role('sales_rep') && !is_admin();
$sqlQuotes = "
  SELECT q.*, p.company_name, p.entity_type 
  FROM quotations q 
  LEFT JOIN partners p ON q.client_id = p.id 
";
$paramsQuotes = [];
if ($isRep) {
  $uid = get_current_user_id();
  $sqlQuotes .= " WHERE q.sales_rep_id = ? ";
  $paramsQuotes = [$uid];
}
$sqlQuotes .= " ORDER BY q.created_at DESC";

$stmt = $pdo->prepare($sqlQuotes);
$stmt->execute($paramsQuotes);
$records = $stmt->fetchAll();

$quotes = [];
$total_quotes = count($records);
$pending_count = 0;
$accepted_count = 0;
$pipeline_value = 0;

foreach ($records as $row) {
  $st = strtolower($row['status'] ?? 'draft');
  if ($st === 'sent') $pending_count++;
  if ($st === 'accepted') $accepted_count++;
  if (in_array($st, ['draft', 'sent'])) {
    $pipeline_value += (float)($row['total_value'] ?? 0);
  }

  $words = preg_split('/\s+/', trim((string)($row['company_name'] ?? '')));
  $initials = '';
  if (count($words) >= 2) {
    $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
  } elseif (count($words) === 1 && mb_strlen($words[0]) > 0) {
    $initials = strtoupper(mb_substr($words[0], 0, 2));
  }
  $initials = $initials ?: 'QT';

  $quotes[] = [
    'db_id'  => $row['id'],
    'id'    => $row['quotation_number'],
    'client'  => $row['company_name'] ?? 'Unknown Client',
    'initials' => $initials,
    'corp'   => (($row['entity_type'] ?? '') === 'Corporate'),
    'specs'  => $row['specs'] ?? 'Standard Gas Mixture',
    'created' => $row['issue_date'],
    'expires' => $row['expiry_date'],
    'value'  => (float)($row['total_value'] ?? 0),
    'status'  => $st
  ];
}

$conversion_rate = $total_quotes > 0 ? round(($accepted_count / $total_quotes) * 100, 1) : 0;

$statusConfigs = [
  'draft'  => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-slate-400', 'dot' => 'bg-neutral-400', 'label' => 'Draft', 'label_ar' => 'مسودة'],
  'sent'   => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Sent', 'label_ar' => 'مرسل'],
  'accepted' => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'dot' => 'bg-teal-500', 'label' => 'Accepted', 'label_ar' => 'مقبول'],
  'declined' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Declined', 'label_ar' => 'مرفوض'],
  'expired' => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-neutral-500', 'dot' => 'bg-neutral-400', 'label' => 'Expired', 'label_ar' => 'منتهي'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales &amp; Quotations | I-GAS Enterprise</title>
  
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
        <span data-en="COMMERCIAL ESTIMATE ENGINE" data-ar="محرك عروض الأسعار">COMMERCIAL ESTIMATE ENGINE</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500"><?= $total_quotes ?> <span data-en="PROPOSALS" data-ar="عرض سعر">PROPOSALS</span></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">
      <!-- Header Row -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1.5">
            <span class="text-xs font-mono text-neutral-500 uppercase tracking-wider" data-en="Sales & Commercial Operations" data-ar="المبيعات والعمليات التجارية">Sales & Commercial Operations</span>
          </div>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Commercial Quotations Register" data-ar="سجل عروض الأسعار التجارية">
            Commercial Quotations Register
          </h1>
          <p class="text-xs text-neutral-500 mt-1" data-en="Formal industrial gas pricing proposals, client acceptances, and validity tracking." data-ar="عروض تسعير الغازات الصناعية الرسمية وموافقات العملاء ومتابعة الصلاحية.">
            Formal industrial gas pricing proposals, client acceptances, and validity tracking.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button onclick="exportQuotationsData()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <i data-lucide="download" class="w-4 h-4"></i>
            <span data-en="Export CSV" data-ar="تصدير CSV">Export CSV</span>
          </button>

          <?php if (has_permission($pdo, 'quotations.create')): ?>
          <!-- Single Primary CTA: Safety Valve Orange -->
          <a href="<?= app_url('modules/crm_sales/new_quotation.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span data-en="New Quotation" data-ar="عرض سعر جديد">New Quotation</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento KPI Grid (4 cards with 3px start accents) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Quotations -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Total Quotes" data-ar="إجمالي العروض">Total Quotes</span>
            <i data-lucide="file-text" class="w-4 h-4 text-teal-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-white">
            <?= number_format($total_quotes) ?>
            <span class="text-xs font-sans font-normal text-neutral-500" data-en="Files" data-ar="ملف">Files</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Cumulative proposals generated" data-ar="إجمالي المقترحات المنشأة">Cumulative proposals generated</p>
        </div>

        <!-- Awaiting Decision -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Awaiting Decision" data-ar="بانتظار القرار">Awaiting Decision</span>
            <i data-lucide="clock" class="w-4 h-4 text-amber-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-amber-400 dark:text-amber-400">
            <?= number_format($pending_count) ?>
            <span class="text-xs font-sans font-normal text-neutral-500" data-en="Pending" data-ar="معلق">Pending</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Sent to client review" data-ar="مرسلة لمراجعة العميل">Sent to client review</p>
        </div>

        <!-- Pipeline Value -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Pipeline Valuation" data-ar="قيمة العروض المفتوحة">Pipeline Valuation</span>
            <i data-lucide="activity" class="w-4 h-4 text-blue-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-white">
            <?= number_format($pipeline_value) ?>
            <span class="text-xs font-sans font-normal text-neutral-500">SAR</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Total value in open proposals" data-ar="القيمة الإجمالية للعروض الجارية">Total value in open proposals</p>
        </div>

        <!-- Conversion Rate -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Win Conversion Rate" data-ar="معدل قبول العروض">Win Conversion Rate</span>
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-emerald-700 dark:text-emerald-400">
            <?= $conversion_rate ?><span class="text-xs font-sans font-normal text-neutral-500">%</span>
          </div>
          <div class="w-full bg-[#09151f] h-1.5 rounded-none mt-2 overflow-hidden border border-[#1e3a50]">
            <div class="bg-emerald-600 h-full" style="width: <?= min(100, $conversion_rate) ?>%;"></div>
          </div>
        </div>
      </div>

      <!-- Quotations Table Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-md overflow-hidden">
        <!-- Filter Bar -->
        <div class="px-6 py-4 border-b border-[#1a3346] flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <h2 class="text-sm font-semibold text-white" data-en="All Quotations" data-ar="جميع عروض الأسعار">
              All Quotations
            </h2>
            <span class="px-2 py-0.5 text-xs font-mono rounded bg-[#09151f] text-slate-400 border border-[#1e3a50]">
              <?= count($quotes) ?>
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search by number, client..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>

            <select id="statusFilter" class="h-9 py-1.5 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
              <option value="" data-en="All Statuses" data-ar="جميع الحالات">All Statuses</option>
              <option value="draft" data-en="Draft" data-ar="مسودة">Draft</option>
              <option value="sent" data-en="Sent" data-ar="مرسل">Sent</option>
              <option value="accepted" data-en="Accepted" data-ar="مقبول">Accepted</option>
              <option value="declined" data-en="Declined" data-ar="مرفوض">Declined</option>
              <option value="expired" data-en="Expired" data-ar="منتهي">Expired</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-start text-xs border-collapse" id="quotationsTable">
            <thead>
              <tr class="bg-[#09151f] border-b border-[#1a3346] text-slate-400 font-mono uppercase text-xs">
                <th class="py-2.5 px-4 text-start" data-en="Quotation Ref" data-ar="رقم عرض السعر">Quotation Ref</th>
                <th class="py-2.5 px-4 text-start" data-en="Client Commercial Account" data-ar="حساب العميل">Client Commercial Account</th>
                <th class="py-2.5 px-4 text-start" data-en="Specifications" data-ar="المواصفات">Specifications</th>
                <th class="py-2.5 px-4 text-start" data-en="Issued" data-ar="تاريخ الإصدار">Issued</th>
                <th class="py-2.5 px-4 text-start" data-en="Valid Until" data-ar="صالح حتى">Valid Until</th>
                <th class="py-2.5 px-4 text-end" data-en="Total Value (SAR)" data-ar="القيمة الإجمالية (ر.س)">Total Value (SAR)</th>
                <th class="py-2.5 px-4 text-center" data-en="Status" data-ar="الحالة">Status</th>
                <th class="py-2.5 px-4 text-end" data-en="Action" data-ar="الإجراء">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a] font-mono">
              <?php if (empty($quotes)): ?>
                <tr>
                  <td colspan="8" class="py-12 text-center text-slate-400 font-sans text-xs" data-en="No commercial quotations registered in database." data-ar="لا توجد عروض أسعار مسجلة في قاعدة البيانات.">
                    No commercial quotations registered in database.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($quotes as $q): ?>
                  <?php $sc = $statusConfigs[$q['status']] ?? $statusConfigs['draft']; ?>
                  <tr class="hover:bg-[#102332] transition-colors quotation-row" data-status="<?= htmlspecialchars($q['status']) ?>">
                    <td class="py-2.5 px-4 font-bold text-white">
                      <a href="<?= app_url('modules/crm_sales/view_quotation.php?id=' . urlencode((string)$q['db_id'])) ?>" class="hover:text-cyan-400 hover:underline">
                        <?= htmlspecialchars($q['id']) ?>
                      </a>
                    </td>
                    <td class="py-2.5 px-4 font-sans">
                      <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded border border-[#1e3a50] bg-[#09151f] flex items-center justify-center font-mono font-bold text-[10px] text-slate-300 shrink-0">
                          <?= htmlspecialchars($q['initials']) ?>
                        </div>
                        <span class="font-medium text-white client-name truncate max-w-xs">
                          <?= htmlspecialchars($q['client']) ?>
                        </span>
                      </div>
                    </td>
                    <td class="py-2.5 px-4 font-sans text-slate-400">
                      <?= htmlspecialchars($q['specs']) ?>
                    </td>
                    <td class="py-2.5 px-4 text-slate-400">
                      <?= !empty($q['created']) ? htmlspecialchars(date('d M Y', strtotime($q['created']))) : '—' ?>
                    </td>
                    <td class="py-2.5 px-4 text-slate-400">
                      <?= !empty($q['expires']) ? htmlspecialchars(date('d M Y', strtotime($q['expires']))) : '—' ?>
                    </td>
                    <td class="py-2.5 px-4 text-end font-bold text-white">
                      <?= number_format($q['value']) ?>
                    </td>
                    <td class="py-2.5 px-4 text-center">
                      <span class="px-2 py-0.5 text-[10px] font-mono rounded-full border <?= $sc['border'] ?> <?= $sc['bg'] ?> <?= $sc['text'] ?> inline-flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                        <span data-en="<?= $sc['label'] ?>" data-ar="<?= $sc['label_ar'] ?>"><?= $sc['label'] ?></span>
                      </span>
                    </td>
                    <td class="py-2.5 px-4 text-end">
                      <div class="flex items-center justify-end gap-1.5">
                        <a href="<?= app_url('modules/crm_sales/view_quotation.php?id=' . urlencode((string)$q['db_id'])) ?>" class="btn-table-action whitespace-nowrap" title="View Quotation Document">
                          <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        </a>
                        <a href="<?= app_url('modules/crm_sales/quotation_details.php?id=' . urlencode((string)$q['db_id'])) ?>" class="btn-table-action whitespace-nowrap" title="Edit / Technical Specs">
                          <i data-lucide="sliders" class="w-3.5 h-3.5"></i>
                        </a>
                        <?php if (has_permission($pdo, 'quotations.delete')): ?>
                        <form method="POST" action="<?= app_url('modules/crm_sales/quotations.php') ?>" class="m-0 p-0 inline-block" onsubmit="return confirm('Delete this quotation?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="quotation_id" value="<?= htmlspecialchars((string)$q['db_id']) ?>">
                          <button type="submit" class="w-7 h-7 rounded flex items-center justify-center bg-[#102230] hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-[#1e3a50] hover:border-rose-500/50 transition-colors" title="Delete Quotation">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                          </button>
                        </form>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="px-6 py-4 border-t border-[#1a3346] flex flex-col sm:flex-row justify-between items-center gap-3 bg-[#09151f]/50">
          <span class="text-xs font-mono text-neutral-500">Showing <?= count($quotes) > 0 ? '1' : '0' ?>–<?= count($quotes) ?> of <?= number_format($total_quotes) ?> proposals</span>
          <span class="text-xs font-mono text-neutral-400">PAGE 1 OF 1</span>
        </div>
      </div>
    </div>
  </main>

  <script>
    lucide.createIcons();

    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const rows = document.querySelectorAll('.quotation-row');

    function filterRows() {
      const query = (searchInput?.value || '').toLowerCase().trim();
      const selectedStatus = (statusFilter?.value || '').toLowerCase().trim();

      rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();

        const matchesSearch = !query || text.includes(query);
        const matchesStatus = !selectedStatus || rowStatus === selectedStatus;

        if (matchesSearch && matchesStatus) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (statusFilter) statusFilter.addEventListener('change', filterRows);

    function exportQuotationsData() {
      let csv = [];
      let rows = document.querySelectorAll("#quotationsTable tr");
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
      downloadLink.download = "quotations_export_" + new Date().toISOString().slice(0,10) + ".csv";
      downloadLink.href = window.URL.createObjectURL(csvFile);
      downloadLink.style.display = "none";
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
    }
  </script>
</body>
</html>