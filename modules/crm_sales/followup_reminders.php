<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('clients.view');

$active_page = 'followup_reminders';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Follow-up Reminders'];

$is_rep = has_role('sales_rep') && !has_role('admin') && !has_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  if (isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
      require_permission('clients.edit');
      $fid = (int)($_POST['followup_id'] ?? 0);
      $new_status = $_POST['status'] ?? 'Follow-up';

      $chk = $pdo->prepare("SELECT cf.client_id, p.sales_rep_id, p.assigned_to FROM customer_followups cf JOIN partners p ON cf.client_id = p.id WHERE cf.id = ?");
      $chk->execute([$fid]);
      $frec = $chk->fetch(PDO::FETCH_ASSOC);

      if ($frec && (check_record_ownership($pdo, 'partners', $frec['client_id'], 'assigned_to') || check_record_ownership($pdo, 'partners', $frec['client_id'], 'sales_rep_id'))) {
        $stmt = $pdo->prepare("UPDATE customer_followups SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $fid]);
      }
      redirect('modules/crm_sales/followup_reminders.php');
    } elseif ($_POST['action'] === 'add_followup') {
      require_permission('clients.edit');
      $client_id = (int)($_POST['client_id'] ?? 0);
      $owner = $_SESSION['user_name'] ?? 'Staff';
      $status = $_POST['status'] ?? 'New';
      $last_contact = $_POST['last_contact'] ?? date('Y-m-d');
      $next_followup = $_POST['next_followup'] ?? '';
      $notes = trim($_POST['notes'] ?? '');

      if ($client_id > 0 && !empty($next_followup)) {
        if (check_record_ownership($pdo, 'partners', $client_id, 'assigned_to') || check_record_ownership($pdo, 'partners', $client_id, 'sales_rep_id')) {
          $stmt = $pdo->prepare("INSERT INTO customer_followups (client_id, status, last_contact, next_followup, owner, notes) VALUES (?, ?, ?, ?, ?, ?)");
          $stmt->execute([$client_id, $status, $last_contact, $next_followup, $owner, $notes]);
        }
      }
      redirect('modules/crm_sales/followup_reminders.php');
    } elseif ($_POST['action'] === 'delete_followup') {
      require_permission('clients.delete');
      $fid = (int)($_POST['followup_id'] ?? 0);

      $chk = $pdo->prepare("SELECT cf.client_id, p.sales_rep_id, p.assigned_to FROM customer_followups cf JOIN partners p ON cf.client_id = p.id WHERE cf.id = ?");
      $chk->execute([$fid]);
      $frec = $chk->fetch(PDO::FETCH_ASSOC);

      if ($frec && (check_record_ownership($pdo, 'partners', $frec['client_id'], 'assigned_to') || check_record_ownership($pdo, 'partners', $frec['client_id'], 'sales_rep_id'))) {
        $stmt = $pdo->prepare("DELETE FROM customer_followups WHERE id = ?");
        $stmt->execute([$fid]);
      }
      redirect('modules/crm_sales/followup_reminders.php');
    }
  }
}

if ($is_rep) {
  $stmt = $pdo->prepare("
    SELECT cf.*, p.company_name, p.reference_id 
    FROM customer_followups cf 
    JOIN partners p ON cf.client_id = p.id 
    WHERE p.partner_type = 'client' AND (p.sales_rep_id = :uid OR p.assigned_to = :uid OR cf.owner = :uname)
    ORDER BY cf.next_followup ASC
  ");
  $stmt->execute([':uid' => $_SESSION['user_id'], ':uname' => $_SESSION['user_name'] ?? '']);
  $followups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt_clients = $pdo->prepare("SELECT id, company_name FROM partners WHERE partner_type = 'client' AND (sales_rep_id = :uid OR assigned_to = :uid) ORDER BY company_name ASC");
  $stmt_clients->execute([':uid' => $_SESSION['user_id']]);
  $clients = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);
} else {
  $stmt = $pdo->query("
    SELECT cf.*, p.company_name, p.reference_id 
    FROM customer_followups cf 
    JOIN partners p ON cf.client_id = p.id 
    ORDER BY cf.next_followup ASC
  ");
  $followups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt_clients = $pdo->query("SELECT id, company_name FROM partners WHERE partner_type = 'client' ORDER BY company_name ASC");
  $clients = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);
}

$today = date('Y-m-d');
$overdue = 0;
$due_today = 0;
$due_soon = 0;
$on_track = 0;

foreach ($followups as $f) {
  if ($f['status'] === 'Won' || $f['status'] === 'Lost') continue;
  
  $days = floor((strtotime($f['next_followup']) - strtotime($today)) / (60 * 60 * 24));
  
  if ($days < 0) {
    $overdue++;
  } elseif ($days == 0) {
    $due_today++;
  } elseif ($days > 0 && $days <= 3) {
    $due_soon++;
  } else {
    $on_track++;
  }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Follow-up Reminders | I-GAS Enterprise" data-ar="تذكيرات المتابعة | I-GAS Enterprise">Follow-up Reminders | I-GAS Enterprise</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  
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
        <span data-en="CRM COMM LOG & PIPELINE TASK FORCE" data-ar="سجل اتصالات العملاء ومتابعة المهام">CRM COMM LOG & PIPELINE TASK FORCE</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">SCHEDULE: <?= date('Y-m-d') ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400 font-mono">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Page Title & Actions -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <span class="text-xs font-mono uppercase tracking-wider text-teal-600 dark:text-teal-400 font-semibold block mb-1" data-en="PIPELINE VELOCITY CONTROL" data-ar="التحكم في سرعة خط المبيعات">PIPELINE VELOCITY CONTROL</span>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Follow-up Reminders" data-ar="تذكيرات المتابعة">
            Follow-up Reminders
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Track and prioritize client communications, contract reviews, and pipeline tasks." data-ar="تتبع وتحديد أولويات اتصالات العملاء ومراجعات العقود ومهام خط المبيعات.">
            Track and prioritize client communications, contract reviews, and pipeline tasks.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button onclick="window.print()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <i data-lucide="printer" class="w-3.5 h-3.5 text-slate-400"></i>
            <span data-en="Print Sheet" data-ar="طباعة الجدول">Print Sheet</span>
          </button>
          <a href="<?= app_url('modules/crm_sales/add_followup.php') ?>" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <i data-lucide="file-plus" class="w-3.5 h-3.5 text-slate-400"></i>
            <span data-en="New Task Form" data-ar="نموذج مهمة جديدة">New Task Form</span>
          </a>
          <button onclick="openModal('addFollowupModal')" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            <span data-en="Quick Add" data-ar="إضافة سريعة">Quick Add</span>
          </button>
        </div>
      </div>

      <!-- Bento Telemetry Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Overdue Card -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316] dark:border-s-rose-500">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Overdue" data-ar="متأخر">Overdue</span>
            <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 dark:text-rose-500"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-rose-400 dark:text-rose-400 tabular-nums">
            <?= $overdue ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span class="inline-flex items-center gap-1.5 text-rose-600 dark:text-rose-400">
              <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
              <span data-en="Action Required" data-ar="إجراء مطلوب">Action Required</span>
            </span>
            <span>&gt; 0 days</span>
          </div>
        </div>

        <!-- Due Today Card -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316] dark:border-s-amber-500">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Due Today" data-ar="مستحق اليوم">Due Today</span>
            <i data-lucide="clock" class="w-4 h-4 text-amber-600 dark:text-amber-500"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-amber-400 dark:text-amber-400 tabular-nums">
            <?= $due_today ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span class="inline-flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
              <span data-en="Immediate Priority" data-ar="أولوية فورية">Immediate Priority</span>
            </span>
            <span>Day 0</span>
          </div>
        </div>

        <!-- Due Soon Card -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Due Soon (≤ 3 Days)" data-ar="مستحق قريباً (≤ 3 أيام)">Due Soon (≤ 3 Days)</span>
            <i data-lucide="calendar" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-teal-400 dark:text-teal-400 tabular-nums">
            <?= $due_soon ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span class="inline-flex items-center gap-1.5 text-teal-600 dark:text-teal-400">
              <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
              <span data-en="Upcoming Pipeline" data-ar="خط مبيعات قادم">Upcoming Pipeline</span>
            </span>
            <span>1–3 days</span>
          </div>
        </div>

        <!-- On Track Card -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316] dark:border-s-emerald-500">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="On Track" data-ar="في المسار الصحيح">On Track</span>
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-500"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
            <?= $on_track ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span data-en="Scheduled Normal" data-ar="مجدول بصورة طبيعية">Scheduled Normal</span>
            </span>
            <span>&gt; 3 days</span>
          </div>
        </div>
      </div>

      <!-- Action Items Table Container -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm flex flex-col overflow-hidden">
        <!-- Table Header Controls -->
        <div class="p-4 sm:p-5 border-b border-[#1a3346] flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-[#071018]">
          <div>
            <h2 class="text-base font-mono font-semibold text-white flex items-center gap-2" data-en="Pipeline Action Schedule" data-ar="جدول إجراءات خط المبيعات">
              Pipeline Action Schedule
              <span class="text-xs px-2 py-0.5 rounded-sm bg-[#112433] text-slate-300 font-mono">
                <?= count($followups) ?>
              </span>
            </h2>
            <p class="text-xs font-mono text-neutral-500 mt-0.5" data-en="Direct client follow-up assignments, SLA renewals, and meeting callbacks." data-ar="مهام المتابعة المباشرة وتجديد الاتفاقيات وإعادة الاتصال بالعملاء.">
              Direct client follow-up assignments, SLA renewals, and meeting callbacks.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
            <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="followupSearchInput" onkeyup="filterFollowups()" placeholder="Search client or owner..." data-en="Search client or owner..." data-ar="البحث عن عميل أو مالك..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
            <select id="statusFilterSelect" onchange="filterFollowups()" class="h-9 px-3 py-1.5 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
              <option value="ALL" data-en="All Statuses" data-ar="جميع الحالات">All Statuses</option>
              <option value="New" data-en="New" data-ar="جديد">New</option>
              <option value="Follow-up" data-en="Follow-up" data-ar="متابعة">Follow-up</option>
              <option value="Won" data-en="Won" data-ar="ناجح">Won</option>
              <option value="Lost" data-en="Lost" data-ar="مفقود">Lost</option>
            </select>
          </div>
        </div>

        <!-- Table Content -->
        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse" id="followupsTable">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider border-b border-[#1a3346] bg-[#102230]/60 bg-[#0c1924] text-slate-400">
                <th class="px-4 py-3 font-semibold text-center w-28" data-en="Urgency" data-ar="الأهمية">Urgency</th>
                <th class="px-4 py-3 font-semibold" data-en="Customer Account" data-ar="حساب العميل">Customer Account</th>
                <th class="px-4 py-3 font-semibold" data-en="Owner" data-ar="المالك">Owner</th>
                <th class="px-4 py-3 font-semibold" data-en="Pipeline State" data-ar="حالة المتابعة">Pipeline State</th>
                <th class="px-4 py-3 font-semibold" data-en="Next Date" data-ar="الموعد القادم">Next Date</th>
                <th class="px-4 py-3 font-semibold text-center" data-en="Delta" data-ar="الفارق">Delta</th>
                <th class="px-4 py-3 font-semibold" data-en="Direct Notes" data-ar="الملاحظات">Direct Notes</th>
                <th class="px-4 py-3 font-semibold text-right rtl:text-left w-24" data-en="Actions" data-ar="إجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-[#152a3a]">
              <?php if (empty($followups)): ?>
                <tr>
                  <td colspan="8" class="text-center py-12 text-neutral-500 font-mono" data-en="No follow-up reminders scheduled." data-ar="لا توجد تذكيرات متابعة مجدولة.">
                    No follow-up reminders scheduled.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($followups as $f): ?>
                <?php
                  $days = floor((strtotime($f['next_followup']) - strtotime($today)) / (60 * 60 * 24));
                  
                  $urgencyBadge = '';
                  if ($f['status'] === 'Won' || $f['status'] === 'Lost') {
                    $urgencyBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-mono uppercase bg-[#09151f] text-neutral-500 border border-[#1e3a50] rounded-sm"><span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>Closed</span>';
                    $deltaStr = '-';
                  } elseif ($days < 0) {
                    $urgencyBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-mono uppercase bg-rose-500/10 text-rose-400 dark:text-rose-400 border border-rose-500/30 rounded-sm"><span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>Overdue</span>';
                    $deltaStr = $days . 'd';
                  } elseif ($days == 0) {
                    $urgencyBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-mono uppercase bg-amber-500/10 text-amber-400 dark:text-amber-400 border border-amber-500/30 rounded-sm"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Today</span>';
                    $deltaStr = '0d';
                  } elseif ($days <= 3) {
                    $urgencyBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-mono uppercase bg-teal-500/10 text-teal-400 dark:text-teal-400 border border-teal-500/30 rounded-sm"><span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>Soon</span>';
                    $deltaStr = '+' . $days . 'd';
                  } else {
                    $urgencyBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-mono uppercase bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 rounded-sm"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Normal</span>';
                    $deltaStr = '+' . $days . 'd';
                  }

                  $words = explode(' ', trim($f['company_name'] ?? ''));
                  $initials = '';
                  if (count($words) >= 2) {
                    $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
                  } elseif (count($words) == 1 && mb_strlen($words[0]) > 0) {
                    $initials = strtoupper(mb_substr($words[0], 0, 2));
                  }
                ?>
                <tr class="hover:bg-[#102332] transition-colors followup-row" data-client="<?= htmlspecialchars(strtolower($f['company_name'])) ?>" data-owner="<?= htmlspecialchars(strtolower($f['owner'])) ?>" data-status="<?= htmlspecialchars($f['status']) ?>">
                  <td class="px-4 py-3 text-center whitespace-nowrap">
                    <?= $urgencyBadge ?>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5">
                      <div class="w-7 h-7 rounded-sm bg-[#112433] text-slate-200 flex items-center justify-center font-mono font-semibold text-[11px] flex-shrink-0 border border-[#1e3a50]">
                        <?= htmlspecialchars($initials) ?>
                      </div>
                      <div>
                        <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode($f['client_id'])) ?>" class="font-medium text-white hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                          <?= htmlspecialchars($f['company_name']) ?>
                        </a>
                        <?php if (!empty($f['reference_id'])): ?>
                          <span class="block text-[10px] font-mono text-neutral-400">
                            <?= htmlspecialchars($f['reference_id']) ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-slate-400 font-mono whitespace-nowrap">
                    <?= htmlspecialchars($f['owner']) ?>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <form method="POST" action="" class="m-0 p-0 inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_status">
                      <input type="hidden" name="followup_id" value="<?= $f['id'] ?>">
                      <select name="status" onchange="this.form.submit()" class="px-2 py-1 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
                        <option value="New" <?= $f['status'] === 'New' ? 'selected' : '' ?>>New</option>
                        <option value="Follow-up" <?= $f['status'] === 'Follow-up' ? 'selected' : '' ?>>Follow-up</option>
                        <option value="Won" <?= $f['status'] === 'Won' ? 'selected' : '' ?>>Won</option>
                        <option value="Lost" <?= $f['status'] === 'Lost' ? 'selected' : '' ?>>Lost</option>
                      </select>
                    </form>
                  </td>
                  <td class="px-4 py-3 font-mono tabular-nums text-slate-200 whitespace-nowrap">
                    <?= date('Y-m-d', strtotime($f['next_followup'])) ?>
                  </td>
                  <td class="px-4 py-3 font-mono tabular-nums text-center whitespace-nowrap font-semibold <?= $days < 0 ? 'text-rose-600 dark:text-rose-400' : ($days == 0 ? 'text-amber-600 dark:text-amber-400' : 'text-neutral-500') ?>">
                    <?= $deltaStr ?>
                  </td>
                  <td class="px-4 py-3 text-slate-400 max-w-xs truncate" title="<?= htmlspecialchars($f['notes']) ?>">
                    <?= htmlspecialchars($f['notes'] ?: '—') ?>
                  </td>
                  <td class="px-4 py-3 text-right rtl:text-left whitespace-nowrap">
                    <?php if (has_permission('clients.delete')): ?>
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to remove this follow-up reminder? / هل أنت متأكد من حذف هذا التذكير؟');" class="inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_followup">
                      <input type="hidden" name="followup_id" value="<?= $f['id'] ?>">
                      <button type="submit" class="p-1 text-neutral-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors rounded-sm" title="Delete">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Footer Summary Pagination -->
        <div class="p-3.5 border-t border-[#1a3346] flex justify-between items-center bg-[#071018] text-xs font-mono text-neutral-500">
          <div>
            <span data-en="Active Reminders:" data-ar="التذكيرات النشطة:">Active Reminders:</span>
            <span class="font-bold tabular-nums text-white"><?= count($followups) ?></span>
          </div>
          <div class="text-[11px] text-neutral-400">
            <span data-en="Auto-synchronized with CRM partner accounts" data-ar="مزامنة تلقائية مع حسابات شركاء إدارة العملاء">Auto-synchronized with CRM partner accounts</span>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- Quick Add Follow-up Modal -->
  <div id="addFollowupModal" class="fixed inset-0 bg-[#060e14]/80 backdrop-blur-sm backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[#0c1924] border border-[#1e3a50] rounded-sm w-full max-w-lg flex flex-col overflow-hidden">
      <!-- Modal Header -->
      <div class="px-5 py-4 border-b border-[#1a3346] flex justify-between items-center bg-[#102230]/60 bg-[#0c1924]">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-teal-500"></span>
          <h3 class="text-sm font-mono font-bold text-white uppercase tracking-wider" data-en="Add Follow-up Reminder" data-ar="إضافة تذكير متابعة">
            Add Follow-up Reminder
          </h3>
        </div>
        <button type="button" onclick="closeModal('addFollowupModal')" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <!-- Modal Form -->
      <form method="POST" action="" class="p-5 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_followup">
        
        <div>
          <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1" data-en="Client Account *" data-ar="حساب العميل *">
            Client Account *
          </label>
          <select name="client_id" required class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
            <option value="" disabled selected data-en="Select customer..." data-ar="اختر عميلاً...">Select customer...</option>
            <?php foreach($clients as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1" data-en="Task Owner" data-ar="مالك المهمة">
              Task Owner
            </label>
            <input type="text" class="w-full px-3 py-2 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-sm text-neutral-500 cursor-not-allowed" value="<?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?>" disabled>
          </div>
          <div>
            <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1" data-en="Initial Status" data-ar="الحالة المبدئية">
              Initial Status
            </label>
            <select name="status" required class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
              <option value="New" data-en="New" data-ar="جديد">New</option>
              <option value="Follow-up" selected data-en="Follow-up" data-ar="متابعة">Follow-up</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1" data-en="Last Contact Date" data-ar="آخر اتصال">
              Last Contact Date
            </label>
            <input type="date" name="last_contact" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= date('Y-m-d') ?>">
          </div>
          <div>
            <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1" data-en="Next Follow-up Date *" data-ar="المتابعة القادمة *">
              Next Follow-up Date *
            </label>
            <input type="date" name="next_followup" required class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
          </div>
        </div>

        <div>
          <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1" data-en="Notes / Objective" data-ar="ملاحظات / أهداف">
            Notes / Objective
          </label>
          <textarea name="notes" rows="3" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 resize-none" placeholder="Discuss cryogenic tank capacity, price revision, or contract signoff..."></textarea>
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-[#1a3346]">
          <button type="button" onclick="closeModal('addFollowupModal')" class="px-4 py-2 text-xs font-mono uppercase tracking-wider bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] text-slate-300 rounded-sm hover:bg-[#102230] dark:hover:bg-neutral-750 transition-colors" data-en="Cancel" data-ar="إلغاء">
            Cancel
          </button>
          <button type="submit" class="btn-cta-orange px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm" data-en="Schedule Reminder" data-ar="جدولة التذكير">
            Schedule Reminder
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    lucide.createIcons();

    function openModal(id) {
      const el = document.getElementById(id);
      if (el) {
        el.classList.remove('hidden');
        el.classList.add('flex');
      }
    }

    function closeModal(id) {
      const el = document.getElementById(id);
      if (el) {
        el.classList.add('hidden');
        el.classList.remove('flex');
      }
    }

    function filterFollowups() {
      const search = document.getElementById('followupSearchInput').value.toLowerCase();
      const status = document.getElementById('statusFilterSelect').value;
      const rows = document.querySelectorAll('.followup-row');

      rows.forEach(row => {
        const client = row.getAttribute('data-client') || '';
        const owner = row.getAttribute('data-owner') || '';
        const rowStatus = row.getAttribute('data-status') || '';

        const matchesSearch = client.includes(search) || owner.includes(search);
        const matchesStatus = (status === 'ALL' || rowStatus === status);

        if (matchesSearch && matchesStatus) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>