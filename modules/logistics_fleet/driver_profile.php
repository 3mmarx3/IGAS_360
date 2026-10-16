<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_permission($pdo, 'logistics.view');

$is_admin = has_role($pdo, 'Admin') || has_role($pdo, 'Super Admin') || has_role($pdo, 'Logistics Manager');
$is_driver = has_role($pdo, 'Driver');

$active_page = 'drivers_directory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Drivers Directory', 'Driver Profile'];

$driver_id_param = $_GET['id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trip_action'])) {
  require_csrf();
  $manifest_id_action = $_POST['manifest_id'] ?? null;
  $trip_action = $_POST['trip_action'];

  if ($manifest_id_action !== null && in_array($trip_action, ['accept', 'reject'])) {
    $new_status = $trip_action === 'accept' ? 'in_transit' : 'cancelled';
    $stmt_update = $pdo->prepare("UPDATE dispatches SET status = ? WHERE manifest_id = ?");
    $stmt_update->execute([$new_status, $manifest_id_action]);
  }

  redirect(app_url('modules/logistics_fleet/driver_profile.php') . "?id=" . urlencode($driver_id_param));
}

$stmt = $pdo->prepare("SELECT d.*, v.make_model as vehicle_name FROM drivers d LEFT JOIN vehicles v ON d.assigned_vehicle = v.fleet_id WHERE d.driver_id = ? OR d.id = ? LIMIT 1");
$stmt->execute([$driver_id_param, $driver_id_param]);
$db_driver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$db_driver) {
  $db_driver = $pdo->query("SELECT d.*, v.make_model as vehicle_name FROM drivers d LEFT JOIN vehicles v ON d.assigned_vehicle = v.fleet_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if (!$db_driver) {
  $db_driver = [
    'id' => 1,
    'driver_id' => 'DRV-001',
    'full_name' => 'Demo Carrier Driver',
    'license_class' => 'heavy_hazmat',
    'license_number' => 'DL-992019',
    'license_expiry' => date('Y-m-d', strtotime('+1 year')),
    'blood_type' => 'O+',
    'mobile_number' => '+966 50 000 0000',
    'emergency_contact' => '+966 50 111 2222',
    'status' => 'active',
    'rating' => 5.0,
    'on_time_rate' => 100,
    'created_at' => date('Y-m-d H:i:s'),
    'assigned_vehicle' => 'FLT-001',
    'vehicle_name' => 'Mercedes Actros 3340'
  ];
}

$internal_id = $db_driver['id'];
$driver_code = $db_driver['driver_id'];

$stmt_trips_count = $pdo->prepare("SELECT COUNT(*) FROM dispatches WHERE driver_id = ?");
$stmt_trips_count->execute([$driver_code]);
$total_trips = (int)$stmt_trips_count->fetchColumn();

$stmt_trips = $pdo->prepare("
  SELECT 
    ds.manifest_id as id,
    ds.order_ref,
    ds.destination,
    ds.dispatch_date as date,
    ds.eta_time,
    ds.status,
    ds.distance,
    ds.instructions,
    ds.vehicle_id,
    ds.created_at,
    v.make_model as trip_vehicle_name,
    v.vehicle_type as trip_vehicle_type
  FROM dispatches ds
  LEFT JOIN vehicles v ON ds.vehicle_id = v.fleet_id
  WHERE ds.driver_id = ? 
  ORDER BY ds.dispatch_date DESC 
  LIMIT 6
");
$stmt_trips->execute([$driver_code]);
$trips = $stmt_trips->fetchAll(PDO::FETCH_ASSOC);

$license_map = [
  'heavy_hazmat' => ['en' => 'Heavy / Hazmat', 'ar' => 'نقل ثقيل / مواد خطرة'],
  'heavy'    => ['en' => 'Heavy Transport', 'ar' => 'نقل ثقيل'],
  'light'    => ['en' => 'Light Commercial', 'ar' => 'نقل خفيف'],
  'private'   => ['en' => 'Private', 'ar' => 'خاص']
];

$raw_lic = strtolower($db_driver['license_class'] ?? '');
$display_license_en = $license_map[$raw_lic]['en'] ?? ucfirst($raw_lic);
$display_license_ar = $license_map[$raw_lic]['ar'] ?? ucfirst($raw_lic);

$words = explode(' ', trim($db_driver['full_name']));
$initials = '';
foreach ($words as $w) {
  if (!empty($w)) {
    $initials .= strtoupper($w[0]);
  }
  if (strlen($initials) >= 2) break;
}
if (empty($initials)) {
  $initials = 'DR';
}

$driver = [
  'internal_id' => $db_driver['id'],
  'id' => $db_driver['driver_id'],
  'name' => $db_driver['full_name'],
  'initials' => $initials,
  'license_en' => $display_license_en,
  'license_ar' => $display_license_ar,
  'license_no' => $db_driver['license_number'],
  'expiry' => $db_driver['license_expiry'],
  'blood_type' => $db_driver['blood_type'] ?? 'O+',
  'phone' => $db_driver['mobile_number'],
  'emergency_contact' => $db_driver['emergency_contact'] ?? 'Not Specified',
  'status' => strtolower($db_driver['status']),
  'rating' => $db_driver['rating'] ?? 5.0,
  'total_trips' => $total_trips,
  'on_time_rate' => $db_driver['on_time_rate'] ?? 100,
  'joined' => date('Y-m-d', strtotime($db_driver['created_at'])),
  'vehicle_id' => ($db_driver['assigned_vehicle'] !== 'unassigned' && !empty($db_driver['assigned_vehicle'])) ? $db_driver['assigned_vehicle'] : 'Unassigned',
  'vehicle_name' => $db_driver['vehicle_name'] ?? 'No Vehicle Assigned'
];

$statusStyles = [
  'active'  => ['badge' => 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'On Duty', 'label_ar' => 'في العمل'],
  'on_leave' => ['badge' => 'border border-amber-500/30 bg-amber-500/10 text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'On Leave', 'label_ar' => 'في إجازة'],
  'suspended' => ['badge' => 'border border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400', 'dot' => 'bg-red-500', 'label_en' => 'Suspended', 'label_ar' => 'موقوف'],
];

$tripStyles = [
  'delivered' => ['badge' => 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', 'label_en' => 'Completed', 'label_ar' => 'مكتمل'],
  'completed' => ['badge' => 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', 'label_en' => 'Completed', 'label_ar' => 'مكتمل'],
  'dispatched' => ['badge' => 'border border-amber-500/30 bg-amber-500/10 text-amber-400 dark:text-amber-400', 'label_en' => 'Dispatched', 'label_ar' => 'تم التوجيه'],
  'in_transit' => ['badge' => 'border border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-400', 'label_en' => 'In Transit', 'label_ar' => 'في الطريق'],
  'cancelled' => ['badge' => 'border border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400', 'label_en' => 'Cancelled', 'label_ar' => 'ملغي'],
];

$ds = $statusStyles[$driver['status']] ?? $statusStyles['active'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Operator Dossier: <?= htmlspecialchars($driver['name']) ?> | I-GAS Control Room" data-ar="ملف السائق: <?= htmlspecialchars($driver['name']) ?> | غرفة التحكم I-GAS">Operator Dossier: <?= htmlspecialchars($driver['name']) ?> | I-GAS Control Room</title>
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

  <!-- Control Room Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-400">
        <span class="w-2 h-2 rounded-full bg-teal-500"></span>
        <span data-en="HAZMAT OPERATOR DOSSIER" data-ar="ملف سائق نقل المواد الخطرة">HAZMAT OPERATOR DOSSIER</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="CODE:" data-ar="الرمز:">CODE:</span> <strong class="text-teal-400 dark:text-teal-400"><?= htmlspecialchars($driver['id']) ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="STATUS:" data-ar="الحالة:">STATUS:</span> <strong class="text-white"><?= htmlspecialchars($ds['label_en']) ?></strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="shield-check" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
      <span data-en="ADR HAZMAT VALIDATED" data-ar="معتمد لنقل الغازات الصناعية">ADR HAZMAT VALIDATED</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="drivers_directory.php" class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-[#1e3a50] text-slate-400 hover:border-teal-500 hover:text-teal-600 rounded-xs transition-colors">
            ← DRIVERS ROSTER
          </a>
          <span class="text-xs text-slate-400 font-mono">SYS-DRV-02</span>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xs border border-[#1e3a50] bg-[#09151f] flex items-center justify-center font-mono text-sm font-bold text-slate-200 shrink-0">
            <?= htmlspecialchars($driver['initials']) ?>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white">
                <?= htmlspecialchars($driver['name']) ?>
              </h1>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-xs text-[11px] font-mono font-medium <?= $ds['badge'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $ds['dot'] ?>"></span>
                <span data-en="<?= $ds['label_en'] ?>" data-ar="<?= $ds['label_ar'] ?>"><?= $ds['label_en'] ?></span>
              </span>
            </div>
            <p class="text-xs font-mono text-slate-400 mt-0.5">
              <?= htmlspecialchars($driver['id']) ?> · <span data-en="<?= htmlspecialchars($driver['license_en']) ?>" data-ar="<?= htmlspecialchars($driver['license_ar']) ?>"><?= htmlspecialchars($driver['license_en']) ?></span>
            </p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2.5">
        <button onclick="window.print()" class="inline-flex items-center gap-2 px-3 py-2 text-xs font-medium font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] dark:hover:bg-neutral-700 cursor-pointer transition-colors">
          <i data-lucide="printer" class="w-4 h-4 text-neutral-500"></i>
          <span data-en="Print Dossier" data-ar="طباعة الملف">Print Dossier</span>
        </button>
        <?php if ($is_admin): ?>
        <a href="edit_driver.php?id=<?= htmlspecialchars($driver['internal_id']) ?>" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs transition-colors">
          <i data-lucide="pencil" class="w-4 h-4"></i>
          <span data-en="Edit Dossier" data-ar="تعديل البيانات">Edit Dossier</span>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Metric KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Safety & Rating -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="Safety & Rating" data-ar="السلامة والتقييم">Safety & Rating</span>
          <i data-lucide="star" class="w-4 h-4 text-amber-500 fill-amber-500"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-white"><?= htmlspecialchars($driver['rating']) ?> <span class="text-xs text-neutral-400 font-normal">/ 5.0</span></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono" data-en="Zero hazmat safety incidents" data-ar="سجل خالٍ من حوادث المواد الخطرة">
          Zero hazmat safety incidents
        </div>
      </div>

      <!-- Total Lifetime Trips -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-cyan-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="Completed Dispatches" data-ar="الرحلات المكتملة">Completed Dispatches</span>
          <i data-lucide="navigation" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-cyan-600 dark:text-cyan-400"><?= number_format($driver['total_trips']) ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono" data-en="Carrier manifests executed" data-ar="إرساليات نقل منفذة بنجاح">
          Carrier manifests executed
        </div>
      </div>

      <!-- On-Time Delivery Rate -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-emerald-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="On-Time Delivery" data-ar="نسبة الالتزام بالمواعيد">On-Time Delivery</span>
          <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-white"><?= htmlspecialchars($driver['on_time_rate']) ?>%</div>
        <div class="mt-2 w-full bg-[#09151f] h-1.5 rounded-full overflow-hidden">
          <div class="bg-emerald-500 h-full rounded-full" style="width: <?= htmlspecialchars($driver['on_time_rate']) ?>%"></div>
        </div>
      </div>

      <!-- License Expiry -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-amber-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="Permit Validity" data-ar="صلاحية رخصة القيادة">Permit Validity</span>
          <i data-lucide="shield" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
        </div>
        <div class="text-lg font-bold font-mono text-white"><?= htmlspecialchars(date('d M Y', strtotime($driver['expiry']))) ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono">
          <?php if (strtotime($driver['expiry']) > time()): ?>
            <span class="text-emerald-600 dark:text-emerald-400 font-bold" data-en="Active & Valid" data-ar="سارية ومعتمدة">Active & Valid</span>
          <?php else: ?>
            <span class="text-red-600 dark:text-red-400 font-bold" data-en="Expired - Immediate Renewal" data-ar="منتهية - يلزم التجديد الفوري">Expired - Immediate Renewal</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 2-Column Content Layout -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      
      <!-- Left: Recent Trips / Manifests (2 cols) -->
      <div class="xl:col-span-2 bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
        <div class="p-4 border-b border-[#1a3346] flex justify-between items-center bg-[#09151f]">
          <div class="flex items-center gap-2">
            <i data-lucide="route" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="Recent Delivery Manifests & Trips" data-ar="سجل رحلات وإرساليات التسليم الأخيرة">
              Recent Delivery Manifests & Trips
            </h3>
          </div>
          <span class="text-xs font-mono text-slate-400">
            <?= count($trips) ?> <span data-en="recent logs" data-ar="سجل">recent logs</span>
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="bg-[#09151f] border-b border-[#1a3346] text-[11px] font-mono text-slate-400 uppercase tracking-wider">
                <th class="py-3 px-4" data-en="Order Ref" data-ar="رقم الطلب">Order Ref</th>
                <th class="py-3 px-4" data-en="Destination" data-ar="الوجهة">Destination</th>
                <th class="py-3 px-4" data-en="Assigned Unit" data-ar="المركبة">Assigned Unit</th>
                <th class="py-3 px-4" data-en="Schedule Date" data-ar="الموعد">Schedule Date</th>
                <th class="py-3 px-4" data-en="Trip Status" data-ar="الحالة">Trip Status</th>
                <th class="py-3 px-4 text-right rtl:text-left no-export" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a] text-xs font-mono">
              <?php if (empty($trips)): ?>
              <tr>
                <td colspan="6" class="py-12 text-center text-slate-400 font-sans" data-en="No delivery trips assigned to this operator yet." data-ar="لا توجد رحلات تسليم مسندة لهذا السائق حتى الآن.">
                  No delivery trips assigned to this operator yet.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($trips as $t): ?>
                <?php
                  $t_status = strtolower($t['status'] ?? '');
                  $ts = $tripStyles[$t_status] ?? $tripStyles['dispatched'];
                  $formatted_date = !empty($t['date']) ? date('d M Y', strtotime($t['date'])) : '--';
                  $formatted_time = !empty($t['eta_time']) ? date('H:i', strtotime($t['eta_time'])) : '--:--';
                  $is_pending = $t_status === 'dispatched';
                ?>
                <tr class="hover:bg-[#102332] transition-colors">
                  <td class="py-3 px-4 font-bold text-white">
                    <?= htmlspecialchars($t['order_ref']) ?>
                  </td>
                  
                  <td class="py-3 px-4 font-sans text-slate-200">
                    <?= htmlspecialchars($t['destination']) ?>
                  </td>

                  <td class="py-3 px-4">
                    <div class="flex flex-col">
                      <span class="font-medium text-white font-sans">
                        <?= htmlspecialchars($t['trip_vehicle_name'] ?? 'Tanker') ?>
                      </span>
                      <span class="text-[10px] text-teal-600 dark:text-teal-400">
                        <?= htmlspecialchars($t['vehicle_id']) ?>
                      </span>
                    </div>
                  </td>

                  <td class="py-3 px-4 text-slate-400">
                    <?= htmlspecialchars($formatted_date) ?> <span class="text-neutral-300 dark:text-neutral-700">·</span> <?= htmlspecialchars($formatted_time) ?>
                  </td>

                  <td class="py-3 px-4">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-xs text-[10px] font-mono font-medium <?= $ts['badge'] ?>" data-en="<?= $ts['label_en'] ?>" data-ar="<?= $ts['label_ar'] ?>">
                      <?= $ts['label_en'] ?>
                    </span>
                  </td>

                  <td class="py-3 px-4 text-right rtl:text-left no-export font-sans">
                    <div class="flex items-center justify-end gap-1.5">
                      <button type="button"
                        class="trip-view-btn p-1 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 cursor-pointer transition inline-flex items-center"
                        title="Trip Details"
                        data-manifest="<?= htmlspecialchars($t['id']) ?>"
                        data-order="<?= htmlspecialchars($t['order_ref']) ?>"
                        data-destination="<?= htmlspecialchars($t['destination']) ?>"
                        data-date="<?= htmlspecialchars($formatted_date) ?>"
                        data-time="<?= htmlspecialchars($formatted_time) ?>"
                        data-vehicle="<?= htmlspecialchars(($t['trip_vehicle_name'] ?? 'Carrier') . ' (' . $t['vehicle_id'] . ')') ?>"
                        data-distance="<?= htmlspecialchars($t['distance']) ?>"
                        data-instructions="<?= htmlspecialchars($t['instructions'] ?? '') ?>"
                        data-status="<?= htmlspecialchars($t['status']) ?>"
                        data-status-label="<?= htmlspecialchars($ts['label_en']) ?>"
                        data-status-badge="<?= htmlspecialchars($ts['badge']) ?>"
                        data-pending="<?= $is_pending ? '1' : '0' ?>"
                      >
                        <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                      </button>
                      
                      <a href="order_details.php?order_num=<?= urlencode($t['order_ref']) ?>" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 cursor-pointer transition inline-flex items-center" title="Full Order Dossier">
                        <i data-lucide="eye" class="w-4 h-4"></i>
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

      <!-- Right: Driver Credentials & Vehicle Assignment (1 col) -->
      <div class="space-y-6">
        
        <!-- Identification & Safety Credentials -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
          <div class="p-4 border-b border-[#1a3346] bg-[#09151f]">
            <h3 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="Operator Credentials" data-ar="بيانات واعتماد السائق">
              Operator Credentials
            </h3>
          </div>
          <div class="p-4 space-y-3 text-xs font-mono divide-y divide-[#152a3a]">
            <div class="pt-2 first:pt-0">
              <span class="block text-[10px] uppercase text-neutral-500 mb-0.5" data-en="Mobile Contact" data-ar="رقم الجوال">Mobile Contact</span>
              <span class="font-bold text-white"><?= htmlspecialchars($driver['phone'] ?? 'N/A') ?></span>
            </div>

            <div class="pt-3">
              <span class="block text-[10px] uppercase text-neutral-500 mb-0.5" data-en="Commercial License Number" data-ar="رقم رخصة القيادة">Commercial License Number</span>
              <span class="font-bold text-white"><?= htmlspecialchars($driver['license_no']) ?></span>
            </div>

            <div class="pt-3">
              <span class="block text-[10px] uppercase text-neutral-500 mb-0.5" data-en="Blood Group / Medical" data-ar="فصيلة الدم / الطبي">Blood Group / Medical</span>
              <span class="inline-block px-1.5 py-0.5 text-xs font-bold rounded-xs bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/30">
                <?= htmlspecialchars($driver['blood_type']) ?>
              </span>
            </div>

            <div class="pt-3">
              <span class="block text-[10px] uppercase text-neutral-500 mb-0.5" data-en="Emergency Contact" data-ar="جهة الاتصال في الطوارئ">Emergency Contact</span>
              <span class="font-medium text-slate-200"><?= htmlspecialchars($driver['emergency_contact']) ?></span>
            </div>

            <div class="pt-3">
              <span class="block text-[10px] uppercase text-neutral-500 mb-0.5" data-en="Fleet Induction Date" data-ar="تاريخ الانضمام للأسطول">Fleet Induction Date</span>
              <span class="text-slate-300"><?= htmlspecialchars(date('d M Y', strtotime($driver['joined']))) ?></span>
            </div>
          </div>
        </div>

        <!-- Assigned Vehicle Card -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden border-s-4 border-s-[#f97316]">
          <div class="p-4 border-b border-[#1a3346] bg-[#09151f] flex justify-between items-center">
            <h3 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="Primary Assigned Carrier" data-ar="الناقلة المعينة حالياً">
              Primary Assigned Carrier
            </h3>
          </div>
          <div class="p-4 space-y-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xs bg-[#09151f] border border-[#1e3a50] flex items-center justify-center shrink-0">
                <i data-lucide="truck" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
              </div>
              <div>
                <p class="text-sm font-bold font-sans text-white"><?= htmlspecialchars($driver['vehicle_name']) ?></p>
                <p class="text-xs font-mono text-teal-400 dark:text-teal-400 mt-0.5"><?= htmlspecialchars($driver['vehicle_id']) ?></p>
              </div>
            </div>

            <?php if ($driver['vehicle_id'] !== 'Unassigned'): ?>
            <a href="vehicle_logs.php?id=<?= htmlspecialchars($driver['vehicle_id']) ?>" class="w-full inline-flex items-center justify-center gap-2 py-2 px-3 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#09151f] border border-[#1e3a50] rounded-xs hover:bg-[#112433] hover:text-white transition-colors">
              <i data-lucide="gauge" class="w-4 h-4"></i>
              <span data-en="View Vehicle Logs" data-ar="عرض سجل المركبة">View Vehicle Logs</span>
            </a>
            <?php endif; ?>
          </div>
        </div>

      </div>

    </div>

  </div>
</main>

<!-- Trip Detail / Action Modal -->
<div class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-[#060e14]/80 backdrop-blur-sm backdrop-blur-xs" id="tripModalOverlay">
  <div class="bg-[#0c1924] rounded-xs w-full max-w-lg flex flex-col border border-[#1a3346]">
    <div class="px-6 py-4 border-b border-[#1a3346] flex justify-between items-center bg-[#09151f]">
      <div>
        <p class="text-[10px] font-mono uppercase tracking-widest text-neutral-500 mb-0.5" data-en="Dispatch Manifest Details" data-ar="تفاصيل إرسالية الشحن">Dispatch Manifest Details</p>
        <h3 class="text-base font-bold font-oxanium tracking-tight text-white" id="modalManifestId">--</h3>
      </div>
      <button type="button" id="modalCloseBtn" class="text-neutral-400 hover:text-slate-300 dark:hover:text-neutral-200 cursor-pointer">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <div class="p-6 space-y-3 text-xs font-mono divide-y divide-[#152a3a]">
      <div class="flex justify-between items-center pt-2 first:pt-0">
        <span class="text-neutral-500 uppercase" data-en="Order Ref" data-ar="مرجع الطلب">Order Ref</span>
        <span class="font-bold text-white" id="modalOrderRef">--</span>
      </div>
      <div class="flex justify-between items-center pt-2">
        <span class="text-neutral-500 uppercase" data-en="Destination" data-ar="الوجهة">Destination</span>
        <span class="font-sans font-medium text-white" id="modalDestination">--</span>
      </div>
      <div class="flex justify-between items-center pt-2">
        <span class="text-neutral-500 uppercase" data-en="Schedule Date" data-ar="التاريخ">Schedule Date</span>
        <span class="text-slate-200" id="modalDate">--</span>
      </div>
      <div class="flex justify-between items-center pt-2">
        <span class="text-neutral-500 uppercase" data-en="ETA Time" data-ar="الوقت">ETA Time</span>
        <span class="text-slate-200" id="modalTime">--</span>
      </div>
      <div class="flex justify-between items-center pt-2">
        <span class="text-neutral-500 uppercase" data-en="Vehicle Assigned" data-ar="المركبة">Vehicle Assigned</span>
        <span class="text-teal-400 dark:text-teal-400" id="modalVehicle">--</span>
      </div>
      <div class="flex justify-between items-center pt-2">
        <span class="text-neutral-500 uppercase" data-en="Route Distance" data-ar="مسافة المسار">Route Distance</span>
        <span class="text-white font-bold" id="modalDistance">--</span>
      </div>
      <div class="flex justify-between items-center pt-2">
        <span class="text-neutral-500 uppercase" data-en="Current Status" data-ar="الحالة الحالية">Current Status</span>
        <span id="modalStatus">--</span>
      </div>
      <div class="pt-3">
        <span class="block text-[10px] text-neutral-500 uppercase mb-1" data-en="Driver Delivery Instructions" data-ar="تعليمات التسليم">Driver Delivery Instructions</span>
        <p class="font-sans text-xs p-3 bg-[#09151f] border border-[#1a3346] rounded-xs text-slate-200" id="modalInstructions">--</p>
      </div>
    </div>

    <form method="POST" action="" id="tripActionForm" class="px-6 py-4 border-t border-[#1a3346] flex gap-3 bg-[#09151f]">
      <?= csrf_field() ?>
      <input type="hidden" name="manifest_id" id="modalActionManifestId" value="">
      <input type="hidden" name="trip_action" id="modalActionType" value="">
      <button type="submit" id="modalRejectBtn" class="flex-1 py-2 px-3 text-xs font-mono uppercase tracking-wider border border-red-500/30 text-red-600 hover:bg-red-500/10 rounded-xs cursor-pointer transition-colors flex items-center justify-center gap-1.5">
        <i data-lucide="x-circle" class="w-4 h-4"></i>
        <span data-en="Reject Manifest" data-ar="رفض الإرسالية">Reject Manifest</span>
      </button>
      <button type="submit" id="modalAcceptBtn" class="flex-1 py-2 px-3 text-xs font-mono uppercase tracking-wider bg-emerald-600 hover:bg-emerald-700 text-white rounded-xs cursor-pointer transition-colors flex items-center justify-center gap-1.5">
        <i data-lucide="check-circle" class="w-4 h-4"></i>
        <span data-en="Accept & Dispatch" data-ar="قبول وبدء الرحلة">Accept & Dispatch</span>
      </button>
    </form>
  </div>
</div>

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

  document.addEventListener("DOMContentLoaded", () => {
    applyLanguage(localStorage.getItem('app_lang') || 'en');
  });

  const modalOverlay = document.getElementById('tripModalOverlay');
  const modalCloseBtn = document.getElementById('modalCloseBtn');
  const modalActionsWrap = document.getElementById('tripActionForm');
  const modalAcceptBtn = document.getElementById('modalAcceptBtn');
  const modalRejectBtn = document.getElementById('modalRejectBtn');
  const modalActionManifestId = document.getElementById('modalActionManifestId');
  const modalActionType = document.getElementById('modalActionType');

  document.querySelectorAll('.trip-view-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('modalManifestId').textContent = btn.dataset.manifest;
      document.getElementById('modalOrderRef').textContent = btn.dataset.order;
      document.getElementById('modalDestination').textContent = btn.dataset.destination;
      document.getElementById('modalDate').textContent = btn.dataset.date;
      document.getElementById('modalTime').textContent = btn.dataset.time;
      document.getElementById('modalVehicle').textContent = btn.dataset.vehicle;
      document.getElementById('modalDistance').textContent = btn.dataset.distance + ' KM';
      document.getElementById('modalInstructions').textContent = btn.dataset.instructions && btn.dataset.instructions.trim() !== '' ? btn.dataset.instructions : 'No special instructions provided.';

      const statusBadge = document.getElementById('modalStatus');
      statusBadge.innerHTML = '<span class="inline-flex items-center px-2 py-0.5 rounded-xs text-[10px] font-mono font-medium ' + btn.dataset.statusBadge + '">' + btn.dataset.statusLabel + '</span>';

      if (modalActionManifestId) {
        modalActionManifestId.value = btn.dataset.manifest;
      }

      if (modalActionsWrap) {
        if (btn.dataset.pending === '1') {
          modalActionsWrap.style.display = 'flex';
        } else {
          modalActionsWrap.style.display = 'none';
        }
      }

      modalOverlay.classList.remove('hidden');
      lucide.createIcons();
    });
  });

  if (modalAcceptBtn) {
    modalAcceptBtn.addEventListener('click', function () {
      if (modalActionType) modalActionType.value = 'accept';
    });
  }
  if (modalRejectBtn) {
    modalRejectBtn.addEventListener('click', function () {
      if (modalActionType) modalActionType.value = 'reject';
    });
  }

  if (modalCloseBtn) {
    modalCloseBtn.addEventListener('click', function () {
      modalOverlay.classList.add('hidden');
    });
  }
  
  if (modalOverlay) {
    modalOverlay.addEventListener('click', function (e) {
      if (e.target === modalOverlay) {
        modalOverlay.classList.add('hidden');
      }
    });
  }
</script>
</body>
</html>