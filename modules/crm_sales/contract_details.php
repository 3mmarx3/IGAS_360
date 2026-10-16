<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('clients.view');

$active_page = 'client_contracts';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Client Contracts', 'Contract Details'];

$contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($contract_id <= 0) {
  $firstId = (int)$pdo->query("SELECT id FROM client_contracts ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstId > 0) {
    $contract_id = $firstId;
  } else {
    redirect('modules/crm_sales/client_contracts.php');
  }
}

$stmt = $pdo->prepare("
  SELECT 
    c.*,
    p.company_name,
    p.entity_type,
    p.contact_first_name,
    p.contact_last_name,
    p.job_title,
    p.email,
    p.phone,
    p.country,
    p.city,
    p.address AS partner_address,
    p.cr_number,
    p.tax_id,
    p.segment,
    p.rating,
    p.sales_rep_id,
    p.assigned_to
  FROM client_contracts c
  JOIN partners p ON c.client_id = p.id
  WHERE c.id = :id
  LIMIT 1
");
$stmt->execute(['id' => $contract_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
  redirect('modules/crm_sales/client_contracts.php');
}

if (!check_record_ownership($pdo, 'partners', $contract['client_id'], 'assigned_to') && !check_record_ownership($pdo, 'partners', $contract['client_id'], 'sales_rep_id')) {
  redirect('modules/crm_sales/client_contracts.php?error=unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_csrf();
  require_permission('clients.delete');
  $del = $pdo->prepare("DELETE FROM client_contracts WHERE id = :id");
  $del->execute(['id' => $contract_id]);
  redirect('modules/crm_sales/client_contracts.php?deleted=1');
}

$today = new DateTime();
$start = new DateTime($contract['start_date'] ?? 'now');
$end  = new DateTime($contract['end_date'] ?? '+1 year');
$expiringThreshold = (clone $today)->modify('+60 days');

$status = $contract['status'] ?? 'active';
if ($end >= $today && $end <= $expiringThreshold && $status !== 'terminated' && $status !== 'expired') {
  $status = 'expiring';
}

$statusConfigs = [
  'active'   => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'dot' => 'bg-teal-500', 'label' => 'Active', 'label_ar' => 'نشط'],
  'expiring'  => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Expiring Soon', 'label_ar' => 'ينتهي قريباً'],
  'expired'  => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-slate-400', 'dot' => 'bg-neutral-500', 'label' => 'Expired', 'label_ar' => 'منتهي'],
  'terminated' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Terminated', 'label_ar' => 'ملغي'],
];
$sc = $statusConfigs[$status] ?? $statusConfigs['active'];

$totalDays = max(1, $start->diff($end)->days);
$elapsedDays = $today >= $start ? $today->diff($start)->days : 0;
if ($today > $end) { $elapsedDays = $totalDays; }
$progressPct = min(100, max(0, round(($elapsedDays / $totalDays) * 100)));
$daysRemaining = $today <= $end ? $today->diff($end)->days : 0;

$words = preg_split('/\s+/', trim((string)($contract['company_name'] ?? '')));
$initials = '';
if (count($words) >= 2) {
  $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
} elseif (count($words) === 1 && mb_strlen($words[0]) > 0) {
  $initials = strtoupper(mb_substr($words[0], 0, 2));
}
$initials = $initials ?: 'CL';

$paymentTermsLabel = [
  'cod'  => 'Cash on Delivery',
  'net15' => 'Net 15 Days',
  'net30' => 'Net 30 Days',
  'net45' => 'Net 45 Days',
  'net60' => 'Net 60 Days',
];
$payTerm = $contract['payment_terms'] ?? '';
$payTermDisplay = $paymentTermsLabel[$payTerm] ?? ($payTerm ?: 'Net 30 Days');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contract <?= htmlspecialchars($contract['contract_number']) ?> | I-GAS Enterprise</title>
  
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

    <!-- Telemetry Bar -->
      <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 sm:px-6 flex items-center justify-between text-xs font-mono text-slate-400">
        <div class="flex items-center gap-4 overflow-x-auto no-scrollbar">
          <span class="flex items-center gap-1.5 font-medium text-teal-400 dark:text-teal-400">
            <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
            <span data-en="SYS: NOMINAL" data-ar="النظام: مستقر">SYS: NOMINAL</span>
          </span>
          <span class="text-neutral-300 dark:text-neutral-700">|</span>
          <span data-en="MODULE: SUPPLY AGREEMENTS" data-ar="الوحدة: عقود التوريد">MODULE: SUPPLY AGREEMENTS</span>
          <span class="text-neutral-300 dark:text-neutral-700">|</span>
          <span class="font-mono text-neutral-500">REF: <?= htmlspecialchars($contract['contract_number']) ?></span>
        </div>
        <div class="hidden sm:flex items-center gap-3">
          <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
        </div>
      </div>

      <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">
        <!-- Breadcrumbs & Navigation Back -->
        <div class="flex items-center justify-between">
          <a href="<?= app_url('modules/crm_sales/client_contracts.php') ?>" class="inline-flex items-center gap-2 text-xs font-mono text-neutral-500 hover:text-teal-400 dark:hover:text-teal-400 transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
            <span data-en="Back to Master Contracts Registry" data-ar="العودة لسجل العقود الرئيسية">Back to Master Contracts Registry</span>
          </a>
          <span class="text-xs font-mono text-neutral-400">ID #<?= (int)$contract['id'] ?></span>
        </div>

        <!-- Page Header with Status & Primary CTA -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 border-s-4 border-s-[#f97316]">
          <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-md border border-[#1e3a50] bg-[#09151f] flex items-center justify-center font-mono font-bold text-lg text-slate-200 shrink-0">
              <?= htmlspecialchars($initials) ?>
            </div>
            <div class="space-y-1.5">
              <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-2xl font-bold tracking-tight text-white">
                  <?= htmlspecialchars($contract['contract_number']) ?>
                </h1>
                <span class="px-2.5 py-0.5 text-xs font-mono font-medium rounded-full border <?= $sc['border'] ?> <?= $sc['bg'] ?> <?= $sc['text'] ?> inline-flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                  <span data-en="<?= $sc['label'] ?>" data-ar="<?= $sc['label_ar'] ?>"><?= $sc['label'] ?></span>
                </span>
                <?php if (!empty($contract['gas_type'])): ?>
                  <?= gas_chip($contract['gas_type']) ?>
                <?php endif; ?>
              </div>
              <p class="text-sm text-slate-400 flex items-center gap-2">
                <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode((string)$contract['client_id'])) ?>" class="font-medium text-white hover:text-teal-600 dark:hover:text-teal-400 hover:underline">
                  <?= htmlspecialchars($contract['company_name']) ?>
                </a>
                <span class="text-neutral-300 dark:text-neutral-700">•</span>
                <span><?= htmlspecialchars($contract['entity_type'] ?? 'Corporate') ?></span>
                <span class="text-neutral-300 dark:text-neutral-700">•</span>
                <span class="font-mono text-xs text-neutral-500"><?= htmlspecialchars($contract['city'] ?? '') ?>, <?= htmlspecialchars($contract['country'] ?? 'KSA') ?></span>
              </p>
            </div>
          </div>

          <!-- Action Controls -->
          <div class="flex flex-wrap items-center gap-3">
            <?php if (has_permission($pdo, 'clients.edit')): ?>
            <a href="<?= app_url('modules/crm_sales/renew_contract.php?id=' . urlencode((string)$contract['id'])) ?>" class="px-3.5 py-2 text-xs font-medium border border-[#1e3a50] hover:border-neutral-900 dark:hover:border-white rounded-md text-slate-200 transition-colors inline-flex items-center gap-2">
              <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
              <span data-en="<?= $status === 'expiring' ? 'Renew Contract' : 'Amend / Renew' ?>" data-ar="<?= $status === 'expiring' ? 'تجديد العقد' : 'تعديل / تجديد' ?>">
                <?= $status === 'expiring' ? 'Renew Contract' : 'Amend / Renew' ?>
              </span>
            </a>
            <?php endif; ?>

            <!-- Single Primary CTA: Safety Valve Orange -->
            <a href="<?= app_url('modules/crm_sales/new_calloff.php?client_id=' . urlencode((string)$contract['client_id']) . '&contract_id=' . urlencode((string)$contract['id'])) ?>" class="btn-cta-orange px-4 py-2 text-xs font-semibold rounded-md inline-flex items-center gap-2 transition-all">
              <i data-lucide="truck" class="w-4 h-4"></i>
              <span data-en="Issue Call-off Release" data-ar="إصدار استدعاء توريد">Issue Call-off Release</span>
            </a>

            <?php if (has_permission($pdo, 'clients.delete')): ?>
            <form method="POST" action="" class="m-0 p-0 inline-block" onsubmit="return confirm('Are you sure you want to delete this contract? This action cannot be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="p-2 border border-rose-800 dark:border-rose-900 hover:border-rose-600 rounded-md text-rose-600 hover:bg-rose-950/30 dark:hover:bg-rose-950/40 transition-colors" title="Delete Contract">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>

        <!-- 4 Bento KPI Cards with 3px module accent rules -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Value Card -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
            <div class="flex items-center justify-between text-neutral-500 mb-2">
              <span class="text-xs font-mono uppercase tracking-wider" data-en="Contract Value" data-ar="قيمة العقد">Contract Value</span>
              <i data-lucide="coins" class="w-4 h-4 text-teal-600"></i>
            </div>
            <div class="text-2xl font-mono font-bold text-white">
              <?= number_format((float)($contract['contract_value'] ?? 0)) ?>
              <span class="text-xs font-sans font-normal text-neutral-500">SAR</span>
            </div>
            <p class="text-xs text-neutral-500 mt-1" data-en="Total agreed committed ceiling" data-ar="إجمالي السقف المتفق عليه">Total committed ceiling</p>
          </div>

          <!-- Quota Card -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
            <div class="flex items-center justify-between text-neutral-500 mb-2">
              <span class="text-xs font-mono uppercase tracking-wider" data-en="Monthly Quota" data-ar="الحصة الشهرية">Monthly Quota</span>
              <i data-lucide="gauge" class="w-4 h-4 text-blue-600"></i>
            </div>
            <div class="text-2xl font-mono font-bold text-white">
              <?= number_format((float)($contract['monthly_quota'] ?? 0)) ?>
              <span class="text-xs font-sans font-normal text-neutral-500"><?= htmlspecialchars($contract['unit'] ?? 'm³') ?></span>
            </div>
            <p class="text-xs text-neutral-500 mt-1 flex items-center gap-1.5">
              <span data-en="Gas Product:" data-ar="منتج الغاز:">Gas Product:</span>
              <span class="font-medium text-slate-300"><?= htmlspecialchars($contract['gas_type'] ?? 'N/A') ?></span>
            </p>
          </div>

          <!-- Days Remaining Card -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-[3px] <?= $status === 'expiring' ? 'border-s-amber-600' : 'border-s-cyan-600' ?>">
            <div class="flex items-center justify-between text-neutral-500 mb-2">
              <span class="text-xs font-mono uppercase tracking-wider" data-en="Days Remaining" data-ar="الأيام المتبقية">Days Remaining</span>
              <i data-lucide="clock" class="w-4 h-4 <?= $status === 'expiring' ? 'text-amber-600' : 'text-cyan-600' ?>"></i>
            </div>
            <div class="text-2xl font-mono font-bold <?= $status === 'expiring' ? 'text-amber-400 dark:text-amber-400' : 'text-white' ?>">
              <?= number_format($daysRemaining) ?>
              <span class="text-xs font-sans font-normal text-neutral-500" data-en="Days" data-ar="يوم">Days</span>
            </div>
            <p class="text-xs text-neutral-500 mt-1">
              <span data-en="of total" data-ar="من إجمالي">of total</span> <?= number_format($totalDays) ?> <span data-en="days term" data-ar="يوم مدة العقد">days term</span>
            </p>
          </div>

          <!-- Progress Card -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
            <div class="flex items-center justify-between text-neutral-500 mb-2">
              <span class="text-xs font-mono uppercase tracking-wider" data-en="Term Progress" data-ar="تقدم مدة العقد">Term Progress</span>
              <i data-lucide="activity" class="w-4 h-4 text-slate-600"></i>
            </div>
            <div class="text-2xl font-mono font-bold text-white">
              <?= $progressPct ?><span class="text-xs font-sans font-normal text-neutral-500">%</span>
            </div>
            <div class="w-full bg-[#09151f] h-2 rounded-none mt-2 overflow-hidden border border-[#1e3a50]">
              <div class="bg-teal-600 h-full" style="width: <?= $progressPct ?>%;"></div>
            </div>
          </div>
        </div>

        <!-- Two-Column Technical Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left: Specifications & Terms (2 cols) -->
          <div class="lg:col-span-2 space-y-6">
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md">
              <div class="px-6 py-4 border-b border-[#1a3346] flex items-center justify-between">
                <div>
                  <h2 class="text-sm font-semibold text-white" data-en="Master Supply Terms & SLA" data-ar="بنود التوريد ومستوى الخدمة">
                    Master Supply Terms & SLA
                  </h2>
                  <p class="text-xs text-neutral-500" data-en="Contractual parameters, delivery schedules, and invoicing." data-ar="المعايير التعاقدية وجداول التسليم والفوترة.">
                    Contractual parameters, delivery schedules, and invoicing.
                  </p>
                </div>
                <span class="px-2 py-0.5 text-[11px] font-mono rounded bg-[#09151f] text-slate-400 border border-[#1e3a50]">
                  EN-10204 / ISO 14175
                </span>
              </div>

              <div class="p-6 divide-y divide-[#152a3a] dark:divide-neutral-800 text-sm">
                <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                  <span class="text-xs font-mono text-neutral-500" data-en="Contract Reference" data-ar="الرقم التعاقدي المرجعي">Contract Reference</span>
                  <span class="font-mono font-medium text-white"><?= htmlspecialchars($contract['contract_number']) ?></span>
                </div>
                <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                  <span class="text-xs font-mono text-neutral-500" data-en="Designated Gas Type" data-ar="نوع الغاز المخصص">Designated Gas Type</span>
                  <span class="font-medium text-white flex items-center gap-2">
                    <?= htmlspecialchars($contract['gas_type'] ?? 'Bulk Gas') ?>
                    <?php if (!empty($contract['gas_type'])): ?>
                      <?= gas_chip($contract['gas_type']) ?>
                    <?php endif; ?>
                  </span>
                </div>
                <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                  <span class="text-xs font-mono text-neutral-500" data-en="Monthly Guaranteed Quota" data-ar="الحصة الشهرية المضمونة">Monthly Guaranteed Quota</span>
                  <span class="font-mono font-semibold text-white">
                    <?= number_format((float)($contract['monthly_quota'] ?? 0)) ?> <?= htmlspecialchars($contract['unit'] ?? 'm³') ?>
                  </span>
                </div>
                <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                  <span class="text-xs font-mono text-neutral-500" data-en="Commencement Date" data-ar="تاريخ بدء السريان">Commencement Date</span>
                  <span class="font-mono text-slate-200">
                    <?= !empty($contract['start_date']) ? htmlspecialchars(date('d M Y', strtotime($contract['start_date']))) : '—' ?>
                  </span>
                </div>
                <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                  <span class="text-xs font-mono text-neutral-500" data-en="Expiration Date" data-ar="تاريخ انتهاء السريان">Expiration Date</span>
                  <span class="font-mono font-medium <?= $status === 'expiring' ? 'text-amber-600' : 'text-slate-200' ?>">
                    <?= !empty($contract['end_date']) ? htmlspecialchars(date('d M Y', strtotime($contract['end_date']))) : '—' ?>
                  </span>
                </div>
                <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                  <span class="text-xs font-mono text-neutral-500" data-en="Payment Settlement Terms" data-ar="شروط سداد الفواتير">Payment Settlement Terms</span>
                  <span class="font-medium text-white"><?= htmlspecialchars($payTermDisplay) ?></span>
                </div>
                <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                  <span class="text-xs font-mono text-neutral-500" data-en="Formal Invoicing Address" data-ar="عنوان الفوترة المعتمد">Formal Invoicing Address</span>
                  <span class="text-slate-300 max-w-sm text-end">
                    <?= htmlspecialchars($contract['billing_address'] ?? ($contract['partner_address'] ?? '—')) ?>
                  </span>
                </div>
                <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                  <span class="text-xs font-mono text-neutral-500" data-en="Committed Contract Ceiling" data-ar="سقف التعاقد الإجمالي">Committed Contract Ceiling</span>
                  <span class="font-mono font-bold text-white">
                    <?= number_format((float)($contract['contract_value'] ?? 0)) ?> SAR
                  </span>
                </div>
              </div>
            </div>

            <!-- Contract Notes & Special Clauses -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h3 class="text-sm font-semibold text-white mb-2" data-en="Operational Notes & Special Clauses" data-ar="الملاحظات التشغيلية والشروط الخاصة">
                Operational Notes & Special Clauses
              </h3>
              <p class="text-xs text-neutral-500 mb-4" data-en="Emergency dispatch thresholds, purity certificates, and delivery logistics instructions." data-ar="عتبات التوريد الطارئ وشهادات النقاوة وتعليمات لوجستيات التوصيل.">
                Emergency dispatch thresholds, purity certificates, and delivery logistics instructions.
              </p>
              <div class="bg-[#09151f] p-4 border border-[#1a3346] rounded text-xs font-mono text-slate-300 leading-relaxed whitespace-pre-wrap">
                <?= !empty($contract['notes']) ? htmlspecialchars($contract['notes']) : 'Standard gas supply protocol applied. No custom clauses logged.' ?>
              </div>
            </div>
          </div>

          <!-- Right: Customer Account & Metadata (1 col) -->
          <div class="space-y-6">
            <!-- Account Details Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h3 class="text-sm font-semibold text-white mb-4" data-en="Client Commercial Account" data-ar="الحساب التجاري للعميل">
                Client Commercial Account
              </h3>
              
              <div class="flex items-center gap-3 pb-4 mb-4 border-b border-neutral-100 border-[#1a3346]">
                <div class="w-10 h-10 rounded border border-[#1e3a50] bg-[#09151f] flex items-center justify-center font-mono font-bold text-slate-300">
                  <?= htmlspecialchars($initials) ?>
                </div>
                <div class="min-w-0">
                  <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode((string)$contract['client_id'])) ?>" class="text-sm font-bold text-white hover:text-teal-600 dark:hover:text-teal-400 hover:underline truncate block">
                    <?= htmlspecialchars($contract['company_name']) ?>
                  </a>
                  <span class="text-xs text-neutral-500"><?= htmlspecialchars($contract['entity_type'] ?? 'Corporate') ?> · <?= htmlspecialchars($contract['segment'] ?? 'Industrial') ?></span>
                </div>
              </div>

              <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between py-1">
                  <span class="text-neutral-500" data-en="Primary Contact" data-ar="جهة الاتصال">Primary Contact</span>
                  <span class="font-medium text-white"><?= htmlspecialchars(trim(($contract['contact_first_name'] ?? '') . ' ' . ($contract['contact_last_name'] ?? ''))) ?></span>
                </div>
                <?php if (!empty($contract['job_title'])): ?>
                <div class="flex items-center justify-between py-1">
                  <span class="text-neutral-500" data-en="Designation" data-ar="المسمى الوظيفي">Designation</span>
                  <span class="text-slate-200"><?= htmlspecialchars($contract['job_title']) ?></span>
                </div>
                <?php endif; ?>
                <div class="flex items-center justify-between py-1">
                  <span class="text-neutral-500" data-en="Email" data-ar="البريد الإلكتروني">Email</span>
                  <a href="mailto:<?= htmlspecialchars($contract['email'] ?? '') ?>" class="font-mono text-teal-600 hover:underline truncate max-w-[180px]"><?= htmlspecialchars($contract['email'] ?? '—') ?></a>
                </div>
                <div class="flex items-center justify-between py-1">
                  <span class="text-neutral-500" data-en="Phone" data-ar="الهاتف">Phone</span>
                  <span class="font-mono text-white"><?= htmlspecialchars($contract['phone'] ?? '—') ?></span>
                </div>
                <div class="flex items-center justify-between py-1">
                  <span class="text-neutral-500" data-en="CR Number" data-ar="السجل التجاري">CR Number</span>
                  <span class="font-mono text-slate-200"><?= htmlspecialchars($contract['cr_number'] ?? '—') ?></span>
                </div>
                <div class="flex items-center justify-between py-1">
                  <span class="text-neutral-500" data-en="VAT / Tax ID" data-ar="الرقم الضريبي">VAT / Tax ID</span>
                  <span class="font-mono text-slate-200"><?= htmlspecialchars($contract['tax_id'] ?? '—') ?></span>
                </div>
              </div>

              <div class="mt-5 pt-4 border-t border-neutral-100 border-[#1a3346]">
                <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode((string)$contract['client_id'])) ?>" class="w-full py-2 px-3 text-xs font-semibold text-center rounded border border-[#1e3a50] hover:border-neutral-900 dark:hover:border-white text-slate-200 block transition-colors">
                  <span data-en="Open Full Customer 360" data-ar="فتح ملف العميل الكامل">Open Full Customer 360</span>
                </a>
              </div>
            </div>

            <!-- System Record Audit -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h3 class="text-sm font-semibold text-white mb-3" data-en="Audit & Compliance Metadata" data-ar="بيانات التدقيق والامتثال">
                Audit & Compliance Metadata
              </h3>
              <div class="space-y-2.5 text-xs font-mono text-slate-400">
                <div class="flex justify-between py-1 border-b border-neutral-100 border-[#1a3346]">
                  <span class="text-neutral-400">CREATED:</span>
                  <span><?= !empty($contract['created_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime($contract['created_at']))) : '—' ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-neutral-100 border-[#1a3346]">
                  <span class="text-neutral-400">UPDATED:</span>
                  <span><?= !empty($contract['updated_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime($contract['updated_at']))) : '—' ?></span>
                </div>
                <div class="flex justify-between py-1">
                  <span class="text-neutral-400">HASH:</span>
                  <span class="truncate max-w-[140px] text-neutral-500">SHA256:<?= substr(hash('sha256', (string)$contract['id'] . ($contract['contract_number'] ?? '')), 0, 10) ?></span>
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