<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_permission($pdo, 'logistics.view');

$is_admin = has_role($pdo, 'Admin') || has_role($pdo, 'Super Admin') || has_role($pdo, 'Logistics Manager') || has_permission($pdo, 'logistics.delete');

$active_page = 'vehicles_fleet';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Vehicles Fleet'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_csrf();
  require_permission($pdo, 'logistics.delete');
  $fleet_id_to_delete = $_POST['fleet_id'] ?? '';
  if (!empty($fleet_id_to_delete)) {
    $stmt_del = $pdo->prepare("DELETE FROM vehicles WHERE fleet_id = ?");
    $stmt_del->execute([$fleet_id_to_delete]);
    redirect(app_url('modules/logistics_fleet/vehicles_fleet.php'));
  }
}

$stmt_stats = $pdo->query("SELECT status, COUNT(*) as count FROM vehicles GROUP BY status");
$stats_raw = $stmt_stats->fetchAll(PDO::FETCH_KEY_PAIR);

$stats = [];
if ($stats_raw) {
  foreach ($stats_raw as $k => $v) {
    $stats[strtolower($k)] = (int)$v;
  }
}

$available   = $stats['available'] ?? 0;
$in_transit   = ($stats['transit'] ?? 0) + ($stats['in_transit'] ?? 0); 
$in_maintenance = $stats['maintenance'] ?? 0;
$total_vehicles = array_sum($stats);

$utilization = $total_vehicles > 0 ? round((($in_transit + $available) / $total_vehicles) * 100, 1) : 0;

$stmt_fleet = $pdo->query("
  SELECT v.* 
  FROM vehicles v 
  ORDER BY v.created_at DESC
");
$fleet_data = $stmt_fleet->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
  'available'  => ['badge' => 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'Available', 'label_ar' => 'متاح', 'tab' => 'available'],
  'transit'   => ['badge' => 'border border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-400', 'dot' => 'bg-cyan-500', 'label_en' => 'In Transit', 'label_ar' => 'في الطريق', 'tab' => 'in_transit'],
  'in_transit' => ['badge' => 'border border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-400', 'dot' => 'bg-cyan-500', 'label_en' => 'In Transit', 'label_ar' => 'في الطريق', 'tab' => 'in_transit'],
  'maintenance' => ['badge' => 'border border-amber-500/30 bg-amber-500/10 text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'Maintenance', 'label_ar' => 'في الصيانة', 'tab' => 'maintenance'],
];

$vehicle_type_map = [
  'cryo'  => ['en' => 'Cryogenic Tanker', 'ar' => 'صهريج تبريد (Cryo)', 'badge' => 'border-cyan-500/40 text-cyan-600 dark:text-cyan-400 bg-cyan-500/5'],
  'flatbed' => ['en' => 'Flatbed Truck', 'ar' => 'شاحنة مسطحة', 'badge' => 'border-neutral-500/40 text-slate-400 bg-neutral-500/5'],
  'bobtail' => ['en' => 'Bobtail Tanker', 'ar' => 'صهريج بوب تيل', 'badge' => 'border-teal-500/40 text-teal-600 dark:text-teal-400 bg-teal-500/5'],
  'pickup' => ['en' => 'Pickup Truck', 'ar' => 'شاحنة بيك أب', 'badge' => 'border-neutral-500/40 text-slate-400 bg-neutral-500/5']
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Gas Fleet & Tanker Transport | I-GAS Control Room" data-ar="أسطول صهاريج نقل الغاز | غرفة التحكم I-GAS">Gas Fleet & Tanker Transport | I-GAS Control Room</title>
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
        <span data-en="LOGISTICS FLEET DISPATCH NOMINAL" data-ar="جاهزية أسطول النقل اللوجستي مستقرة">LOGISTICS FLEET DISPATCH NOMINAL</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="UNITS REGISTERED:" data-ar="المركبات المسجلة:">UNITS REGISTERED:</span> <strong class="text-white"><?= $total_vehicles ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="UTILIZATION:" data-ar="معدل التشغيل:">UTILIZATION:</span> <strong class="text-teal-400 dark:text-teal-400"><?= $utilization ?>%</strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="shield-check" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
      <span data-en="ADR / HAZMAT COMPLIANT" data-ar="معتمد لنقل المواد الخطرة ADR">ADR / HAZMAT COMPLIANT</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-teal-500/30 bg-teal-500/10 text-teal-400 dark:text-teal-400 rounded-xs">
            LOGISTICS // FLEET REGISTRY
          </span>
          <span class="text-xs text-slate-400 font-mono">SYS-VEH-01</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Gas Fleet & Tanker Transport" data-ar="أسطول صهاريج نقل الغاز">
          Gas Fleet & Tanker Transport
        </h1>
        <p class="text-xs md:text-sm text-slate-400 mt-1 max-w-2xl" data-en="Manage cryogenic road tankers, bobtails, flatbeds, hazardous material transport permits, and route telemetry." data-ar="إدارة شاحنات نقل الغاز المسال، الصهاريج المبردة، شاحنات الأسطوانات، وتراخيص نقل المواد الخطرة.">
          Manage cryogenic road tankers, bobtails, flatbeds, hazardous material transport permits, and route telemetry.
        </p>
      </div>
      <div class="flex items-center gap-2.5">
        <button id="exportExcelBtn" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
          <i data-lucide="download" class="w-4 h-4 text-slate-400"></i>
          <span data-en="Export Registry" data-ar="تصدير السجل">Export Registry</span>
        </button>
        <?php if (has_permission($pdo, 'logistics.create')): ?>
        <a href="new_vehicle.php" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span data-en="Register Vehicle" data-ar="إضافة مركبة">Register Vehicle</span>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Metric KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Fleet -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="Total Fleet Units" data-ar="إجمالي وحدات الأسطول">Total Fleet Units</span>
          <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-white"><?= $total_vehicles ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono" data-en="Active registered carriers" data-ar="مركبات نقل مسجلة">
          Active registered carriers
        </div>
      </div>

      <!-- Active / In Transit -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-cyan-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="In Transit / On Route" data-ar="في الطريق / قيد النقل">In Transit / On Route</span>
          <i data-lucide="navigation" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-cyan-600 dark:text-cyan-400"><?= $in_transit ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-ping"></span>
          <span data-en="Active road delivery trips" data-ar="رحلات تسليم حية على الطريق">Active road delivery trips</span>
        </div>
      </div>

      <!-- Maintenance -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-amber-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="In Maintenance" data-ar="تحت الصيانة / الفحص">In Maintenance</span>
          <i data-lucide="wrench" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-amber-600 dark:text-amber-400"><?= $in_maintenance ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono" data-en="Hydro test & valve inspect" data-ar="فحص هيدروستاتيكي وصمامات">
          Hydro test & valve inspect
        </div>
      </div>

      <!-- Fleet Utilization -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-emerald-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="Fleet Utilization" data-ar="معدل استغلال الأسطول">Fleet Utilization</span>
          <i data-lucide="gauge" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-white"><?= $utilization ?>%</div>
        <div class="mt-2 w-full bg-[#09151f] h-1.5 rounded-full overflow-hidden">
          <div class="bg-emerald-500 h-full rounded-full transition-all duration-300" style="width: <?= min(100, $utilization) ?>%"></div>
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
            <span data-en="All Fleet" data-ar="كامل الأسطول">All Fleet</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-[#09151f] text-slate-400"><?= $total_vehicles ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="available">
            <span data-en="Available" data-ar="متاح">Available</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"><?= $available ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="in_transit">
            <span data-en="In Transit" data-ar="في الطريق">In Transit</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-cyan-500/10 text-cyan-700 dark:text-cyan-400"><?= $in_transit ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="maintenance">
            <span data-en="Maintenance" data-ar="صيانة">Maintenance</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-amber-500/10 text-amber-400 dark:text-amber-400"><?= $in_maintenance ?></span>
          </button>
        </div>

        <!-- Search and Type Filter -->
        <div class="flex items-center gap-3">
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search ID or Plate..." data-en-placeholder="Search ID or Plate..." data-ar-placeholder="بحث بالمعرف أو اللوحة..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
          <select id="typeFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
            <option value="all" data-en="All Types" data-ar="جميع الأنواع">All Types</option>
            <option value="cryo" data-en="Cryogenic Tanker" data-ar="صهريج تبريد (Cryo)">Cryogenic Tanker</option>
            <option value="bobtail" data-en="Bobtail Tanker" data-ar="صهريج بوب تيل">Bobtail Tanker</option>
            <option value="flatbed" data-en="Flatbed Truck" data-ar="شاحنة مسطحة">Flatbed Truck</option>
            <option value="pickup" data-en="Pickup Truck" data-ar="شاحنة بيك أب">Pickup Truck</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-left rtl:text-right border-collapse" id="fleetTable">
          <thead>
            <tr class="bg-[#09151f] border-b border-[#1a3346] text-[11px] font-mono text-slate-400 uppercase tracking-wider">
              <th class="py-3 px-4" data-en="Fleet ID" data-ar="معرف الأسطول">Fleet ID</th>
              <th class="py-3 px-4" data-en="Plate Number" data-ar="رقم اللوحة">Plate Number</th>
              <th class="py-3 px-4" data-en="Vehicle & Spec" data-ar="المركبة والمواصفات">Vehicle & Spec</th>
              <th class="py-3 px-4 text-right rtl:text-left" data-en="Payload Capacity" data-ar="السعة القصوى">Payload Capacity</th>
              <th class="py-3 px-4" data-en="Operational Status" data-ar="الحالة التشغيلية">Operational Status</th>
              <th class="py-3 px-4 text-right rtl:text-left no-export" data-en="Actions" data-ar="الإجراءات">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] text-xs font-mono">
            <?php if (empty($fleet_data)): ?>
            <tr class="empty-state">
              <td colspan="6" class="py-12 text-center text-slate-400 font-sans" data-en="No vehicles found in fleet registry." data-ar="لم يتم العثور على مركبات في سجل الأسطول.">
                No vehicles found in fleet registry.
              </td>
            </tr>
            <?php else: ?>
              <?php foreach ($fleet_data as $v): ?>
              <?php
                $v_status = strtolower($v['status'] ?? '');
                $s = $statusStyles[$v_status] ?? $statusStyles['available'];
                
                $capacity_display = '';
                if ($v['load_capacity'] > 0) {
                  $capacity_display = (float)$v['load_capacity'] . ' Ton';
                } else {
                  $capacity_display = $v['cylinder_capacity'] . ' Cyl.';
                }

                $raw_type = strtolower($v['vehicle_type'] ?? '');
                $display_type_en = $vehicle_type_map[$raw_type]['en'] ?? ucfirst($raw_type);
                $display_type_ar = $vehicle_type_map[$raw_type]['ar'] ?? ucfirst($raw_type);
                $type_badge_cls = $vehicle_type_map[$raw_type]['badge'] ?? 'border-neutral-500/30 text-slate-400';
              ?>
              <tr class="hover:bg-[#102332] transition-colors filterable-row" data-tab="<?= $s['tab'] ?>" data-type="<?= $raw_type ?>" data-search="<?= strtolower(htmlspecialchars($v['fleet_id'] . ' ' . $v['plate_number'] . ' ' . $v['make_model'])) ?>">
                <td class="py-3 px-4">
                  <a href="vehicle_logs.php?id=<?= urlencode($v['fleet_id']) ?>" class="font-bold text-teal-600 dark:text-teal-400 hover:underline inline-flex items-center gap-1.5">
                    <i data-lucide="truck" class="w-3.5 h-3.5"></i>
                    <?= htmlspecialchars($v['fleet_id']) ?>
                  </a>
                </td>
                
                <td class="py-3 px-4">
                  <span class="inline-block px-2 py-0.5 rounded-xs bg-[#09151f] border border-[#1e3a50] font-bold text-slate-200">
                    <?= htmlspecialchars($v['plate_number']) ?>
                  </span>
                </td>
                
                <td class="py-3 px-4 font-sans">
                  <div class="flex flex-col">
                    <div class="flex items-center gap-1.5">
                      <span class="font-medium text-white" data-en="<?= htmlspecialchars($display_type_en) ?>" data-ar="<?= htmlspecialchars($display_type_ar) ?>">
                        <?= htmlspecialchars($display_type_en) ?>
                      </span>
                      <span class="px-1.5 py-0.2 text-[10px] font-mono border rounded-xs <?= $type_badge_cls ?>">
                        <?= strtoupper($raw_type) ?>
                      </span>
                    </div>
                    <span class="text-[11px] font-mono text-slate-400 mt-0.5"><?= htmlspecialchars($v['make_model'] ?? 'N/A') ?></span>
                  </div>
                </td>
                
                <td class="py-3 px-4 text-right rtl:text-left font-bold text-white">
                  <?= htmlspecialchars($capacity_display) ?>
                </td>
                
                <td class="py-3 px-4">
                  <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs text-[11px] font-mono font-medium <?= $s['badge'] ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $s['dot'] ?>"></span>
                    <span data-en="<?= $s['label_en'] ?>" data-ar="<?= $s['label_ar'] ?>"><?= $s['label_en'] ?></span>
                  </span>
                </td>
                
                <td class="py-3 px-4 text-right rtl:text-left no-export font-sans">
                  <div class="flex items-center justify-end gap-1.5">
                    <!-- View Log History -->
                    <a href="vehicle_logs.php?id=<?= urlencode($v['fleet_id']) ?>" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 cursor-pointer transition" title="View Logs">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </a>

                    <!-- Add Log Entry -->
                    <a href="new_log_entry.php?id=<?= urlencode($v['fleet_id']) ?>" class="p-1 rounded-xs border border-teal-500/30 bg-teal-500/10 text-teal-400 dark:text-teal-400 hover:bg-teal-500/20 cursor-pointer transition" title="Log Trip / Odometer">
                      <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    </a>

                    <?php if ($is_admin): ?>
                    <form method="POST" action="" class="m-0 p-0 inline-flex" onsubmit="return confirm('Are you sure you want to delete this vehicle from fleet registry? / هل أنت متأكد من حذف هذه المركبة من سجل الأسطول؟');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="fleet_id" value="<?= htmlspecialchars($v['fleet_id']) ?>">
                      <button type="submit" class="p-1 rounded-xs border border-red-500/30 bg-red-500/10 text-red-600 hover:bg-red-500/20 cursor-pointer transition" title="Delete Vehicle">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <tr id="noResultsRow" style="display: none;">
                <td colspan="6" class="py-8 text-center text-slate-400 font-sans" data-en="No vehicles match your search criteria." data-ar="لا توجد مركبات تطابق معايير البحث.">
                  No vehicles match your search criteria.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Footer Pagination / Counts -->
      <div class="p-3 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between text-xs font-mono text-slate-400">
        <div>
          <span data-en="Showing" data-ar="عرض">Showing</span> <strong id="visibleCount" class="text-white"><?= count($fleet_data) ?></strong> <span data-en="of" data-ar="من">of</span> <strong class="text-white"><?= count($fleet_data) ?></strong> <span data-en="fleet units" data-ar="مركبة">fleet units</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
          <span data-en="All GPS transmitters live" data-ar="جميع أجهزة التتبع تعمل">All GPS transmitters live</span>
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
        const table = document.getElementById("fleetTable");
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
        link.setAttribute("download", "Fleet_Registry_Export_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
    }
  });
</script>
</body>
</html>