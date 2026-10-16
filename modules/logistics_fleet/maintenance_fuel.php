<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'logistics.view');

$is_admin = has_role($pdo, 'Admin') || has_role($pdo, 'Super Admin') || has_role($pdo, 'Logistics Manager') || has_permission($pdo, 'logistics.edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  require_csrf();
  $action = $_POST['action'];
  $ticket_id = $_POST['ticket_id'] ?? '';
  
  if ($action === 'delete') {
    require_permission($pdo, 'logistics.delete');
    $stmt_del = $pdo->prepare("DELETE FROM maintenance_tickets WHERE ticket_id = ?");
    $stmt_del->execute([$ticket_id]);
  } elseif ($action === 'complete') {
    require_permission($pdo, 'logistics.edit');
    $stmt_upd = $pdo->prepare("UPDATE maintenance_tickets SET status = 'completed' WHERE ticket_id = ?");
    $stmt_upd->execute([$ticket_id]);
  }
  redirect(app_url('modules/logistics_fleet/maintenance_fuel.php'));
}

$active_page = 'maintenance_fuel';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Maintenance & Fuel'];

$stmt_active = $pdo->query("SELECT COUNT(*) FROM maintenance_tickets WHERE status IN ('scheduled', 'in_progress')");
$active_tickets = $stmt_active->fetchColumn() ?: 0;

$stmt_overdue = $pdo->query("SELECT COUNT(*) FROM maintenance_tickets WHERE status NOT IN ('completed', 'cancelled') AND scheduled_date < CURDATE()");
$overdue_tasks = $stmt_overdue->fetchColumn() ?: 0;

$stmt_fuel = $pdo->query("SELECT SUM(fuel_liters) FROM vehicle_logs WHERE event_type = 'refuel' AND MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE())");
$fuel_mtd = $stmt_fuel->fetchColumn() ?: 0;

$stmt_cost = $pdo->query("SELECT SUM(estimated_cost) FROM maintenance_tickets WHERE MONTH(scheduled_date) = MONTH(CURDATE()) AND YEAR(scheduled_date) = YEAR(CURDATE())");
$cost_mtd = $stmt_cost->fetchColumn() ?: 0;

$stmt_records = $pdo->query("
  SELECT m.ticket_id as id, m.vehicle_id as vehicle, v.make_model as model, m.service_type as type, m.status as db_status, m.scheduled_date as date, m.estimated_cost as cost 
  FROM maintenance_tickets m 
  LEFT JOIN vehicles v ON m.vehicle_id = v.fleet_id 
  ORDER BY m.created_at DESC
");
$raw_records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

// Also fetch fuel logs for the Fuel tab
$stmt_fuel_logs = $pdo->query("
  SELECT l.id, l.fleet_id as vehicle, v.make_model as model, 'refuel' as type, 'completed' as db_status, l.event_date as date, l.event_cost as cost, l.fuel_liters, l.odometer
  FROM vehicle_logs l
  LEFT JOIN vehicles v ON l.fleet_id = v.fleet_id
  WHERE l.event_type = 'refuel'
  ORDER BY l.event_date DESC LIMIT 50
");
$raw_fuel_logs = $stmt_fuel_logs->fetchAll(PDO::FETCH_ASSOC);

$maintenance_records = [];
$tab_counts = [
  'active' => 0,
  'history' => 0,
  'fuel' => count($raw_fuel_logs)
];

$type_map_en = [
  'preventive' => 'Preventive (PM)',
  'repair' => 'Repair',
  'inspection' => 'Inspection',
  'tires' => 'Tire Replacement',
  'oil' => 'Oil Change',
  'refuel' => 'Fuel Replenishment'
];

$type_map_ar = [
  'preventive' => 'صيانة وقائية',
  'repair' => 'إصلاح',
  'inspection' => 'فحص فني',
  'tires' => 'تغيير إطارات',
  'oil' => 'تغيير زيت',
  'refuel' => 'تزود بالوقود'
];

foreach ($raw_records as $r) {
  $status = $r['db_status'];
  if (in_array($status, ['scheduled', 'in_progress']) && strtotime($r['date']) < strtotime(date('Y-m-d'))) {
    $status = 'overdue';
  }
  
  $tab_category = 'active';
  if (in_array($status, ['completed', 'cancelled'])) {
    $tab_category = 'history';
    if ($status === 'cancelled') $status = 'overdue'; 
  }

  $tab_counts[$tab_category]++;
  
  $maintenance_records[] = [
    'id' => $r['id'],
    'vehicle' => $r['vehicle'],
    'model' => $r['model'] ?: 'Unknown Model',
    'type_en' => $type_map_en[$r['type']] ?? ucfirst($r['type']),
    'type_ar' => $type_map_ar[$r['type']] ?? ucfirst($r['type']),
    'status' => $status,
    'date' => $r['date'],
    'cost' => (float)$r['cost'],
    'tab' => $tab_category,
    'is_ticket' => true
  ];
}

foreach ($raw_fuel_logs as $fl) {
  $maintenance_records[] = [
    'id' => 'FL-' . str_pad($fl['id'], 5, '0', STR_PAD_LEFT),
    'vehicle' => $fl['vehicle'],
    'model' => $fl['model'] ?: 'Tanker Unit',
    'type_en' => 'Refuel (' . number_format($fl['fuel_liters'], 1) . ' L)',
    'type_ar' => 'وقود (' . number_format($fl['fuel_liters'], 1) . ' لتر)',
    'status' => 'completed',
    'date' => $fl['date'],
    'cost' => (float)$fl['cost'],
    'tab' => 'fuel',
    'is_ticket' => false
  ];
}

$statusStyles = [
  'scheduled'  => ['bg' => 'bg-sky-500/10', 'text' => 'text-sky-700 dark:text-sky-400', 'border' => 'border-sky-300 dark:border-sky-800', 'dot' => 'bg-sky-500', 'label_en' => 'Scheduled', 'label_ar' => 'مجدول'],
  'in_progress' => ['bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'border' => 'border-amber-300 dark:border-amber-800', 'dot' => 'bg-amber-500', 'label_en' => 'In Workshop', 'label_ar' => 'في الورشة'],
  'completed'  => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'border' => 'border-emerald-300 dark:border-emerald-800', 'dot' => 'bg-emerald-500', 'label_en' => 'Completed', 'label_ar' => 'مكتمل'],
  'overdue'   => ['bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'border' => 'border-rose-800 dark:border-rose-800', 'dot' => 'bg-rose-500', 'label_en' => 'Overdue', 'label_ar' => 'متأخر'],
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Maintenance & Fuel Tracking | I-GAS Control Room" data-ar="متابعة الصيانة واستهلاك الوقود | غرفة التحكم I-GAS">Maintenance & Fuel Tracking | I-GAS Control Room</title>
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
      <span class="inline-flex items-center gap-1.5 text-slate-400">
        <span class="w-2 h-2 rounded-full bg-teal-500"></span>
        <span data-en="WORKSHOP FLEET TELEMETRY & FUEL MTD" data-ar="بيانات صيانة الأسطول ومعدل الوقود">WORKSHOP FLEET TELEMETRY & FUEL MTD</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="WORKSHOP QUEUE:" data-ar="قائمة الورشة:">WORKSHOP QUEUE:</span> <strong class="text-white"><?= $active_tickets ?> UNITS</strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="CRITICAL OVERDUE:" data-ar="متأخرات حرجة:">CRITICAL OVERDUE:</span> <strong class="<?= $overdue_tasks > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-white' ?>"><?= $overdue_tasks ?></strong>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: OPS-MAINT</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">

    <!-- Header Row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
          <span data-en="Logistics & Fleet" data-ar="اللوجستيات والأسطول">Logistics & Fleet</span>
          <span>/</span>
          <span class="text-white font-semibold" data-en="Workshop Operations" data-ar="عمليات الورشة">Workshop Operations</span>
        </div>
        <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white flex items-center gap-2">
          <i data-lucide="wrench" class="w-6 h-6 text-teal-600 dark:text-teal-400"></i>
          <span data-en="Maintenance & Fuel Tracking" data-ar="متابعة الصيانة وسجلات الوقود">Maintenance & Fuel Tracking</span>
        </h1>
        <p class="text-sm text-slate-400 mt-1" data-en="Manage fleet preventative service schedules, workshop tickets, and fuel consumption analytics." data-ar="إدارة جداول الصيانة الوقائية للأسطول، وتذاكر الورشة، وتحليلات استهلاك الوقود.">
          Manage fleet preventative service schedules, workshop tickets, and fuel consumption analytics.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <a href="manual_entry.php" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 transition-colors">
          <i data-lucide="fuel" class="w-4 h-4 text-cyan-400"></i>
          <span data-en="Log Fuel Entry" data-ar="تسجيل استهلاك وقود">Log Fuel Entry</span>
        </a>
        <?php if ($is_admin): ?>
        <a href="new_maintenance.php" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
          <i data-lucide="plus-circle" class="w-4 h-4"></i>
          <span data-en="New Service Ticket" data-ar="تذكرة صيانة جديدة">New Service Ticket</span>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- 4 KPI Telemetry Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- KPI 1: Active Workshop Tickets -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Active Workshop Tickets" data-ar="تذاكر الورشة النشطة">
          Active Workshop Tickets
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= $active_tickets ?>
          </span>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-mono uppercase rounded-xs bg-amber-500/10 text-amber-400 dark:text-amber-400 border border-amber-300 dark:border-amber-800">
            <span class="w-1 h-1 rounded-full bg-amber-500"></span>
            <span data-en="In Progress" data-ar="قيد التنفيذ">In Progress</span>
          </span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Vehicles currently undergoing repair / PM" data-ar="مركبات تخضع للصيانة أو الإصلاح">
          Vehicles currently undergoing repair / PM
        </div>
      </div>

      <!-- KPI 2: Overdue Services -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Overdue Services" data-ar="خدمات متأخرة">
          Overdue Services
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono <?= $overdue_tasks > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-white' ?>">
            <?= $overdue_tasks ?>
          </span>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-mono uppercase rounded-xs <?= $overdue_tasks > 0 ? 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border border-rose-800 dark:border-rose-800' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800' ?>">
            <span class="w-1 h-1 rounded-full <?= $overdue_tasks > 0 ? 'bg-rose-500' : 'bg-emerald-500' ?>"></span>
            <span data-en="<?= $overdue_tasks > 0 ? 'Action Req' : 'Nominal' ?>" data-ar="<?= $overdue_tasks > 0 ? 'مطلوب إجراء' : 'طبيعي' ?>"><?= $overdue_tasks > 0 ? 'Action Req' : 'Nominal' ?></span>
          </span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Past scheduled maintenance window" data-ar="تجاوزت الفترة المحددة للصيانة">
          Past scheduled maintenance window
        </div>
      </div>

      <!-- KPI 3: Fuel Burned MTD -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Fuel Consumption (MTD)" data-ar="استهلاك الوقود الشهري">
          Fuel Consumption (MTD)
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= number_format($fuel_mtd) ?> <span class="text-xs font-normal text-neutral-500">L</span>
          </span>
          <span class="text-[10px] font-mono text-neutral-500">DIESEL/GAS</span>
        </div>
        <div class="mt-2 w-full bg-[#132636] bg-[#112433] h-1.5 rounded-full overflow-hidden">
          <div class="bg-teal-500 h-full rounded-full" style="width: 65%;"></div>
        </div>
      </div>

      <!-- KPI 4: Maintenance Cost MTD -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Maintenance Cost (MTD)" data-ar="تكلفة الصيانة الشهرية">
          Maintenance Cost (MTD)
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= number_format($cost_mtd, 0) ?> <span class="text-xs font-normal text-neutral-500">SAR</span>
          </span>
          <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400">ON BUDGET</span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Estimated vs actual workshop spend" data-ar="الميزانية المقدرة مقابل الإنفاق الفعلي">
          Estimated vs actual workshop spend
        </div>
      </div>

    </div>

    <!-- Registry & Data Table Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
      
      <!-- Controls Header: Search + Underline Tabs -->
      <div class="p-4 border-b border-[#1a3346] space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
            <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="Service & Repair Registry" data-ar="سجل الصيانة والإصلاحات">
              Service & Repair Registry
            </h2>
          </div>

          <!-- Search Box -->
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search ticket, asset, type..." data-en-placeholder="Search ticket, asset, type..." data-ar-placeholder="ابحث عن التذكرة، الأصل، النوع..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
        </div>

        <!-- Underline Tabs -->
        <div class="flex items-center gap-6 border-t border-[#1a3346] pt-3 text-xs font-mono">
          <button type="button" class="tab-btn active pb-2 -mb-3 border-b-2 border-orange-500 font-semibold text-white flex items-center gap-1.5 cursor-pointer" data-tab="active">
            <span data-en="Active Maintenance" data-ar="الصيانة النشطة">Active Maintenance</span>
            <span class="px-1.5 py-0.2 rounded-xs bg-[#112433] text-[10px] text-cyan-400" id="countActive"><?= $tab_counts['active'] ?></span>
          </button>
          <button type="button" class="tab-btn pb-2 -mb-3 border-b-2 border-transparent text-slate-400 hover:text-white flex items-center gap-1.5 cursor-pointer transition-colors" data-tab="history">
            <span data-en="Service History" data-ar="سجل الخدمات">Service History</span>
            <span class="px-1.5 py-0.2 rounded-xs bg-[#112433] text-[10px] text-slate-300" id="countHistory"><?= $tab_counts['history'] ?></span>
          </button>
          <button type="button" class="tab-btn pb-2 -mb-3 border-b-2 border-transparent text-slate-400 hover:text-white flex items-center gap-1.5 cursor-pointer transition-colors" data-tab="fuel">
            <span data-en="Fuel Logs" data-ar="سجلات الوقود">Fuel Logs</span>
            <span class="px-1.5 py-0.2 rounded-xs bg-[#112433] text-[10px] text-slate-300" id="countFuel"><?= $tab_counts['fuel'] ?></span>
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-start text-xs border-collapse" id="dataTable">
          <thead>
            <tr class="border-b border-[#1a3346] bg-[#09151f] text-slate-400 font-mono uppercase text-[10px] tracking-wider">
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Ticket / Ref ID" data-ar="معرف التذكرة">Ticket / Ref ID</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Target Carrier Unit" data-ar="المركبة المستهدفة">Target Carrier Unit</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Classification" data-ar="تصنيف الخدمة">Classification</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Scheduled Date" data-ar="التاريخ المجدول">Scheduled Date</th>
              <th class="py-2.5 px-3 text-end font-semibold" data-en="Cost (SAR)" data-ar="التكلفة (ريال)">Cost (SAR)</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Status" data-ar="الحالة">Status</th>
              <th class="py-2.5 px-3 text-end font-semibold" data-en="Actions" data-ar="الإجراءات">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a]">
            <?php if (empty($maintenance_records)): ?>
              <tr class="empty-state">
                <td colspan="7" class="py-8 text-center text-neutral-500 font-mono text-xs" data-en="No maintenance or workshop records found." data-ar="لم يتم العثور على سجلات صيانة أو تذاكر ورشة.">
                  No maintenance or workshop records found.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($maintenance_records as $r): ?>
                <?php
                  $s = $statusStyles[$r['status']] ?? $statusStyles['scheduled'];
                ?>
                <tr class="filterable-row hover:bg-[#102332] transition-colors" data-tab="<?= $r['tab'] ?>" data-search="<?= strtolower(htmlspecialchars($r['id'] . ' ' . $r['vehicle'] . ' ' . $r['model'] . ' ' . $r['type_en'] . ' ' . $r['type_ar'])) ?>">
                  <td class="py-2.5 px-3 font-mono font-medium text-white">
                    <?= htmlspecialchars($r['id']) ?>
                  </td>
                  <td class="py-2.5 px-3">
                    <div class="font-mono font-semibold text-white">
                      <?= htmlspecialchars($r['vehicle']) ?>
                    </div>
                    <div class="text-[11px] text-slate-400">
                      <?= htmlspecialchars($r['model']) ?>
                    </div>
                  </td>
                  <td class="py-2.5 px-3 font-medium text-slate-200">
                    <span data-en="<?= htmlspecialchars($r['type_en']) ?>" data-ar="<?= htmlspecialchars($r['type_ar']) ?>"><?= htmlspecialchars($r['type_en']) ?></span>
                  </td>
                  <td class="py-2.5 px-3 font-mono text-slate-400">
                    <?= htmlspecialchars(date('Y-m-d', strtotime($r['date']))) ?>
                  </td>
                  <td class="py-2.5 px-3 text-end font-mono text-white font-medium">
                    <?= number_format($r['cost'], 2) ?>
                  </td>
                  <td class="py-2.5 px-3">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-mono uppercase tracking-wider rounded-xs border <?= $s['bg'] ?> <?= $s['text'] ?> <?= $s['border'] ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?= $s['dot'] ?>"></span>
                      <span data-en="<?= $s['label_en'] ?>" data-ar="<?= $s['label_ar'] ?>"><?= $s['label_en'] ?></span>
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-end">
                    <div class="inline-flex items-center justify-end gap-1.5">
                      <?php if ($r['is_ticket']): ?>
                        <?php if ($is_admin && !in_array($r['status'], ['completed', 'cancelled'])): ?>
                        <form method="POST" action="" class="inline-block m-0" onsubmit="return confirm('Mark this maintenance ticket as completed? / هل تريد تحديد هذه التذكرة كمكتملة؟');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="complete">
                          <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($r['id']) ?>">
                          <button type="submit" class="btn-table-action hover:!border-emerald-500 hover:!text-emerald-400 whitespace-nowrap" title="Complete Service Ticket">
                            <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                          </button>
                        </form>
                        <?php endif; ?>

                        <a href="maintenance_details.php?id=<?= urlencode($r['id']) ?>" class="btn-table-action whitespace-nowrap" title="View Work Order">
                          <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        </a>

                        <?php if ($is_admin): ?>
                        <form method="POST" action="" class="inline-block m-0" onsubmit="return confirm('Are you sure you want to delete this ticket? / هل أنت متأكد أنك تريد حذف هذه التذكرة؟');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($r['id']) ?>">
                          <button type="submit" class="btn-table-action hover:!border-rose-500 hover:!text-rose-400 whitespace-nowrap" title="Delete Ticket">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                          </button>
                        </form>
                        <?php endif; ?>
                      <?php else: ?>
                        <a href="vehicle_logs.php?id=<?= urlencode($r['vehicle']) ?>" class="btn-table-action whitespace-nowrap" title="View Fuel Log History">
                          <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                        </a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            <tr id="noResultsRow" style="display: none;">
              <td colspan="7" class="py-8 text-center text-neutral-500 font-mono text-xs" data-en="No records available for the selected filter." data-ar="لا توجد سجلات مطابقة للفلتر المحدد.">
                No records available for the selected filter.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Footer -->
      <div class="p-4 border-t border-[#1a3346] bg-[#071018] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs font-mono text-neutral-500">
        <div>
          <span data-en="Showing" data-ar="عرض">Showing</span> <span id="visibleCount" class="font-semibold text-white"><?= count($maintenance_records) ?></span> <span data-en="operational entries" data-ar="سجلات تشغيلية">operational entries</span>
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
    const tabBtns = document.querySelectorAll('.tab-btn');
    const rows = document.querySelectorAll('.filterable-row');
    const visibleCountElem = document.getElementById('visibleCount');
    const noResultsRow = document.getElementById('noResultsRow');
    let currentTab = 'active';

    function filterTable() {
      const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
      let visibleCount = 0;

      rows.forEach(row => {
        const rowTab = row.getAttribute('data-tab');
        const searchData = row.getAttribute('data-search') || '';
        
        const matchesSearch = !query || searchData.includes(query);
        const matchesTab = (rowTab === currentTab);

        if (matchesSearch && matchesTab) {
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

    // Initial filter
    filterTable();
  });
</script>
</body>
</html>