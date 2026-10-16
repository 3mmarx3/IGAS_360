<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('users.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_rep') {
  require_csrf();
  require_permission('users.create');
  try {
    $allowed_roles = ['Sales Rep', 'Sales Manager'];
    $role = in_array($_POST['role'] ?? '', $allowed_roles) ? $_POST['role'] : 'Sales Rep';

    $role_key = ($role === 'Sales Manager') ? 'manager' : 'sales_rep';
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_key = ? LIMIT 1");
    $roleStmt->execute([$role_key]);
    $role_id = $roleStmt->fetchColumn() ?: null;

    $stmt = $pdo->prepare("
      INSERT INTO users (emp_id, full_name, username, email, phone, role, role_id, permission_level, status, password_hash, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([
      trim($_POST['emp_id'] ?? ''), 
      trim($_POST['full_name'] ?? ''), 
      trim($_POST['username'] ?? ''), 
      trim($_POST['email'] ?? ''), 
      trim($_POST['phone'] ?? ''), 
      $role, 
      $role_id,
      'View', 
      'Active', 
      password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT)
    ]);
    redirect('modules/crm_sales/sales_reps.php?created=1');
  } catch (PDOException $e) {
    $error_message = "Unable to create sales representative account. Please verify input data.";
  }
}

$active_page = 'sales_reps';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Sales Team Directory'];

$stmt_reps = $pdo->query("
  SELECT 
    u.id, u.full_name, u.role, u.email, u.phone, u.status, u.emp_id,
    (SELECT COUNT(id) FROM partners WHERE FIND_IN_SET(u.id, sales_rep_id) AND partner_type='client') as client_count,
    (SELECT COALESCE(SUM(total_value),0) FROM purchase_orders WHERE FIND_IN_SET(u.id, sales_rep_id) AND status NOT IN ('cancelled', 'draft')) as total_sales,
    (SELECT COALESCE(SUM(amount),0) FROM receipt_vouchers WHERE FIND_IN_SET(u.id, sales_rep_id)) as total_collections
  FROM users u
  WHERE u.role IN ('Sales Rep', 'sales_rep', 'Sales Manager', 'sales_manager', 'Admin', 'admin') 
  ORDER BY total_sales DESC, full_name ASC
");
$reps = $stmt_reps->fetchAll(PDO::FETCH_ASSOC);

// Calculate top-level KPIs
$total_reps = count($reps);
$total_portfolio_sales = 0;
$total_clients_managed = 0;
$total_collections_all = 0;
foreach ($reps as $r) {
  $total_portfolio_sales += (float)($r['total_sales'] ?? 0);
  $total_clients_managed += (int)($r['client_count'] ?? 0);
  $total_collections_all += (float)($r['total_collections'] ?? 0);
}

$role_map = [
  'Sales Rep' => ['en' => 'Sales Rep', 'ar' => 'مندوب مبيعات'],
  'sales_rep' => ['en' => 'Sales Rep', 'ar' => 'مندوب مبيعات'],
  'Sales Manager' => ['en' => 'Sales Manager', 'ar' => 'مدير مبيعات'],
  'sales_manager' => ['en' => 'Sales Manager', 'ar' => 'مدير مبيعات'],
  'Admin' => ['en' => 'Admin', 'ar' => 'مسؤول النظام'],
  'admin' => ['en' => 'Admin', 'ar' => 'مسؤول النظام']
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Team Directory | I-GAS Enterprise</title>
  
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
        <span data-en="COMMERCIAL FORCE DIRECTORY" data-ar="دليل القوة البيعية">COMMERCIAL FORCE DIRECTORY</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500"><?= $total_reps ?> <span data-en="REPRESENTATIVES" data-ar="مندوب">REPRESENTATIVES</span></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">
      <!-- Alert Messages -->
      <?php if (!empty($error_message)): ?>
        <div class="p-4 rounded-md border border-rose-800 dark:border-rose-900/60 bg-rose-950/30 dark:bg-rose-950/30 text-rose-300 dark:text-rose-300 text-xs flex items-center gap-2">
          <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600"></i>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['created'])): ?>
        <div class="p-4 rounded-md border border-teal-800 dark:border-teal-900/60 bg-teal-950/30 dark:bg-teal-950/30 text-teal-300 dark:text-teal-300 text-xs flex items-center gap-2">
          <i data-lucide="check-circle" class="w-4 h-4 text-teal-600"></i>
          <span data-en="Representative account successfully provisioned." data-ar="تم إنشاء حساب مندوب المبيعات بنجاح.">Representative account successfully provisioned.</span>
        </div>
      <?php endif; ?>

      <!-- Page Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1.5">
            <span class="text-xs font-mono text-neutral-500 uppercase tracking-wider" data-en="CRM & Commercial Operations" data-ar="إدارة علاقات العملاء والعمليات التجارية">CRM & Commercial Operations</span>
          </div>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Sales Representatives & Key Accounts" data-ar="مناديب المبيعات وكبار العملاء">
            Sales Representatives & Key Accounts
          </h1>
          <p class="text-xs text-neutral-500 mt-1" data-en="Manage technical sales personnel, customer account assignments, and quota realization." data-ar="إدارة الكادر الفني للمبيعات، وتوزيع محافظ العملاء، ومتابعة تحقيق الحصص.">
            Manage technical sales personnel, customer account assignments, and quota realization.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search by name, ID or role..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>

          <?php if (has_permission($pdo, 'users.create')): ?>
          <!-- Single Primary CTA: Safety Valve Orange -->
          <button onclick="openModal('repModal')" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span data-en="Add Sales Rep" data-ar="إضافة مندوب مبيعات">Add Sales Rep</span>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento KPI Grid (4 cards with 3px start accents) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Active Sales Force" data-ar="فريق المبيعات النشط">Active Sales Force</span>
            <i data-lucide="users" class="w-4 h-4 text-teal-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-white">
            <?= number_format($total_reps) ?>
            <span class="text-xs font-sans font-normal text-neutral-500" data-en="Officers" data-ar="مسؤول">Officers</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Account managers & executives" data-ar="مديرو حسابات وتنفيذيون">Account managers & executives</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Total Booked Sales" data-ar="إجمالي المبيعات المحققة">Total Booked Sales</span>
            <i data-lucide="trending-up" class="w-4 h-4 text-blue-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-white">
            <?= number_format($total_portfolio_sales) ?>
            <span class="text-xs font-sans font-normal text-neutral-500">SAR</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Cumulated PO revenue volume" data-ar="إجمالي إيرادات أوامر الشراء">Cumulated PO revenue volume</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Client Portfolios" data-ar="المحافظ المعينة">Client Portfolios</span>
            <i data-lucide="briefcase" class="w-4 h-4 text-amber-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-white">
            <?= number_format($total_clients_managed) ?>
            <span class="text-xs font-sans font-normal text-neutral-500" data-en="Accounts" data-ar="حساب">Accounts</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Active client accounts assigned" data-ar="حسابات عملاء مسندة">Active client accounts assigned</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Cash Collected" data-ar="إجمالي التحصيلات">Cash Collected</span>
            <i data-lucide="wallet" class="w-4 h-4 text-emerald-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-emerald-700 dark:text-emerald-400">
            <?= number_format($total_collections_all) ?>
            <span class="text-xs font-sans font-normal text-neutral-500">SAR</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Voucher payment realizations" data-ar="سندات القبض المودعة">Voucher payment realizations</p>
        </div>
      </div>

      <!-- Representatives List View -->
      <?php if (empty($reps)): ?>
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-12 text-center flex flex-col items-center justify-center">
          <i data-lucide="users-round" class="w-12 h-12 text-neutral-300 dark:text-neutral-700 mb-3"></i>
          <h3 class="text-sm font-semibold text-white mb-1" data-en="No Sales Representatives Found" data-ar="لم يتم العثور على مناديب مبيعات">
            No Sales Representatives Found
          </h3>
          <p class="text-xs text-neutral-500" data-en="There are currently no active users assigned to a commercial sales role." data-ar="لا يوجد حالياً مستخدمون نشطون معينون بدور المبيعات التجارية.">
            There are currently no active users assigned to a commercial sales role.
          </p>
        </div>
      <?php else: ?>
        <div class="space-y-3" id="repsList">
          <?php foreach ($reps as $rep): ?>
            <?php 
              $words = preg_split('/\s+/', trim((string)$rep['full_name']));
              $initials = '';
              if (count($words) >= 2) {
                $initials = mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8');
              } elseif (count($words) == 1 && mb_strlen($words[0]) > 0) {
                $initials = mb_substr($words[0], 0, 2, 'UTF-8');
              }
              $initials = mb_strtoupper($initials, 'UTF-8') ?: 'SR';
              
              $role_clean = $rep['role'] ?? 'Sales Rep';
              $display_role_en = $role_map[$role_clean]['en'] ?? $role_clean;
              $display_role_ar = $role_map[$role_clean]['ar'] ?? $role_clean;
            ?>
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:border-neutral-400 dark:hover:border-neutral-600 transition-colors filterable-row border-s-4 border-s-[#f97316]" data-search="<?= strtolower(htmlspecialchars((string)$rep['full_name'] . ' ' . ($rep['emp_id'] ?? '') . ' ' . $rep['role'])) ?>">
              
              <!-- Profile Identity -->
              <div class="flex items-center gap-4 min-w-[260px]">
                <div class="relative shrink-0">
                  <div class="w-12 h-12 rounded-md bg-[#09151f] border border-[#1e3a50] flex items-center justify-center font-mono font-bold text-sm text-slate-200">
                    <?= htmlspecialchars($initials) ?>
                  </div>
                  <span class="w-2.5 h-2.5 rounded-full absolute -top-1 -end-1 border-2 border-white dark:border-neutral-900 <?= strtolower($rep['status'] ?? '') === 'active' ? 'bg-teal-500' : 'bg-rose-500' ?>" title="<?= htmlspecialchars($rep['status'] ?? 'Active') ?>"></span>
                </div>
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-white">
                      <?= htmlspecialchars($rep['full_name']) ?>
                    </h3>
                    <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-[#09151f] text-slate-400 border border-[#1e3a50]">
                      <?= htmlspecialchars($display_role_en) ?>
                    </span>
                  </div>
                  <div class="flex items-center gap-2 text-xs text-neutral-500 font-mono">
                    <span>EMP: <?= htmlspecialchars($rep['emp_id'] ?? 'N/A') ?></span>
                    <span>•</span>
                    <span><?= htmlspecialchars($rep['phone'] ?? '—') ?></span>
                  </div>
                </div>
              </div>
              
              <!-- KPI Metrics for Rep -->
              <div class="grid grid-cols-3 gap-6 flex-1 max-w-lg border-y md:border-y-0 md:border-s md:border-e border-neutral-100 border-[#1a3346] py-3 md:py-0 md:px-6">
                <div>
                  <span class="text-[11px] font-mono text-neutral-400 block uppercase" data-en="Clients" data-ar="العملاء">Clients</span>
                  <span class="text-base font-mono font-bold text-white"><?= number_format((int)$rep['client_count']) ?></span>
                </div>
                <div>
                  <span class="text-[11px] font-mono text-neutral-400 block uppercase" data-en="Booked Sales" data-ar="المبيعات">Booked Sales</span>
                  <span class="text-base font-mono font-bold text-white"><?= number_format((float)$rep['total_sales']) ?> <span class="text-[10px] font-normal text-neutral-400">SAR</span></span>
                </div>
                <div>
                  <span class="text-[11px] font-mono text-neutral-400 block uppercase" data-en="Collections" data-ar="التحصيلات">Collections</span>
                  <span class="text-base font-mono font-bold text-emerald-700 dark:text-emerald-400"><?= number_format((float)$rep['total_collections']) ?> <span class="text-[10px] font-normal text-neutral-400">SAR</span></span>
                </div>
              </div>
              
              <!-- Actions -->
              <div class="flex items-center gap-2 shrink-0">
                <?php if (!empty($rep['email'])): ?>
                <a href="mailto:<?= htmlspecialchars($rep['email']) ?>" class="p-2 rounded-md border border-[#1e3a50] hover:border-neutral-900 dark:hover:border-white text-slate-400 hover:text-white transition-colors" title="Send Email">
                  <i data-lucide="mail" class="w-4 h-4"></i>
                </a>
                <?php endif; ?>
                <a href="<?= app_url('modules/crm_sales/rep_profile.php?id=' . urlencode((string)$rep['id'])) ?>" class="px-3.5 py-2 text-xs font-semibold rounded-md border border-[#1e3a50] hover:border-neutral-900 dark:hover:border-white text-slate-200 transition-colors inline-flex items-center gap-1.5">
                  <span data-en="Sales Cockpit" data-ar="لوحة التحكم">Sales Cockpit</span>
                  <i data-lucide="arrow-right" class="w-3.5 h-3.5 rtl:rotate-180"></i>
                </a>
              </div>
            </div>
          <?php endforeach; ?>

          <div id="noResultsState" style="display: none;" class="bg-[#0c1924] border border-[#1a3346] rounded-md p-10 text-center flex-col items-center justify-center">
            <i data-lucide="search-x" class="w-10 h-10 text-neutral-400 mb-2"></i>
            <p class="text-sm font-semibold text-white" data-en="No matching personnel found" data-ar="لا توجد نتائج مطابقة">No matching personnel found</p>
            <p class="text-xs text-neutral-500 mt-1" data-en="Refine your search keyword." data-ar="يرجى تعديل كلمة البحث.">Refine your search keyword.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Provisioning Modal (Flat industrial, 1px border) -->
  <div id="repModal" class="fixed inset-0 bg-[#060e14]/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-md w-full max-w-lg overflow-hidden flex flex-col">
      <div class="px-6 py-4 border-b border-[#1a3346] flex items-center justify-between">
        <h3 class="text-sm font-semibold text-white" data-en="Provision Sales Representative" data-ar="إضافة مندوب مبيعات جديد">
          Provision Sales Representative
        </h3>
        <button type="button" onclick="closeModal('repModal')" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <form method="POST" action="" class="p-6 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_rep">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-mono font-medium text-slate-300 mb-1" data-en="Full Name *" data-ar="الاسم الكامل *">Full Name *</label>
            <input type="text" name="full_name" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-600">
          </div>
          <div>
            <label class="block text-xs font-mono font-medium text-slate-300 mb-1" data-en="Employee ID *" data-ar="معرف الموظف *">Employee ID *</label>
            <input type="text" name="emp_id" placeholder="EMP-4000" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-mono font-medium text-slate-300 mb-1" data-en="Username *" data-ar="اسم المستخدم *">Username *</label>
            <input type="text" name="username" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
          </div>
          <div>
            <label class="block text-xs font-mono font-medium text-slate-300 mb-1" data-en="Initial Password *" data-ar="كلمة المرور الأولية *">Initial Password *</label>
            <input type="password" name="password" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-600">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-mono font-medium text-slate-300 mb-1" data-en="Email Address *" data-ar="البريد الإلكتروني *">Email Address *</label>
            <input type="email" name="email" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-600">
          </div>
          <div>
            <label class="block text-xs font-mono font-medium text-slate-300 mb-1" data-en="Phone Number" data-ar="رقم الهاتف">Phone Number</label>
            <input type="text" name="phone" placeholder="+966 50 000 0000" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
          </div>
        </div>

        <div>
          <label class="block text-xs font-mono font-medium text-slate-300 mb-1" data-en="Designated Sales Role *" data-ar="الدور الوظيفي في المبيعات *">Designated Sales Role *</label>
          <select name="role" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
            <option value="Sales Rep" data-en="Sales Rep" data-ar="مندوب مبيعات">Sales Rep (مندوب مبيعات)</option>
            <option value="Sales Manager" data-en="Sales Manager" data-ar="مدير مبيعات">Sales Manager (مدير مبيعات)</option>
          </select>
        </div>

        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-[#1a3346]">
          <button type="button" onclick="closeModal('repModal')" class="px-4 py-2 text-xs font-medium border border-[#1e3a50] hover:border-neutral-900 dark:hover:border-white rounded text-slate-300 transition-colors" data-en="Cancel" data-ar="إلغاء">Cancel</button>
          <button type="submit" class="px-4 py-2 text-xs font-semibold rounded bg-teal-800 hover:bg-teal-700 text-white transition-colors" data-en="Create Account" data-ar="إنشاء الحساب">Create Account</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    lucide.createIcons();

    function openModal(id) {
      const m = document.getElementById(id);
      if (m) {
        m.classList.remove('hidden');
        m.classList.add('flex');
      }
    }

    function closeModal(id) {
      const m = document.getElementById(id);
      if (m) {
        m.classList.add('hidden');
        m.classList.remove('flex');
      }
    }

    document.getElementById('searchInput')?.addEventListener('input', function(e) {
      const q = e.target.value.toLowerCase().trim();
      const rows = document.querySelectorAll('.filterable-row');
      let visible = 0;
      rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        if (!q || text.includes(q)) {
          r.style.display = '';
          visible++;
        } else {
          r.style.display = 'none';
        }
      });
      const noRes = document.getElementById('noResultsState');
      if (noRes) {
        noRes.style.display = (visible === 0 && rows.length > 0) ? 'flex' : 'none';
      }
    });
  </script>
</body>
</html>