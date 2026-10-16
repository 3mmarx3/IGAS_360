<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('users.view');

$active_page = 'sales_reps';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Sales Team Directory', 'Rep Cockpit'];

$rep_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($rep_id <= 0) {
  $firstId = (int)$pdo->query("SELECT id FROM users WHERE role IN ('Sales Rep', 'sales_rep', 'Sales Manager', 'sales_manager', 'Admin', 'admin') ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstId > 0) {
    $rep_id = $firstId;
  } else {
    redirect('modules/crm_sales/sales_reps.php');
  }
}

// Sales Reps can only view their own profile unless they have managerial/admin privileges
if (has_role('sales_rep') && !has_role('admin') && !has_role('manager') && !has_role('super_admin')) {
  if ($rep_id !== (int)$_SESSION['user_id']) {
    redirect('modules/crm_sales/rep_profile.php?id=' . $_SESSION['user_id']);
  }
}

$stmt_rep = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_rep->execute([$rep_id]);
$rep = $stmt_rep->fetch(PDO::FETCH_ASSOC);

if (!$rep) {
  redirect('modules/crm_sales/sales_reps.php');
}

$words = preg_split('/\s+/', trim((string)$rep['full_name']));
$initials = '';
if (count($words) >= 2) {
  $initials = mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8');
} elseif (count($words) == 1 && mb_strlen($words[0]) > 0) {
  $initials = mb_substr($words[0], 0, 2, 'UTF-8');
}
$initials = mb_strtoupper($initials, 'UTF-8') ?: 'SR';

$total_sales = $pdo->prepare("SELECT COALESCE(SUM(total_value),0) FROM purchase_orders WHERE sales_rep_id = ? AND status NOT IN ('cancelled', 'draft')");
$total_sales->execute([$rep_id]);
$m_sales = (float)$total_sales->fetchColumn();

$total_collections = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM receipt_vouchers WHERE sales_rep_id = ?");
$total_collections->execute([$rep_id]);
$m_collections = (float)$total_collections->fetchColumn();

$stmt_comm = $pdo->prepare("
  SELECT 
    COALESCE(SUM(CASE WHEN status IN ('pending', 'approved') THEN commission_amount ELSE 0 END), 0) as pending_comm,
    COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END), 0) as paid_comm
  FROM sales_commissions 
  WHERE sales_rep_id = ?
");
$stmt_comm->execute([$rep_id]);
$comm_data = $stmt_comm->fetch(PDO::FETCH_ASSOC);
$m_comm_pending = (float)($comm_data['pending_comm'] ?? 0);
$m_comm_paid = (float)($comm_data['paid_comm'] ?? 0);

$stmt_clients = $pdo->prepare("
  SELECT 
    p.id, p.reference_id, p.company_name, p.segment, p.status, p.balance_due,
    (SELECT COUNT(*) FROM purchase_orders WHERE client_id = p.id) as order_count,
    (SELECT COALESCE(SUM(total_value),0) FROM purchase_orders WHERE client_id = p.id AND status NOT IN ('cancelled','draft')) as client_value
  FROM partners p 
  WHERE p.partner_type = 'client'
  AND (
    p.sales_rep_id = ?
    OR p.id IN (SELECT client_id FROM quotations WHERE sales_rep_id = ?)
    OR p.id IN (SELECT client_id FROM purchase_orders WHERE sales_rep_id = ?)
  )
  ORDER BY client_value DESC
");
$stmt_clients->execute([$rep_id, $rep_id, $rep_id]);
$clients = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);

$role_clean = $rep['role'] ?? 'Sales Rep';
$role_ar = ($role_clean === 'Sales Manager') ? 'مدير مبيعات' : (($role_clean === 'Admin') ? 'مسؤول النظام' : 'مندوب مبيعات');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($rep['full_name']) ?> | Sales Cockpit | I-GAS Enterprise</title>
  
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
        <span data-en="SALES COCKPIT" data-ar="قمرة قيادة المبيعات">SALES COCKPIT</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">EMP ID: <?= htmlspecialchars($rep['emp_id'] ?? 'N/A') ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">
      <!-- Navigation Back & ID -->
      <div class="flex items-center justify-between">
        <a href="<?= app_url('modules/crm_sales/sales_reps.php') ?>" class="inline-flex items-center gap-2 text-xs font-mono text-neutral-500 hover:text-teal-400 dark:hover:text-teal-400 transition-colors">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          <span data-en="Back to Sales Force Directory" data-ar="العودة لدليل فريق المبيعات">Back to Sales Force Directory</span>
        </a>
        <span class="text-xs font-mono text-neutral-400">UID #<?= (int)$rep['id'] ?></span>
      </div>

      <!-- Profile Summary Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 border-s-4 border-s-[#f97316]">
        <div class="flex items-center gap-4">
          <div class="relative shrink-0">
            <div class="w-16 h-16 rounded-md bg-[#09151f] border border-[#1e3a50] flex items-center justify-center font-mono font-bold text-xl text-slate-200">
              <?= htmlspecialchars($initials) ?>
            </div>
            <span class="w-3 h-3 rounded-full absolute -top-1 -end-1 border-2 border-white dark:border-neutral-900 <?= strtolower($rep['status'] ?? '') === 'active' ? 'bg-teal-500' : 'bg-rose-500' ?>" title="<?= htmlspecialchars($rep['status'] ?? 'Active') ?>"></span>
          </div>

          <div class="space-y-1.5">
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-2xl font-bold font-mono tracking-tight text-white">
                <?= htmlspecialchars($rep['full_name']) ?>
              </h1>
              <span class="px-2.5 py-0.5 text-xs font-mono font-medium rounded-full border border-teal-500/30 bg-teal-500/10 text-teal-400 dark:text-teal-400 inline-flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
                <span data-en="<?= htmlspecialchars($role_clean) ?>" data-ar="<?= htmlspecialchars($role_ar) ?>"><?= htmlspecialchars($role_clean) ?></span>
              </span>
            </div>
            <p class="text-xs font-mono text-neutral-500 flex items-center gap-3">
              <span>EMP: <?= htmlspecialchars($rep['emp_id'] ?? 'N/A') ?></span>
              <span>•</span>
              <a href="mailto:<?= htmlspecialchars($rep['email'] ?? '') ?>" class="hover:text-teal-600 hover:underline"><?= htmlspecialchars($rep['email'] ?? '—') ?></a>
              <?php if (!empty($rep['phone'])): ?>
                <span>•</span>
                <span><?= htmlspecialchars($rep['phone']) ?></span>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <?php if (!empty($rep['email'])): ?>
          <a href="mailto:<?= htmlspecialchars($rep['email']) ?>" class="px-4 py-2 text-xs font-medium border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 rounded-md transition-colors inline-flex items-center gap-2">
            <i data-lucide="mail" class="w-4 h-4"></i>
            <span data-en="Email Representative" data-ar="مراسلة عبر البريد">Email Representative</span>
          </a>
          <?php endif; ?>

          <!-- Single Primary CTA: Safety Valve Orange -->
          <a href="<?= app_url('modules/crm_sales/new_quotation.php?rep_id=' . urlencode((string)$rep['id'])) ?>" class="btn-cta-orange px-4 py-2 text-xs font-semibold rounded-md inline-flex items-center gap-2 transition-all">
            <i data-lucide="file-plus" class="w-4 h-4"></i>
            <span data-en="Create Quotation" data-ar="إنشاء عرض سعر جديد">Create Quotation</span>
          </a>
        </div>
      </div>

      <!-- Bento KPI Grid (4 cards with 3px start accents) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Total Booked Sales" data-ar="إجمالي المبيعات المحققة">Total Booked Sales</span>
            <i data-lucide="trending-up" class="w-4 h-4 text-teal-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-white">
            <?= number_format($m_sales) ?>
            <span class="text-xs font-sans font-normal text-neutral-500">SAR</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="PO revenue volume" data-ar="إيرادات أوامر الشراء">PO revenue volume</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Total Collections" data-ar="إجمالي التحصيلات">Total Collections</span>
            <i data-lucide="wallet" class="w-4 h-4 text-emerald-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-emerald-700 dark:text-emerald-400">
            <?= number_format($m_collections) ?>
            <span class="text-xs font-sans font-normal text-neutral-500">SAR</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Receipt vouchers realized" data-ar="سندات القبض المودعة">Receipt vouchers realized</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Pending Commissions" data-ar="العمولات المعلقة">Pending Commissions</span>
            <i data-lucide="clock" class="w-4 h-4 text-amber-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-amber-400 dark:text-amber-400">
            <?= number_format($m_comm_pending) ?>
            <span class="text-xs font-sans font-normal text-neutral-500">SAR</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Approved awaiting payout" data-ar="معتمدة بانتظار الصرف">Approved awaiting payout</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Disbursed Commissions" data-ar="العمولات المصروفة">Disbursed Commissions</span>
            <i data-lucide="check-circle" class="w-4 h-4 text-blue-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-white">
            <?= number_format($m_comm_paid) ?>
            <span class="text-xs font-sans font-normal text-neutral-500">SAR</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Settled through payroll" data-ar="تم تسويتها مع الرواتب">Settled through payroll</p>
        </div>
      </div>

      <!-- Assigned Client Portfolio Table -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-md overflow-hidden">
        <div class="px-6 py-4 border-b border-[#1a3346] flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
              <i data-lucide="briefcase" class="w-4 h-4 text-teal-600"></i>
              <span data-en="Assigned Customer Portfolio (<?= count($clients) ?>)" data-ar="محفظة العملاء المعينين (<?= count($clients) ?>)">
                Assigned Customer Portfolio (<?= count($clients) ?>)
              </span>
            </h2>
            <p class="text-xs text-neutral-500" data-en="Direct client accounts, historical ordering volume, and account receivable status." data-ar="حسابات العملاء المباشرة وحجم الطلبات التاريخي والمستحقات.">
              Direct client accounts, historical ordering volume, and account receivable status.
            </p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-[#09151f] bg-[#112433]/75 border-b border-[#1a3346] text-neutral-500 font-mono uppercase">
                <th class="py-3 px-6 text-start" data-en="Client Company" data-ar="شركة العميل">Client Company</th>
                <th class="py-3 px-4 text-start" data-en="Segment" data-ar="القطاع">Segment</th>
                <th class="py-3 px-4 text-end" data-en="Orders" data-ar="الطلبات">Orders</th>
                <th class="py-3 px-4 text-end" data-en="Total Sales (SAR)" data-ar="إجمالي المبيعات (ر.س)">Total Sales (SAR)</th>
                <th class="py-3 px-4 text-end" data-en="Balance Due (SAR)" data-ar="المستحقات (ر.س)">Balance Due (SAR)</th>
                <th class="py-3 px-4 text-center" data-en="Account Status" data-ar="حالة الحساب">Account Status</th>
                <th class="py-3 px-6 text-end" data-en="Action" data-ar="الإجراء">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a] dark:divide-neutral-800 font-mono">
              <?php if (empty($clients)): ?>
                <tr>
                  <td colspan="7" class="py-10 text-center text-neutral-400 font-sans text-xs" data-en="No clients are currently assigned to this representative." data-ar="لا يوجد عملاء معينون لهذا المندوب حالياً.">
                    No clients are currently assigned to this representative.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($clients as $c): ?>
                  <tr class="hover:bg-[#102332] transition-colors">
                    <td class="py-3.5 px-6 font-sans">
                      <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode((string)$c['id'])) ?>" class="font-semibold text-white hover:text-teal-600 hover:underline block">
                        <?= htmlspecialchars($c['company_name'] ?? 'Unknown Company') ?>
                      </a>
                      <span class="text-[11px] font-mono text-neutral-400"><?= htmlspecialchars($c['reference_id'] ?? 'REF') ?></span>
                    </td>
                    <td class="py-3.5 px-4 font-sans text-slate-400">
                      <?= htmlspecialchars($c['segment'] ?? 'General') ?>
                    </td>
                    <td class="py-3.5 px-4 text-end font-mono text-white font-medium">
                      <?= number_format((int)$c['order_count']) ?>
                    </td>
                    <td class="py-3.5 px-4 text-end font-mono text-white font-bold">
                      <?= number_format((float)$c['client_value']) ?>
                    </td>
                    <td class="py-3.5 px-4 text-end font-mono font-bold <?= (float)$c['balance_due'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-teal-600 dark:text-teal-400' ?>">
                      <?= number_format((float)$c['balance_due']) ?>
                    </td>
                    <td class="py-3.5 px-4 text-center">
                      <?php $st = strtolower($c['status'] ?? 'active'); ?>
                      <span class="px-2 py-0.5 text-[10px] font-mono rounded-full border <?= $st === 'approved' || $st === 'active' ? 'border-teal-500/30 bg-teal-500/10 text-teal-400 dark:text-teal-400' : 'border-neutral-500/30 bg-neutral-500/10 text-neutral-600' ?>">
                        <?= htmlspecialchars(ucfirst($st)) ?>
                      </span>
                    </td>
                    <td class="py-3.5 px-6 text-end">
                      <div class="flex items-center justify-end gap-2">
                        <a href="<?= app_url('modules/crm_sales/client_transactions.php?id=' . urlencode((string)$c['reference_id'])) ?>" class="px-2.5 py-1 text-[11px] font-mono font-medium rounded border border-[#1e3a50] hover:border-neutral-900 dark:hover:border-white text-slate-300 transition-colors inline-flex items-center gap-1">
                          <span data-en="Statement" data-ar="كشف الحساب">Statement</span>
                          <i data-lucide="arrow-right" class="w-3 h-3 rtl:rotate-180"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <script>
    lucide.createIcons();
  </script>
</body>
</html>