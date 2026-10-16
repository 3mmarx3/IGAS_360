<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission($pdo, 'clients.view');

$active_page = 'clients';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'CRM & Accounts', 'Clients Directory'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_csrf();
  require_permission($pdo, 'clients.delete');

  $client_id = $_POST['client_id'] ?? '';
  if (!empty($client_id)) {
    try {
      $del_stmt = $pdo->prepare("DELETE FROM partners WHERE reference_id = ? AND partner_type = 'client'");
      $del_stmt->execute([$client_id]);
      redirect(app_url('modules/crm_sales/clients_directory.php'));
    } catch (PDOException $e) {
    }
  }
}

$isRep = has_role('sales_rep') && !is_admin();
$sqlClients = "
  SELECT p.*, 
      (SELECT GROUP_CONCAT(u.full_name) FROM users u WHERE (',' || p.sales_rep_id || ',') LIKE ('%,' || u.id || ',%')) as sales_rep_names 
  FROM partners p 
  WHERE p.partner_type = 'client' 
";
$paramsClients = [];
if ($isRep) {
  $uid = get_current_user_id();
  $sqlClients .= " AND (p.sales_rep_id = ? OR p.assigned_to = ? OR (',' || p.sales_rep_id || ',') LIKE ?) ";
  $paramsClients = [(string)$uid, $uid, "%,{$uid},%"];
}
$sqlClients .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sqlClients);
$stmt->execute($paramsClients);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$clients = [];
$total_clients = count($records);
$active_count = 0;
$total_receivable = 0.0;
$new_this_month = 0;
$total_lifetime = 0.0;

$current_month = date('m');
$current_year = date('Y');

foreach ($records as $row) {
  $ui_status = $row['status'] ?? 'pending';
  if ($ui_status === 'approved') $ui_status = 'active';
  if ($ui_status === 'suspended') $ui_status = 'inactive';
  
  $balance_due = (float)($row['balance_due'] ?? 0);
  $lifetime_val = (float)($row['lifetime_value'] ?? 0);
  $last_order_ts = !empty($row['last_order_date']) ? strtotime($row['last_order_date']) : 0;

  if ($balance_due > 0 && $ui_status === 'active') {
    if ($last_order_ts > 0 && $last_order_ts < strtotime('-30 days')) {
      $ui_status = 'overdue';
    }
  }

  if ($ui_status === 'active') {
    $active_count++;
  }
  
  $total_receivable += $balance_due;
  $total_lifetime += $lifetime_val;

  if (!empty($row['created_at'])) {
    $c_ts = strtotime($row['created_at']);
    if (date('m', $c_ts) == $current_month && date('Y', $c_ts) == $current_year) {
      $new_this_month++;
    }
  }

  $words = explode(' ', trim($row['company_name'] ?? 'Client'));
  $initials = '';
  if (count($words) >= 2) {
    $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
  } elseif (count($words) == 1 && mb_strlen($words[0]) > 0) {
    $initials = strtoupper(mb_substr($words[0], 0, 2));
  }

  $clients[] = [
    'id'     => $row['reference_id'] ?? ('CLI-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT)),
    'name'    => $row['company_name'] ?? 'Unnamed Client',
    'initials'  => $initials,
    'type'    => $row['entity_type'] ?? 'Corporate',
    'segment'  => $row['segment'] ?? 'General',
    'sales_reps' => !empty($row['sales_rep_names']) ? $row['sales_rep_names'] : 'Unassigned',
    'last_order' => $last_order_ts > 0 ? date('d M Y', $last_order_ts) : 'Never',
    'lifetime'  => $lifetime_val,
    'balance'  => $balance_due,
    'status'   => $ui_status
  ];
}

$avg_account_value = $total_clients > 0 ? round($total_lifetime / $total_clients) : 0;
$active_percentage = $total_clients > 0 ? round(($active_count / $total_clients) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Clients Directory | I-GAS Enterprise" data-ar="دليل العملاء | I-GAS Enterprise">Clients Directory | I-GAS Enterprise</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
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
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[var(--surface-0)] text-[var(--ink)] font-sans">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 bg-[var(--surface-0)] overflow-hidden">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

    <!-- Telemetry Status Bar -->
    <div class="h-9 border-b border-[var(--line)] bg-[var(--surface-1)] flex items-center px-4 md:px-8 gap-3 sm:gap-4 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px] font-mono">
      <span class="flex items-center gap-2 font-medium uppercase tracking-wide text-[var(--ink)]">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span data-en="CRM Core Active" data-ar="نظام إدارة العملاء نشط">CRM Core Active</span>
      </span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">
        <span data-en="Registered Accounts:" data-ar="الحسابات المسجلة:">Registered Accounts:</span> <span class="text-[var(--ink)] font-semibold"><?= number_format($total_clients) ?></span>
      </span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">
        <span data-en="Active Pipeline:" data-ar="الحسابات النشطة:">Active Pipeline:</span> <span class="text-[var(--cryo-teal)] font-semibold"><?= $active_percentage ?>%</span>
      </span>
      <?= pipe_divider() ?>
      <span class="ms-auto text-[var(--ink-muted)] uppercase tracking-wide">
        <span data-en="Audit Protocol · ISO 9001" data-ar="بروتوكول التدقيق · ISO 9001">Audit Protocol · ISO 9001</span>
      </span>
    </div>

    <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7 bg-grid">
      
      <!-- Page Title Bar -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <?= tag_plate('CRM-DIR-01', 'teal') ?>
            <span class="text-[11px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Industrial Client Accounts & Supply Standing" data-ar="حسابات العملاء الصناعيين والوضع المالي">Industrial Client Accounts & Supply Standing</span>
          </div>
          <h1 class="text-2xl font-bold font-heading tracking-tight text-[var(--ink)]" data-en="Clients Directory" data-ar="دليل العملاء">Clients Directory</h1>
          <p class="text-xs text-[var(--ink-muted)] mt-1 font-mono" data-en="Enterprise client ledger, cylinder quotas, contract bindings, and receivable balances." data-ar="سجل حسابات العملاء، حصص الأسطوانات، العقود، والأرصدة المستحقة.">Enterprise client ledger, cylinder quotas, contract bindings, and receivable balances.</p>
        </div>
        <div class="flex items-center gap-3">
          <button type="button" class="btn-secondary h-9 px-3.5 py-2 rounded-md border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 text-xs font-semibold uppercase tracking-wider inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
            <?= icon('download', 'w-3.5 h-3.5 text-slate-400') ?>
            <span data-en="Export CSV" data-ar="تصدير CSV">Export CSV</span>
          </button>
          <?php if (has_permission($pdo, 'clients.create')): ?>
          <a href="<?= app_url('modules/crm_sales/new_client.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 rounded-md text-xs font-semibold uppercase tracking-wider inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <?= icon('plus', 'w-4 h-4') ?>
            <span data-en="New Client Account" data-ar="تسجيل عميل جديد">New Client Account</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- KPI Metric Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        
        <!-- KPI 1: Total Accounts -->
        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Total Accounts" data-ar="إجمالي الحسابات">Total Accounts</span>
            <?= icon('users', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
          </div>
          <div class="text-2xl font-bold font-mono tabular-nums text-[var(--ink)]">
            <?= number_format($total_clients) ?>
          </div>
          <div class="mt-2 flex items-center gap-1.5 text-[11px] font-mono text-[var(--ink-muted)]">
            <span class="text-emerald-600 font-semibold">+<?= $new_this_month ?></span>
            <span data-en="onboarded this month" data-ar="تم تسجيلهم هذا الشهر">onboarded this month</span>
          </div>
        </div>

        <!-- KPI 2: Active Accounts -->
        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-emerald-600 rounded-sm p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Active Pipeline" data-ar="الحسابات النشطة">Active Pipeline</span>
            <?= icon('activity', 'w-4 h-4 text-emerald-600') ?>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-2xl font-bold font-mono tabular-nums text-[var(--ink)]"><?= number_format($active_count) ?></span>
            <span class="text-xs font-mono text-[var(--ink-muted)]">/ <?= number_format($total_clients) ?> (<?= $active_percentage ?>%)</span>
          </div>
          <div class="mt-2.5 w-full bg-[var(--surface-2)] border border-[var(--line)] h-1.5 rounded-xs overflow-hidden">
            <div class="bg-emerald-600 h-full" style="width: <?= $active_percentage ?>%"></div>
          </div>
        </div>

        <!-- KPI 3: Total Receivable -->
        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--safety-orange)] rounded-sm p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Outstanding Balance" data-ar="الرصيد المستحق">Outstanding Balance</span>
            <?= icon('dollar-sign', 'w-4 h-4 text-[var(--safety-orange)]') ?>
          </div>
          <div class="text-2xl font-bold font-mono tabular-nums text-[var(--ink)]">
            <?= number_format($total_receivable, 2) ?>
            <span class="text-xs font-normal text-[var(--ink-muted)] font-mono">SAR</span>
          </div>
          <div class="mt-2 flex items-center gap-1.5 text-[11px] font-mono text-[var(--ink-muted)]">
            <span>Receivables ledger active</span>
          </div>
        </div>

        <!-- KPI 4: Avg Account Value -->
        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--frost-cyan)] rounded-sm p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Avg Account Lifetime" data-ar="متوسط قيمة الحساب">Avg Account Lifetime</span>
            <?= icon('trending-up', 'w-4 h-4 text-[var(--frost-cyan)]') ?>
          </div>
          <div class="text-2xl font-bold font-mono tabular-nums text-[var(--ink)]">
            <?= number_format($avg_account_value) ?>
            <span class="text-xs font-normal text-[var(--ink-muted)] font-mono">SAR</span>
          </div>
          <div class="mt-2 flex items-center gap-1.5 text-[11px] font-mono text-[var(--ink-muted)]">
            <span>Historical LTV index</span>
          </div>
        </div>

      </div>

      <!-- Table Card -->
      <div class="bg-[var(--surface-1)] border border-[var(--line)] rounded-sm overflow-hidden flex flex-col">
        
        <!-- Table Filter Header -->
        <div class="px-5 py-3.5 border-b border-[var(--line)] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-[var(--surface-1)]">
          <div class="flex items-center gap-2">
            <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="Registered Client Directory" data-ar="دليل الحسابات المسجلة">Registered Client Directory</h2>
            <span class="px-1.5 py-0.5 rounded-xs bg-[var(--surface-2)] border border-[var(--line)] text-[10px] font-mono text-[var(--ink-muted)]">
              <?= count($clients) ?> records
            </span>
          </div>
          
          <div class="flex items-center gap-2 w-full sm:w-auto">
            <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
              <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
              <input type="text" id="clientSearchInput" placeholder="Filter company or ID..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0" onkeyup="filterTable()">
            </div>
          </div>
        </div>

        <!-- Responsive Table Wrapper -->
        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse" id="clientsTable">
            <thead>
              <tr class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] bg-[var(--surface-2)] border-b border-[var(--line)]">
                <th class="px-3 py-2.5 font-semibold whitespace-nowrap" data-en="Client Reference" data-ar="معرف العميل">Client Reference</th>
                <th class="px-3 py-2.5 font-semibold whitespace-nowrap" data-en="Company Name" data-ar="اسم الشركة">Company Name</th>
                <th class="px-3 py-2.5 font-semibold text-center whitespace-nowrap" data-en="Status" data-ar="الحالة">Status</th>
                <th class="px-3 py-2.5 font-semibold whitespace-nowrap" data-en="Sector" data-ar="القطاع">Sector</th>
                <th class="px-3 py-2.5 font-semibold whitespace-nowrap" data-en="Sales Officer" data-ar="مسؤول المبيعات">Sales Officer</th>
                <th class="px-3 py-2.5 font-semibold whitespace-nowrap" data-en="Last Dispatch" data-ar="آخر طلب">Last Dispatch</th>
                <th class="px-3 py-2.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="LTV (SAR)" data-ar="القيمة الإجمالية">LTV (SAR)</th>
                <th class="px-3 py-2.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Balance Due" data-ar="الرصيد المستحق">Balance Due</th>
                <th class="px-3 py-2.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="text-xs font-mono divide-y divide-[var(--line-soft)]">
              <?php if (empty($clients)): ?>
              <tr>
                <td colspan="9" class="text-center py-10 text-[var(--ink-muted)] font-mono text-xs">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <?= icon('inbox', 'w-6 h-6 text-[var(--ink-muted)]') ?>
                    <span data-en="No client records match the query." data-ar="لا توجد سجلات مطابقة للبحث.">No client records match the query.</span>
                  </div>
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($clients as $c): ?>
                <tr class="hover:bg-[var(--surface-2)] transition-colors">
                  <td class="px-3 py-2.5 whitespace-nowrap">
                    <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode($c['id'])) ?>" class="font-semibold text-[var(--cryo-teal)] hover:text-[var(--frost-cyan)] transition-colors">
                      <?= htmlspecialchars($c['id']) ?>
                    </a>
                  </td>
                  <td class="px-3 py-2.5">
                    <div class="flex items-center gap-2.5">
                      <div class="w-7 h-7 rounded-xs border border-[var(--line)] bg-[var(--surface-0)] flex items-center justify-center text-[10px] font-bold text-[var(--ink)] flex-shrink-0">
                        <?= htmlspecialchars($c['initials']) ?>
                      </div>
                      <div class="min-w-0">
                        <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode($c['id'])) ?>" class="font-medium text-[var(--ink)] hover:text-[var(--cryo-teal)] block truncate max-w-[200px]">
                          <?= htmlspecialchars($c['name']) ?>
                        </a>
                        <span class="text-[10px] text-[var(--ink-muted)] whitespace-nowrap"><?= htmlspecialchars($c['type']) ?></span>
                      </div>
                    </div>
                  </td>
                  <td class="px-3 py-2.5 text-center whitespace-nowrap">
                    <?php if ($c['status'] === 'active'): ?>
                      <?= tag_plate('ACTIVE', 'teal') ?>
                    <?php elseif ($c['status'] === 'overdue'): ?>
                      <?= tag_plate('OVERDUE', 'orange') ?>
                    <?php elseif ($c['status'] === 'pending'): ?>
                      <?= tag_plate('PENDING', 'zinc') ?>
                    <?php else: ?>
                      <?= tag_plate(strtoupper($c['status']), 'zinc') ?>
                    <?php endif; ?>
                  </td>
                  <td class="px-3 py-2.5 text-[var(--ink-muted)] whitespace-nowrap">
                    <?= htmlspecialchars($c['segment']) ?>
                  </td>
                  <td class="px-3 py-2.5 text-[var(--ink)] whitespace-nowrap">
                    <?= htmlspecialchars($c['sales_reps']) ?>
                  </td>
                  <td class="px-3 py-2.5 text-[var(--ink-muted)] whitespace-nowrap">
                    <?= htmlspecialchars($c['last_order']) ?>
                  </td>
                  <td class="px-3 py-2.5 text-right rtl:text-left tabular-nums text-[var(--ink)] font-semibold whitespace-nowrap">
                    <?= number_format($c['lifetime'], 2) ?>
                  </td>
                  <td class="px-3 py-2.5 text-right rtl:text-left tabular-nums font-semibold whitespace-nowrap <?= $c['balance'] > 0 ? 'text-[var(--safety-orange)]' : 'text-[var(--ink-muted)]' ?>">
                    <?= $c['balance'] > 0 ? number_format($c['balance'], 2) . ' SAR' : '—' ?>
                  </td>
                  <td class="px-3 py-2.5 text-right rtl:text-left whitespace-nowrap">
                    <div class="flex items-center justify-end gap-1.5">
                      <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode($c['id'])) ?>" class="btn-table-action whitespace-nowrap" title="View Customer 360">
                        <?= icon('eye', 'w-3.5 h-3.5') ?>
                      </a>
                      <?php if (has_permission($pdo, 'clients.delete')): ?>
                      <form method="POST" action="<?= app_url('modules/crm_sales/clients_directory.php') ?>" class="m-0 p-0 inline-block" onsubmit="return confirm('Confirm deletion of client record <?= htmlspecialchars($c['id']) ?>?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="client_id" value="<?= htmlspecialchars($c['id']) ?>">
                        <button type="submit" class="btn-table-action hover:!border-rose-500 hover:!text-rose-400 whitespace-nowrap" title="Purge Client">
                          <?= icon('trash-2', 'w-3.5 h-3.5') ?>
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

        <!-- Table Footer Pagination Strip -->
        <div class="px-5 py-3 border-t border-[var(--line)] flex flex-col sm:flex-row justify-between items-center gap-2 bg-[var(--surface-1)]">
          <span class="text-[11px] font-mono text-[var(--ink-muted)]" data-en="Showing <?= count($clients) ?> of <?= number_format($total_clients) ?> client ledger entries" data-ar="عرض <?= count($clients) ?> من أصل <?= number_format($total_clients) ?> حساب">
            Showing <?= count($clients) ?> of <?= number_format($total_clients) ?> client ledger entries
          </span>
          <div class="flex items-center gap-1.5">
            <button type="button" class="px-2 py-1 text-xs font-mono rounded-xs border border-[var(--line)] bg-[var(--surface-0)] text-[var(--ink-muted)] cursor-not-allowed">
              <?= icon('chevron-left', 'w-3.5 h-3.5 rtl:rotate-180') ?>
            </button>
            <span class="px-2.5 py-1 text-xs font-mono font-semibold rounded-xs border border-[var(--line)] bg-[var(--surface-2)] text-[var(--ink)]">1</span>
            <button type="button" class="px-2 py-1 text-xs font-mono rounded-xs border border-[var(--line)] bg-[var(--surface-0)] text-[var(--ink-muted)] cursor-not-allowed">
              <?= icon('chevron-right', 'w-3.5 h-3.5 rtl:rotate-180') ?>
            </button>
          </div>
        </div>

      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();

    function filterTable() {
      const input = document.getElementById("clientSearchInput");
      const filter = input.value.toUpperCase();
      const table = document.getElementById("clientsTable");
      const tr = table.getElementsByTagName("tr");

      for (let i = 1; i < tr.length; i++) {
        const tdText = tr[i].textContent || tr[i].innerText;
        if (tdText.toUpperCase().indexOf(filter) > -1) {
          tr[i].style.display = "";
        } else {
          tr[i].style.display = "none";
        }
      }
    }

    function applyLanguage(lang) {
      document.querySelectorAll('[data-en]').forEach(el => {
        const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
        if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
          el.placeholder = transText;
        } else {
          el.innerText = transText;
        }
      });
      document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
      if (lang === 'ar') {
        document.body.classList.add('arabic-mode');
      } else {
        document.body.classList.remove('arabic-mode');
      }
    }

    document.addEventListener("DOMContentLoaded", function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      applyLanguage(savedLang);
    });
  </script>
</body>
</html>