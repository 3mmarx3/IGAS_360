<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'logistics.view');

$active_page = 'vehicles_fleet';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Vehicles Fleet', 'Vehicle Logs'];

$vehicle_id = $_GET['id'] ?? '';

if (empty($vehicle_id)) {
  $firstFleet = $pdo->query("SELECT fleet_id FROM vehicles ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstFleet) {
    $vehicle_id = $firstFleet;
  } else {
    redirect(app_url('modules/logistics_fleet/vehicles_fleet.php'));
  }
}

$stmt_vehicle = $pdo->prepare("
  SELECT * 
  FROM vehicles 
  WHERE fleet_id = ? OR CAST(id AS CHAR) = ?
");
$stmt_vehicle->execute([$vehicle_id, $vehicle_id]);
$db_vehicle = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);

if (!$db_vehicle) {
  redirect(app_url('modules/logistics_fleet/vehicles_fleet.php'));
}
$vehicle_id = $db_vehicle['fleet_id'];

$stmt_odo = $pdo->prepare("SELECT MAX(odometer) FROM vehicle_logs WHERE fleet_id = ?");
$stmt_odo->execute([$vehicle_id]);
$max_odo = $stmt_odo->fetchColumn();
$mileage = $max_odo ? number_format($max_odo) : '0';

$stmt_maint = $pdo->prepare("SELECT MAX(event_date) FROM vehicle_logs WHERE fleet_id = ? AND event_type = 'maintenance'");
$stmt_maint->execute([$vehicle_id]);
$last_maint_date = $stmt_maint->fetchColumn();

$last_maintenance = $last_maint_date ? date('d M Y', strtotime($last_maint_date)) : 'N/A';
$next_maintenance = $last_maint_date ? date('d M Y', strtotime('+6 months', strtotime($last_maint_date))) : 'N/A';

$vehicle = [
  'id' => $db_vehicle['fleet_id'],
  'plate' => $db_vehicle['plate_number'],
  'type' => ucfirst($db_vehicle['vehicle_type']),
  'make' => $db_vehicle['make_model'],
  'status' => $db_vehicle['status'],
  'mileage' => $mileage,
  'fuel_level' => 78,
  'last_maintenance' => $last_maintenance,
  'next_maintenance' => $next_maintenance
];

$stmt_logs = $pdo->prepare("
  SELECT 
    l.id,
    l.event_type,
    l.event_date,
    l.event_time,
    l.odometer,
    l.fuel_liters,
    l.event_cost,
    l.logged_by,
    l.description,
    (SELECT dr.full_name 
     FROM dispatches ds 
     LEFT JOIN drivers dr ON ds.driver_id = dr.driver_id 
     WHERE ds.vehicle_id = l.fleet_id AND ds.dispatch_date = l.event_date 
     LIMIT 1) as trip_driver
  FROM vehicle_logs l 
  WHERE l.fleet_id = ?
  
  UNION ALL
  
  SELECT 
    d.id,
    'dispatch' as event_type,
    d.dispatch_date as event_date,
    d.eta_time as event_time,
    0 as odometer,
    0 as fuel_liters,
    0 as event_cost,
    'System' as logged_by,
    CONCAT('Dispatch: ', d.manifest_id, ' to ', d.destination, ' (Ref: ', d.order_ref, ')') as description,
    (SELECT dr.full_name FROM drivers dr WHERE dr.driver_id = d.driver_id LIMIT 1) as trip_driver
  FROM dispatches d
  WHERE d.vehicle_id = ?
  
  ORDER BY event_date DESC, event_time DESC
");
$stmt_logs->execute([$vehicle_id, $vehicle_id]);
$db_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

$logs = [];
foreach ($db_logs as $l) {
  $type = strtolower($l['event_type']);
  $icon = 'circle';
  $badge_cls = 'border-neutral-500/30 bg-neutral-500/10 text-slate-400';
  $display_type = ucfirst($type);

  if ($type === 'dispatch') {
    $icon = 'arrow-up-right';
    $badge_cls = 'border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-400';
  } elseif ($type === 'arrival') {
    $icon = 'arrow-down-left';
    $badge_cls = 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
  } elseif (in_array($type, ['refuel', 'refuel operations'])) {
    $icon = 'fuel';
    $badge_cls = 'border-amber-500/30 bg-amber-500/10 text-amber-400 dark:text-amber-400';
    $display_type = 'Refuel Operations';
  } elseif ($type === 'maintenance') {
    $icon = 'wrench';
    $badge_cls = 'border-rose-500/30 bg-rose-500/10 text-rose-400 dark:text-rose-400';
  } elseif ($type === 'incident') {
    $icon = 'alert-triangle';
    $badge_cls = 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400';
  }

  $date_time_display = date('d M Y, H:i', strtotime($l['event_date'] . ' ' . $l['event_time']));
  
  $driver_name = '-';
  if (in_array($type, ['dispatch', 'arrival'])) {
    $driver_name = $l['trip_driver'] ?: ($l['logged_by'] ?: 'Unknown Driver');
  }

  $logs[] = [
    'id' => $l['id'],
    'type' => $display_type,
    'icon' => $icon,
    'badge_cls' => $badge_cls,
    'date' => date('d M Y', strtotime($l['event_date'])),
    'time' => date('H:i', strtotime($l['event_time'])),
    'date_time_display' => $date_time_display,
    'odometer' => $l['odometer'] ? number_format($l['odometer']) : '-',
    'fuel' => $l['fuel_liters'] > 0 ? number_format($l['fuel_liters'], 1) : '-',
    'cost' => $l['event_cost'] > 0 ? number_format($l['event_cost'], 2) : '-',
    'operator' => $l['logged_by'] ?: 'System',
    'driver' => $driver_name,
    'desc' => $l['description'] ?: 'No additional notes provided.'
  ];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Carrier Telemetry Dossier <?= htmlspecialchars($vehicle['id']) ?> | I-GAS Control Room" data-ar="ملف تتبع الناقلة <?= htmlspecialchars($vehicle['id']) ?> | غرفة التحكم I-GAS">Carrier Telemetry Dossier <?= htmlspecialchars($vehicle['id']) ?> | I-GAS Control Room</title>
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
        <span data-en="CARRIER DOSSIER & LOGISTICS TELEMETRY" data-ar="ملف الناقلة وتتبع العمليات اللوجستية">CARRIER DOSSIER & LOGISTICS TELEMETRY</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="FLEET ID:" data-ar="معرف الأسطول:">FLEET ID:</span> <strong class="text-teal-400 dark:text-teal-400"><?= htmlspecialchars($vehicle['id']) ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="PLATE:" data-ar="اللوحة:">PLATE:</span> <strong class="text-white"><?= htmlspecialchars($vehicle['plate']) ?></strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="gauge" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
      <span data-en="ODOMETER:" data-ar="العداد:">ODOMETER:</span> <span class="font-bold text-white"><?= htmlspecialchars($vehicle['mileage']) ?> KM</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="vehicles_fleet.php" class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-[#1e3a50] text-slate-400 hover:border-teal-500 hover:text-teal-600 rounded-xs transition-colors">
            ← FLEET REGISTRY
          </a>
          <span class="text-xs text-slate-400 font-mono">SYS-LOG-01</span>
        </div>
        <div class="flex items-center gap-3">
          <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Carrier Telemetry & Event History" data-ar="سجل وتتبع حركة الناقلة">
            Carrier Telemetry & Event History
          </h1>
          <span class="px-2 py-0.5 text-xs font-mono font-bold bg-[#09151f] border border-[#1e3a50] rounded-xs text-teal-600 dark:text-teal-400">
            <?= htmlspecialchars($vehicle['id']) ?>
          </span>
        </div>
        <p class="text-xs md:text-sm text-slate-400 mt-1" data-en="Trip logs, odometer progression, refueling events, and preventative maintenance history." data-ar="سجلات الرحلات، تطور عداد الكيلومترات، عمليات التزود بالوقود، وسجل الصيانة الوقائية.">
          Trip logs, odometer progression, refueling events, and preventative maintenance history.
        </p>
      </div>
      <div class="flex items-center gap-2.5">
        <button onclick="window.print()" class="inline-flex items-center gap-2 px-3 py-2 text-xs font-medium font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] dark:hover:bg-neutral-700 cursor-pointer transition-colors">
          <i data-lucide="printer" class="w-4 h-4 text-neutral-500"></i>
          <span data-en="Print Dossier" data-ar="طباعة الملف">Print Dossier</span>
        </button>
        <a href="<?= app_url('modules/logistics_fleet/new_log_entry.php?id=' . urlencode($vehicle['id'])) ?>" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs transition-colors">
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span data-en="Log Trip / Event" data-ar="تسجيل حركة جديدة">Log Trip / Event</span>
        </a>
      </div>
    </div>

    <!-- Vehicle Telemetry Bento Box -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <!-- Identification -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-slate-400 text-xs font-mono uppercase mb-1" data-en="Carrier Unit Spec" data-ar="مواصفات الناقلة">
          Carrier Unit Spec
        </div>
        <div class="text-base font-bold text-white font-sans"><?= htmlspecialchars($vehicle['make']) ?></div>
        <div class="text-xs text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($vehicle['type']) ?></div>
        <div class="mt-3">
          <span class="inline-block px-2.5 py-0.5 bg-[#09151f] border border-[#1e3a50] rounded-xs font-mono text-xs font-bold text-slate-200">
            <?= htmlspecialchars($vehicle['plate']) ?>
          </span>
        </div>
      </div>

      <!-- Current Status -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-cyan-400">
        <div class="text-slate-400 text-xs font-mono uppercase mb-1" data-en="Operational Status" data-ar="الحالة التشغيلية">
          Operational Status
        </div>
        <div class="mt-2">
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xs text-xs font-mono font-medium border border-teal-500/30 bg-teal-500/10 text-teal-400 dark:text-teal-400">
            <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
            <?= htmlspecialchars(ucfirst($vehicle['status'])) ?>
          </span>
        </div>
        <div class="text-[11px] text-slate-400 font-mono mt-3" data-en="ADR Hazmat Certified" data-ar="معتمد لنقل المواد الخطرة">
          ADR Hazmat Certified
        </div>
      </div>

      <!-- Odometer & Fuel -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-emerald-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-1">
          <span data-en="Odometer & Tank" data-ar="العداد وخزان الوقود">Odometer & Tank</span>
          <i data-lucide="fuel" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="text-lg font-bold font-mono text-white">
          <?= htmlspecialchars($vehicle['mileage']) ?> <span class="text-xs font-normal text-neutral-500">KM</span>
        </div>
        <div class="mt-3 space-y-1">
          <div class="flex justify-between text-[11px] font-mono">
            <span class="text-neutral-500" data-en="Fuel Reservoir" data-ar="مستوى الوقود">Fuel Reservoir</span>
            <span class="font-bold text-white"><?= $vehicle['fuel_level'] ?>%</span>
          </div>
          <div class="w-full bg-[#09151f] h-1.5 rounded-full overflow-hidden">
            <div class="bg-emerald-500 h-full rounded-full" style="width: <?= $vehicle['fuel_level'] ?>%"></div>
          </div>
        </div>
      </div>

      <!-- Maintenance Schedule -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-amber-400">
        <div class="text-slate-400 text-xs font-mono uppercase mb-1" data-en="Safety & Inspection" data-ar="الفحص الدوري والسلامة">
          Safety & Inspection
        </div>
        <div class="text-xs font-mono mt-1 space-y-1 text-slate-300">
          <div>
            <span class="text-neutral-500" data-en="Last:" data-ar="السابق:">Last:</span>
            <strong><?= htmlspecialchars($vehicle['last_maintenance']) ?></strong>
          </div>
          <div>
            <span class="text-neutral-500" data-en="Next Due:" data-ar="المستحق:">Next Due:</span>
            <strong class="text-amber-600 dark:text-amber-400"><?= htmlspecialchars($vehicle['next_maintenance']) ?></strong>
          </div>
        </div>
        <div class="text-[11px] text-slate-400 font-mono mt-2" data-en="Hydro test valid" data-ar="فحص الصهريج ساري">
          Hydro test valid
        </div>
      </div>
    </div>

    <!-- Activity Log Table Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
      <div class="p-4 border-b border-[#1a3346] flex justify-between items-center bg-[#09151f]">
        <div class="flex items-center gap-2">
          <i data-lucide="history" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          <h3 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="Carrier Telemetry & Event Stream" data-ar="سجل أحداث وحركة الناقلة">
            Carrier Telemetry & Event Stream
          </h3>
        </div>
        <span class="text-xs font-mono text-slate-400">
          <?= count($logs) ?> <span data-en="records logged" data-ar="سجل مسجل">records logged</span>
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left rtl:text-right border-collapse">
          <thead>
            <tr class="bg-[#09151f] border-b border-[#1a3346] text-[11px] font-mono text-slate-400 uppercase tracking-wider">
              <th class="py-3 px-4" data-en="Fleet ID" data-ar="معرف الأسطول">Fleet ID</th>
              <th class="py-3 px-4" data-en="Plate" data-ar="اللوحة">Plate</th>
              <th class="py-3 px-4" data-en="Event Type" data-ar="نوع الحدث">Event Type</th>
              <th class="py-3 px-4" data-en="Assigned Driver" data-ar="السائق">Assigned Driver</th>
              <th class="py-3 px-4" data-en="Timestamp" data-ar="التوقيت">Timestamp</th>
              <th class="py-3 px-4 text-right rtl:text-left" data-en="Actions" data-ar="الإجراءات">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] text-xs font-mono">
            <?php if (empty($logs)): ?>
            <tr>
              <td colspan="6" class="py-12 text-center text-slate-400 font-sans" data-en="No telemetry or activity logs recorded for this vehicle." data-ar="لا توجد سجلات حركة أو أحداث مسجلة لهذه الناقلة.">
                No telemetry or activity logs recorded for this vehicle.
              </td>
            </tr>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
              <tr class="hover:bg-[#102332] transition-colors">
                <td class="py-3 px-4 font-bold text-teal-600 dark:text-teal-400"><?= htmlspecialchars($vehicle['id']) ?></td>
                <td class="py-3 px-4 text-slate-400 font-bold"><?= htmlspecialchars($vehicle['plate']) ?></td>
                <td class="py-3 px-4">
                  <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs text-[11px] font-mono font-medium border <?= $log['badge_cls'] ?>">
                    <i data-lucide="<?= $log['icon'] ?>" class="w-3 h-3"></i>
                    <?= htmlspecialchars($log['type']) ?>
                  </span>
                </td>
                <td class="py-3 px-4 font-sans text-slate-200">
                  <?php if ($log['driver'] !== '-'): ?>
                    <span class="inline-flex items-center gap-1.5 font-medium">
                      <i data-lucide="user" class="w-3.5 h-3.5 text-neutral-400"></i>
                      <?= htmlspecialchars($log['driver']) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-neutral-400 font-mono">-</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4 text-slate-300 font-mono"><?= htmlspecialchars($log['date_time_display']) ?></td>
                <td class="py-3 px-4 text-right rtl:text-left">
                  <button type="button" class="p-1.5 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 cursor-pointer transition inline-flex items-center" onclick="openLogModal(this)" data-log="<?= htmlspecialchars(json_encode($log)) ?>" title="View Event Dossier">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                  </button>
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

<!-- Modal for Viewing Full Log Details -->
<div id="log-modal-overlay" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-[#060e14]/80 backdrop-blur-sm backdrop-blur-xs">
  <div class="bg-[#0c1924] rounded-xs w-full max-w-2xl flex flex-col border border-[#1a3346]">
    <div class="px-6 py-4 border-b border-[#1a3346] flex justify-between items-center bg-[#09151f]">
      <div class="flex items-center gap-2">
        <i data-lucide="file-text" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
        <h2 class="text-sm font-bold font-oxanium tracking-tight text-white" data-en="Telemetry Event Details" data-ar="تفاصيل حدث التتبع">
          Telemetry Event Details
        </h2>
      </div>
      <button type="button" onclick="closeLogModal()" class="text-neutral-400 hover:text-slate-300 dark:hover:text-neutral-200 transition cursor-pointer">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    
    <div class="p-6 space-y-6 text-xs font-mono">
      <div>
        <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Target Carrier Unit" data-ar="الناقلة المستهدفة">Target Carrier Unit</span>
        <div class="text-sm font-bold text-white font-sans">
          <?= htmlspecialchars($vehicle['id']) ?> (<?= htmlspecialchars($vehicle['make']) ?> — <?= htmlspecialchars($vehicle['plate']) ?>)
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-5 gap-4 py-4 border-y border-[#1a3346]">
        <div>
          <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Event Type" data-ar="نوع الحدث">Event Type</span>
          <span class="font-bold text-teal-600 dark:text-teal-400" id="mdl-type">-</span>
        </div>
        <div>
          <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Driver" data-ar="السائق">Driver</span>
          <span class="font-medium text-white font-sans" id="mdl-driver">-</span>
        </div>
        <div>
          <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Date" data-ar="التاريخ">Date</span>
          <span class="text-slate-200" id="mdl-date">-</span>
        </div>
        <div>
          <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Time" data-ar="الوقت">Time</span>
          <span class="text-slate-200" id="mdl-time">-</span>
        </div>
        <div>
          <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Logged By" data-ar="التسجيل بواسطة">Logged By</span>
          <span class="text-slate-200" id="mdl-operator">-</span>
        </div>
      </div>

      <div id="metrics-row" class="grid grid-cols-3 gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Odometer" data-ar="عداد المسافة">Odometer</span>
          <span class="text-sm font-bold text-white"><span id="mdl-odo">-</span> <span class="text-[10px] text-neutral-500">KM</span></span>
        </div>
        <div>
          <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Fuel Dispensed" data-ar="كمية الوقود">Fuel Dispensed</span>
          <span class="text-sm font-bold text-white"><span id="mdl-fuel">-</span> <span class="text-[10px] text-neutral-500">LTR</span></span>
        </div>
        <div>
          <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Associated Cost" data-ar="التكلفة">Associated Cost</span>
          <span class="text-sm font-bold text-white"><span id="mdl-cost">-</span> <span class="text-[10px] text-neutral-500">SAR</span></span>
        </div>
      </div>

      <div>
        <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Description & Technical Notes" data-ar="الوصف والملاحظات الفنية">Description & Technical Notes</span>
        <p class="text-xs leading-relaxed text-slate-200 font-sans p-3 bg-[#09151f] border border-[#1a3346] rounded-xs" id="mdl-desc">-</p>
      </div>
    </div>
    
    <div class="px-6 py-4 border-t border-[#1a3346] flex justify-end bg-[#09151f]">
      <button type="button" onclick="closeLogModal()" class="px-4 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] dark:hover:bg-neutral-700 cursor-pointer transition-colors" data-en="Close" data-ar="إغلاق">
        Close
      </button>
    </div>
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
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", () => {
    applyLanguage(localStorage.getItem('app_lang') || 'en');
  });

  function openLogModal(btn) {
    const data = JSON.parse(btn.getAttribute('data-log'));
    
    document.getElementById('mdl-type').textContent = data.type;
    document.getElementById('mdl-driver').textContent = data.driver !== '-' ? data.driver : 'N/A';
    document.getElementById('mdl-date').textContent = data.date;
    document.getElementById('mdl-time').textContent = data.time;
    document.getElementById('mdl-operator').textContent = data.operator;
    
    document.getElementById('mdl-odo').textContent = data.odometer;
    document.getElementById('mdl-fuel').textContent = data.fuel !== '-' ? data.fuel : 'N/A';
    document.getElementById('mdl-cost').textContent = data.cost !== '-' ? data.cost : 'N/A';
    
    document.getElementById('mdl-desc').textContent = data.desc;

    if (data.type === 'Maintenance' || data.type === 'Refuel Operations') {
      document.getElementById('metrics-row').style.display = 'grid';
    } else {
      document.getElementById('metrics-row').style.display = 'none';
    }

    document.getElementById('log-modal-overlay').classList.remove('hidden');
  }

  function closeLogModal() {
    document.getElementById('log-modal-overlay').classList.add('hidden');
  }

  document.getElementById('log-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) {
      closeLogModal();
    }
  });
</script>
</body>
</html>