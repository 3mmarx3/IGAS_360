<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../../includes/auth.php';
if (!is_admin() && !has_permission('users.edit') && !has_permission('roles.manage')) {
  redirect(app_url('index.php'));
}
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['account_id'], $_POST['account_type'])) {
  require_csrf();
  $action = $_POST['action'];
  $account_id = (int)$_POST['account_id'];
  $account_type = $_POST['account_type'];

  if ($account_type === 'user') {
    $check_admin = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $check_admin->execute([$account_id]);
    $role = $check_admin->fetchColumn();
    
    if (($role === 'Admin' || $account_id === get_current_user_id()) && $action === 'suspend') {
      // Cannot suspend admin or oneself
    } else {
      $new_status = ($action === 'activate') ? 'Active' : 'Suspended';
      $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
      $stmt->execute([$new_status, $account_id]);
    }
  } elseif ($account_type === 'partner') {
    if ($action === 'approve' || $action === 'activate') {
      $new_status = 'approved';
    } elseif ($action === 'suspend') {
      $new_status = 'suspended';
    } elseif ($action === 'reject') {
      $new_status = 'rejected';
    }
    
    if (isset($new_status)) {
      $stmt = $pdo->prepare("UPDATE partners SET status = ? WHERE id = ?");
      $stmt->execute([$new_status, $account_id]);
    }
  }
  
  redirect(app_url('modules/insights/partner_approvals.php'));
}

$stmt_users = $pdo->query("SELECT id, 'user' as type, emp_id as ref_id, full_name as name, full_name as contact_name, role as classification, email, phone, status, NULL as cr_number, created_at FROM users");
$users = $stmt_users ? $stmt_users->fetchAll(PDO::FETCH_ASSOC) : [];

$stmt_partners = $pdo->query("SELECT id, 'partner' as type, reference_id as ref_id, company_name as name, CONCAT(contact_first_name, ' ', contact_last_name) as contact_name, partner_type as classification, email, phone, status, cr_number, created_at FROM partners");
$partners = $stmt_partners ? $stmt_partners->fetchAll(PDO::FETCH_ASSOC) : [];

$all_accounts = array_merge($users, $partners);

usort($all_accounts, function($a, $b) {
  return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

$total_accounts = count($all_accounts);
$pending_approvals = 0;
$active_accounts = 0;
$suspended_accounts = 0;

foreach ($all_accounts as $acc) {
  $stat = strtolower($acc['status']);
  if ($stat === 'pending') {
    $pending_approvals++;
  } elseif (in_array($stat, ['active', 'approved'])) {
    $active_accounts++;
  } elseif (in_array($stat, ['suspended', 'rejected'])) {
    $suspended_accounts++;
  }
}

$active_page = 'partner_approvals';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Global Administration', 'System Accounts Control'];

$typeStyles = [
  'client'  => ['border' => 'border-blue-200 dark:border-blue-800/60', 'bg' => 'bg-blue-950/30 dark:bg-blue-950/40', 'fg' => 'text-blue-700 dark:text-blue-400', 'label' => 'Corporate Client', 'label_ar' => 'عميل شركات'],
  'supplier' => ['border' => 'border-[#1e3a50]', 'bg' => 'bg-[#09151f]', 'fg' => 'text-slate-300', 'label' => 'Industrial Supplier', 'label_ar' => 'مورد صناعي'],
  'logistics' => ['border' => 'border-rose-800 dark:border-rose-800/60', 'bg' => 'bg-rose-950/30 dark:bg-rose-950/40', 'fg' => 'text-rose-400 dark:text-rose-400', 'label' => 'Logistics Partner', 'label_ar' => 'شريك لوجستي'],
  'user'   => ['border' => 'border-teal-800 dark:border-teal-800/60', 'bg' => 'bg-teal-950/30 dark:bg-teal-950/40', 'fg' => 'text-teal-400 dark:text-teal-400', 'label' => 'Internal Staff', 'label_ar' => 'طاقم العمل الداخلي']
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Accounts Control & Review Queue | I-GAS Industrial" data-ar="التحكم بالحسابات وقائمة المراجعة | I-GAS Industrial">Accounts Control & Review Queue | I-GAS Industrial</title>
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
        <span data-en="GATEKEEPER IDENTITY & SECURITY AUTHORIZATION" data-ar="نظام التحقق وتفويض الحسابات الأمنية">GATEKEEPER IDENTITY & SECURITY AUTHORIZATION</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="NETWORK ROLE MATRIX ACTIVE" data-ar="مصفوفة أدوار الشبكة نشطة">NETWORK ROLE MATRIX ACTIVE</span>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: RBAC-ADMIN</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6 max-w-7xl mx-auto w-full">

    <!-- Top Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs font-mono uppercase tracking-wider text-neutral-500" data-en="Global Administration" data-ar="الإدارة العامة">Global Administration</span>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400 rtl:rotate-180"></i>
          <span class="text-xs font-mono uppercase tracking-wider text-white font-semibold" data-en="Accounts & Approvals" data-ar="الحسابات والموافقات">Accounts & Approvals</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="System Accounts Control & Approvals" data-ar="إدارة الحسابات واعتماد الكيانات">System Accounts Control & Approvals</h1>
        <p class="text-xs text-slate-400 mt-1" data-en="Review, approve, suspend, or revoke operational access for all internal staff, clients, and suppliers." data-ar="مراجعة، اعتماد، إيقاف أو إلغاء صلاحيات الوصول لجميع الموظفين، العملاء، والموردين.">Review, approve, suspend, or revoke operational access for all internal staff, clients, and suppliers.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <button id="exportExcelBtn" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
          <i data-lucide="download" class="w-4 h-4"></i>
          <span data-en="Export Compliance Logs" data-ar="تصدير سجل الامتثال">Export Compliance Logs</span>
        </button>
      </div>
    </div>

    <!-- Metric Bento Cards (4-col) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- Total Accounts -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Total Network Entities" data-ar="إجمالي الكيانات المسجلة">Total Network Entities</span>
          <i data-lucide="users" class="w-4 h-4 text-slate-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $total_accounts ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Accounts" data-ar="حساب">Accounts</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-[#1e3a50] bg-[#09151f] text-slate-300 text-[11px] font-mono rounded-xs">
            <span data-en="Staff & External Counterparties" data-ar="الموظفون والشركاء">Staff & External Counterparties</span>
          </span>
        </div>
      </div>

      <!-- Pending Approvals -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Pending Review Queue" data-ar="قائمة المراجعة المعلقة">Pending Review Queue</span>
          <i data-lucide="clock" class="w-4 h-4 text-amber-500"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-amber-600 dark:text-amber-400"><?= $pending_approvals ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Pending" data-ar="معلق">Pending</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-amber-800 dark:border-amber-800/60 bg-amber-950/30 dark:bg-amber-950/40 text-amber-400 dark:text-amber-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="alert-circle" class="w-3 h-3"></i>
            <span data-en="Action Required" data-ar="يتطلب إجراء">Action Required</span>
          </span>
        </div>
      </div>

      <!-- Active & Approved -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Active & Cleared" data-ar="نشط ومعتمد تشغيلياً">Active & Cleared</span>
          <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $active_accounts ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Operational" data-ar="تشغيلي">Operational</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="shield" class="w-3 h-3"></i>
            <span data-en="Access Authorized" data-ar="مصرح بالوصول">Access Authorized</span>
          </span>
        </div>
      </div>

      <!-- Suspended / Rejected -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Suspended / Revoked" data-ar="موقوف أو مرفوض">Suspended / Revoked</span>
          <i data-lucide="slash" class="w-4 h-4 text-rose-500"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight <?= $suspended_accounts > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-white' ?>"><?= $suspended_accounts ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Revoked" data-ar="ملغي">Revoked</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-rose-800 dark:border-rose-800/60 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="lock" class="w-3 h-3"></i>
            <span data-en="Access Denied" data-ar="الوصول محجوب">Access Denied</span>
          </span>
        </div>
      </div>

    </div>

    <!-- Master Directory Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
      <div class="p-5 border-b border-[#1a3346] flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-[#09151f]">
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
              <i data-lucide="shield-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Unified Accounts Directory" data-ar="دليل الحسابات الموحد">Unified Accounts Directory</span>
            </h2>
          </div>
          <div class="flex items-center gap-2 text-xs font-mono border-l border-[#1e3a50] pl-4 rtl:border-l-0 rtl:border-r rtl:pl-0 rtl:pr-4">
            <button class="px-2.5 py-1 text-xs font-mono uppercase tracking-wider rounded-xs transition-colors tab-item active text-white bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50]" data-tab="all">
              <span data-en="All" data-ar="الكل">All</span> (<?= count($all_accounts) ?>)
            </button>
            <button class="px-2.5 py-1 text-xs font-mono uppercase tracking-wider rounded-xs transition-colors tab-item text-neutral-500 hover:text-white" data-tab="pending">
              <span data-en="Pending" data-ar="المعلق">Pending</span> (<?= $pending_approvals ?>)
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-60 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search entity, ref, or contact..." data-en-placeholder="Search entity, ref, or contact..." data-ar-placeholder="البحث بالاسم، المرجع، أو المسؤول..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
          <select id="statusFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
            <option value="all" data-en="All Statuses" data-ar="جميع الحالات">All Statuses</option>
            <option value="pending" data-en="Pending" data-ar="معلق">Pending</option>
            <option value="active" data-en="Active / Approved" data-ar="نشط / معتمد">Active / Approved</option>
            <option value="suspended" data-en="Suspended / Rejected" data-ar="موقوف / مرفوض">Suspended / Rejected</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-left rtl:text-right border-collapse" id="accountsTable">
          <thead>
            <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
              <th class="px-5 py-3 font-semibold" data-en="Account Name & Ref" data-ar="اسم الحساب والمرجع">Account Name & Ref</th>
              <th class="px-5 py-3 font-semibold" data-en="Registration / CR" data-ar="السجل التجاري / الوظيفي">Registration / CR</th>
              <th class="px-5 py-3 font-semibold" data-en="Contact Details" data-ar="بيانات الاتصال">Contact Details</th>
              <th class="px-5 py-3 font-semibold" data-en="Classification" data-ar="التصنيف">Classification</th>
              <th class="px-5 py-3 font-semibold text-center" data-en="Status" data-ar="الحالة">Status</th>
              <th class="px-5 py-3 font-semibold text-right rtl:text-left no-export" data-en="Authorization Actions" data-ar="إجراءات التفويض">Authorization Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] text-xs">
            <?php if (empty($all_accounts)): ?>
              <tr>
                <td colspan="6" class="px-5 py-8 text-center text-xs font-mono text-neutral-400" data-en="No accounts found in the system." data-ar="لم يتم العثور على حسابات في النظام.">No accounts found in the system.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($all_accounts as $acc): ?>
                <?php 
                  $typeKey = $acc['type'] === 'user' ? 'user' : $acc['classification'];
                  $style = $typeStyles[$typeKey] ?? $typeStyles['client']; 
                  
                  $stat = strtolower($acc['status']);
                  $normalizedStat = 'active';
                  
                  if (in_array($stat, ['active', 'approved'])) {
                    $pillBorder = 'border-emerald-200 dark:border-emerald-800/60';
                    $pillBg = 'bg-emerald-50 dark:bg-emerald-950/40';
                    $pillFg = 'text-emerald-700 dark:text-emerald-400';
                    $dotBg = 'bg-emerald-500';
                    $normalizedStat = 'active';
                  } elseif ($stat === 'pending') {
                    $pillBorder = 'border-amber-800 dark:border-amber-800/60';
                    $pillBg = 'bg-amber-950/30 dark:bg-amber-950/40';
                    $pillFg = 'text-amber-400 dark:text-amber-400';
                    $dotBg = 'bg-amber-500';
                    $normalizedStat = 'pending';
                  } else {
                    $pillBorder = 'border-rose-800 dark:border-rose-800/60';
                    $pillBg = 'bg-rose-950/30 dark:bg-rose-950/40';
                    $pillFg = 'text-rose-400 dark:text-rose-400';
                    $dotBg = 'bg-rose-500';
                    $normalizedStat = 'suspended';
                  }
                ?>
                <tr class="hover:bg-[#102332] transition-colors filterable-row" data-status="<?= $normalizedStat ?>" data-search="<?= strtolower(htmlspecialchars($acc['name']) . ' ' . htmlspecialchars($acc['ref_id'] ?? '') . ' ' . htmlspecialchars($acc['contact_name']) . ' ' . htmlspecialchars($acc['email'])) ?>">
                  <td class="px-5 py-3.5">
                    <div class="font-medium text-white"><?= htmlspecialchars($acc['name']) ?></div>
                    <span class="block text-[11px] font-mono text-neutral-400"><?= htmlspecialchars($acc['ref_id'] ?? 'SYS-ID-' . $acc['id']) ?></span>
                  </td>
                  <td class="px-5 py-3.5 font-mono text-slate-400">
                    <?= $acc['cr_number'] ? htmlspecialchars($acc['cr_number']) : '<span class="text-neutral-400" data-en="Internal Staff" data-ar="طاقم داخلي">Internal Staff</span>' ?>
                  </td>
                  <td class="px-5 py-3.5">
                    <div class="font-medium text-slate-200"><?= htmlspecialchars($acc['contact_name']) ?></div>
                    <div class="text-[11px] font-mono text-neutral-500"><?= htmlspecialchars($acc['email']) ?></div>
                  </td>
                  <td class="px-5 py-3.5">
                    <span class="inline-flex items-center px-2 py-0.5 border <?= $style['border'] ?> <?= $style['bg'] ?> <?= $style['fg'] ?> text-[11px] font-mono rounded-xs">
                      <?= $acc['type'] === 'user' ? 'Internal Staff' : ucfirst(htmlspecialchars($acc['classification'])) ?>
                    </span>
                  </td>
                  <td class="px-5 py-3.5 text-center">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 border <?= $pillBorder ?> <?= $pillBg ?> <?= $pillFg ?> text-[11px] font-mono rounded-xs">
                      <span class="w-1.5 h-1.5 rounded-full <?= $dotBg ?>"></span>
                      <?= ucfirst(htmlspecialchars($acc['status'])) ?>
                    </span>
                  </td>
                  <td class="px-5 py-3.5 text-right rtl:text-left no-export whitespace-nowrap">
                    <form method="POST" action="<?= app_url('modules/insights/partner_approvals.php') ?>" class="inline-flex items-center gap-1.5 m-0 p-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="account_id" value="<?= $acc['id'] ?>">
                      <input type="hidden" name="account_type" value="<?= $acc['type'] ?>">
                      
                      <?php if ($acc['type'] === 'user'): ?>
                        <?php if ($stat === 'active'): ?>
                          <button type="submit" name="action" value="suspend" class="border border-rose-800 dark:border-rose-800 text-rose-400 dark:text-rose-400 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] hover:bg-rose-950/30 dark:hover:bg-rose-950/40 px-2.5 py-1 text-xs font-mono uppercase tracking-wider rounded-xs transition-colors" data-en="Suspend" data-ar="إيقاف">Suspend</button>
                        <?php else: ?>
                          <button type="submit" name="action" value="activate" class="border border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] hover:bg-emerald-50 dark:hover:bg-emerald-950/40 px-2.5 py-1 text-xs font-mono uppercase tracking-wider rounded-xs transition-colors" data-en="Activate" data-ar="تفعيل">Activate</button>
                        <?php endif; ?>
                      <?php else: ?>
                        <?php if ($stat === 'pending'): ?>
                          <button type="submit" name="action" value="reject" class="border border-rose-800 dark:border-rose-800 text-rose-400 dark:text-rose-400 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] hover:bg-rose-950/30 dark:hover:bg-rose-950/40 px-2.5 py-1 text-xs font-mono uppercase tracking-wider rounded-xs transition-colors" data-en="Reject" data-ar="رفض">Reject</button>
                          <button type="submit" name="action" value="approve" class="border border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] hover:bg-emerald-50 dark:hover:bg-emerald-950/40 px-2.5 py-1 text-xs font-mono uppercase tracking-wider rounded-xs transition-colors font-semibold" data-en="Approve" data-ar="موافقة">Approve</button>
                        <?php elseif ($stat === 'approved'): ?>
                          <button type="submit" name="action" value="suspend" class="border border-[#1e3a50] text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] hover:bg-[#102230] dark:hover:bg-neutral-700 px-2.5 py-1 text-xs font-mono uppercase tracking-wider rounded-xs transition-colors" data-en="Suspend" data-ar="إيقاف">Suspend</button>
                        <?php elseif (in_array($stat, ['suspended', 'rejected'])): ?>
                          <button type="submit" name="action" value="activate" class="border border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] hover:bg-emerald-50 dark:hover:bg-emerald-950/40 px-2.5 py-1 text-xs font-mono uppercase tracking-wider rounded-xs transition-colors" data-en="Reactivate" data-ar="إعادة تفعيل">Reactivate</button>
                        <?php endif; ?>
                      <?php endif; ?>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="px-5 py-3 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between text-xs font-mono text-neutral-500">
        <span>
          <span data-en="Displaying" data-ar="عرض">Displaying</span> <strong class="text-white" id="visibleCount"><?= count($all_accounts) ?></strong> <span data-en="entities" data-ar="كيان">entities</span>
        </span>
        <span>SEC: SOX-AUDITED</span>
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
    document.querySelectorAll('input[data-en-placeholder]').forEach(el => {
      const ph = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
      el.placeholder = ph;
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", function() {
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);

    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const tabItems = document.querySelectorAll('.tab-item');
    const rows = document.querySelectorAll('.filterable-row');
    const visibleCountElem = document.getElementById('visibleCount');
    let currentTab = 'all';

    function filterTable() {
      const query = searchInput.value.toLowerCase().trim();
      const filterVal = statusFilter.value;
      let visibleCount = 0;

      rows.forEach(row => {
        const status = row.getAttribute('data-status');
        const searchData = row.getAttribute('data-search') || '';
        
        const matchesSearch = searchData.includes(query);
        const matchesDropdown = (filterVal === 'all' || status === filterVal);
        const matchesTab = (currentTab === 'all' || status === currentTab);

        if (matchesSearch && matchesDropdown && matchesTab) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      if (visibleCountElem) {
        visibleCountElem.innerText = visibleCount;
      }
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (statusFilter) statusFilter.addEventListener('change', filterTable);

    tabItems.forEach(tab => {
      tab.addEventListener('click', function() {
        tabItems.forEach(t => {
          t.classList.remove('active', 'text-white', 'text-white', 'bg-[#112433]', 'bg-[#112433]', 'border', 'border-neutral-300', 'border-[#1e3a50]');
          t.classList.add('text-neutral-500');
        });
        this.classList.add('active', 'text-white', 'text-white', 'bg-[#112433]', 'bg-[#112433]', 'border', 'border-neutral-300', 'border-[#1e3a50]');
        this.classList.remove('text-neutral-500');
        currentTab = this.getAttribute('data-tab');
        filterTable();
      });
    });

    const exportBtn = document.getElementById('exportExcelBtn');
    if (exportBtn) {
      exportBtn.addEventListener('click', function() {
        let csvContent = "\uFEFF"; 
        const table = document.getElementById("accountsTable");
        const trs = table.querySelectorAll("tr");

        trs.forEach(function(row) {
          if (row.style.display === 'none') return;
          
          let rowData = [];
          const cols = row.querySelectorAll("th, td");
          
          cols.forEach(function(col) {
            if (col.classList.contains('no-export')) return;
            
            let text = col.innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/"/g, '""');
            text = text.trim();
            rowData.push('"' + text + '"');
          });
          
          if (rowData.length > 0) {
            csvContent += rowData.join(",") + "\r\n";
          }
        });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", "Accounts_Compliance_Export.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
    }
  });
</script>
</body>
</html>