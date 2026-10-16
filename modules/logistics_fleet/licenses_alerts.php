<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'logistics.view');

$is_admin = has_role($pdo, 'Admin') || has_role($pdo, 'Super Admin') || has_role($pdo, 'Logistics Manager') || has_permission($pdo, 'logistics.edit');

$active_page = 'drivers_directory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Licenses & Alerts'];

$renew_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew') {
  require_csrf();
  require_permission($pdo, 'logistics.edit');
  $entity_id = $_POST['entity_id'] ?? '';
  $doc_type = $_POST['doc_type'] ?? '';
  $new_date = date('Y-m-d', strtotime('+1 year'));

  if ($doc_type === 'Driver License') {
    $stmt = $pdo->prepare("UPDATE drivers SET license_expiry = ? WHERE driver_id = ?");
    $stmt->execute([$new_date, $entity_id]);
  } elseif ($doc_type === 'Medical Certificate') {
    $stmt = $pdo->prepare("UPDATE drivers SET medical_expiry = ? WHERE driver_id = ?");
    $stmt->execute([$new_date, $entity_id]);
  } elseif ($doc_type === 'Vehicle Registration') {
    $stmt = $pdo->prepare("UPDATE vehicles SET registration_expiry = ? WHERE fleet_id = ?");
    $stmt->execute([$new_date, $entity_id]);
  } elseif ($doc_type === 'Commercial Insurance') {
    $stmt = $pdo->prepare("UPDATE vehicles SET insurance_expiry = ? WHERE fleet_id = ?");
    $stmt->execute([$new_date, $entity_id]);
  }
  
  $renew_message = "Document {$doc_type} for ID {$entity_id} successfully renewed until {$new_date}.";
}

$stmt = $pdo->query("
  SELECT driver_id as id, full_name as name, 'Driver License' as type, license_expiry as expiry FROM drivers WHERE license_expiry IS NOT NULL
  UNION ALL
  SELECT driver_id, full_name, 'Medical Certificate', medical_expiry FROM drivers WHERE medical_expiry IS NOT NULL
  UNION ALL
  SELECT fleet_id, make_model, 'Vehicle Registration', registration_expiry FROM vehicles WHERE registration_expiry IS NOT NULL
  UNION ALL
  SELECT fleet_id, make_model, 'Commercial Insurance', insurance_expiry FROM vehicles WHERE insurance_expiry IS NOT NULL
");
$all_docs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$alerts = [];
$total_monitored = count($all_docs);
$critical_docs = 0;
$warning_docs = 0;
$valid_docs = 0;

$today = new DateTime();
$today->setTime(0, 0, 0);

foreach ($all_docs as $doc) {
  if (empty($doc['expiry'])) continue;
  $exp = new DateTime($doc['expiry']);
  $exp->setTime(0, 0, 0);
  $diff = $today->diff($exp);
  $days = (int)$diff->format('%R%a');

  $status = 'active';
  if ($days < 0) {
    $status = 'expired';
    $critical_docs++;
  } elseif ($days <= 7) {
    $status = 'critical';
    $critical_docs++;
  } elseif ($days <= 30) {
    $status = 'warning';
    $warning_docs++;
  } else {
    $valid_docs++;
  }

  $alerts[] = [
    'id'   => $doc['id'],
    'name'  => $doc['name'],
    'type'  => $doc['type'],
    'expiry' => $doc['expiry'],
    'days'  => $days,
    'status' => $status
  ];
}

usort($alerts, fn($a, $b) => $a['days'] <=> $b['days']);

$total_alerts = count($alerts);
$compliance = $total_monitored > 0 ? round(($valid_docs / $total_monitored) * 100, 1) : 100;

$statusStyles = [
  'active'  => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'border' => 'border-emerald-300 dark:border-emerald-800', 'dot' => 'bg-emerald-500', 'label_en' => 'Valid & Up to Date', 'label_ar' => 'ساري ومحدث'],
  'expired' => ['bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'border' => 'border-rose-800 dark:border-rose-800', 'dot' => 'bg-rose-500', 'label_en' => 'Expired', 'label_ar' => 'منتهي الصلاحية'],
  'critical' => ['bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'border' => 'border-rose-800 dark:border-rose-800', 'dot' => 'bg-rose-500', 'label_en' => 'Action Required (<=7d)', 'label_ar' => 'إجراء فوري (<=7 أيام)'],
  'warning' => ['bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'border' => 'border-amber-300 dark:border-amber-800', 'dot' => 'bg-amber-500', 'label_en' => 'Expiring Soon (<=30d)', 'label_ar' => 'ينتهي قريباً (<=30 يوم)'],
];

$iconMap = [
  'Driver License'    => 'id-card',
  'Medical Certificate' => 'activity',
  'Vehicle Registration' => 'file-text',
  'Commercial Insurance' => 'shield',
  'Hazmat Permit'    => 'flame',
];

$typeTranslationMap = [
  'Driver License'    => ['en' => 'Driver License', 'ar' => 'رخصة قيادة'],
  'Medical Certificate' => ['en' => 'Medical Certificate', 'ar' => 'شهادة فحص طبي'],
  'Vehicle Registration' => ['en' => 'Vehicle Registration', 'ar' => 'استمارة رخصة سير'],
  'Commercial Insurance' => ['en' => 'Commercial Insurance', 'ar' => 'وثيقة تأمين تجاري'],
  'Hazmat Permit'    => ['en' => 'Hazmat Permit', 'ar' => 'تصريح نقل مواد خطرة']
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Licenses, Permits & Regulatory Alerts | I-GAS Control Room" data-ar="التراخيص، التصاريح وتنبيهات الامتثال | غرفة التحكم I-GAS">Licenses, Permits & Regulatory Alerts | I-GAS Control Room</title>
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
      <span class="inline-flex items-center gap-1.5 text-slate-400 whitespace-nowrap">
        <span class="w-2 h-2 rounded-full bg-teal-500"></span>
        <span data-en="REGULATORY COMPLIANCE & TANK TEST PERMITS" data-ar="الامتثال التنظيمي واختبارات صهاريج الغاز">REGULATORY COMPLIANCE & TANK TEST PERMITS</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="MONITORED:" data-ar="المستندات المراقبة:">MONITORED:</span> <strong class="text-white font-mono"><?= $total_monitored ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="COMPLIANCE INDEX:" data-ar="مؤشر الامتثال:">COMPLIANCE INDEX:</span> <strong class="font-mono text-emerald-600 dark:text-emerald-400"><?= $compliance ?>%</strong>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: AUDIT-COMPLIANCE</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">

    <!-- Flash Renew Message -->
    <?php if (!empty($renew_message)): ?>
      <div class="p-4 border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300 rounded-xs flex items-center gap-3 text-sm">
        <i data-lucide="check-circle" class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
        <div class="font-mono text-xs">
          <?= htmlspecialchars($renew_message) ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Header Row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
          <span data-en="Logistics & Fleet" data-ar="اللوجستيات والأسطول">Logistics & Fleet</span>
          <span>/</span>
          <span class="text-white font-semibold" data-en="Regulatory Audits" data-ar="التدقيق التنظيمي">Regulatory Audits</span>
        </div>
        <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white flex items-center gap-2">
          <i data-lucide="shield-alert" class="w-6 h-6 text-teal-600 dark:text-teal-400"></i>
          <span data-en="Licenses, Permits & Regulatory Alerts" data-ar="تراخيص السائقين وتصاريح المركبات">Licenses, Permits & Regulatory Alerts</span>
        </h1>
        <p class="text-sm text-slate-400 mt-1" data-en="Monitor regulatory compliance, track expiring driver licenses, and manage vehicle documentation renewals." data-ar="مراقبة الامتثال التنظيمي للسلامة، وتتبع رخص القيادة وشهادات الفحص الدوري وتأمين الشاحنات.">
          Monitor regulatory compliance, track expiring driver licenses, and manage vehicle documentation renewals.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <button type="button" id="exportAlertsBtn" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 transition-colors cursor-pointer">
          <i data-lucide="download" class="w-4 h-4"></i>
          <span data-en="Export Alert CSV" data-ar="تصدير كملف CSV">Export Alert CSV</span>
        </button>
        <a href="drivers_directory.php" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
          <i data-lucide="users" class="w-4 h-4"></i>
          <span data-en="Manage Drivers Directory" data-ar="إدارة سجل السائقين">Manage Drivers Directory</span>
        </a>
      </div>
    </div>

    <!-- 4 KPI Telemetry Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- KPI 1: Total Monitored Docs -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Total Monitored Docs" data-ar="إجمالي المستندات المراقبة">
          Total Monitored Docs
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= $total_monitored ?>
          </span>
          <span class="text-[10px] font-mono text-neutral-500">FLEET & CREW</span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Active licenses, certificates & registrations" data-ar="رخص القيادة، الفحوصات الطبية، الاستمارات والتأمين">
          Active licenses, certificates & registrations
        </div>
      </div>

      <!-- KPI 2: Critical / Expired -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Critical / Expired" data-ar="منتهي أو حرج (<= 7 أيام)">
          Critical / Expired
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono <?= $critical_docs > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-white' ?>">
            <?= $critical_docs ?>
          </span>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-mono uppercase rounded-xs <?= $critical_docs > 0 ? 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border border-rose-800 dark:border-rose-800' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800' ?>">
            <span class="w-1 h-1 rounded-full <?= $critical_docs > 0 ? 'bg-rose-500' : 'bg-emerald-500' ?>"></span>
            <span data-en="<?= $critical_docs > 0 ? 'Action Req' : 'Zero Critical' ?>" data-ar="<?= $critical_docs > 0 ? 'مطلوب إجراء' : 'لا يوجد حرج' ?>"><?= $critical_docs > 0 ? 'Action Req' : 'Zero Critical' ?></span>
          </span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Requires immediate renewal action" data-ar="يتطلب اتخاذ إجراء تجديد فوري">
          Requires immediate renewal action
        </div>
      </div>

      <!-- KPI 3: Expiring Soon (30 Days) -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Expiring Soon (30 Days)" data-ar="ينتهي قريباً (30 يوم)">
          Expiring Soon (30 Days)
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= $warning_docs ?>
          </span>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-mono uppercase rounded-xs bg-amber-500/10 text-amber-400 dark:text-amber-400 border border-amber-300 dark:border-amber-800">
            <span class="w-1 h-1 rounded-full bg-amber-500"></span>
            <span data-en="Warning" data-ar="تنبيه مسبق">Warning</span>
          </span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Upcoming renewal deadline window" data-ar="تقع ضمن نافذة الاستحقاق القريبة">
          Upcoming renewal deadline window
        </div>
      </div>

      <!-- KPI 4: Compliance Rate -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Fleet Compliance Rate" data-ar="معدل الامتثال العام">
          Fleet Compliance Rate
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= $compliance ?>%
          </span>
          <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400">AUDIT OK</span>
        </div>
        <div class="mt-2 w-full bg-[#132636] bg-[#112433] h-1.5 rounded-full overflow-hidden">
          <div class="bg-emerald-500 h-full rounded-full" style="width: <?= $compliance ?>%;"></div>
        </div>
      </div>

    </div>

    <!-- Registry & Data Table Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
      
      <!-- Filter Toolbar Header -->
      <div class="p-4 border-b border-[#1a3346] space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="Compliance Alert Registry" data-ar="سجل تنبيهات ومطابقة الوثائق">
              Compliance Alert Registry
            </h2>
          </div>

          <!-- Search + Type Filter -->
          <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search entity, name, ID..." data-en-placeholder="Search entity, name, ID..." data-ar-placeholder="ابحث عن الكيان، الاسم، المعرف..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>

            <select id="docTypeFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <option value="all" data-en="All Document Types" data-ar="جميع أنواع الوثائق">All Document Types</option>
              <option value="Driver License" data-en="Driver Licenses" data-ar="رخص القيادة">Driver Licenses</option>
              <option value="Medical Certificate" data-en="Medical Certificates" data-ar="شهادات الفحص الطبي">Medical Certificates</option>
              <option value="Vehicle Registration" data-en="Vehicle Registrations" data-ar="استمارات سير المركبات">Vehicle Registrations</option>
              <option value="Commercial Insurance" data-en="Commercial Insurances" data-ar="وثائق التأمين">Commercial Insurances</option>
            </select>
          </div>
        </div>

        <!-- Underline Tabs -->
        <div class="flex items-center gap-6 border-t border-[#1a3346] pt-3 text-xs font-mono">
          <button type="button" class="tab-btn active pb-2 -mb-3 border-b-2 border-orange-500 font-semibold text-white flex items-center gap-1.5 cursor-pointer" data-tab="all">
            <span data-en="All Records" data-ar="جميع السجلات">All Records</span>
            <span class="px-1.5 py-0.2 rounded-xs bg-[#112433] text-[10px] text-cyan-400"><?= $total_alerts ?></span>
          </button>
          <button type="button" class="tab-btn pb-2 -mb-3 border-b-2 border-transparent text-slate-400 hover:text-white flex items-center gap-1.5 cursor-pointer transition-colors" data-tab="critical">
            <span data-en="Critical / Expired" data-ar="منتهي / حرج">Critical / Expired</span>
            <span class="px-1.5 py-0.2 rounded-xs bg-rose-950/50 text-rose-400 border border-rose-800/50 text-[10px]"><?= $critical_docs ?></span>
          </button>
          <button type="button" class="tab-btn pb-2 -mb-3 border-b-2 border-transparent text-slate-400 hover:text-white flex items-center gap-1.5 cursor-pointer transition-colors" data-tab="warning">
            <span data-en="Expiring Soon (30d)" data-ar="ينتهي قريباً (30 يوم)">Expiring Soon (30d)</span>
            <span class="px-1.5 py-0.2 rounded-xs bg-amber-950/50 text-amber-400 border border-amber-800/50 text-[10px]"><?= $warning_docs ?></span>
          </button>
        </div>
      </div>

      <!-- Table Container -->
      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-start text-xs border-collapse" id="alertsTable">
          <thead>
            <tr class="border-b border-[#1a3346] bg-[#09151f] text-slate-400 font-mono uppercase text-[10px] tracking-wider">
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Target Entity / Name" data-ar="الكيان / الاسم">Target Entity / Name</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Document Type" data-ar="نوع المستند">Document Type</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Expiry Date" data-ar="تاريخ الانتهاء">Expiry Date</th>
              <th class="py-2.5 px-3 text-end font-semibold" data-en="Remaining Days" data-ar="الأيام المتبقية">Remaining Days</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Audit Status" data-ar="حالة التدقيق">Audit Status</th>
              <th class="py-2.5 px-3 text-end font-semibold no-export" data-en="Actions" data-ar="الإجراءات">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a]">
            <?php if (empty($alerts)): ?>
              <tr class="empty-state">
                <td colspan="6" class="py-8 text-center text-neutral-500 font-mono text-xs" data-en="No compliance documents or alerts recorded." data-ar="لم يتم تسجيل مستندات أو تنبيهات امتثال.">
                  No compliance documents or alerts recorded.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($alerts as $a): ?>
                <?php
                  $statusObj = $statusStyles[$a['status']] ?? $statusStyles['active'];
                  $icon = $iconMap[$a['type']] ?? 'file-text';
                  $isExpired = $a['status'] === 'expired';
                  $isActive = $a['status'] === 'active';
                  $tabCategory = ($a['status'] === 'expired' || $a['status'] === 'critical') ? 'critical' : ($a['status'] === 'warning' ? 'warning' : 'active');
                  $docNameEn = $typeTranslationMap[$a['type']]['en'] ?? $a['type'];
                  $docNameAr = $typeTranslationMap[$a['type']]['ar'] ?? $a['type'];
                ?>
                <tr class="filterable-row hover:bg-[#102332] transition-colors" data-tab="<?= $tabCategory ?>" data-type="<?= htmlspecialchars($a['type']) ?>" data-search="<?= strtolower(htmlspecialchars($a['name'] . ' ' . $a['id'] . ' ' . $a['type'] . ' ' . $docNameAr)) ?>">
                  <td class="py-3 px-4">
                    <div class="font-medium text-white">
                      <?= htmlspecialchars($a['name']) ?>
                    </div>
                    <div class="text-[11px] font-mono text-neutral-500">
                      <?= htmlspecialchars($a['id']) ?>
                    </div>
                  </td>
                  <td class="py-2.5 px-3">
                    <div class="inline-flex items-center gap-2 text-slate-200">
                      <i data-lucide="<?= $icon ?>" class="w-4 h-4 text-slate-400"></i>
                      <span data-en="<?= htmlspecialchars($docNameEn) ?>" data-ar="<?= htmlspecialchars($docNameAr) ?>"><?= htmlspecialchars($docNameEn) ?></span>
                    </div>
                  </td>
                  <td class="py-2.5 px-3 font-mono text-slate-300">
                    <?= htmlspecialchars(date('Y-m-d', strtotime($a['expiry']))) ?>
                  </td>
                  <td class="py-2.5 px-3 text-end font-mono font-semibold <?= $isExpired || $a['status'] === 'critical' ? 'text-rose-400' : 'text-white' ?>">
                    <?php if ($isExpired): ?>
                      <span data-en="Expired" data-ar="منتهي">Expired (<?= abs($a['days']) ?>d ago)</span>
                    <?php else: ?>
                      <?= $a['days'] ?> <span class="text-[10px] text-slate-400 font-normal">days</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-3">
                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 text-xs font-mono uppercase tracking-wider rounded-xs border <?= $statusObj['bg'] ?> <?= $statusObj['text'] ?> <?= $statusObj['border'] ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
                      <span data-en="<?= $statusObj['label_en'] ?>" data-ar="<?= $statusObj['label_ar'] ?>"><?= $statusObj['label_en'] ?></span>
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-end no-export">
                    <?php if (!$isActive && $is_admin): ?>
                    <form method="POST" action="" class="m-0 inline-block" onsubmit="return confirm('Renew this document for one calendar year? / تجديد هذه الوثيقة لمدة عام ميلادي؟');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="renew">
                      <input type="hidden" name="entity_id" value="<?= htmlspecialchars($a['id']) ?>">
                      <input type="hidden" name="doc_type" value="<?= htmlspecialchars($a['type']) ?>">
                      <button type="submit" class="px-2.5 py-1 text-xs font-mono uppercase tracking-wider bg-[#112433] hover:bg-[#173042] text-slate-200 border border-[#234257] hover:border-orange-500/50 rounded-xs transition-colors cursor-pointer" data-en="Renew (+1Y)" data-ar="تجديد (+سنة)">
                        Renew (+1Y)
                      </button>
                    </form>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 text-xs font-mono text-emerald-400">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        <span data-en="Compliant" data-ar="ممتثل">Compliant</span>
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            <tr id="noResultsRow" style="display: none;">
              <td colspan="6" class="py-8 text-center text-neutral-500 font-mono text-xs" data-en="No compliance records matched your filter criteria." data-ar="لا توجد سجلات مطابقة للفلتر المحدد.">
                No compliance records matched your filter criteria.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Footer -->
      <div class="p-4 border-t border-[#1a3346] bg-[#071018] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs font-mono text-neutral-500">
        <div>
          <span data-en="Showing" data-ar="عرض">Showing</span> <span id="visibleCount" class="font-semibold text-white"><?= count($alerts) ?></span> <span data-en="regulatory compliance records" data-ar="سجلات امتثال وتراخيص">regulatory compliance records</span>
        </div>
        <div class="flex items-center gap-1">
          <button type="button" class="px-2 py-1 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] transition-colors">
            <i data-lucide="chevron-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          </button>
          <span class="px-2 py-1 bg-[#112433] text-slate-200 border-[#234257] text-white text-white rounded-xs font-mono">1</span>
          <button type="button" class="px-2 py-1 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] transition-colors">
            <i data-lucide="chevron-right" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          </button>
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
    const docTypeFilter = document.getElementById('docTypeFilter');
    const tabBtns = document.querySelectorAll('.tab-btn');
    const rows = document.querySelectorAll('.filterable-row');
    const visibleCountElem = document.getElementById('visibleCount');
    const noResultsRow = document.getElementById('noResultsRow');
    let currentTab = 'all';

    function filterTable() {
      const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
      const typeFilter = docTypeFilter ? docTypeFilter.value : 'all';
      let visibleCount = 0;

      rows.forEach(row => {
        const rowType = row.getAttribute('data-type');
        const rowTab = row.getAttribute('data-tab');
        const searchData = row.getAttribute('data-search') || '';
        
        const matchesSearch = !query || searchData.includes(query);
        const matchesDropdown = (typeFilter === 'all' || rowType === typeFilter);
        const matchesTab = (currentTab === 'all' || rowTab === currentTab);

        if (matchesSearch && matchesDropdown && matchesTab) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      if (visibleCountElem) visibleCountElem.innerText = visibleCount;
      if (noResultsRow) {
        noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
      }
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (docTypeFilter) docTypeFilter.addEventListener('change', filterTable);

    tabBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        tabBtns.forEach(b => {
          b.classList.remove('active', 'border-[#234257]', 'dark:border-neutral-100', 'text-white', 'text-white', 'font-semibold');
          b.classList.add('border-transparent', 'text-neutral-500');
        });
        this.classList.add('active', 'border-[#234257]', 'dark:border-neutral-100', 'text-white', 'text-white', 'font-semibold');
        this.classList.remove('border-transparent', 'text-neutral-500');
        currentTab = this.getAttribute('data-tab');
        filterTable();
      });
    });

    // Export to CSV
    const exportBtn = document.getElementById('exportAlertsBtn');
    if (exportBtn) {
      exportBtn.addEventListener('click', function() {
        let csvContent = "\uFEFF"; 
        const table = document.getElementById("alertsTable");
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
        link.setAttribute("download", "IGAS_Compliance_Alerts_" + (new Date().toISOString().slice(0, 10)) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
    }

    // Initial filter
    filterTable();
  });
</script>
</body>
</html>