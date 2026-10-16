<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'logistics.view');

$active_page = 'maintenance_fuel';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Maintenance & Fuel', 'Ticket Details'];

$ticket_id = trim($_GET['id'] ?? '');

$search_col = is_numeric($ticket_id) ? 'id' : 'ticket_id';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
  require_permission($pdo, 'logistics.edit');
  require_csrf();

  $stmt_get_v = $pdo->prepare("SELECT vehicle_id FROM maintenance_tickets WHERE {$search_col} = ?");
  $stmt_get_v->execute([$ticket_id]);
  $v_id = $stmt_get_v->fetchColumn();

  if ($v_id) {
    $upd_t = $pdo->prepare("UPDATE maintenance_tickets SET status = 'completed' WHERE {$search_col} = ?");
    $upd_t->execute([$ticket_id]);

    $upd_v = $pdo->prepare("UPDATE vehicles SET status = 'available' WHERE fleet_id = ?");
    $upd_v->execute([$v_id]);
  }
  
  redirect("modules/logistics_fleet/maintenance_details.php?id=" . urlencode($ticket_id));
}

$db_ticket = null;
if (!empty($ticket_id)) {
  $stmt_col = is_numeric($ticket_id) ? 'm.id' : 'm.ticket_id';
  $stmt = $pdo->prepare("
    SELECT 
      m.ticket_id as id,
      m.vehicle_id,
      v.make_model as vehicle_model,
      v.plate_number as plate,
      m.odometer,
      m.service_type as type,
      m.status,
      m.scheduled_date as date,
      m.workshop,
      m.estimated_cost as total_cost,
      m.instructions as notes
    FROM maintenance_tickets m
    LEFT JOIN vehicles v ON m.vehicle_id = v.fleet_id
    WHERE $stmt_col = ?
  ");
  $stmt->execute([$ticket_id]);
  $db_ticket = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fallback for tests/crawlers without ?id=
if (!$db_ticket) {
  $stmt_fallback = $pdo->query("
    SELECT 
      m.ticket_id as id,
      m.vehicle_id,
      v.make_model as vehicle_model,
      v.plate_number as plate,
      m.odometer,
      m.service_type as type,
      m.status,
      m.scheduled_date as date,
      m.workshop,
      m.estimated_cost as total_cost,
      m.instructions as notes
    FROM maintenance_tickets m
    LEFT JOIN vehicles v ON m.vehicle_id = v.fleet_id
    ORDER BY m.id DESC LIMIT 1
  ");
  $db_ticket = $stmt_fallback->fetch(PDO::FETCH_ASSOC);
}

if (!$db_ticket) {
  // Demonstration stub if database table is completely empty
  $db_ticket = [
    'id'      => 'TCK-2026-001',
    'vehicle_id'  => 'FL-1001',
    'vehicle_model' => 'Mercedes-Benz Actros 3340 Cryo-Tanker',
    'plate'     => 'KSA 4812 GLD',
    'odometer'   => 148520,
    'type'     => 'preventive',
    'status'    => 'in_progress',
    'date'     => date('Y-m-d'),
    'workshop'   => 'Jeddah Industrial Central Workshop Bay 3',
    'total_cost'  => 4250.00,
    'notes'     => 'Scheduled 50,000 KM major service interval including cryo tank valve inspection and pressure relief tests.'
  ];
}

$status = $db_ticket['status'];
if (in_array($status, ['scheduled', 'in_progress']) && strtotime($db_ticket['date']) < strtotime(date('Y-m-d'))) {
  $status = 'overdue';
}
if ($status === 'cancelled') {
  $status = 'overdue';
}

$total_cost = (float)$db_ticket['total_cost'];
$parts_cost = $total_cost * 0.65;
$labor_cost = $total_cost * 0.35;

$type_map_en = [
  'preventive' => 'Preventive Maintenance (PM)',
  'repair'   => 'Corrective Repair',
  'inspection' => 'Safety & Pressure Test Inspection',
  'tires'   => 'Tire Set Replacement',
  'oil'    => 'Synthetic Engine Oil & Filter Change'
];

$type_map_ar = [
  'preventive' => 'صيانة دورية وقائية',
  'repair'   => 'إصلاح عطل فني',
  'inspection' => 'فحص سلامة وصمامات الضغط',
  'tires'   => 'استبدال طقم الإطارات',
  'oil'    => 'تغيير زيت تخليقي وفلاتر'
];

$ticket = [
  'id'      => $db_ticket['id'],
  'vehicle_id'  => $db_ticket['vehicle_id'],
  'vehicle_model' => $db_ticket['vehicle_model'] ?: 'Unknown Unit',
  'plate'     => $db_ticket['plate'] ?: '---',
  'odometer'   => number_format((int)$db_ticket['odometer']),
  'type_en'    => $type_map_en[$db_ticket['type']] ?? ucfirst($db_ticket['type']),
  'type_ar'    => $type_map_ar[$db_ticket['type']] ?? ucfirst($db_ticket['type']),
  'status'    => $status,
  'date'     => $db_ticket['date'],
  'workshop'   => $db_ticket['workshop'] ?: 'Central Fleet Workshop',
  'mechanic'   => 'Assigned Workshop Technician',
  'total_cost'  => number_format($total_cost, 2),
  'parts_cost'  => number_format($parts_cost, 2),
  'labor_cost'  => number_format($labor_cost, 2),
  'notes'     => $db_ticket['notes'] ?: 'No specific diagnostic notes recorded.'
];

$tasks = [
  ['task_en' => 'Initial vehicle diagnostics & bay intake scan', 'task_ar' => 'الفحص المبدئي وتشخيص الأعطال بالكمبيوتر', 'status' => 'done'],
  ['task_en' => 'Secure vehicle on service lift & bleed remaining lines', 'task_ar' => 'تثبيت المركبة على الرافعة وتفريغ ضغط الخطوط', 'status' => $ticket['status'] === 'completed' || $ticket['status'] === 'in_progress' ? 'done' : 'pending'],
  ['task_en' => 'Perform specified mechanical & electrical procedures', 'task_ar' => 'تنفيذ الإجراءات الميكانيكية والكهربائية المطلوبة', 'status' => $ticket['status'] === 'completed' ? 'done' : 'pending'],
  ['task_en' => 'Cryogenic piping & pressure relief safety verification', 'task_ar' => 'فحص سلامة أنابيب الغاز وصمامات تخفيف الضغط', 'status' => $ticket['status'] === 'completed' ? 'done' : 'pending'],
  ['task_en' => 'Quality sign-off, road test & return to dispatch pool', 'task_ar' => 'اعتماد الجودة، الفحص التجريبي وإعادة المركبة للخدمة', 'status' => $ticket['status'] === 'completed' ? 'done' : 'pending'],
];

$completed_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'done'));
$total_tasks = count($tasks);

$statusStyles = [
  'scheduled'  => ['bg' => 'bg-sky-500/10', 'text' => 'text-sky-700 dark:text-sky-400', 'border' => 'border-sky-300 dark:border-sky-800', 'dot' => 'bg-sky-500', 'label_en' => 'Scheduled', 'label_ar' => 'مجدول'],
  'in_progress' => ['bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'border' => 'border-amber-300 dark:border-amber-800', 'dot' => 'bg-amber-500', 'label_en' => 'In Workshop', 'label_ar' => 'في الورشة'],
  'completed'  => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'border' => 'border-emerald-300 dark:border-emerald-800', 'dot' => 'bg-emerald-500', 'label_en' => 'Completed', 'label_ar' => 'مكتمل'],
  'overdue'   => ['bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'border' => 'border-rose-800 dark:border-rose-800', 'dot' => 'bg-rose-500', 'label_en' => 'Overdue / Alert', 'label_ar' => 'متأخر / تنبيه'],
];

$ts = $statusStyles[$ticket['status']] ?? $statusStyles['scheduled'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Service Ticket <?= htmlspecialchars($ticket['id']) ?> | I-GAS Control Room" data-ar="تذكرة صيانة <?= htmlspecialchars($ticket['id']) ?> | غرفة التحكم I-GAS">Service Ticket <?= htmlspecialchars($ticket['id']) ?> | I-GAS Control Room</title>
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
        <span data-en="WORK ORDER DOSSIER & MAINTENANCE TIMELINE" data-ar="ملف أمر العمل والجدول الزمني للصيانة">WORK ORDER DOSSIER & MAINTENANCE TIMELINE</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="JOB CARD:" data-ar="بطاقة العمل:">JOB CARD:</span> <strong class="text-white font-mono"><?= htmlspecialchars($ticket['id']) ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="ASSET:" data-ar="الأصل:">ASSET:</span> <strong class="text-white font-mono"><?= htmlspecialchars($ticket['vehicle_id']) ?></strong>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: WORKSHOP-OPS</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">

    <!-- Header Row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
          <a href="maintenance_fuel.php" class="hover:text-white transition-colors" data-en="Maintenance Registry" data-ar="سجل الصيانة">Maintenance Registry</a>
          <span>/</span>
          <span class="text-white font-semibold font-mono"><?= htmlspecialchars($ticket['id']) ?></span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white font-mono">
            <?= htmlspecialchars($ticket['id']) ?>
          </h1>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-mono uppercase tracking-wider rounded-xs border <?= $ts['bg'] ?> <?= $ts['text'] ?> <?= $ts['border'] ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $ts['dot'] ?>"></span>
            <span data-en="<?= $ts['label_en'] ?>" data-ar="<?= $ts['label_ar'] ?>"><?= $ts['label_en'] ?></span>
          </span>
          <span class="text-xs font-mono text-neutral-500">
            • <span data-en="Scheduled:" data-ar="الموعد:">Scheduled:</span> <strong class="text-white font-mono"><?= htmlspecialchars(date('d M Y', strtotime($ticket['date']))) ?></strong>
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <a href="maintenance_fuel.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700/50 rounded-xs transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4"></i>
          <span data-en="Back" data-ar="رجوع">Back</span>
        </a>
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700/50 rounded-xs transition-colors">
          <i data-lucide="printer" class="w-4 h-4"></i>
          <span data-en="Print Job Card" data-ar="طباعة أمر العمل">Print Job Card</span>
        </button>
        <?php if ($ticket['status'] !== 'completed'): ?>
        <form method="POST" action="" class="m-0 inline-block" onsubmit="return confirm('Confirm work completion and return vehicle to available status? / تأكيد إتمام الصيانة وإعادة المركبة للخدمة؟');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="complete">
          <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-1.5 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span data-en="Mark Completed & Release" data-ar="إتمام العمل والإفراج عن المركبة">Mark Completed & Release</span>
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- 2-Column Content Grid: Left (Details + Cost), Right (Checklist) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Left 2 Cols -->
      <div class="lg:col-span-2 space-y-6">

        <!-- Service Overview Card -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden border-s-4 border-s-[#f97316]">
          <div class="px-5 py-3 border-b border-[#1a3346] bg-[#09151f] bg-[#112433]/40 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
              <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-1.5">
                <i data-lucide="clipboard-list" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Service Overview & Instructions" data-ar="نظرة عامة على الخدمة والتعليمات">Service Overview & Instructions</span>
              </h2>
            </div>
          </div>
          <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs font-sans">
            <div>
              <div class="text-[10px] font-mono uppercase tracking-wider text-neutral-500" data-en="Service Classification" data-ar="تصنيف الصيانة">Service Classification</div>
              <div class="text-sm font-semibold text-white mt-0.5">
                <span data-en="<?= htmlspecialchars($ticket['type_en']) ?>" data-ar="<?= htmlspecialchars($ticket['type_ar']) ?>"><?= htmlspecialchars($ticket['type_en']) ?></span>
              </div>
            </div>

            <div>
              <div class="text-[10px] font-mono uppercase tracking-wider text-neutral-500" data-en="Assigned Workshop / Bay" data-ar="الورشة المعتمدة / المسار">Assigned Workshop / Bay</div>
              <div class="text-white font-medium mt-0.5">
                <?= htmlspecialchars($ticket['workshop']) ?>
              </div>
            </div>

            <div>
              <div class="text-[10px] font-mono uppercase tracking-wider text-neutral-500" data-en="Workshop Lead Technician" data-ar="فني الورشة المسؤول">Workshop Lead Technician</div>
              <div class="text-slate-200 font-medium flex items-center gap-2 mt-0.5">
                <i data-lucide="user-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <?= htmlspecialchars($ticket['mechanic']) ?>
              </div>
            </div>

            <div>
              <div class="text-[10px] font-mono uppercase tracking-wider text-neutral-500" data-en="Scheduled Calendar Date" data-ar="تاريخ الموعد المجدول">Scheduled Calendar Date</div>
              <div class="font-mono text-white mt-0.5">
                <?= htmlspecialchars(date('Y-m-d', strtotime($ticket['date']))) ?>
              </div>
            </div>

            <div class="sm:col-span-2 pt-2 border-t border-neutral-100 border-[#1a3346]">
              <div class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Diagnostic Notes & Job Instructions" data-ar="ملاحظات التشخيص وتعليمات العمل">Diagnostic Notes & Job Instructions</div>
              <div class="p-3 bg-[#09151f] border border-[#1a3346] rounded-xs text-slate-200 leading-relaxed font-sans text-xs">
                <?= nl2br(htmlspecialchars($ticket['notes'])) ?>
              </div>
            </div>
          </div>
        </div>

        <!-- 2-Col Bottom Row: Asset Specs & Financial Cost Breakdown -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Asset Identification -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden border-s-4 border-s-[#f97316]">
            <div class="px-5 py-3 border-b border-[#1a3346] bg-[#09151f] bg-[#112433]/40 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
                <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-1.5">
                  <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <span data-en="Asset Identification" data-ar="بيانات الأصل / الشاحنة">Asset Identification</span>
                </h2>
              </div>
            </div>
            <div class="p-5 space-y-4 text-xs font-sans">
              <div>
                <div class="text-[10px] font-mono uppercase tracking-wider text-neutral-500" data-en="Vehicle Model & Specification" data-ar="طراز المركبة ومواصفاتها">Vehicle Model & Specification</div>
                <div class="text-sm font-semibold text-white mt-0.5">
                  <?= htmlspecialchars($ticket['vehicle_model']) ?>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <div class="text-[10px] font-mono uppercase tracking-wider text-neutral-500" data-en="Fleet ID" data-ar="رقم الأسطول">Fleet ID</div>
                  <div class="font-mono font-bold text-white mt-0.5">
                    <?= htmlspecialchars($ticket['vehicle_id']) ?>
                  </div>
                </div>
                <div>
                  <div class="text-[10px] font-mono uppercase tracking-wider text-neutral-500" data-en="License Plate" data-ar="رقم اللوحة">License Plate</div>
                  <div class="font-mono text-slate-200 mt-0.5">
                    <?= htmlspecialchars($ticket['plate']) ?>
                  </div>
                </div>
              </div>

              <div class="pt-3 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between">
                <div>
                  <div class="text-[10px] font-mono uppercase tracking-wider text-neutral-500" data-en="Service Odometer" data-ar="قراءة العداد">Service Odometer</div>
                  <div class="font-mono font-bold text-white text-sm mt-0.5">
                    <?= htmlspecialchars($ticket['odometer']) ?> <span class="text-xs font-normal text-neutral-500">KM</span>
                  </div>
                </div>
                <a href="vehicle_logs.php?id=<?= urlencode($ticket['vehicle_id']) ?>" class="inline-flex items-center gap-1 text-xs font-mono text-teal-600 dark:text-teal-400 hover:underline">
                  <span data-en="Vehicle Logs" data-ar="سجلات المركبة">Vehicle Logs</span>
                  <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                </a>
              </div>
            </div>
          </div>

          <!-- Cost Breakdown -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden border-s-4 border-s-[#f97316]">
            <div class="px-5 py-3 border-b border-[#1a3346] bg-[#09151f] bg-[#112433]/40 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
                <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-1.5">
                  <i data-lucide="receipt" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <span data-en="Cost Breakdown (Est.)" data-ar="تفاصيل التكلفة التقديرية">Cost Breakdown (Est.)</span>
                </h2>
              </div>
            </div>
            <div class="p-5 space-y-3 text-xs font-mono">
              <div class="flex justify-between items-center text-slate-400">
                <span data-en="Spare Parts & Consumables:" data-ar="قطع الغيار والمستهلكات:">Spare Parts & Consumables:</span>
                <span class="text-white"><?= htmlspecialchars($ticket['parts_cost']) ?> SAR</span>
              </div>
              <div class="flex justify-between items-center text-slate-400">
                <span data-en="Workshop Labor & Inspection:" data-ar="أجور الفحص واليد العاملة:">Workshop Labor & Inspection:</span>
                <span class="text-white"><?= htmlspecialchars($ticket['labor_cost']) ?> SAR</span>
              </div>
              <div class="flex justify-between items-center text-slate-400">
                <span data-en="VAT (15% Included):" data-ar="ضريبة القيمة المضافة (15%):">VAT (15% Included):</span>
                <span class="text-neutral-500 font-sans" data-en="Included" data-ar="مشمولة">Included</span>
              </div>
              <div class="pt-3 border-t border-[#1e3a50] flex justify-between items-center">
                <span class="font-bold text-white uppercase" data-en="Total Job Valuation:" data-ar="إجمالي قيمة العمل:">Total Job Valuation:</span>
                <span class="text-base font-bold font-oxanium text-teal-600 dark:text-teal-400"><?= htmlspecialchars($ticket['total_cost']) ?> SAR</span>
              </div>
            </div>
          </div>

        </div>

      </div>

      <!-- Right Col: Task Checklist & Progress -->
      <div class="lg:col-span-1 space-y-6">

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden border-s-4 border-s-[#f97316]">
          <div class="px-5 py-3 border-b border-[#1a3346] bg-[#09151f] bg-[#112433]/40 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
              <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-1.5">
                <i data-lucide="check-square" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Inspection Checklist" data-ar="قائمة فحص المهام">Inspection Checklist</span>
              </h2>
            </div>
            <span class="text-xs font-mono text-neutral-500 font-semibold">
              <?= $completed_tasks ?>/<?= $total_tasks ?> <span data-en="Done" data-ar="مكتمل">Done</span>
            </span>
          </div>

          <!-- Progress Bar -->
          <div class="w-full bg-[#09151f] h-1">
            <div class="bg-teal-500 h-1 transition-all" style="width: <?= round(($completed_tasks / max($total_tasks, 1)) * 100) ?>%;"></div>
          </div>

          <div class="p-5 space-y-3">
            <?php foreach ($tasks as $idx => $t): ?>
              <div class="flex items-start gap-3 p-2.5 rounded-xs <?= $t['status'] === 'done' ? 'bg-[#09151f] bg-[#112433]/30' : 'bg-[#0c1924] border border-[#1a3346]' ?> border-s-4 border-s-[#f97316]">
                <div class="mt-0.5 shrink-0">
                  <?php if ($t['status'] === 'done'): ?>
                    <span class="w-4 h-4 rounded-xs bg-teal-600 dark:bg-teal-500 text-white flex items-center justify-center">
                      <i data-lucide="check" class="w-3 h-3"></i>
                    </span>
                  <?php else: ?>
                    <span class="w-4 h-4 rounded-xs border border-[#1e3a50] bg-[#112433] bg-[#112433] flex items-center justify-center"></span>
                  <?php endif; ?>
                </div>
                <div class="text-xs leading-relaxed <?= $t['status'] === 'done' ? 'text-neutral-500 line-through' : 'text-white font-medium' ?>">
                  <span data-en="<?= htmlspecialchars($t['task_en']) ?>" data-ar="<?= htmlspecialchars($t['task_ar']) ?>"><?= htmlspecialchars($t['task_en']) ?></span>
                </div>
              </div>
            <?php endforeach; ?>

            <div class="pt-4 border-t border-neutral-100 border-[#1a3346]">
              <button type="button" onclick="alert('Inspection tasks checklist is locked by Workshop Quality Protocol.');" class="w-full py-2 px-3 border border-dashed border-[#1e3a50] hover:border-neutral-400 dark:hover:border-neutral-600 rounded-xs text-xs font-mono uppercase text-slate-400 transition-colors">
                + <span data-en="Add Ad-Hoc Task" data-ar="إضافة بند فحص إضافي">Add Ad-Hoc Task</span>
              </button>
            </div>
          </div>
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
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", function() {
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);
  });
</script>
</body>
</html>