<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/**
 * ============================================================================
 * I-GAS ENTERPRISE CRM — SALES REPRESENTATIVE PIPELINE DASHBOARD
 * ============================================================================
 * File: rep_dashboard.php
 *
 * Demonstrates:
 * 1. Strict 1-to-N Sales Rep to Client isolation using PDO prepared statements.
 * 2. Active Quotations & Pipeline value tracking per assigned client.
 * 3. Actual Client Consumptions / Physical deliveries tracking.
 * 4. Row-Level Security & IDOR prevention with `is_owner()` and `require_client_access()`.
 * ============================================================================
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';

// ----------------------------------------------------------------------------
// 1. AUTHENTICATION & ACCESS GUARD
// ----------------------------------------------------------------------------
require_authenticated();
require_permission('clients.view');

// Allow authorized administrators to view pipeline under another rep for audit
if (isset($_GET['switch_user']) && is_admin($pdo)) {
  $switchId = (int)$_GET['switch_user'];
  $stmtSwitch = $pdo->prepare("SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1");
  $stmtSwitch->execute([$switchId]);
  $targetUser = $stmtSwitch->fetch();
  if ($targetUser) {
    $_SESSION['user_id'] = (int)$targetUser['id'];
    $_SESSION['user_name'] = $targetUser['full_name'];
    $_SESSION['user_email'] = $targetUser['email'];
    $_SESSION['role'] = $targetUser['role'];
  }
  header('Location: ' . app_url('rep_dashboard.php'));
  exit;
}

if (!function_exists('get_current_user_name')) {
  function get_current_user_name(): string {
    return $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Authorized User';
  }
}

if (!function_exists('require_client_access')) {
  function require_client_access(int $clientId, PDO $pdo): void {
    if (is_admin() || (function_exists('is_sales_manager') && is_sales_manager())) {
      return;
    }
    $userId = get_current_user_id();
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ? AND sales_rep_id = ? LIMIT 1");
    $stmt->execute([$clientId, $userId]);
    if (!$stmt->fetch()) {
      http_response_code(403);
      die("Access Denied: You do not have permission to access client #{$clientId}.");
    }
  }
}

$currentUserId = get_current_user_id();
$currentUserName = get_current_user_name();
$currentUserRole = get_current_user_role();
$isAdminOrManager = is_admin() || (function_exists('is_sales_manager') && is_sales_manager());

// ----------------------------------------------------------------------------
// 2. IDOR INTERACTIVE TEST HANDLER
// ----------------------------------------------------------------------------
$selectedClient = null;
if (isset($_GET['inspect_client_id'])) {
  $clientId = (int)$_GET['inspect_client_id'];
  if (function_exists('require_client_access')) {
    require_client_access($clientId, $pdo);
  }
  
  $stmtInspect = $pdo->prepare("
    SELECT c.*, u.full_name AS rep_name, cc.full_name AS contact_person, cc.phone AS contact_phone, cc.email AS contact_email
    FROM clients c
    INNER JOIN users u ON u.id = c.sales_rep_id
    LEFT JOIN client_contacts cc ON cc.client_id = c.id AND cc.is_primary = 1
    WHERE c.id = ?
    LIMIT 1
  ");
  $stmtInspect->execute([$clientId]);
  $selectedClient = $stmtInspect->fetch();
}

// ----------------------------------------------------------------------------
// 3. ROW-LEVEL SECURITY DATA FETCHING
// ----------------------------------------------------------------------------

// A. Fetch Assigned Clients with pipeline aggregates
$sqlClients = "
  SELECT 
    c.id,
    c.client_code,
    c.company_name,
    c.industry,
    c.city,
    c.credit_limit,
    c.current_balance,
    c.status,
    c.sales_rep_id,
    u.full_name AS sales_rep_name,
    COUNT(DISTINCT q.id) AS total_quotes,
    COALESCE(SUM(CASE WHEN q.status IN ('draft', 'sent', 'accepted') THEN q.total_amount ELSE 0 END), 0) AS active_pipeline_value,
    COUNT(DISTINCT so.id) AS total_orders,
    COALESCE(SUM(cs.quantity_consumed), 0) AS total_volume_consumed
  FROM clients c
  INNER JOIN users u ON u.id = c.sales_rep_id
  LEFT JOIN quotations q ON q.client_id = c.id
  LEFT JOIN sales_orders so ON so.client_id = c.id
  LEFT JOIN consumptions cs ON cs.client_id = c.id
  WHERE (? = 1 OR c.sales_rep_id = ?)
  GROUP BY c.id, c.client_code, c.company_name, c.industry, c.city, c.credit_limit, c.current_balance, c.status, c.sales_rep_id, u.full_name
  ORDER BY c.company_name ASC
";
$stmtClients = $pdo->prepare($sqlClients);
$stmtClients->execute([$isAdminOrManager ? 1 : 0, $currentUserId]);
$assignedClients = $stmtClients->fetchAll();

// B. Fetch Active Quotations for Assigned Clients
$sqlQuotes = "
  SELECT 
    q.id,
    q.quotation_number,
    q.issue_date,
    q.expiry_date,
    q.status,
    q.total_amount,
    c.id AS client_id,
    c.company_name,
    c.client_code
  FROM quotations q
  INNER JOIN clients c ON c.id = q.client_id
  WHERE (? = 1 OR q.sales_rep_id = ?)
   AND q.status IN ('draft', 'sent', 'accepted')
  ORDER BY q.issue_date DESC
  LIMIT 6
";
$stmtQuotes = $pdo->prepare($sqlQuotes);
$stmtQuotes->execute([$isAdminOrManager ? 1 : 0, $currentUserId]);
$activeQuotations = $stmtQuotes->fetchAll();

// C. Fetch Recent Consumptions / Gas Deliveries for Assigned Clients
$sqlConsumptions = "
  SELECT 
    cs.id,
    cs.consumption_code,
    cs.reading_date,
    cs.quantity_consumed,
    cs.unit,
    cs.flow_rate_or_pressure,
    cs.delivery_status,
    cs.batch_or_cylinder_id,
    p.name AS product_name,
    c.id AS client_id,
    c.company_name,
    c.client_code
  FROM consumptions cs
  INNER JOIN clients c ON c.id = cs.client_id
  INNER JOIN products p ON p.id = cs.product_id
  WHERE (? = 1 OR cs.sales_rep_id = ?)
  ORDER BY cs.reading_date DESC
  LIMIT 6
";
$stmtConsumptions = $pdo->prepare($sqlConsumptions);
$stmtConsumptions->execute([$isAdminOrManager ? 1 : 0, $currentUserId]);
$recentConsumptions = $stmtConsumptions->fetchAll();

// D. KPI Aggregates
$kpiTotalClients  = count($assignedClients);
$kpiPipelineValue = array_sum(array_column($activeQuotations, 'total_amount'));
$kpiTotalBalance  = array_sum(array_column($assignedClients, 'current_balance'));
$kpiTotalConsumed = array_sum(array_column($assignedClients, 'total_volume_consumed'));

$active_page = 'rep_dashboard';
$base_url  = './';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Sales Representative Hub'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Sales Rep Pipeline & Client Hub | I-GAS Industrial" data-ar="مركز مندوبي المبيعات والعملاء | I-GAS Industrial">Sales Rep Pipeline & Client Hub | I-GAS Industrial</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="assets/css/theme.css">
  <link rel="stylesheet" href="assets/css/main.css">
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

<?php include __DIR__ . '/includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

  <?php include __DIR__ . '/includes/header.php'; ?>

  <!-- Sub-Header / Telemetry Strip & Role Impersonation -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0 text-slate-400">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-cyan-400">
        <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
        <span data-en="ROW-LEVEL SECURITY GATEWAY & PIPELINE MONITOR" data-ar="بوابة عزل الصلاحيات ومراقبة مسار المبيعات">ROW-LEVEL SECURITY GATEWAY & PIPELINE MONITOR</span>
      </span>
      <span class="text-[#1a3346]">|</span>
      <span class="text-slate-400">
        <span data-en="AUTH:" data-ar="المستخدم:">AUTH:</span>
        <strong class="text-white font-mono"><?= htmlspecialchars($currentUserName) ?> (<?= strtoupper($currentUserRole) ?>)</strong>
      </span>
    </div>
    <div class="flex items-center gap-1.5 text-xs font-mono">
      <span class="text-slate-500 hidden sm:inline" data-en="SWITCH:" data-ar="تبديل:">SWITCH:</span>
      <a href="?switch_user=2" class="px-2 py-0.5 rounded-xs border <?= $currentUserId === 2 ? 'border-orange-500 bg-orange-950/40 text-orange-400 font-bold' : 'border-[#1e3a50] bg-[#102230] text-slate-400 hover:text-white' ?>">Tariq</a>
      <a href="?switch_user=3" class="px-2 py-0.5 rounded-xs border <?= $currentUserId === 3 ? 'border-orange-500 bg-orange-950/40 text-orange-400 font-bold' : 'border-[#1e3a50] bg-[#102230] text-slate-400 hover:text-white' ?>">Fatima</a>
      <a href="?switch_user=1" class="px-2 py-0.5 rounded-xs border <?= $currentUserId === 1 ? 'border-orange-500 bg-orange-950/40 text-orange-400 font-bold' : 'border-[#1e3a50] bg-[#102230] text-slate-400 hover:text-white' ?>">Admin</a>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6 max-w-7xl mx-auto w-full">

    <!-- Top Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="CRM Operations Center" data-ar="مركز عمليات المبيعات">CRM Operations Center</span>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-500 rtl:rotate-180"></i>
          <span class="text-xs font-mono uppercase tracking-wider text-white font-semibold" data-en="Pipeline Hub" data-ar="مسار المبيعات">Pipeline Hub</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white">
          <?= $isAdminOrManager ? 'Enterprise Client Distribution & Sales Hub' : 'My Assigned Clients & Pipeline Dashboard' ?>
        </h1>
        <p class="text-xs text-slate-400 mt-1">
          <?= $isAdminOrManager 
            ? 'Administrative clearance active: Inspecting all customer accounts and telemetry across all sales representatives.'
            : 'Strict Row-Level Isolation active: Viewing only client accounts and pipeline records assigned to your User ID.' ?>
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <a href="<?= app_url('modules/crm_sales/clients_directory.php') ?>" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 transition-colors">
          <i data-lucide="users" class="w-3.5 h-3.5"></i>
          <span data-en="Clients Directory" data-ar="دليل العملاء">Clients Directory</span>
        </a>
        <a href="<?= app_url('modules/crm_sales/new_quotation.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
          <i data-lucide="file-plus-2" class="w-4 h-4"></i>
          <span data-en="Create Quotation" data-ar="إنشاء عرض سعر جديد">Create Quotation</span>
        </a>
      </div>
    </div>

    <!-- Metric Bento Cards (4-col) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- Assigned Clients -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Assigned Clients" data-ar="العملاء المعينون">Assigned Clients</span>
          <i data-lucide="briefcase" class="w-4 h-4 text-cyan-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $kpiTotalClients ?></span>
          <span class="text-xs font-mono text-slate-400" data-en="Accounts" data-ar="حساب">Accounts</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-cyan-800/60 bg-[#0d2a38] text-cyan-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="shield-check" class="w-3 h-3"></i>
            <span>RLS Isolated #<?= $currentUserId ?></span>
          </span>
        </div>
      </div>

      <!-- Active Pipeline Value -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Active Pipeline Value" data-ar="قيمة عروض الأسعار النشطة">Active Pipeline Value</span>
          <i data-lucide="trending-up" class="w-4 h-4 text-cyan-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= number_format($kpiPipelineValue, 2) ?></span>
          <span class="text-xs font-mono text-slate-400" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-[#234257] bg-[#112433] text-slate-300 text-[11px] font-mono rounded-xs">
            <span><?= count($activeQuotations) ?> <span data-en="Active Quotes" data-ar="عروض مفتوحة">Active Quotes</span></span>
          </span>
        </div>
      </div>

      <!-- Total Volume Consumed -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Delivered Volume" data-ar="الحجم المورد الفعلي">Delivered Volume</span>
          <i data-lucide="gauge" class="w-4 h-4 text-teal-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= number_format($kpiTotalConsumed, 0) ?></span>
          <span class="text-xs font-mono text-slate-400" data-en="L / Units" data-ar="لتر / وحدة">L / Units</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-cyan-800/60 bg-[#0d2a38] text-cyan-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="activity" class="w-3 h-3"></i>
            <span data-en="Live Telemetry Feeds" data-ar="بيانات استهلاك حية">Live Telemetry Feeds</span>
          </span>
        </div>
      </div>

      <!-- Receivables Balance -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Receivables Balance" data-ar="رصيد الذمم المستحقة">Receivables Balance</span>
          <i data-lucide="clock" class="w-4 h-4 text-amber-500"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-amber-400"><?= number_format($kpiTotalBalance, 2) ?></span>
          <span class="text-xs font-mono text-slate-400" data-en="SAR" data-ar="ريال">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-amber-800/60 bg-amber-950/40 text-amber-400 text-[11px] font-mono rounded-xs">
            <span data-en="Awaiting Settlement" data-ar="بانتظار التحصيل">Awaiting Settlement</span>
          </span>
        </div>
      </div>

    </div>

    <!-- Selected Client Inspection Card (when inspected) -->
    <?php if ($selectedClient): ?>
    <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-cyan-500 rounded-xs p-6 space-y-4">
      <div class="flex justify-between items-start pb-3 border-b border-[#1a3346]">
        <div>
          <div class="inline-flex items-center gap-1.5 px-2 py-0.5 border border-cyan-800/60 bg-[#0d2a38] text-cyan-400 text-[11px] font-mono rounded-xs mb-2">
            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
            <span>ACCESS GRANTED: VERIFIED CLIENT RECORD</span>
          </div>
          <h2 class="text-lg font-bold font-oxanium text-white"><?= htmlspecialchars($selectedClient['company_name']) ?></h2>
          <p class="text-xs font-mono text-slate-400">Code: <?= htmlspecialchars($selectedClient['client_code']) ?> · Assigned Sales Rep: <?= htmlspecialchars($selectedClient['rep_name']) ?></p>
        </div>
        <a href="<?= app_url('rep_dashboard.php') ?>" class="text-xs font-mono text-slate-400 hover:text-white flex items-center gap-1">
          <i data-lucide="x" class="w-3.5 h-3.5"></i>
          <span data-en="Close" data-ar="إغلاق">Close</span>
        </a>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
        <div>
          <span class="text-slate-400 uppercase font-mono text-[10px] block mb-1">Primary Contact</span>
          <span class="font-bold text-sm text-white block"><?= htmlspecialchars($selectedClient['contact_person'] ?? 'N/A') ?></span>
          <span class="font-mono text-slate-300 block mt-0.5"><?= htmlspecialchars($selectedClient['contact_email'] ?? '') ?></span>
          <span class="font-mono text-slate-300 block"><?= htmlspecialchars($selectedClient['contact_phone'] ?? '') ?></span>
        </div>
        <div>
          <span class="text-slate-400 uppercase font-mono text-[10px] block mb-1">Commercial Details</span>
          <span class="font-mono text-slate-200 block">CR: <?= htmlspecialchars($selectedClient['cr_number'] ?? 'N/A') ?></span>
          <span class="font-mono text-slate-200 block">VAT ID: <?= htmlspecialchars($selectedClient['tax_id'] ?? 'N/A') ?></span>
          <span class="block mt-1 text-slate-300">Address: <?= htmlspecialchars($selectedClient['address'] ?? 'Not specified') ?></span>
        </div>
        <div>
          <span class="text-slate-400 uppercase font-mono text-[10px] block mb-1">Financial Terms</span>
          <span class="font-mono text-slate-200 block">Credit Limit: <?= number_format((float)($selectedClient['credit_limit'] ?? 0), 2) ?> SAR</span>
          <span class="font-mono font-bold text-amber-400 block">Current Balance: <?= number_format((float)($selectedClient['current_balance'] ?? 0), 2) ?> SAR</span>
          <span class="block text-slate-300 mt-1">Terms: <?= htmlspecialchars($selectedClient['payment_terms'] ?? 'Standard 30 Days') ?></span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Assigned Clients Table -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
      <div class="p-5 border-b border-[#1a3346] flex items-center justify-between bg-[#09151f]">
        <div class="flex items-center gap-2">
          <span class="w-1.5 h-3 bg-cyan-400 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="building" class="w-4 h-4 text-cyan-400"></i>
            <span data-en="Assigned Client Accounts (1-to-N Isolation)" data-ar="حسابات العملاء المخصصة (عزل 1 إلى متعدد)">Assigned Client Accounts (1-to-N Isolation)</span>
          </h2>
        </div>
        <span class="text-xs font-mono text-slate-400"><?= count($assignedClients) ?> <span data-en="accounts" data-ar="حسابات">accounts</span></span>
      </div>

      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-left rtl:text-right border-collapse text-xs">
          <thead>
            <tr class="text-[10px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
              <th class="px-3 py-2.5 font-semibold" data-en="Client Code" data-ar="رمز العميل">Client Code</th>
              <th class="px-3 py-2.5 font-semibold" data-en="Company Name" data-ar="اسم المنشأة">Company Name</th>
              <th class="px-3 py-2.5 font-semibold" data-en="City" data-ar="المدينة">City</th>
              <?php if ($isAdminOrManager): ?>
              <th class="px-3 py-2.5 font-semibold" data-en="Assigned Rep" data-ar="المندوب المعين">Assigned Rep</th>
              <?php endif; ?>
              <th class="px-3 py-2.5 font-semibold text-right rtl:text-left" data-en="Quotes" data-ar="العروض">Quotes</th>
              <th class="px-3 py-2.5 font-semibold text-right rtl:text-left" data-en="Orders" data-ar="الطلبات">Orders</th>
              <th class="px-3 py-2.5 font-semibold text-right rtl:text-left" data-en="Gas Consumed" data-ar="الغاز المستهلك">Gas Consumed</th>
              <th class="px-3 py-2.5 font-semibold text-right rtl:text-left" data-en="Balance (SAR)" data-ar="الرصيد (ريال)">Balance (SAR)</th>
              <th class="px-3 py-2.5 font-semibold text-right rtl:text-left" data-en="Actions" data-ar="الإجراءات">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a]">
            <?php if (empty($assignedClients)): ?>
              <tr>
                <td colspan="<?= $isAdminOrManager ? 9 : 8 ?>" class="px-3 py-8 text-center font-mono text-slate-500 text-xs" data-en="No clients currently assigned to this user ID." data-ar="لا يوجد عملاء مخصصين لهذا المستخدم حالياً.">
                  No clients currently assigned to this user ID.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($assignedClients as $client): ?>
              <tr class="hover:bg-[#102332] transition-colors">
                <td class="px-3 py-2.5 font-mono font-semibold text-white"><?= htmlspecialchars($client['client_code']) ?></td>
                <td class="px-3 py-2.5">
                  <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . $client['id']) ?>" class="font-medium text-white hover:text-cyan-400">
                    <?= htmlspecialchars($client['company_name']) ?>
                  </a>
                  <span class="block text-[10px] text-slate-400"><?= htmlspecialchars($client['industry'] ?? 'General') ?></span>
                </td>
                <td class="px-3 py-2.5 text-slate-300"><?= htmlspecialchars($client['city']) ?></td>
                <?php if ($isAdminOrManager): ?>
                <td class="px-3 py-2.5">
                  <span class="inline-flex items-center px-1.5 py-0.5 border border-cyan-800/60 bg-[#0d2a38] text-cyan-400 font-mono text-[10px] rounded-xs">
                    <?= htmlspecialchars($client['sales_rep_name']) ?> (#<?= $client['sales_rep_id'] ?>)
                  </span>
                </td>
                <?php endif; ?>
                <td class="px-3 py-2.5 text-right rtl:text-left font-mono font-medium text-slate-200"><?= $client['total_quotes'] ?></td>
                <td class="px-3 py-2.5 text-right rtl:text-left font-mono font-medium text-slate-200"><?= $client['total_orders'] ?></td>
                <td class="px-3 py-2.5 text-right rtl:text-left font-mono font-bold text-teal-400"><?= number_format((float)$client['total_volume_consumed'], 1) ?> L</td>
                <td class="px-3 py-2.5 text-right rtl:text-left font-mono font-bold <?= $client['current_balance'] > 0 ? 'text-amber-400' : 'text-slate-500' ?>">
                  <?= number_format((float)$client['current_balance'], 2) ?>
                </td>
                <td class="px-3 py-2.5 text-right rtl:text-left whitespace-nowrap">
                  <div class="inline-flex items-center justify-end gap-1.5">
                    <a href="?inspect_client_id=<?= $client['id'] ?>" class="px-2 py-1 rounded-xs border border-cyan-800/60 bg-[#0d2a38] text-cyan-400 hover:bg-[#11384b] text-xs font-mono transition-colors inline-flex items-center gap-1" title="Test IDOR Isolation Safe">
                      <i data-lucide="shield-check" class="w-3 h-3"></i> Inspect
                    </a>
                    <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . $client['id']) ?>" class="btn-table-action whitespace-nowrap" title="View Full Profile">
                      <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- IDOR Proof Simulation Panel -->
      <div class="p-3.5 border-t border-[#1a3346] bg-[#09151f] flex flex-wrap items-center justify-between gap-3 text-xs font-mono text-slate-400">
        <div class="flex items-center gap-2">
          <i data-lucide="shield-alert" class="w-4 h-4 text-amber-500"></i>
          <span><strong>IDOR Defense Simulator:</strong> Test unauthorized access to cross-rep records:</span>
        </div>
        <div>
          <a href="?inspect_client_id=3" class="px-2.5 py-1 border border-rose-800/60 bg-rose-950/40 text-rose-400 rounded-xs hover:bg-rose-900/60 transition-colors font-semibold" title="Attempt to inspect Client ID #3">
            Attempt IDOR Access to Client #3
          </a>
        </div>
      </div>
    </div>

    <!-- Two Column Grid: Pipeline Tracking (Quotations & Consumptions) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- 1. Active Quotations -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-[#1a3346] flex items-center justify-between bg-[#09151f]">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-cyan-400 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
              <i data-lucide="file-text" class="w-4 h-4 text-cyan-400"></i>
              <span data-en="Active Pipeline Quotations" data-ar="عروض الأسعار المفتوحة بالمسار">Active Pipeline Quotations</span>
            </h2>
          </div>
          <span class="text-xs font-mono text-slate-400"><?= count($activeQuotations) ?> quotes</span>
        </div>

        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse text-xs">
            <thead>
              <tr class="text-[10px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-3 py-2.5 font-semibold" data-en="Quote #" data-ar="رقم العرض">Quote #</th>
                <th class="px-3 py-2.5 font-semibold" data-en="Client" data-ar="العميل">Client</th>
                <th class="px-3 py-2.5 font-semibold text-center" data-en="Status" data-ar="الحالة">Status</th>
                <th class="px-3 py-2.5 font-semibold text-right rtl:text-left" data-en="Value (SAR)" data-ar="القيمة (ريال)">Value (SAR)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a]">
              <?php if (empty($activeQuotations)): ?>
                <tr>
                  <td colspan="4" class="px-3 py-8 text-center text-slate-500 font-mono text-xs" data-en="No active quotations found." data-ar="لا توجد عروض أسعار نشطة.">No active quotations found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($activeQuotations as $q): ?>
                <tr class="hover:bg-[#102332] transition-colors">
                  <td class="px-3 py-2.5 font-mono font-semibold text-white"><?= htmlspecialchars($q['quotation_number']) ?></td>
                  <td class="px-3 py-2.5">
                    <span class="font-medium text-white truncate block"><?= htmlspecialchars($q['company_name']) ?></span>
                    <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($q['client_code']) ?></span>
                  </td>
                  <td class="px-3 py-2.5 text-center">
                    <?php if ($q['status'] === 'accepted'): ?>
                      <span class="inline-flex items-center px-1.5 py-0.5 border border-emerald-800/60 bg-emerald-950/40 text-emerald-400 text-[10px] font-mono rounded-xs">Accepted</span>
                    <?php elseif ($q['status'] === 'sent'): ?>
                      <span class="inline-flex items-center px-1.5 py-0.5 border border-cyan-800/60 bg-[#0d2a38] text-cyan-400 text-[10px] font-mono rounded-xs">Sent to Client</span>
                    <?php else: ?>
                      <span class="inline-flex items-center px-1.5 py-0.5 border border-[#234257] bg-[#112433] text-slate-300 text-[10px] font-mono rounded-xs">Draft</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-3 py-2.5 text-right rtl:text-left font-mono font-bold text-white"><?= number_format((float)$q['total_amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 2. Recent Client Consumptions / Physical Deliveries -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-[#1a3346] flex items-center justify-between bg-[#09151f]">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-teal-400 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
              <i data-lucide="gauge" class="w-4 h-4 text-teal-400"></i>
              <span data-en="Client Consumptions & Deliveries" data-ar="استهلاك وتوريدات العملاء">Client Consumptions & Deliveries</span>
            </h2>
          </div>
          <span class="text-xs font-mono text-slate-400"><?= count($recentConsumptions) ?> events</span>
        </div>

        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse text-xs">
            <thead>
              <tr class="text-[10px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-3 py-2.5 font-semibold" data-en="Ref Code" data-ar="رمز المرجع">Ref Code</th>
                <th class="px-3 py-2.5 font-semibold" data-en="Client & Gas" data-ar="العميل والغاز">Client & Gas</th>
                <th class="px-3 py-2.5 text-right rtl:text-left font-semibold" data-en="Volume" data-ar="الحجم">Volume</th>
                <th class="px-3 py-2.5 text-right rtl:text-left font-semibold" data-en="Telemetry" data-ar="القياس">Telemetry</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a]">
              <?php if (empty($recentConsumptions)): ?>
                <tr>
                  <td colspan="4" class="px-3 py-8 text-center text-slate-500 font-mono text-xs" data-en="No consumption readings logged." data-ar="لا توجد قراءات استهلاك مسجلة.">No consumption readings logged.</td>
                </tr>
              <?php else: ?>
              <?php foreach ($recentConsumptions as $cns): ?>
              <tr class="hover:bg-[#102332] transition-colors">
                <td class="px-3 py-2.5 font-mono font-semibold text-white">
                  <?= htmlspecialchars($cns['consumption_code']) ?>
                  <span class="block text-[10px] text-slate-400 font-normal"><?= date('M d, H:i', strtotime($cns['reading_date'])) ?></span>
                </td>
                <td class="px-3 py-2.5">
                  <span class="font-medium text-white block truncate"><?= htmlspecialchars($cns['company_name']) ?></span>
                  <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($cns['product_name']) ?></span>
                </td>
                <td class="px-3 py-2.5 text-right rtl:text-left font-mono font-bold text-teal-400">
                  <?= number_format((float)$cns['quantity_consumed'], 1) ?> <?= htmlspecialchars($cns['unit']) ?>
                </td>
                <td class="px-3 py-2.5 text-right rtl:text-left font-mono text-[10px] text-slate-400">
                  <?= htmlspecialchars($cns['flow_rate_or_pressure'] ?? 'Verified Refill') ?>
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
      if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.hasAttribute('placeholder')) {
        el.placeholder = transText;
      } else if (el.tagName === 'OPTION') {
        el.innerText = transText;
      } else {
        el.innerText = transText;
      }
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
    applyLanguage(localStorage.getItem('app_lang') || 'en');
  });
</script>
</body>
</html>
