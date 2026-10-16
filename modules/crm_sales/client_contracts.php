<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission($pdo, 'clients.view');

$active_page = 'client_contracts';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Client Contracts'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_csrf();
  require_permission($pdo, 'clients.delete');

  $delete_id = isset($_POST['contract_id']) ? (int)$_POST['contract_id'] : 0;

  if ($delete_id > 0) {
    $del = $pdo->prepare("DELETE FROM client_contracts WHERE id = :id");
    $del->execute(['id' => $delete_id]);
  }

  redirect(app_url('modules/crm_sales/client_contracts.php'));
}

$stmt = $pdo->prepare("
  SELECT 
    c.id AS row_id,
    c.contract_number AS id,
    p.company_name AS client,
    p.entity_type,
    c.minimum_commitment_tons AS quota,
    c.contract_type AS gas_type,
    c.start_date,
    c.end_date,
    COALESCE(c.minimum_commitment_tons * 1500, 50000.00) AS value,
    c.status
  FROM client_contracts c
  JOIN partners p ON c.client_id = p.id
  ORDER BY c.start_date DESC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$contracts = [];
$total_active_contracts = 0;
$expiring_soon_count = 0;
$monthly_recurring = 0;
$total_contract_value = 0;

$today = new DateTime();
$expiringThreshold = (clone $today)->modify('+60 days');

foreach ($rows as $row) {
  $words = preg_split('/\s+/', trim($row['client']));
  $initials = '';

  if (count($words) >= 2) {
    $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
  } elseif (count($words) === 1 && mb_strlen($words[0]) > 0) {
    $initials = strtoupper(mb_substr($words[0], 0, 2));
  }

  $start = !empty($row['start_date']) ? new DateTime($row['start_date']) : null;
  $end  = !empty($row['end_date']) ? new DateTime($row['end_date']) : null;

  $status = $row['status'];

  if ($status === 'active') {
    $total_active_contracts++;
    $monthly_recurring += (float)$row['value'];
  }

  if ($end && $end >= $today && $end <= $expiringThreshold) {
    $expiring_soon_count++;
    if ($status !== 'terminated' && $status !== 'expired') {
      $status = 'expiring';
    }
  }

  if ($status === 'active') {
    $total_contract_value += (float)$row['value'];
  }

  $contracts[] = [
    'row_id' => $row['row_id'],
    'id' => $row['id'],
    'client' => $row['client'],
    'initials' => $initials ?: 'CL',
    'corp' => ($row['entity_type'] === 'Corporate'),
    'quota' => $row['quota'],
    'gas_type' => $row['gas_type'],
    'start_date' => $row['start_date'],
    'end_date' => $row['end_date'],
    'value' => (float)$row['value'],
    'status' => $status
  ];
}

if ($total_contract_value <= 0) {
  $total_contract_value = array_sum(array_map(fn($c) => $c['value'], $contracts));
}

$statusStyles = [
  'active'   => ['bg' => '#0d2a38', 'fg' => '#2dd4bf', 'dot' => '#2dd4bf', 'label' => 'Active', 'dotBorder' => ''],
  'expiring'  => ['bg' => 'rgba(245, 158, 11, 0.15)', 'fg' => '#fbbf24', 'dot' => '#f59e0b', 'label' => 'Expiring Soon', 'dotBorder' => ''],
  'expired'  => ['bg' => 'rgba(148, 163, 184, 0.1)', 'fg' => '#94a3b8', 'dot' => '#64748b', 'label' => 'Expired', 'dotBorder' => ''],
  'terminated' => ['bg' => 'rgba(244, 63, 94, 0.15)', 'fg' => '#fb7185', 'dot' => '#e11d48', 'label' => 'Terminated', 'dotBorder' => ''],
];

if ($monthly_recurring <= 0) {
  $monthly_recurring = count(array_filter($contracts, fn($c) => $c['status'] === 'active'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Client Contracts | I-GAS Enterprise</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    .pill {
      display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500;
      padding: 3px 9px; border-radius: 3px; line-height: 1; white-space: nowrap;
    }
    .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="h-9 border-b flex items-center px-4 md:px-8 gap-3 sm:gap-6 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px]" style="background: var(--paper-deep); border-color: var(--line-soft);">
  <span class="flex items-center gap-2 text-[11px] font-medium mono uppercase tracking-wide" style="color: var(--ink);">
    <span class="status-dot" style="background: #5C8A5C;"></span>System Nominal
  </span>
  <span class="w-px h-3" style="background: var(--line);"></span>
  <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Plant — Jeddah Industrial</span>
  <span class="w-px h-3" style="background: var(--line);"></span>
  <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Shift B · 14:00–22:00</span>
  <span class="ml-auto text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">v2.4.1</span>
</div>

<div class="flex-1 overflow-auto px-4 sm:px-6 md:px-10 py-6 md:py-8 space-y-6 md:space-y-8">
  <!-- Header -->
  <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pb-2 border-b" style="border-color: var(--line-soft);">
    <div>
      <div class="flex items-center gap-2 mb-2">
        <span class="w-2 h-2 rounded-full" style="background: var(--accent);"></span>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500" data-en="Commercial Sales Agreements" data-ar="اتفاقيات المبيعات التجارية">Commercial Sales Agreements</p>
      </div>
      <h2 class="text-3xl font-bold tracking-tight text-white" data-en="Client Contracts & SLAs" data-ar="عقود العملاء ومستويات الخدمة">Client Contracts & SLAs</h2>
      <p class="text-sm text-neutral-500 mt-2 max-w-2xl leading-relaxed" data-en="Manage long-term gas supply agreements, minimum monthly commitments, call-off quotas, and penalty terms." data-ar="إدارة اتفاقيات توريد الغاز طويلة الأجل، والالتزامات الشهرية الدنيا، وحصص الاستدعاء، وشروط الجزاءات.">Manage long-term gas supply agreements, minimum monthly commitments, call-off quotas, and penalty terms.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
      <button onclick="exportContractsReport()" class="btn-secondary rounded-lg px-4 py-2.5 text-xs font-semibold flex items-center gap-2 hover: transition-all">
        <i data-lucide="download" class="w-4 h-4 text-neutral-500"></i>
        <span data-en="Export Data" data-ar="تصدير البيانات">Export Data</span>
      </button>
      <?php if (has_permission($pdo, 'clients.edit')): ?>
      <a href="<?= app_url('modules/crm_sales/new_calloff.php') ?>" class="btn-secondary rounded-lg px-4 py-2.5 text-xs font-semibold flex items-center gap-2 hover: transition-all">
        <i data-lucide="send" class="w-4 h-4 text-neutral-500"></i>
        <span data-en="New Call-off " data-ar="إصدار استدعاء تعاقدي">New Call-off </span>
      </a>
      <a href="<?= app_url('modules/crm_sales/draft_contract.php') ?>" class="btn-primary rounded-lg px-4 py-2.5 text-xs font-semibold flex items-center gap-2 hover: transition-all">
        <i data-lucide="plus" class="w-4 h-4"></i>
        <span data-en="Draft Contract" data-ar="صياغة عقد جديد">Draft Contract</span>
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- KPI Summary Grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-6 rounded-sm flex flex-col justify-between transition-all duration-200 min-h-[150px]">
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400" data-en="Active Contracts" data-ar="العقود النشطة">Active Contracts</span>
        <div class="w-8 h-8 rounded-sm bg-[#102230] border border-[#1e3a50] flex items-center justify-center text-cyan-400">
          <i data-lucide="file-check-2" class="w-4 h-4"></i>
        </div>
      </div>
      <div class="flex items-baseline gap-2 mb-2">
        <h3 class="text-3xl font-bold tracking-tight text-white num whitespace-nowrap"><?= number_format($total_active_contracts) ?></h3>
        <span class="text-xs text-slate-400" data-en="agreements" data-ar="اتفاقية">agreements</span>
      </div>
      <div class="pt-2 text-xs text-slate-400 flex items-center gap-1.5 whitespace-nowrap">
        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
        <span data-en="Operational & compliant" data-ar="سارية وملتزمة">Operational & compliant</span>
      </div>
    </div>

    <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-6 rounded-sm flex flex-col justify-between transition-all duration-200 min-h-[150px]">
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400" data-en="Expiring Soon" data-ar="تنتهي قريباً">Expiring Soon</span>
        <div class="w-8 h-8 rounded-sm bg-[#102230] border border-amber-800/60 flex items-center justify-center text-amber-400">
          <i data-lucide="clock" class="w-4 h-4"></i>
        </div>
      </div>
      <div class="flex items-baseline gap-2 mb-2">
        <h3 class="text-3xl font-bold tracking-tight text-amber-400 num whitespace-nowrap"><?= number_format($expiring_soon_count) ?></h3>
        <span class="text-xs text-slate-400" data-en="need review" data-ar="تتطلب مراجعة">need review</span>
      </div>
      <div class="pt-2 text-xs">
        <span class="pill bg-amber-500/10 text-amber-400 font-semibold border border-amber-500/30 whitespace-nowrap">
          <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i><span data-en="Within 60 days" data-ar="خلال 60 يوماً">Within 60 days</span>
        </span>
      </div>
    </div>

    <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-6 rounded-sm flex flex-col justify-between transition-all duration-200 min-h-[150px]">
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400" data-en="Monthly Quota Value" data-ar="قيمة الحصص الشهرية">Monthly Quota Value</span>
        <div class="w-8 h-8 rounded-sm bg-[#102230] border border-[#1e3a50] flex items-center justify-center text-cyan-400">
          <i data-lucide="repeat" class="w-4 h-4"></i>
        </div>
      </div>
      <div class="flex items-baseline gap-2 mb-2">
        <h3 class="text-3xl font-bold tracking-tight text-white num whitespace-nowrap"><?= number_format($monthly_recurring) ?></h3>
        <span class="text-xs font-semibold text-slate-400" data-en="SAR" data-ar="ريال">SAR</span>
      </div>
      <div class="pt-2 text-xs text-slate-400" data-en="Guaranteed monthly billing baseline" data-ar="خط الأساس للفواتير الشهرية المضمونة">Guaranteed monthly billing baseline</div>
    </div>

    <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-6 rounded-sm flex flex-col justify-between transition-all duration-200 min-h-[150px]">
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400" data-en="Total Portfolio Value" data-ar="إجمالي قيمة المحفظة">Total Portfolio Value</span>
        <div class="w-8 h-8 rounded-sm bg-[#102230] border border-[#1e3a50] flex items-center justify-center text-cyan-400">
          <i data-lucide="shield-check" class="w-4 h-4"></i>
        </div>
      </div>
      <div class="flex items-baseline gap-2 mb-2">
        <h3 class="text-3xl font-bold tracking-tight text-white num whitespace-nowrap"><?= number_format($total_contract_value / 1000000, 1) ?></h3>
        <span class="text-xs font-semibold text-slate-400" data-en="Million SAR" data-ar="مليون ريال">Million SAR</span>
      </div>
      <div class="pt-2">
        <div class="meter-bar h-1.5 w-full bg-[#132636] rounded-full overflow-hidden">
          <div class="meter-fill h-full bg-[#f97316] rounded-full" style="width: 85%;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Contract Registry Table Card -->
  <div class="bg-[#0c1924] rounded-sm flex flex-col overflow-hidden border border-[#1a3346]">
    <div class="px-6 py-5 border-b border-[#1a3346] flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-[#09151f]">
      <div>
        <h3 class="text-base font-bold tracking-tight text-white" data-en="Active Contract Registry" data-ar="سجل العقود النشطة">Active Contract Registry</h3>
        <p class="text-xs text-slate-400 mt-0.5" data-en="Comprehensive tracking of take-or-pay, supply commitments, and expiry milestones." data-ar="تتبع شامل لالتزامات التوريد ونقاط انتهاء الصلاحية.">Comprehensive tracking of take-or-pay, supply commitments, and expiry milestones.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
          <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
          <input type="text" id="contractSearchInput" onkeyup="filterContractsTable()" placeholder="Search contract or client..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
        </div>
        <select id="contractStatusFilter" onchange="filterContractsTable()" class="h-9 border border-[#1e3a50] rounded-md text-xs font-mono py-1.5 px-3 bg-[#07111a] text-slate-100 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none">
          <option value="" data-en="All Statuses" data-ar="كل الحالات">All Statuses</option>
          <option value="active" data-en="Active" data-ar="نشط">Active</option>
          <option value="expiring" data-en="Expiring" data-ar="ينتهي قريباً">Expiring</option>
          <option value="expired" data-en="Expired" data-ar="منتهي">Expired</option>
          <option value="terminated" data-en="Terminated" data-ar="مفسوخ">Terminated</option>
        </select>
      </div>
    </div>

    <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
      <table class="w-full text-left rtl:text-right border-collapse" id="contractsTable">
        <thead>
          <tr class="text-[10px] font-mono uppercase tracking-wider text-slate-400 bg-[#09151f] border-b border-[#1a3346]">
            <th class="px-4 py-3 font-semibold whitespace-nowrap" data-en="Contract Ref" data-ar="مرجع العقد">Contract Ref</th>
            <th class="px-4 py-3 font-semibold whitespace-nowrap" data-en="Client Account" data-ar="حساب العميل">Client Account</th>
            <th class="px-4 py-3 font-semibold whitespace-nowrap" data-en="Monthly Quota & Gas" data-ar="الحصة والغاز">Monthly Quota & Gas</th>
            <th class="px-4 py-3 font-semibold whitespace-nowrap" data-en="Start Date" data-ar="تاريخ البدء">Start Date</th>
            <th class="px-4 py-3 font-semibold whitespace-nowrap" data-en="End Date" data-ar="تاريخ الانتهاء">End Date</th>
            <th class="px-4 py-3 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Value (SAR)" data-ar="القيمة (ريال)">Value (SAR)</th>
            <th class="px-4 py-3 font-semibold text-center whitespace-nowrap" data-en="Status" data-ar="الحالة">Status</th>
            <th class="px-4 py-3 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Actions" data-ar="الإجراءات">Actions</th>
          </tr>
        </thead>
        <tbody class="text-xs font-mono divide-y divide-[#152a3a]">
          <?php foreach ($contracts as $c): ?>
          <?php
            $s = $statusStyles[$c['status']] ?? ['bg' => '#0d2a38', 'fg' => '#2dd4bf', 'dot' => '#2dd4bf', 'label' => ucfirst($c['status']), 'dotBorder' => ''];
            $avatarBg = '#102230';
            $avatarFg = '#38bdf8';
            $avatarBorder = 'border:1px solid #1e3a50;';
            $isInactive = in_array($c['status'], ['expired', 'terminated']);
            $rowColor = $isInactive ? 'text-slate-400' : 'text-white';
          ?>
          <tr class="contract-row transition-colors hover:bg-[#102332]/80" data-status="<?= htmlspecialchars($c['status']) ?>">
            <td class="whitespace-nowrap px-4 py-3 font-mono font-bold text-xs <?= $rowColor ?> whitespace-nowrap">
              <?= htmlspecialchars($c['id']) ?>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2.5">
                <span class="w-7 h-7 rounded-sm flex items-center justify-center font-bold text-[10px] flex-shrink-0" style="background:<?= $avatarBg ?>; color:<?= $avatarFg ?>; <?= $avatarBorder ?>"><?= htmlspecialchars($c['initials']) ?></span>
                <div class="flex flex-col min-w-0">
                  <span class="font-medium text-xs truncate max-w-[200px] <?= $isInactive ? 'text-slate-400' : 'text-white' ?>"><?= htmlspecialchars($c['client']) ?></span>
                  <span class="text-[10px] text-slate-400 whitespace-nowrap"><?= $c['corp'] ? 'Corporate Client' : 'Commercial Client' ?></span>
                </div>
              </div>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
              <div class="flex flex-col">
                <span class="text-xs font-semibold text-white"><?= htmlspecialchars((string)$c['quota']) ?> Tons/mo</span>
                <span class="text-[11px] font-mono text-slate-400"><?= htmlspecialchars($c['gas_type']) ?></span>
              </div>
            </td>
            <td class="px-4 py-3 text-xs font-mono text-slate-400 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($c['start_date']))) ?></td>
            <td class="px-4 py-3 text-xs font-mono font-medium whitespace-nowrap <?= $c['status'] === 'expiring' ? 'text-amber-400 font-bold' : 'text-slate-400' ?>"><?= htmlspecialchars(date('d M Y', strtotime($c['end_date']))) ?></td>
            <td class="px-4 py-3 text-right rtl:text-left font-bold font-mono text-xs text-white tabular-nums whitespace-nowrap"><?= number_format($c['value']) ?></td>
            <td class="px-4 py-3 text-center whitespace-nowrap">
              <span class="pill whitespace-nowrap" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;">
                <span class="status-dot" style="background:<?= $s['dot'] ?>;<?= $s['dotBorder'] ?>"></span><?= $s['label'] ?>
              </span>
            </td>
            <td class="px-4 py-3 text-right rtl:text-left whitespace-nowrap">
              <div class="flex items-center justify-end gap-1.5">
                <?php if ($c['status'] === 'expiring'): ?>
                <a href="<?= app_url('modules/crm_sales/renew_contract.php?id=' . urlencode($c['id'])) ?>" class="px-2 py-1 text-xs font-semibold rounded-md border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 transition-all whitespace-nowrap" style="text-decoration: none;" data-en="Renew" data-ar="تجديد">Renew</a>
                <?php endif; ?>
                <a href="<?= app_url('modules/crm_sales/contract_details.php?id=' . urlencode((string)$c['row_id'])) ?>" class="btn-table-action whitespace-nowrap" title="View Contract Details">
                  <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                </a>
                <?php if (has_permission($pdo, 'clients.delete')): ?>
                <form method="POST" action="<?= app_url('modules/crm_sales/client_contracts.php') ?>" class="m-0 p-0 inline-block" onsubmit="return confirm('Are you sure you want to delete this contract?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="contract_id" value="<?= htmlspecialchars((string)$c['row_id']) ?>">
                  <button type="submit" class="btn-table-action hover:!border-rose-500 hover:!text-rose-400 whitespace-nowrap" title="Delete Contract">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($contracts)): ?>
          <tr>
            <td colspan="8" class="text-center py-12 text-sm text-slate-400" data-en="No client contracts registered in database." data-ar="لا توجد عقود مسجلة في قاعدة البيانات.">No client contracts registered in database.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="px-6 py-4 border-t border-[#1a3346] flex flex-col sm:flex-row justify-between items-center gap-3 bg-[#09151f]">
      <span class="text-xs font-mono text-slate-400">Showing <?= count($contracts) > 0 ? '1' : '0' ?>–<?= count($contracts) ?> of <?= count($contracts) ?> agreements</span>
      <div class="flex items-center gap-2">
        <a href="<?= app_url('modules/crm_sales/new_calloff.php') ?>" class="text-xs font-semibold text-slate-300 hover:text-white hover:underline flex items-center gap-1">
          <span data-en="Create Blanket Call-off" data-ar="إنشاء استدعاء شامل">Create Blanket Call-off</span>
          <i data-lucide="arrow-right" class="w-3.5 h-3.5 rtl:rotate-180"></i>
        </a>
      </div>
    </div>
  </div>
</div>
</main>

<script>
function filterContractsTable() {
  const q = (document.getElementById('contractSearchInput')?.value || '').toLowerCase().trim();
  const status = (document.getElementById('contractStatusFilter')?.value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('.contract-row');
  rows.forEach(row => {
    const text = row.innerText.toLowerCase();
    const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
    const matchesQ = !q || text.includes(q);
    const matchesStatus = !status || rowStatus === status;
    if (matchesQ && matchesStatus) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

function exportContractsReport() {
  let csv = [];
  let rows = document.querySelectorAll("#contractsTable tr");
  rows.forEach(row => {
    let cols = row.querySelectorAll("td, th");
    let data = [];
    for (let i = 0; i < cols.length - 1; i++) {
      data.push('"' + cols[i].innerText.replace(/"/g, '""').trim() + '"');
    }
    csv.push(data.join(","));
  });
  let csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
  let downloadLink = document.createElement("a");
  downloadLink.download = "client_contracts_export_" + new Date().toISOString().slice(0,10) + ".csv";
  downloadLink.href = window.URL.createObjectURL(csvFile);
  downloadLink.style.display = "none";
  document.body.appendChild(downloadLink);
  downloadLink.click();
  document.body.removeChild(downloadLink);
}
</script>

<script>
  lucide.createIcons();
</script>
</body>
</html>