<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'reports.view');

$active_page = 'sales_performance';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Sales Performance'];

$is_rep = has_role('sales_rep') && !has_role('admin') && !has_role('manager') && !has_role('super_admin');
$uid = (int)($_SESSION['user_id'] ?? 0);

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
  require_permission($pdo, 'reports.export');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="Sales_Reps_Performance_' . date('Ymd') . '.csv"');
  
  $output = fopen('php://output', 'w');
  fputs($output, "\xEF\xBB\xBF");
  
  fputcsv($output, ['Representative Name', 'Role', 'Email', 'Assigned Clients', 'Total Sales (SAR)', 'Total Collected (SAR)', 'Pending Commission (SAR)', 'Paid Commission (SAR)']);

  if ($is_rep) {
    $stmt_export = $pdo->prepare("
      SELECT u.id, u.full_name, u.email, u.role,
          (SELECT COUNT(id) FROM partners WHERE sales_rep_id = u.id AND partner_type = 'client') as client_count,
          (SELECT COALESCE(SUM(total_value),0) FROM purchase_orders WHERE sales_rep_id = u.id AND status NOT IN ('cancelled', 'draft')) as total_sales,
          (SELECT COALESCE(SUM(amount),0) FROM receipt_vouchers WHERE sales_rep_id = u.id) as total_collections,
          (SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE sales_rep_id = u.id AND status IN ('pending', 'approved')) as pending_comm,
          (SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE sales_rep_id = u.id AND status = 'paid') as paid_comm
      FROM users u
      WHERE u.id = ?
    ");
    $stmt_export->execute([$uid]);
  } else {
    $stmt_export = $pdo->query("
      SELECT u.id, u.full_name, u.email, u.role,
          (SELECT COUNT(id) FROM partners WHERE sales_rep_id = u.id AND partner_type = 'client') as client_count,
          (SELECT COALESCE(SUM(total_value),0) FROM purchase_orders WHERE sales_rep_id = u.id AND status NOT IN ('cancelled', 'draft')) as total_sales,
          (SELECT COALESCE(SUM(amount),0) FROM receipt_vouchers WHERE sales_rep_id = u.id) as total_collections,
          (SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE sales_rep_id = u.id AND status IN ('pending', 'approved')) as pending_comm,
          (SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE sales_rep_id = u.id AND status = 'paid') as paid_comm
      FROM users u
      WHERE u.role IN ('Sales Rep', 'sales_rep', 'Sales Manager', 'sales_manager', 'Admin', 'admin')
      GROUP BY u.id
      HAVING total_sales > 0 OR total_collections > 0 OR pending_comm > 0 OR paid_comm > 0 OR client_count > 0
      ORDER BY total_sales DESC
    ");
  }
  
  while ($row = $stmt_export->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
      $row['full_name'],
      $row['role'],
      $row['email'] ?: 'N/A',
      $row['client_count'],
      number_format($row['total_sales'], 2, '.', ''),
      number_format($row['total_collections'], 2, '.', ''),
      number_format($row['pending_comm'], 2, '.', ''),
      number_format($row['paid_comm'], 2, '.', '')
    ]);
  }
  fclose($output);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['commission_id'])) {
  require_csrf();
  require_permission($pdo, 'invoices.manage');
  $action = $_POST['action'];
  $commission_id = (int)$_POST['commission_id'];
  
  if ($action === 'approve') {
    $pdo->prepare("UPDATE sales_commissions SET status = 'approved' WHERE id = ?")->execute([$commission_id]);
  } elseif ($action === 'pay') {
    $pdo->prepare("UPDATE sales_commissions SET status = 'paid' WHERE id = ?")->execute([$commission_id]);
  }
  redirect(app_url('modules/crm_sales/sales_performance.php'));
}

if ($is_rep) {
  $m_sales_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_value),0) FROM purchase_orders WHERE sales_rep_id = ? AND status NOT IN ('cancelled', 'draft')");
  $m_sales_stmt->execute([$uid]);
  $m_sales = (float)$m_sales_stmt->fetchColumn();

  $m_col_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM receipt_vouchers WHERE sales_rep_id = ?");
  $m_col_stmt->execute([$uid]);
  $m_collections = (float)$m_col_stmt->fetchColumn();

  $m_comm_p_stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE sales_rep_id = ? AND status IN ('pending', 'approved')");
  $m_comm_p_stmt->execute([$uid]);
  $m_comm_pending = (float)$m_comm_p_stmt->fetchColumn();

  $m_comm_paid_stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE sales_rep_id = ? AND status = 'paid'");
  $m_comm_paid_stmt->execute([$uid]);
  $m_comm_paid = (float)$m_comm_paid_stmt->fetchColumn();

  $stmt_reps = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.role,
        (SELECT COUNT(id) FROM partners WHERE sales_rep_id = u.id AND partner_type = 'client') as client_count,
        (SELECT COALESCE(SUM(total_value),0) FROM purchase_orders WHERE sales_rep_id = u.id AND status NOT IN ('cancelled', 'draft')) as total_sales,
        (SELECT COALESCE(SUM(amount),0) FROM receipt_vouchers WHERE sales_rep_id = u.id) as total_collections,
        (SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE sales_rep_id = u.id AND status IN ('pending', 'approved')) as pending_comm,
        (SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE sales_rep_id = u.id AND status = 'paid') as paid_comm
    FROM users u
    WHERE u.id = ?
  ");
  $stmt_reps->execute([$uid]);
} else {
  $m_sales = (float)$pdo->query("SELECT COALESCE(SUM(total_value),0) FROM purchase_orders WHERE status NOT IN ('cancelled', 'draft')")->fetchColumn();
  $m_collections = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM receipt_vouchers")->fetchColumn();
  $m_comm_pending = (float)$pdo->query("SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE status IN ('pending', 'approved')")->fetchColumn();
  $m_comm_paid = (float)$pdo->query("SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE status = 'paid'")->fetchColumn();

  $stmt_reps = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.role,
        (SELECT COUNT(id) FROM partners WHERE sales_rep_id = u.id AND partner_type = 'client') as client_count,
        (SELECT COALESCE(SUM(total_value),0) FROM purchase_orders WHERE sales_rep_id = u.id AND status NOT IN ('cancelled', 'draft')) as total_sales,
        (SELECT COALESCE(SUM(amount),0) FROM receipt_vouchers WHERE sales_rep_id = u.id) as total_collections,
        (SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE sales_rep_id = u.id AND status IN ('pending', 'approved')) as pending_comm,
        (SELECT COALESCE(SUM(commission_amount),0) FROM sales_commissions WHERE sales_rep_id = u.id AND status = 'paid') as paid_comm
    FROM users u
    WHERE u.role IN ('Sales Rep', 'sales_rep', 'Sales Manager', 'sales_manager', 'Admin', 'admin')
    GROUP BY u.id
    HAVING total_sales > 0 OR total_collections > 0 OR pending_comm > 0 OR paid_comm > 0 OR client_count > 0
    ORDER BY total_sales DESC
  ");
}
$reps = $stmt_reps->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Performance & Commissions | I-GAS Enterprise</title>
  
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
        <span data-en="COMMERCIAL REVENUE & COMMISSION ENGINE" data-ar="محرك الإيرادات التجارية والعمولات">COMMERCIAL REVENUE & COMMISSION ENGINE</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500"><?= count($reps) ?> <span data-en="REPRESENTATIVES TRACKED" data-ar="مندوب قيد المتابعة">REPRESENTATIVES TRACKED</span></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Page Title & Header Actions -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <span class="text-xs font-mono uppercase tracking-wider text-teal-600 dark:text-teal-400 font-semibold block mb-1" data-en="SALES QUOTAS & REMUNERATION AUDIT" data-ar="تدقيق أهداف المبيعات والمكافآت">SALES QUOTAS & REMUNERATION AUDIT</span>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Sales Performance & Commissions" data-ar="أداء المبيعات والعمولات">
            Sales Performance & Commissions
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Track account manager order throughput, collection settlement rates, and pending commission releases." data-ar="تتبع حجم أوامر مديري الحسابات ومعدلات التحصيل وتحرير العمولات المعلقة.">
            Track account manager order throughput, collection settlement rates, and pending commission releases.
          </p>
        </div>
        <div class="flex items-center gap-3">
          <a href="?export=excel" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm">
            <i data-lucide="download" class="w-3.5 h-3.5"></i>
            <span data-en="Export Performance CSV" data-ar="تصدير تقرير الأداء">Export Performance CSV</span>
          </a>
        </div>
      </div>

      <!-- Bento KPI Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 block mb-2" data-en="TOTAL SALES VOLUME" data-ar="إجمالي حجم المبيعات">TOTAL SALES VOLUME</span>
          <div class="text-2xl font-bold font-mono text-white tabular-nums">
            <?= number_format($m_sales, 2) ?> <span class="text-xs font-normal text-neutral-500">SAR</span>
          </div>
          <span class="text-[11px] font-mono text-neutral-400 block mt-2" data-en="Active & delivered purchase orders" data-ar="أوامر الشراء النشطة والمسلمة">Active & delivered purchase orders</span>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 block mb-2" data-en="TOTAL COLLECTIONS" data-ar="إجمالي التحصيلات">TOTAL COLLECTIONS</span>
          <div class="text-2xl font-bold font-mono text-blue-700 dark:text-blue-400 tabular-nums">
            <?= number_format($m_collections, 2) ?> <span class="text-xs font-normal text-neutral-500">SAR</span>
          </div>
          <span class="text-[11px] font-mono text-neutral-400 block mt-2" data-en="Cash, checks & bank wires cleared" data-ar="دفعات نقدية وشيكات وحوالات">Cash, checks & bank wires cleared</span>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 block mb-2" data-en="PENDING COMMISSIONS" data-ar="العمولات المعلقة">PENDING COMMISSIONS</span>
          <div class="text-2xl font-bold font-mono text-amber-400 dark:text-amber-400 tabular-nums">
            <?= number_format($m_comm_pending, 2) ?> <span class="text-xs font-normal text-neutral-500">SAR</span>
          </div>
          <span class="text-[11px] font-mono text-amber-400 dark:text-amber-400 block mt-2 font-medium" data-en="Awaiting finance release approval" data-ar="بانتظار موافقة المالية">Awaiting finance release approval</span>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 block mb-2" data-en="PAID COMMISSIONS" data-ar="العمولات المدفوعة">PAID COMMISSIONS</span>
          <div class="text-2xl font-bold font-mono text-teal-400 dark:text-teal-400 tabular-nums">
            <?= number_format($m_comm_paid, 2) ?> <span class="text-xs font-normal text-neutral-500">SAR</span>
          </div>
          <span class="text-[11px] font-mono text-teal-400 dark:text-teal-400 block mt-2 font-medium" data-en="Disbursed to sales representatives" data-ar="تم صرفها للمندوبين">Disbursed to sales representatives</span>
        </div>
      </div>

      <!-- Performance Table Container -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden border-s-4 border-s-[#f97316]">
        <div class="px-6 py-4 border-b border-[#1a3346] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i data-lucide="award" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="SALES FORCE QUOTA REALIZATION" data-ar="تحقيق مستهدفات فريق المبيعات">SALES FORCE QUOTA REALIZATION</h3>
          </div>
          <span class="text-xs font-mono text-neutral-500"><?= count($reps) ?> <span data-en="ACCOUNTS ACTIVE" data-ar="حساب نشط">ACCOUNTS ACTIVE</span></span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-6 py-3.5 font-semibold" data-en="Representative Name" data-ar="اسم المندوب">Representative Name</th>
                <th class="px-6 py-3.5 font-semibold text-center" data-en="Clients" data-ar="العملاء">Clients</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left" data-en="Total Sales (SAR)" data-ar="إجمالي المبيعات (ريال)">Total Sales (SAR)</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left" data-en="Collections (SAR)" data-ar="التحصيلات (ريال)">Collections (SAR)</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left" data-en="Pending Comm." data-ar="العمولة المعلقة">Pending Comm.</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left" data-en="Paid Comm." data-ar="العمولة المدفوعة">Paid Comm.</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-[#152a3a]">
              <?php if(empty($reps)): ?>
              <tr>
                <td colspan="6" class="px-6 py-12 text-center text-xs font-mono text-neutral-400" data-en="No active sales performance records found." data-ar="لا توجد سجلات أداء مبيعات نشطة حالياً.">
                  No active sales performance records found.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($reps as $rep): ?>
                <tr class="hover:bg-[#102332]/50 dark:hover:bg-neutral-800/40 transition-colors">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-sm bg-[#09151f] border border-[#1e3a50] flex items-center justify-center font-mono font-bold text-xs text-slate-300">
                        <?= htmlspecialchars(mb_substr($rep['full_name'], 0, 1)) ?>
                      </div>
                      <div>
                        <a href="<?= app_url('modules/crm_sales/rep_profile.php?id=' . (int)$rep['id']) ?>" class="font-sans font-bold text-white hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                          <?= htmlspecialchars($rep['full_name']) ?>
                        </a>
                        <div class="flex items-center gap-2 text-[11px] font-mono text-neutral-400 mt-0.5">
                          <span class="px-1.5 py-0.2 rounded-sm bg-[#09151f] text-[10px] text-neutral-500 uppercase"><?= htmlspecialchars($rep['role']) ?></span>
                          <span><?= htmlspecialchars($rep['email'] ?: 'No email') ?></span>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-center font-mono font-bold text-slate-200">
                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-sm bg-[#09151f] border border-[#1e3a50] text-xs">
                      <?= (int)$rep['client_count'] ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-white tabular-nums">
                    <?= number_format($rep['total_sales'], 2) ?>
                  </td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-blue-700 dark:text-blue-400 tabular-nums">
                    <?= number_format($rep['total_collections'], 2) ?>
                  </td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-amber-400 dark:text-amber-400 tabular-nums">
                    <?= number_format($rep['pending_comm'], 2) ?>
                  </td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-teal-400 dark:text-teal-400 tabular-nums">
                    <?= number_format($rep['paid_comm'], 2) ?>
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

    function applyLanguage(lang) {
      document.querySelectorAll('[data-en]').forEach(el => {
        const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
        el.innerText = transText;
      });
    }

    document.addEventListener("DOMContentLoaded", function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      applyLanguage(savedLang);
    });
  </script>
</body>
</html>