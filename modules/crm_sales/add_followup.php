<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('clients.edit');

$active_page = 'followup_reminders';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Follow-up Reminders', 'Add Follow-up'];

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $client_id = (int)($_POST['client_id'] ?? 0);
  $status = $_POST['status'] ?? 'New';
  $last_contact = $_POST['last_contact'] ?? date('Y-m-d');
  $next_followup = $_POST['next_followup'] ?? '';
  $owner = trim($_POST['owner'] ?? ($_SESSION['user_name'] ?? 'Staff'));
  $notes = trim($_POST['notes'] ?? '');

  if ($client_id <= 0 || empty($next_followup)) {
    $error_msg = "Please select a client and set the next follow-up date.";
  } else {
    if (!check_record_ownership($pdo, 'partners', $client_id, 'assigned_to') && !check_record_ownership($pdo, 'partners', $client_id, 'sales_rep_id')) {
      redirect('modules/crm_sales/followup_reminders.php?error=unauthorized');
    }
    try {
      $stmt = $pdo->prepare("
        INSERT INTO customer_followups (client_id, status, last_contact, next_followup, owner, notes)
        VALUES (?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$client_id, $status, $last_contact, $next_followup, $owner, $notes]);

      redirect('modules/crm_sales/followup_reminders.php?success=1');
    } catch (Exception $e) {
      $error_msg = "Unable to save follow-up task. Please check your input.";
    }
  }
}

$is_rep = has_role('sales_rep') && !has_role('admin') && !has_role('super_admin');
try {
  if ($is_rep) {
    $stmt_clients = $pdo->prepare("SELECT id, company_name, reference_id FROM partners WHERE partner_type = 'client' AND (sales_rep_id = :uid OR assigned_to = :uid) ORDER BY company_name ASC");
    $stmt_clients->execute([':uid' => $_SESSION['user_id']]);
  } else {
    $stmt_clients = $pdo->query("SELECT id, company_name, reference_id FROM partners WHERE partner_type = 'client' ORDER BY company_name ASC");
  }
  $clients = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $clients = [];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Add Follow-up | I-GAS Enterprise" data-ar="إضافة متابعة | I-GAS Enterprise">Add Follow-up | I-GAS Enterprise</title>
  
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
        <span data-en="CRM TASK DISPATCH" data-ar="جدولة مهام إدارة العملاء">CRM TASK DISPATCH</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">TIMESTAMP: <?= date('Y-m-d H:i') ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400 font-mono">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 w-full mx-auto">

      <!-- Breadcrumb Navigation & Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <div class="flex items-center gap-2 mb-1.5 text-xs font-mono text-neutral-500">
            <a href="<?= app_url('modules/crm_sales/followup_reminders.php') ?>" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors uppercase tracking-wider" data-en="Follow-up Reminders" data-ar="تذكيرات المتابعة">
              Follow-up Reminders
            </a>
            <i data-lucide="chevron-right" class="w-3 h-3 text-neutral-400 rtl:rotate-180"></i>
            <span class="text-teal-600 dark:text-teal-400 uppercase tracking-wider font-semibold" data-en="New Task" data-ar="مهمة جديدة">New Task</span>
          </div>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Add Client Follow-up" data-ar="إضافة متابعة عميل">
            Add Client Follow-up
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Schedule future commercial engagements, contract follow-ups, and customer touchpoints." data-ar="جدولة الارتباطات التجارية القادمة ومتابعات العقود ونقاط اتصال العملاء.">
            Schedule future commercial engagements, contract follow-ups, and customer touchpoints.
          </p>
        </div>
      </div>

      <?php if (!empty($error_msg)): ?>
      <div class="p-3.5 border border-rose-800 dark:border-rose-900 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 rounded-sm flex items-center gap-3 text-xs font-mono">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <span><?= htmlspecialchars($error_msg) ?></span>
      </div>
      <?php endif; ?>

      <form action="" method="POST" class="space-y-6">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main Form Fields (2 Cols) -->
          <div class="lg:col-span-2 space-y-6">
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316] space-y-5">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
                <span data-en="1. Client & Task Parameters" data-ar="1. معلمات العميل والمهمة">1. Client & Task Parameters</span>
              </h2>
              
              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Client Account *" data-ar="حساب العميل *">
                    Client Account *
                  </label>
                  <select name="client_id" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                    <option value="" selected disabled data-en="Select client account..." data-ar="اختر حساب العميل...">Select client account...</option>
                    <?php foreach ($clients as $c): ?>
                      <option value="<?= htmlspecialchars($c['id']) ?>">
                        <?= htmlspecialchars($c['company_name']) ?><?= !empty($c['reference_id']) ? ' (' . htmlspecialchars($c['reference_id']) . ')' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Pipeline Status *" data-ar="حالة خط المبيعات *">
                      Pipeline Status *
                    </label>
                    <select name="status" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                      <option value="New" selected data-en="New" data-ar="جديد">New</option>
                      <option value="Follow-up" data-en="Follow-up" data-ar="متابعة">Follow-up</option>
                      <option value="Won" data-en="Won" data-ar="ناجح">Won</option>
                      <option value="Lost" data-en="Lost" data-ar="مفقود">Lost</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Task Owner *" data-ar="مالك المهمة *">
                      Task Owner *
                    </label>
                    <input type="text" name="owner" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?>" required>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Last Contact Date" data-ar="تاريخ آخر اتصال">
                      Last Contact Date
                    </label>
                    <input type="date" name="last_contact" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= date('Y-m-d') ?>">
                  </div>
                  <div>
                    <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Next Follow-up Date *" data-ar="تاريخ المتابعة القادمة *">
                      Next Follow-up Date *
                    </label>
                    <input type="date" name="next_followup" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Notes & Objectives" data-ar="الملاحظات والأهداف">
                    Notes & Objectives
                  </label>
                  <textarea name="notes" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 resize-none" rows="4" placeholder="Enter details about scheduled topics, cryo tank levels, quote negotiations, or technical inspection..."></textarea>
                </div>
              </div>
            </div>

            <!-- Form Actions Bar -->
            <div class="flex items-center justify-between pt-2">
              <a href="<?= app_url('modules/crm_sales/followup_reminders.php') ?>" class="px-4 py-2 text-xs font-mono uppercase tracking-wider bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-200 rounded-sm">
                <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
              </a>
              <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-5 py-2 text-xs font-mono uppercase tracking-wider rounded-sm">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span data-en="Save Follow-up" data-ar="حفظ المتابعة">Save Follow-up</span>
              </button>
            </div>
          </div>

          <!-- Side Information Panel (1 Col) -->
          <div class="lg:col-span-1 space-y-4">
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
              <h3 class="text-xs font-mono font-bold uppercase tracking-wider text-white mb-2 flex items-center gap-2">
                <i data-lucide="info" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Operational Protocol" data-ar="البروتوكول التشغيلي">Operational Protocol</span>
              </h3>
              <p class="text-xs font-mono text-neutral-500 leading-relaxed" data-en="Follow-up tasks automatically appear in the daily plant telemetry board. Overdue items escalate to sales leadership after 48 hours." data-ar="تظهر مهام المتابعة تلقائيًا في لوحة القياس اليومية للمصنع. يتم تصعيد المهام المتأخرة لقيادة المبيعات بعد 48 ساعة.">
                Follow-up tasks automatically appear in the daily plant telemetry board. Overdue items escalate to sales leadership after 48 hours.
              </p>
            </div>

            <div class="bg-[#09151f] border border-[#1a3346] rounded-sm p-4 space-y-2">
              <div class="text-[11px] font-mono uppercase text-neutral-400" data-en="QUICK TIPS" data-ar="إرشادات سريعة">QUICK TIPS</div>
              <ul class="text-xs font-mono text-slate-400 space-y-1.5 list-disc list-inside">
                <li data-en="Log all technical discussions" data-ar="تسجيل جميع النقاشات الفنية">Log all technical discussions</li>
                <li data-en="Verify tank telemetry before calls" data-ar="التحقق من قياسات الخزان قبل الاتصال">Verify tank telemetry before calls</li>
                <li data-en="Update state immediately upon deal close" data-ar="تحديث الحالة فور إغلاق الصفقة">Update state immediately upon deal close</li>
              </ul>
            </div>
          </div>
        </div>
      </form>

    </div>
  </main>

  <script>
    lucide.createIcons();
  </script>
</body>
</html>