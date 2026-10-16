<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

require_permission('users.manage');

$currentUserId = get_current_user_id();
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();

  if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add_user') {
      $emp_id = trim($_POST['emp_id'] ?? '');
      $full_name = trim($_POST['full_name'] ?? '');
      $username = trim($_POST['username'] ?? '');
      $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
      $phone = trim($_POST['phone'] ?? '');
      $role = trim($_POST['role'] ?? 'Sales Rep');
      $perm_level = in_array($_POST['permission_level'] ?? '', ['Admin', 'Edit', 'View'], true) ? $_POST['permission_level'] : 'View';
      $status = in_array($_POST['status'] ?? '', ['Active', 'Suspended'], true) ? $_POST['status'] : 'Active';
      $password = $_POST['password'] ?? '';

      if (empty($emp_id) || empty($full_name) || empty($username) || empty($email) || strlen($password) < 6) {
        $_SESSION['flash_error'] = "Validation Error: Please fill in all fields with a valid email and password (minimum 6 characters).";
      } else {
        try {
          $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE role_key = ?");
          $stmtRole->execute([$role]);
          $roleId = $stmtRole->fetchColumn() ?: null;

          $stmt = $pdo->prepare("INSERT INTO users (emp_id, full_name, username, email, phone, role, role_id, permission_level, status, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $stmt->execute([
            $emp_id, $full_name, $username, strtolower($email), $phone,
            $role, $roleId, $perm_level, $status, password_hash($password, PASSWORD_DEFAULT)
          ]);
          $_SESSION['flash_success'] = "User account successfully created.";
        } catch (PDOException $e) {
          $_SESSION['flash_error'] = ($e->getCode() == 23000) ? "Username, Email or Employee ID already exists." : "Database error occurred.";
        }
      }
      redirect(app_url('modules/insights/team_access.php'));
      exit;
    } elseif ($_POST['action'] === 'edit_user') {
      $targetId = (int)($_POST['user_id'] ?? 0);
      $emp_id = trim($_POST['emp_id'] ?? '');
      $full_name = trim($_POST['full_name'] ?? '');
      $username = trim($_POST['username'] ?? '');
      $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
      $phone = trim($_POST['phone'] ?? '');
      $role = trim($_POST['role'] ?? 'Sales Rep');
      $perm_level = in_array($_POST['permission_level'] ?? '', ['Admin', 'Edit', 'View'], true) ? $_POST['permission_level'] : 'View';
      $status = in_array($_POST['status'] ?? '', ['Active', 'Suspended'], true) ? $_POST['status'] : 'Active';

      // Prevent self-demotion or self-suspension
      if ($targetId === $currentUserId && ($role !== 'Admin' || $status !== 'Active')) {
        $_SESSION['flash_error'] = "Security Protection: You cannot demote or suspend your own active administrator account.";
      } elseif ($targetId > 0 && !empty($email)) {
        try {
          $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE role_key = ?");
          $stmtRole->execute([$role]);
          $roleId = $stmtRole->fetchColumn() ?: null;

          $stmt = $pdo->prepare("UPDATE users SET emp_id=?, full_name=?, username=?, email=?, phone=?, role=?, role_id=?, permission_level=?, status=? WHERE id=?");
          $stmt->execute([
            $emp_id, $full_name, $username, strtolower($email), $phone,
            $role, $roleId, $perm_level, $status, $targetId
          ]);
          $_SESSION['flash_success'] = "Staff account updated successfully.";
        } catch (PDOException $e) {
          $_SESSION['flash_error'] = "Update failed: " . ($e->getCode() == 23000 ? "Conflict with existing user." : "Database error.");
        }
      }
      redirect(app_url('modules/insights/team_access.php'));
      exit;
    } elseif ($_POST['action'] === 'delete_user') {
      $targetId = (int)($_POST['user_id'] ?? 0);
      $accType = $_POST['account_type'] ?? 'user';

      if ($accType === 'partner') {
        $stmt = $pdo->prepare("DELETE FROM partners WHERE id=?");
        $stmt->execute([$targetId]);
        $_SESSION['flash_success'] = "Partner account deleted.";
      } else {
        if ($targetId === $currentUserId) {
          $_SESSION['flash_error'] = "Security Protection: You cannot delete your own account.";
        } else {
          $stmtCountAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin' AND status = 'Active'");
          $activeAdmins = (int)($stmtCountAdmins ? $stmtCountAdmins->fetchColumn() : 0);

          $stmtCheck = $pdo->prepare("SELECT role FROM users WHERE id = ?");
          $stmtCheck->execute([$targetId]);
          $targetRole = $stmtCheck->fetchColumn();

          if ($targetRole === 'Admin' && $activeAdmins <= 1) {
            $_SESSION['flash_error'] = "Security Protection: Cannot delete the only remaining active Administrator.";
          } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$targetId]);
            $_SESSION['flash_success'] = "Staff account deleted.";
          }
        }
      }
      redirect(app_url('modules/insights/team_access.php'));
      exit;
    } elseif ($_POST['action'] === 'reset_password') {
      $targetId = (int)($_POST['user_id'] ?? 0);
      $accType = $_POST['account_type'] ?? 'user';
      $newPassword = $_POST['new_password'] ?? '';

      if (strlen($newPassword) < 6) {
        $_SESSION['flash_error'] = "Password must be at least 6 characters in length.";
      } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($accType === 'partner') {
          $stmt = $pdo->prepare("UPDATE partners SET password_hash=? WHERE id=?");
        } else {
          $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
        }
        $stmt->execute([$hash, $targetId]);
        $_SESSION['flash_success'] = "Password key successfully reset.";
      }
      redirect(app_url('modules/insights/team_access.php'));
      exit;
    }
  }
}

$active_page = 'team_access';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Insights & Admin', 'Directory & Access Control'];

$stmt_users = $pdo->query("SELECT id, 'user' as type, emp_id as ref_id, full_name as name, username, email, phone, role, permission_level, status, created_at FROM users");
$users_data = $stmt_users ? $stmt_users->fetchAll(PDO::FETCH_ASSOC) : [];

$stmt_partners = $pdo->query("SELECT id, 'partner' as type, reference_id as ref_id, CONCAT(contact_first_name, ' ', contact_last_name) as name, company_name as username, email, phone, partner_type as role, 'External' as permission_level, status, created_at FROM partners");
$partners_data = $stmt_partners ? $stmt_partners->fetchAll(PDO::FETCH_ASSOC) : [];

$all_accounts = array_merge($users_data, $partners_data);

usort($all_accounts, function($a, $b) {
  return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

$total_users = count($all_accounts);
$active_users = 0;
$admin_users = 0;
$suspended_users = 0;

foreach ($all_accounts as $u) {
  $stat = strtolower($u['status']);
  if (in_array($stat, ['active', 'approved'])) $active_users++;
  if (in_array($stat, ['suspended', 'rejected'])) $suspended_users++;
  if ($u['permission_level'] === 'Admin' && in_array($stat, ['active', 'approved'])) $admin_users++;
}

$flash_error = $_SESSION['flash_error'] ?? null;
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Directory & Access Control | I-GAS Industrial" data-ar="دليل المستخدمين والصلاحيات | I-GAS Industrial">Directory & Access Control | I-GAS Industrial</title>
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
    .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 50; display: none; align-items: center; justify-content: center; }
    .modal-bg.open { display: flex; }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

  <!-- Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-400 whitespace-nowrap">
        <span class="w-2 h-2 rounded-full bg-teal-500"></span>
        <span data-en="RBAC CREDENTIAL DIRECTORY & ACCESS CONTROL" data-ar="دليل الصلاحيات واعتماد الوصول">RBAC CREDENTIAL DIRECTORY & ACCESS CONTROL</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="ACTIVE SESSIONS SECURED" data-ar="الجلسات النشطة محمية">ACTIVE SESSIONS SECURED</span>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: RBAC-USERS-01</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6 max-w-7xl mx-auto w-full">

    <!-- Top Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs font-mono uppercase tracking-wider text-neutral-500" data-en="Insights & Admin" data-ar="الرؤى والإدارة">Insights & Admin</span>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400 rtl:rotate-180"></i>
          <span class="text-xs font-mono uppercase tracking-wider text-white font-semibold" data-en="Directory & Access" data-ar="الدليل والصلاحيات">Directory & Access</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Team Directory & Access Credentials" data-ar="دليل فريق العمل وبيانات اعتماد الوصول">Team Directory & Access Credentials</h1>
        <p class="text-xs text-slate-400 mt-1" data-en="Manage internal plant staff, administrative privilege tiers, corporate clients, and supplier access keys." data-ar="إدارة موظفي المصنع الداخليين، مستويات الصلاحيات الإدارية، العملاء الشركات، ومفاتيح وصول الموردين.">Manage internal plant staff, administrative privilege tiers, corporate clients, and supplier access keys.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <button onclick="exportUserData()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
          <i data-lucide="download" class="w-3.5 h-3.5"></i>
          <span data-en="Export Directory" data-ar="تصدير الدليل">Export Directory</span>
        </button>
        <button onclick="openModal('userModal')" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
          <i data-lucide="user-plus" class="w-4 h-4"></i>
          <span data-en="Add Staff Member" data-ar="إضافة موظف جديد">Add Staff Member</span>
        </button>
      </div>
    </div>

    <?php if (!empty($flash_error)): ?>
      <div class="p-4 border border-rose-800 dark:border-rose-900 bg-rose-950/30 dark:bg-rose-950/30 text-rose-300 dark:text-rose-300 rounded-xs flex items-center gap-3 text-sm">
        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
        <div class="text-xs font-mono">
          <?= htmlspecialchars($flash_error) ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($flash_success)): ?>
      <div class="p-4 border border-teal-800 dark:border-teal-900 bg-teal-950/30 dark:bg-teal-950/30 text-teal-300 dark:text-teal-300 rounded-xs flex items-center gap-3 text-sm">
        <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-teal-600 dark:text-teal-400"></i>
        <div class="text-xs font-mono">
          <?= htmlspecialchars($flash_success) ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Metric Bento Cards (4-col) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- Total Accounts -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Total Accounts" data-ar="إجمالي الحسابات">Total Accounts</span>
          <i data-lucide="users" class="w-4 h-4 text-slate-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $total_users ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Users" data-ar="مستخدم">Users</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-[#1e3a50] bg-[#09151f] text-slate-300 text-[11px] font-mono rounded-xs">
            <span data-en="Directory Total" data-ar="إجمالي السجل">Directory Total</span>
          </span>
        </div>
      </div>

      <!-- Active & Approved -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Active & Approved" data-ar="نشط ومعتمد">Active & Approved</span>
          <i data-lucide="user-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $active_users ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Active" data-ar="نشط">Active</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="check" class="w-3 h-3"></i>
            <span data-en="Operational" data-ar="تشغيلي">Operational</span>
          </span>
        </div>
      </div>

      <!-- Administrators -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Administrators" data-ar="المسؤولون">Administrators</span>
          <i data-lucide="shield" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight text-white"><?= $admin_users ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Admins" data-ar="مدير">Admins</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-teal-800 dark:border-teal-800/60 bg-teal-950/30 dark:bg-teal-950/40 text-teal-400 dark:text-teal-400 text-[11px] font-mono rounded-xs">
            <span data-en="Super-Admin Access" data-ar="صلاحيات كاملة">Super-Admin Access</span>
          </span>
        </div>
      </div>

      <!-- Suspended / Rejected -->
      <div class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-5 rounded-xs">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono uppercase tracking-wider text-slate-400" data-en="Suspended / Rejected" data-ar="موقوف / مرفوض">Suspended / Rejected</span>
          <i data-lucide="user-x" class="w-4 h-4 text-rose-500"></i>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tracking-tight <?= $suspended_users > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-white' ?>"><?= $suspended_users ?></span>
          <span class="text-xs font-mono text-neutral-500" data-en="Suspended" data-ar="موقوف">Suspended</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5">
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 border border-rose-800 dark:border-rose-800/60 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 text-[11px] font-mono rounded-xs">
            <i data-lucide="lock" class="w-3 h-3"></i>
            <span data-en="Access Revoked" data-ar="وصول ملغي">Access Revoked</span>
          </span>
        </div>
      </div>

    </div>

    <!-- Directory Table Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden flex flex-col">
      <div class="p-5 border-b border-[#1a3346] flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-[#09151f]">
        <div class="flex items-center gap-2">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="folder-lock" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Unified Accounts Directory" data-ar="دليل الحسابات الموحد">Unified Accounts Directory</span>
            <span class="ml-1.5 px-2 py-0.5 text-[11px] font-mono border border-[#1e3a50] bg-[#09151f] text-slate-300 rounded-xs">
              <span id="visibleCount"><?= count($all_accounts) ?></span>
            </span>
          </h2>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search account..." data-en-placeholder="Search account..." data-ar-placeholder="البحث عن حساب..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
        </div>
      </div>

      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-left rtl:text-right border-collapse" id="teamTable">
          <thead>
            <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
              <th class="px-5 py-3 font-semibold" data-en="Account Info" data-ar="معلومات الحساب">Account Info</th>
              <th class="px-5 py-3 font-semibold" data-en="Contact Details" data-ar="تفاصيل الاتصال">Contact Details</th>
              <th class="px-5 py-3 font-semibold" data-en="Classification / Role" data-ar="التصنيف / الدور">Classification / Role</th>
              <th class="px-5 py-3 font-semibold text-center" data-en="Access Tier" data-ar="مستوى الصلاحية">Access Tier</th>
              <th class="px-5 py-3 font-semibold text-center" data-en="Status" data-ar="الحالة">Status</th>
              <th class="px-5 py-3 font-semibold text-right rtl:text-left no-export" data-en="Security Actions" data-ar="إجراءات الأمان">Security Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] text-xs">
            <?php foreach ($all_accounts as $u): ?>
            <?php
              $initials = strtoupper(substr($u['name'], 0, 1) . substr(strstr($u['name'], ' ') ?: '', 1, 1));
              if (strlen(trim($initials)) < 2) $initials = strtoupper(substr($u['name'], 0, 2));
              
              $permBorder = 'border-[#1e3a50]';
              $permBg = 'bg-[#09151f]';
              $permFg = 'text-slate-300';
              
              if ($u['permission_level'] === 'Admin') {
                $permBorder = 'border-teal-800 dark:border-teal-800';
                $permBg = 'bg-teal-950/30 dark:bg-teal-950/40';
                $permFg = 'text-teal-400 dark:text-teal-400';
              } elseif ($u['permission_level'] === 'Edit') {
                $permBorder = 'border-blue-300 dark:border-blue-800';
                $permBg = 'bg-blue-950/30 dark:bg-blue-950/40';
                $permFg = 'text-blue-700 dark:text-blue-400';
              } elseif ($u['permission_level'] === 'External') {
                $permBorder = 'border-amber-300 dark:border-amber-800';
                $permBg = 'bg-amber-950/30 dark:bg-amber-950/40';
                $permFg = 'text-amber-400 dark:text-amber-400';
              }

              $stat = strtolower($u['status']);
              $statusDisplay = ucfirst($u['status']);
              
              if (in_array($stat, ['active', 'approved'])) {
                $statusBorder = 'border-emerald-300 dark:border-emerald-800';
                $statusBg = 'bg-emerald-50 dark:bg-emerald-950/40';
                $statusFg = 'text-emerald-700 dark:text-emerald-400';
                $dotColor = 'bg-emerald-500';
              } elseif ($stat === 'pending') {
                $statusBorder = 'border-amber-300 dark:border-amber-800';
                $statusBg = 'bg-amber-950/30 dark:bg-amber-950/40';
                $statusFg = 'text-amber-400 dark:text-amber-400';
                $dotColor = 'bg-amber-500';
              } else {
                $statusBorder = 'border-rose-800 dark:border-rose-800';
                $statusBg = 'bg-rose-950/30 dark:bg-rose-950/40';
                $statusFg = 'text-rose-400 dark:text-rose-400';
                $dotColor = 'bg-rose-500';
              }
              
              $isUser = ($u['type'] === 'user');
            ?>
            <tr class="hover:bg-[#102332] transition-colors user-row">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-xs flex items-center justify-center font-mono text-xs font-bold text-white shrink-0 <?= $isUser ? 'bg-cyan-500 text-white' : 'bg-amber-600' ?>">
                    <?= htmlspecialchars($initials) ?>
                  </div>
                  <div>
                    <div class="font-medium text-white flex items-center gap-1.5 u-name">
                      <?= htmlspecialchars($u['name']) ?>
                      <?php if (!$isUser): ?>
                        <i data-lucide="building-2" class="w-3.5 h-3.5 text-neutral-400"></i>
                      <?php endif; ?>
                    </div>
                    <div class="text-[11px] font-mono text-neutral-500 u-ref">
                      <?= $isUser ? '@' : '' ?><?= htmlspecialchars($u['username']) ?> · <?= htmlspecialchars($u['ref_id']) ?>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3.5 font-mono text-slate-400">
                <div><?= htmlspecialchars($u['email']) ?></div>
                <div class="text-[11px] text-neutral-400"><?= htmlspecialchars($u['phone']) ?></div>
              </td>
              <td class="px-5 py-3.5 font-mono text-slate-200">
                <?= ucfirst(htmlspecialchars($u['role'])) ?>
              </td>
              <td class="px-5 py-3.5 text-center">
                <span class="inline-flex items-center px-2 py-0.5 border <?= $permBorder ?> <?= $permBg ?> <?= $permFg ?> text-[11px] font-mono rounded-xs">
                  <?= htmlspecialchars($u['permission_level']) ?>
                </span>
              </td>
              <td class="px-5 py-3.5 text-center">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 border <?= $statusBorder ?> <?= $statusBg ?> <?= $statusFg ?> text-[11px] font-mono rounded-xs whitespace-nowrap">
                  <span class="w-1.5 h-1.5 rounded-full <?= $dotColor ?>"></span>
                  <?= $statusDisplay ?>
                </span>
              </td>
              <td class="px-5 py-3.5 text-right rtl:text-left no-export whitespace-nowrap">
                <div class="inline-flex items-center justify-end rtl:justify-start gap-1.5">
                  <?php if ($isUser): ?>
                  <button onclick='editUser(<?= json_encode($u) ?>)' class="w-7 h-7 flex items-center justify-center border border-[#1e3a50] bg-[#112433] hover:bg-[#102230] dark:hover:bg-neutral-700 text-slate-300 rounded-xs transition-colors" title="Edit Staff">
                    <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                  </button>
                  <?php endif; ?>
                  
                  <button onclick='resetPassword(<?= $u['id'] ?>, "<?= htmlspecialchars($u['username']) ?>", "<?= $u['type'] ?>")' class="w-7 h-7 flex items-center justify-center border border-[#1e3a50] bg-[#112433] hover:bg-[#102230] dark:hover:bg-neutral-700 text-slate-300 rounded-xs transition-colors" title="Reset Password">
                    <i data-lucide="key" class="w-3.5 h-3.5"></i>
                  </button>

                  <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this account?');" class="inline m-0 p-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="account_type" value="<?= $u['type'] ?>">
                    <button type="submit" class="w-7 h-7 flex items-center justify-center border border-rose-800 dark:border-rose-800 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] hover:bg-rose-950/30 dark:hover:bg-rose-950/40 text-rose-400 dark:text-rose-400 rounded-xs transition-colors" title="Delete Account">
                      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="px-5 py-3 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between text-xs font-mono text-neutral-500">
        <span data-en="All security keys verified" data-ar="جميع المفاتيح الأمنية موثقة">All security keys verified</span>
        <span>SEC: RBAC-AUDITED</span>
      </div>
    </div>

  </div>
</main>

<!-- Add / Edit User Modal -->
<div id="userModal" class="modal-bg">
  <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs w-[600px] overflow-hidden flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b border-[#1a3346] flex justify-between items-center bg-[#09151f]">
      <h3 id="modalTitle" class="text-sm font-mono uppercase tracking-wider font-semibold text-white" data-en="Add New Staff Member" data-ar="إضافة موظف جديد">Add New Staff Member</h3>
      <button type="button" onclick="closeModal('userModal')" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
    <form method="POST" action="" class="flex-1 overflow-auto p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="modalAction" value="add_user">
      <input type="hidden" name="user_id" id="modalUserId" value="">
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Full Name *" data-ar="الاسم الكامل *">Full Name *</label>
          <input type="text" name="full_name" id="f_full_name" required class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
        </div>
        <div>
          <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Employee ID *" data-ar="معرف الموظف *">Employee ID *</label>
          <input type="text" name="emp_id" id="f_emp_id" required class="w-full h-10 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Username *" data-ar="اسم المستخدم *">Username *</label>
          <input type="text" name="username" id="f_username" required class="w-full h-10 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
        </div>
        <div id="pwContainer">
          <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Initial Password *" data-ar="كلمة المرور الأولية *">Initial Password *</label>
          <input type="password" name="password" id="f_password" class="w-full h-10 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Email Address *" data-ar="البريد الإلكتروني *">Email Address *</label>
          <input type="email" name="email" id="f_email" required class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
        </div>
        <div>
          <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Phone Number" data-ar="رقم الهاتف">Phone Number</label>
          <input type="text" name="phone" id="f_phone" class="w-full h-10 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
        </div>
      </div>

      <div>
        <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="System Role *" data-ar="دور النظام *">System Role *</label>
        <select name="role" id="f_role" required class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
          <option value="Admin">Admin (Executive & System Admin)</option>
          <option value="Sales Manager">Sales Manager</option>
          <option value="Sales Rep">Sales Rep</option>
          <option value="Logistics Manager">Logistics Manager</option>
          <option value="Logistics Assistant">Logistics Assistant</option>
          <option value="Production Manager">Production Manager</option>
          <option value="Production Supervisor">Production Supervisor</option>
        </select>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Access Tier" data-ar="مستوى الصلاحية">Access Tier</label>
          <select name="permission_level" id="f_permission" class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
            <option value="View" data-en="View Only" data-ar="عرض فقط">View Only</option>
            <option value="Edit" data-en="Edit" data-ar="تعديل">Edit</option>
            <option value="Admin" data-en="Admin" data-ar="مسؤول">Admin</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Account Status" data-ar="حالة الحساب">Account Status</label>
          <select name="status" id="f_status" class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
            <option value="Active" data-en="Active" data-ar="نشط">Active</option>
            <option value="Suspended" data-en="Suspended" data-ar="موقوف">Suspended</option>
          </select>
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-[#1a3346]">
        <button type="button" onclick="closeModal('userModal')" class="border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 px-4 py-2 text-xs font-mono uppercase tracking-wider font-medium transition-colors" data-en="Cancel" data-ar="إلغاء">Cancel</button>
        <button type="submit" class="btn-cta-orange px-4 py-2 text-xs font-mono uppercase tracking-wider font-semibold transition-colors" data-en="Save Staff" data-ar="حفظ الموظف">Save Staff</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="pwModal" class="modal-bg">
  <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs w-[400px] overflow-hidden flex flex-col">
    <div class="px-6 py-4 border-b border-[#1a3346] flex justify-between items-center bg-[#09151f]">
      <h3 class="text-sm font-mono uppercase tracking-wider font-semibold text-white" data-en="Reset Security Key" data-ar="إعادة تعيين مفتاح الأمان">Reset Security Key</h3>
      <button type="button" onclick="closeModal('pwModal')" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
    <form method="POST" action="" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="pwUserId" value="">
      <input type="hidden" name="account_type" id="pwAccountType" value="">
      
      <p class="text-xs text-slate-400">
        <span data-en="Resetting credential access for" data-ar="إعادة تعيين بيانات الاعتماد لـ">Resetting credential access for</span> <strong class="font-mono text-white" id="pwUsername"></strong>
      </p>

      <div>
        <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="New Password Key *" data-ar="مفتاح المرور الجديد *">New Password Key *</label>
        <input type="password" name="new_password" required minlength="6" class="w-full h-10 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('pwModal')" class="border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 px-4 py-2 text-xs font-mono uppercase tracking-wider font-medium transition-colors" data-en="Cancel" data-ar="إلغاء">Cancel</button>
        <button type="submit" class="btn-cta-orange px-4 py-2 text-xs font-mono uppercase tracking-wider font-semibold transition-colors" data-en="Update Security Key" data-ar="تحديث المفتاح">Update Security Key</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openModal(id) {
    document.getElementById(id).classList.add('open');
    if (id === 'userModal') {
      const lang = localStorage.getItem('app_lang') || 'en';
      document.getElementById('modalTitle').textContent = lang === 'ar' ? 'إضافة موظف جديد' : 'Add New Staff Member';
      document.getElementById('modalAction').value = 'add_user';
      document.getElementById('modalUserId').value = '';
      document.getElementById('f_full_name').value = '';
      document.getElementById('f_emp_id').value = '';
      document.getElementById('f_username').value = '';
      document.getElementById('f_email').value = '';
      document.getElementById('f_phone').value = '';
      document.getElementById('f_role').value = 'Sales Rep';
      document.getElementById('f_permission').value = 'View';
      document.getElementById('f_status').value = 'Active';
      document.getElementById('pwContainer').style.display = 'block';
      document.getElementById('f_password').required = true;
    }
  }

  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
  }

  function editUser(u) {
    const lang = localStorage.getItem('app_lang') || 'en';
    document.getElementById('modalTitle').textContent = lang === 'ar' ? 'تعديل بيانات الموظف' : 'Edit Staff Member';
    document.getElementById('modalAction').value = 'edit_user';
    document.getElementById('modalUserId').value = u.id;
    
    document.getElementById('f_full_name').value = u.name;
    document.getElementById('f_emp_id').value = u.ref_id;
    document.getElementById('f_username').value = u.username;
    document.getElementById('f_email').value = u.email;
    document.getElementById('f_phone').value = u.phone;
    document.getElementById('f_role').value = u.role;
    document.getElementById('f_permission').value = u.permission_level;
    document.getElementById('f_status').value = u.status;
    
    document.getElementById('pwContainer').style.display = 'none';
    document.getElementById('f_password').required = false;

    document.getElementById('userModal').classList.add('open');
  }

  function resetPassword(id, username, accountType) {
    document.getElementById('pwUserId').value = id;
    document.getElementById('pwAccountType').value = accountType;
    document.getElementById('pwUsername').textContent = '@' + username;
    document.getElementById('pwModal').classList.add('open');
  }

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
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.user-row');
        let count = 0;
        rows.forEach(row => {
          const text = row.innerText.toLowerCase();
          if (text.includes(query)) {
            row.style.display = '';
            count++;
          } else {
            row.style.display = 'none';
          }
        });
        const visibleCount = document.getElementById('visibleCount');
        if (visibleCount) visibleCount.innerText = count;
      });
    }
  });

  function exportUserData() {
    let csv = [];
    csv.push('"Name","Username / Ref","Email","Phone","Role","Access Level","Status"');
    let rows = document.querySelectorAll("#teamTable tbody tr");
    rows.forEach(row => {
      if (row.style.display === 'none') return;
      let name = row.querySelector('.u-name')?.innerText.trim() || '';
      let ref = row.querySelector('.u-ref')?.innerText.trim() || '';
      let cols = row.querySelectorAll('td');
      let contact = cols[1]?.innerText.replace(/\n/g, ' ').trim() || '';
      let role = cols[2]?.innerText.trim() || '';
      let access = cols[3]?.innerText.trim() || '';
      let status = cols[4]?.innerText.trim() || '';
      csv.push(`"${name}","${ref}","${contact}","${role}","${access}","${status}"`);
    });

    let csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    let downloadLink = document.createElement("a");
    downloadLink.download = "Team_Access_Directory_" + new Date().toISOString().slice(0,10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
  }
</script>
</body>
</html>