<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_permission($pdo, 'logistics.view');

$is_admin = has_role($pdo, 'Admin') || has_role($pdo, 'Super Admin') || has_role($pdo, 'Logistics Manager') || has_permission($pdo, 'logistics.delete');

$active_page = 'drivers_directory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Drivers Directory'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_csrf();
  require_permission($pdo, 'logistics.delete');
  $driver_id_to_delete = $_POST['driver_id'] ?? '';
  if (!empty($driver_id_to_delete)) {
    $stmt_del = $pdo->prepare("DELETE FROM drivers WHERE id = ?");
    $stmt_del->execute([$driver_id_to_delete]);
    redirect(app_url('modules/logistics_fleet/drivers_directory.php'));
  }
}

$stmt_stats = $pdo->query("SELECT status, COUNT(*) as count FROM drivers GROUP BY status");
$stats_raw = $stmt_stats->fetchAll(PDO::FETCH_KEY_PAIR);

$stats = [];
if ($stats_raw) {
  foreach ($stats_raw as $k => $v) {
    $stats[strtolower($k)] = (int)$v;
  }
}

$active_count  = $stats['active'] ?? 0;
$on_leave_count = $stats['on_leave'] ?? 0;
$suspended_count = $stats['suspended'] ?? 0;
$total_drivers  = array_sum($stats);

$today = date('Y-m-d');
$sixtyDays = date('Y-m-d', strtotime('+60 days'));
$stmt_exp = $pdo->prepare("SELECT COUNT(*) FROM drivers WHERE license_expiry BETWEEN ? AND ?");
$stmt_exp->execute([$today, $sixtyDays]);
$expiring_soon = (int)$stmt_exp->fetchColumn();

$stmt_drivers = $pdo->query("SELECT * FROM drivers ORDER BY created_at DESC");
$drivers_data = $stmt_drivers->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
  'active'  => ['badge' => 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'On Duty', 'label_ar' => 'في العمل', 'tab' => 'active'],
  'on_leave' => ['badge' => 'border border-amber-500/30 bg-amber-500/10 text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'On Leave', 'label_ar' => 'في إجازة', 'tab' => 'on_leave'],
  'suspended' => ['badge' => 'border border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400', 'dot' => 'bg-red-500', 'label_en' => 'Suspended', 'label_ar' => 'موقوف', 'tab' => 'suspended'],
];

$license_map = [
  'heavy_hazmat' => ['en' => 'Heavy / Hazmat', 'ar' => 'نقل ثقيل / مواد خطرة', 'badge' => 'border-teal-500/40 text-teal-400 dark:text-teal-400 bg-teal-500/10'],
  'heavy'    => ['en' => 'Heavy Transport', 'ar' => 'نقل ثقيل', 'badge' => 'border-cyan-500/40 text-cyan-700 dark:text-cyan-400 bg-cyan-500/10'],
  'light'    => ['en' => 'Light Commercial', 'ar' => 'نقل خفيف', 'badge' => 'border-neutral-500/40 text-slate-400 bg-neutral-500/10'],
  'private'   => ['en' => 'Private', 'ar' => 'خاص', 'badge' => 'border-neutral-500/40 text-slate-400 bg-neutral-500/10']
];

function getInitials($name) {
  $words = explode(' ', trim($name));
  $initials = '';
  foreach ($words as $w) {
    if (!empty($w)) {
      $initials .= strtoupper($w[0]);
    }
    if (strlen($initials) >= 2) break;
  }
  return $initials ?: 'DR';
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Hazmat Drivers Directory | I-GAS Control Room" data-ar="دليل سائقي نقل المواد الخطرة | غرفة التحكم I-GAS">Hazmat Drivers Directory | I-GAS Control Room</title>
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
    .tab-btn { position: relative; padding-bottom: 10px; font-size: 13px; font-weight: 500; color: #64748b; transition: all 0.15s ease; border-bottom: 2px solid transparent; }
    .tab-btn:hover { color: #0f172a; }
    .dark .tab-btn:hover { color: #f8fafc; }
    .tab-btn.active { color: #0b6e8a; border-bottom-color: #0b6e8a; font-weight: 600; }
    .dark .tab-btn.active { color: #19b5c9; border-bottom-color: #19b5c9; }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

  <!-- Control Room Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-400">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span data-en="HAZMAT OPERATOR ACCREDITATION REGISTRY" data-ar="سجل اعتماد وتراخيص مشغلي وسائقي المواد الخطرة">HAZMAT OPERATOR ACCREDITATION REGISTRY</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="REGISTERED DRIVERS:" data-ar="السائقون المعتمدون:">REGISTERED DRIVERS:</span> <strong class="text-white"><?= $total_drivers ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="ACTIVE ON DUTY:" data-ar="على رأس العمل:">ACTIVE ON DUTY:</span> <strong class="text-emerald-700 dark:text-emerald-400"><?= $active_count ?></strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="shield-check" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
      <span data-en="CIVIL DEFENSE & ADR COMPLIANT" data-ar="معتمد من الدفاع المدني ونظام ADR">CIVIL DEFENSE & ADR COMPLIANT</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-teal-500/30 bg-teal-500/10 text-teal-400 dark:text-teal-400 rounded-xs">
            LOGISTICS // FLEET PERSONNEL
          </span>
          <span class="text-xs text-slate-400 font-mono">SYS-DRV-01</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Hazmat Drivers Directory" data-ar="دليل سائقي نقل المواد الخطرة">
          Hazmat Drivers Directory
        </h1>
        <p class="text-xs md:text-sm text-slate-400 mt-1 max-w-2xl" data-en="Manage tanker operators, heavy commercial license renewals, ADR credentials, and active route assignments." data-ar="إدارة سائقي صهاريج الغاز، تجديد رخص النقل الثقيل، شهادات ADR لنقل المواد الخطرة، وتعيينات الرحلات النشطة.">
          Manage tanker operators, heavy commercial license renewals, ADR credentials, and active route assignments.
        </p>
      </div>
      <div class="flex items-center gap-2.5">
        <button id="exportExcelBtn" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
          <i data-lucide="download" class="w-4 h-4 text-slate-400"></i>
          <span data-en="Export Registry" data-ar="تصدير السجل">Export Registry</span>
        </button>
        <?php if (has_permission($pdo, 'logistics.create')): ?>
        <a href="new_driver.php" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span data-en="Register Driver" data-ar="إضافة سائق جديد">Register Driver</span>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Metric KPI Bento Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Drivers -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="Total Drivers" data-ar="إجمالي السائقين">Total Drivers</span>
          <i data-lucide="users" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-white"><?= $total_drivers ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono" data-en="Registered carrier personnel" data-ar="سائقون معتمدون بالأسطول">
          Registered carrier personnel
        </div>
      </div>

      <!-- On Duty -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-emerald-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="On Duty / In Transit" data-ar="على رأس العمل / في المسار">On Duty / In Transit</span>
          <i data-lucide="user-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400"><?= $active_count ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
          <span data-en="Available for dispatch" data-ar="جاهزون للتشغيل والرحلات">Available for dispatch</span>
        </div>
      </div>

      <!-- On Leave -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-cyan-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="On Leave / Rest Cycle" data-ar="في إجازة / راحة دورية">On Leave / Rest Cycle</span>
          <i data-lucide="calendar" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-cyan-600 dark:text-cyan-400"><?= $on_leave_count ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono" data-en="Scheduled mandatory rest" data-ar="فترات الراحة الإلزامية">
          Scheduled mandatory rest
        </div>
      </div>

      <!-- Licenses Expiring Soon -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-amber-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="License Renewal Due" data-ar="تراخيص تستحق التجديد">License Renewal Due</span>
          <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-amber-600 dark:text-amber-400"><?= $expiring_soon ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono" data-en="Expiring within 60 days" data-ar="تنتهي خلال 60 يوماً">
          Expiring within 60 days
        </div>
      </div>
    </div>

    <!-- Filter & Search Toolbar + Data Table Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
      
      <!-- Toolbar -->
      <div class="p-4 border-b border-[#1a3346] flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Status Tabs -->
        <div class="flex items-center gap-6 overflow-x-auto pb-1 md:pb-0" id="filterTabs">
          <button class="tab-btn active cursor-pointer" data-tab="all">
            <span data-en="All Drivers" data-ar="كامل السائقين">All Drivers</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-[#09151f] text-slate-400"><?= $total_drivers ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="active">
            <span data-en="On Duty" data-ar="في العمل">On Duty</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"><?= $active_count ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="on_leave">
            <span data-en="On Leave" data-ar="في إجازة">On Leave</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-amber-500/10 text-amber-400 dark:text-amber-400"><?= $on_leave_count ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="suspended">
            <span data-en="Suspended" data-ar="موقوف">Suspended</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-red-500/10 text-red-700 dark:text-red-400"><?= $suspended_count ?></span>
          </button>
        </div>

        <!-- Search and License Filter -->
        <div class="flex items-center gap-3">
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search name or ID..." data-en-placeholder="Search name or ID..." data-ar-placeholder="بحث بالاسم أو المعرف..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
          <select id="typeFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
            <option value="all" data-en="All Licenses" data-ar="جميع الرخص">All Licenses</option>
            <option value="heavy_hazmat" data-en="Heavy / Hazmat" data-ar="نقل ثقيل / مواد خطرة">Heavy / Hazmat</option>
            <option value="heavy" data-en="Heavy Transport" data-ar="نقل ثقيل">Heavy Transport</option>
            <option value="light" data-en="Light Commercial" data-ar="نقل خفيف">Light Commercial</option>
            <option value="private" data-en="Private" data-ar="خاص">Private</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-left rtl:text-right border-collapse" id="driversTable">
          <thead>
            <tr class="bg-[#09151f] border-b border-[#1a3346] text-[11px] font-mono text-slate-400 uppercase tracking-wider">
              <th class="py-3 px-4" data-en="Driver Identity" data-ar="هوية السائق">Driver Identity</th>
              <th class="py-3 px-4" data-en="Mobile Contact" data-ar="رقم التواصل">Mobile Contact</th>
              <th class="py-3 px-4" data-en="License Category" data-ar="فئة الرخصة">License Category</th>
              <th class="py-3 px-4" data-en="Permit Expiry" data-ar="تاريخ انتهاء الرخصة">Permit Expiry</th>
              <th class="py-3 px-4" data-en="Assigned Carrier" data-ar="المركبة المسندة">Assigned Carrier</th>
              <th class="py-3 px-4" data-en="Duty Status" data-ar="الحالة التشغيلية">Duty Status</th>
              <th class="py-3 px-4 text-right rtl:text-left no-export" data-en="Actions" data-ar="الإجراءات">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] text-xs font-mono">
            <?php if (empty($drivers_data)): ?>
            <tr class="empty-state">
              <td colspan="7" class="py-12 text-center text-slate-400 font-sans" data-en="No driver personnel found in directory." data-ar="لم يتم العثور على سائقين في سجل الأسطول.">
                No driver personnel found in directory.
              </td>
            </tr>
            <?php else: ?>
              <?php foreach ($drivers_data as $d): ?>
              <?php
                $d_status = strtolower($d['status'] ?? '');
                $s = $statusStyles[$d_status] ?? $statusStyles['active'];
                
                $expiryDate = !empty($d['license_expiry']) ? strtotime($d['license_expiry']) : 0;
                $now = time();
                $daysToExpiry = $expiryDate > 0 ? ($expiryDate - $now) / 86400 : -1;
                $expiryClass = ($daysToExpiry <= 60 && $daysToExpiry > 0) ? 'text-amber-600 dark:text-amber-400 font-bold' : ($daysToExpiry <= 0 ? 'text-red-600 dark:text-red-400 font-bold' : 'text-slate-300');

                $raw_license = strtolower($d['license_class'] ?? '');
                $display_license_en = $license_map[$raw_license]['en'] ?? ucfirst($raw_license);
                $display_license_ar = $license_map[$raw_license]['ar'] ?? ucfirst($raw_license);
                $license_badge_cls = $license_map[$raw_license]['badge'] ?? 'border-neutral-500/30 text-slate-400 bg-neutral-500/10';

                $initials = getInitials($d['full_name'] ?? '');
              ?>
              <tr class="hover:bg-[#102332] transition-colors filterable-row" data-tab="<?= $s['tab'] ?>" data-type="<?= $raw_license ?>" data-search="<?= strtolower(htmlspecialchars($d['full_name'] . ' ' . $d['driver_id'] . ' ' . $d['mobile_number'] . ' ' . $d['assigned_vehicle'])) ?>">
                <td class="py-3 px-4 font-sans">
                  <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xs border border-[#1e3a50] bg-[#09151f] flex items-center justify-center font-mono text-xs font-bold text-slate-300 shrink-0">
                      <?= htmlspecialchars($initials) ?>
                    </div>
                    <div class="flex flex-col">
                      <a href="driver_profile.php?id=<?= $d['id'] ?>" class="font-medium text-white hover:text-teal-600 hover:underline">
                        <?= htmlspecialchars($d['full_name']) ?>
                      </a>
                      <span class="text-[11px] font-mono text-slate-400 mt-0.5">
                        <?= htmlspecialchars($d['driver_id']) ?>
                      </span>
                    </div>
                  </div>
                </td>

                <td class="py-3 px-4 text-slate-400 font-mono">
                  <?= htmlspecialchars($d['mobile_number'] ?? 'N/A') ?>
                </td>

                <td class="py-3 px-4">
                  <span class="inline-block px-2 py-0.5 rounded-xs text-[10px] font-mono border font-medium <?= $license_badge_cls ?>" data-en="<?= htmlspecialchars($display_license_en) ?>" data-ar="<?= htmlspecialchars($display_license_ar) ?>">
                    <?= htmlspecialchars($display_license_en) ?>
                  </span>
                </td>

                <td class="py-3 px-4 <?= $expiryClass ?>">
                  <?= $expiryDate > 0 ? htmlspecialchars(date('d M Y', $expiryDate)) : 'N/A' ?>
                  <?php if ($daysToExpiry <= 60 && $daysToExpiry > 0): ?>
                    <span class="text-[10px] block font-sans" data-en="Expiring soon" data-ar="قريب الانتهاء">Expiring soon</span>
                  <?php elseif ($daysToExpiry <= 0 && $expiryDate > 0): ?>
                    <span class="text-[10px] block font-sans text-red-500" data-en="Expired" data-ar="منتهي">Expired</span>
                  <?php endif; ?>
                </td>

                <td class="py-3 px-4">
                  <?php if (empty($d['assigned_vehicle']) || $d['assigned_vehicle'] === 'unassigned'): ?>
                    <span class="text-neutral-400 italic font-sans" data-en="Unassigned" data-ar="غير مخصص">Unassigned</span>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs bg-[#09151f] border border-[#1e3a50] text-teal-400 dark:text-teal-400 font-bold">
                      <i data-lucide="truck" class="w-3 h-3 text-neutral-400"></i>
                      <?= htmlspecialchars($d['assigned_vehicle']) ?>
                    </span>
                  <?php endif; ?>
                </td>

                <td class="py-3 px-4">
                  <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs text-[11px] font-mono font-medium <?= $s['badge'] ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $s['dot'] ?>"></span>
                    <span data-en="<?= $s['label_en'] ?>" data-ar="<?= $s['label_ar'] ?>"><?= $s['label_en'] ?></span>
                  </span>
                </td>

                <td class="py-3 px-4 text-right rtl:text-left no-export font-sans">
                  <div class="flex items-center justify-end gap-1.5">
                    <a href="driver_profile.php?id=<?= $d['id'] ?>" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 cursor-pointer transition" title="View Dossier">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </a>

                    <?php if ($is_admin): ?>
                    <form method="POST" action="" class="m-0 p-0 inline-flex" onsubmit="return confirm('Are you sure you want to remove this driver from directory? / هل أنت متأكد من حذف هذا السائق من الدليل؟');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="driver_id" value="<?= htmlspecialchars($d['id']) ?>">
                      <button type="submit" class="p-1 rounded-xs border border-red-500/30 bg-red-500/10 text-red-600 hover:bg-red-500/20 cursor-pointer transition" title="Delete Driver">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <tr id="noResultsRow" style="display: none;">
                <td colspan="7" class="py-8 text-center text-slate-400 font-sans" data-en="No drivers match your search criteria." data-ar="لا يوجد سائقين يطابقون معايير البحث.">
                  No drivers match your search criteria.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Footer Pagination / Counts -->
      <div class="p-3 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between text-xs font-mono text-slate-400">
        <div>
          <span data-en="Showing" data-ar="عرض">Showing</span> <strong id="visibleCount" class="text-white"><?= count($drivers_data) ?></strong> <span data-en="of" data-ar="من">of</span> <strong class="text-white"><?= count($drivers_data) ?></strong> <span data-en="driver records" data-ar="سائق">driver records</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
          <span data-en="All driver credentials verified" data-ar="جميع بيانات السائقين معتمدة">All driver credentials verified</span>
        </div>
      </div>

    </div>

  </div>
</main>

<script>
  lucide.createIcons();

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
    document.querySelectorAll('[data-en-placeholder]').forEach(el => {
      el.placeholder = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", function() {
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);

    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const tabButtons = document.querySelectorAll('#filterTabs .tab-btn');
    const rows = document.querySelectorAll('.filterable-row');
    const visibleCountElem = document.getElementById('visibleCount');
    const noResultsRow = document.getElementById('noResultsRow');
    let currentTab = 'all';

    function filterTable() {
      const query = (searchInput?.value || '').toLowerCase().trim();
      const filterVal = typeFilter ? typeFilter.value : 'all';
      let visibleCount = 0;

      rows.forEach(row => {
        const rowTab = row.getAttribute('data-tab');
        const rowType = row.getAttribute('data-type');
        const searchData = row.getAttribute('data-search') || '';
        
        const matchesSearch = !query || searchData.includes(query);
        const matchesDropdown = (filterVal === 'all' || rowType === filterVal);
        const matchesTab = (currentTab === 'all' || rowTab === currentTab);

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
      
      if (noResultsRow && rows.length > 0) {
        noResultsRow.style.display = (visibleCount === 0) ? '' : 'none';
      }
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (typeFilter) typeFilter.addEventListener('change', filterTable);

    tabButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        tabButtons.forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentTab = this.getAttribute('data-tab');
        filterTable();
      });
    });

    const exportBtn = document.getElementById('exportExcelBtn');
    if (exportBtn) {
      exportBtn.addEventListener('click', function() {
        let csvContent = "\uFEFF"; 
        const table = document.getElementById("driversTable");
        const trs = table.querySelectorAll("tr");

        trs.forEach(function(row) {
          if (row.style.display === 'none' && !row.classList.contains('empty-state') && row.id !== 'noResultsRow') return;
          if (row.classList.contains('empty-state') || row.id === 'noResultsRow') return;
          
          let rowData = [];
          const cols = row.querySelectorAll("th, td");
          
          cols.forEach(function(col) {
            if (col.classList.contains('no-export')) return;
            let text = col.innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/"/g, '""').trim();
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
        link.setAttribute("download", "Drivers_Directory_Export_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
    }
  });
</script>
</body>
</html>