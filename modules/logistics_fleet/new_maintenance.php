<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'logistics.create');

$active_page = 'maintenance_fuel';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Maintenance & Fuel', 'New Service Ticket'];

$error_msg = '';

try {
  $stmt_seq = $pdo->query("SELECT COUNT(id) FROM maintenance_tickets");
  $count = $stmt_seq->fetchColumn();
  $new_ticket_id = 'MNT-' . str_pad($count + 4025, 4, '0', STR_PAD_LEFT);

  $stmt_vehicles = $pdo->query("SELECT fleet_id, make_model, plate_number FROM vehicles ORDER BY fleet_id ASC");
  $vehicles = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $vehicles = [];
  $new_ticket_id = 'MNT-' . rand(4025, 4999);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $ticket_id = $_POST['ticket_id'] ?? $new_ticket_id;
  $service_type = trim($_POST['service_type'] ?? '');
  $vehicle_id = $_POST['vehicle_id'] ?? '';
  $odometer = isset($_POST['odometer']) && $_POST['odometer'] !== '' ? (int)$_POST['odometer'] : null;
  $scheduled_date = $_POST['scheduled_date'] ?? '';
  $estimated_cost = !empty($_POST['estimated_cost']) ? (float)$_POST['estimated_cost'] : 0;
  $workshop = trim($_POST['workshop'] ?? '');
  $instructions = trim($_POST['instructions'] ?? '');

  if (empty($service_type) || empty($vehicle_id) || $odometer === null || empty($scheduled_date) || empty($workshop)) {
    $error_msg = "Please fill in all required fields (Service Type, Vehicle, Odometer, Date, Workshop).";
  } else {
    try {
      $pdo->beginTransaction();

      $stmt = $pdo->prepare("
        INSERT INTO maintenance_tickets (ticket_id, service_type, vehicle_id, odometer, scheduled_date, estimated_cost, workshop, instructions)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$ticket_id, $service_type, $vehicle_id, $odometer, $scheduled_date, $estimated_cost, $workshop, $instructions]);

      $stmt_v = $pdo->prepare("UPDATE vehicles SET status = 'maintenance' WHERE fleet_id = ?");
      $stmt_v->execute([$vehicle_id]);

      $log_description = "Service ticket {$ticket_id} ({$service_type}) scheduled at {$workshop}.";
      if (!empty($instructions)) {
        $log_description .= " Notes: {$instructions}";
      }

      $stmt_log = $pdo->prepare("
        INSERT INTO vehicle_logs (fleet_id, event_type, event_date, event_time, odometer, fuel_liters, event_cost, logged_by, description)
        VALUES (?, 'maintenance', ?, ?, ?, 0, ?, ?, ?)
      ");
      $stmt_log->execute([
        $vehicle_id,
        $scheduled_date,
        date('H:i:s'),
        $odometer,
        $estimated_cost,
        $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System',
        $log_description
      ]);

      $pdo->commit();
      header("Location: maintenance_fuel.php");
      exit;
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $error_msg = "Error creating ticket: " . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="New Service Ticket | I-GAS Control Room" data-ar="تذكرة صيانة جديدة | غرفة التحكم I-GAS">New Service Ticket | I-GAS Control Room</title>
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
        <span data-en="WORK ORDER CREATION PROTOCOL" data-ar="بروتوكول فتح أمر عمل صيانة">WORK ORDER CREATION PROTOCOL</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="ASSIGNED TICKET ID:" data-ar="معرف التذكرة:">ASSIGNED TICKET ID:</span> <strong class="text-white font-mono"><?= htmlspecialchars($new_ticket_id) ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="DATE:" data-ar="التاريخ:">DATE:</span> <strong class="text-white"><?= date('Y-m-d') ?></strong>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: MAINT-LEVEL-2</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 w-full mx-auto space-y-6">

    <!-- Breadcrumbs & Title -->
    <div>
      <div class="flex items-center gap-2 text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
        <a href="maintenance_fuel.php" class="hover:text-white transition-colors" data-en="Maintenance Registry" data-ar="سجل الصيانة">Maintenance Registry</a>
        <span>/</span>
        <span class="text-white font-semibold" data-en="New Ticket" data-ar="تذكرة جديدة">New Ticket</span>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white flex items-center gap-2">
            <i data-lucide="wrench" class="w-6 h-6 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Create Service Ticket" data-ar="إنشاء تذكرة صيانة للأسطول">Create Service Ticket</span>
          </h1>
          <p class="text-sm text-slate-400 mt-1" data-en="Schedule vehicle maintenance, log workshop repairs, and forecast service costs." data-ar="جدولة صيانة الشاحنات ومركبات الأسطول، وتحديد الورشة وتكاليف الإصلاح المتوقعة.">
            Schedule vehicle maintenance, log workshop repairs, and forecast service costs.
          </p>
        </div>
        <div>
          <a href="maintenance_fuel.php" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700/50 rounded-xs transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span data-en="Back to Registry" data-ar="العودة لسجل الصيانة">Back to Registry</span>
          </a>
        </div>
      </div>
    </div>

    <?php if ($error_msg): ?>
      <div class="p-4 border border-rose-800 dark:border-rose-900 bg-rose-950/30 dark:bg-rose-950/30 text-rose-300 dark:text-rose-300 rounded-xs flex items-center gap-3 text-sm">
        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
        <div>
          <strong class="font-mono text-xs uppercase" data-en="Submission Error:" data-ar="خطأ في الإدخال:">Submission Error:</strong> <?= htmlspecialchars($error_msg) ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Form Card -->
    <form action="" method="POST" class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
      <?= csrf_field() ?>
      <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($new_ticket_id) ?>">

      <!-- Section 1: Ticket Initialization -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="tag" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Ticket Metadata & Classification" data-ar="بيانات التذكرة والتصنيف">Ticket Metadata & Classification</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Ticket Job ID" data-ar="معرف التذكرة المخصص">
              Ticket Job ID
            </label>
            <input type="text" class="w-full h-10 px-3 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-xs text-neutral-500 cursor-not-allowed" value="<?= htmlspecialchars($new_ticket_id) ?>" readonly>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Service Classification *" data-ar="نوع الخدمة والصيانة *">
              Service Classification *
            </label>
            <select name="service_type" required class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <option value="" disabled selected data-en="-- Select service classification --" data-ar="-- اختر تصنيف الصيانة المطلوبة --">-- Select service classification --</option>
              <option value="preventive" data-en="Preventive Maintenance (PM)" data-ar="صيانة دورية وقائية (PM)">Preventive Maintenance (PM)</option>
              <option value="repair" data-en="Corrective Repair / Overhaul" data-ar="إصلاح عطل فني / عمرة">Corrective Repair / Overhaul</option>
              <option value="inspection" data-en="Safety & Pressure Valve Inspection" data-ar="فحص السلامة وصمامات الضغط">Safety & Pressure Valve Inspection</option>
              <option value="tires" data-en="Tire Replacement / Wheel Balancing" data-ar="استبدال إطارات وضبط ميزان">Tire Replacement / Wheel Balancing</option>
              <option value="oil" data-en="Oil & Filter Fluid Change" data-ar="تغيير زيت وفلاتر وسوائل">Oil & Filter Fluid Change</option>
              <option value="electrical" data-en="Cryo Electrical & Sensor Diagnostics" data-ar="فحص كهربائي وحساسات الخزان">Cryo Electrical & Sensor Diagnostics</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Section 2: Asset Details -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Target Carrier Asset" data-ar="المركبة المستهدفة للصيانة">Target Carrier Asset</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Select Fleet Unit *" data-ar="اختر شاحنة الأسطول *">
              Select Fleet Unit *
            </label>
            <select name="vehicle_id" required class="w-full h-10 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <option value="" disabled selected data-en="-- Select Vehicle Fleet ID --" data-ar="-- اختر المركبة من الأسطول --">-- Select Vehicle Fleet ID --</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= htmlspecialchars($v['fleet_id']) ?>">
                  <?= htmlspecialchars($v['fleet_id']) ?> (<?= htmlspecialchars($v['make_model']) ?> — <?= htmlspecialchars($v['plate_number']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Current Odometer (KM) *" data-ar="قراءة العداد الحالية (كم) *">
              Current Odometer (KM) *
            </label>
            <div class="relative">
              <input type="number" name="odometer" placeholder="e.g. 145000" required class="w-full h-10 ps-3 pe-12 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <span class="absolute end-3 top-2.5 text-xs font-mono text-neutral-400 pointer-events-none">KM</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 3: Scheduling & Workshop Financials -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="calendar-clock" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Scheduling & Cost Forecast" data-ar="الجدولة الزمنية وتوقع التكلفة">Scheduling & Cost Forecast</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Scheduled Calendar Date *" data-ar="تاريخ الموعد المجدول *">
              Scheduled Calendar Date *
            </label>
            <input type="date" name="scheduled_date" value="<?= date('Y-m-d') ?>" required class="w-full h-10 px-3 text-xs font-mono bg-[#0c1924] border border-[#1e3a50] rounded-xs text-white focus:border-neutral-900 dark:focus:border-neutral-100 outline-none transition-colors">
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Estimated Cost (SAR) *" data-ar="التكلفة التقديرية (ريال) *">
              Estimated Cost (SAR) *
            </label>
            <div class="relative">
              <input type="number" step="0.01" name="estimated_cost" placeholder="0.00" required class="w-full h-10 ps-3 pe-12 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <span class="absolute end-3 top-2.5 text-xs font-mono text-neutral-400 pointer-events-none">SAR</span>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Assigned Workshop / Bay Name *" data-ar="اسم الورشة أو المسار المعين *">
              Assigned Workshop / Bay Name *
            </label>
            <input type="text" name="workshop" placeholder="e.g. Central Plant Garage Bay 2, MAN Truck Center Jeddah..." required class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
          </div>
        </div>
      </div>

      <!-- Section 4: Service Instructions -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="clipboard-list" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Reported Issues & Work Instructions" data-ar="المشاكل المبلغ عنها وتعليمات الفحص">Reported Issues & Work Instructions</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 gap-6">
          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Diagnostic Instructions & Part Details" data-ar="تعليمات التشخيص وتفاصيل القطع">
              Diagnostic Instructions & Part Details
            </label>
            <textarea name="instructions" rows="4" placeholder="Detail the symptoms, required part replacements, or standard operating procedures for this service ticket..." class="w-full p-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors"></textarea>
          </div>
        </div>
      </div>

      <!-- Form Actions Bar -->
      <div class="p-4 bg-[#09151f] flex items-center justify-between">
        <a href="maintenance_fuel.php" class="px-4 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 hover:text-white transition-colors" data-en="Discard" data-ar="إلغاء">
          Discard
        </a>
        <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-6 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span data-en="Authorize & Schedule Ticket" data-ar="اعتماد وجدولة تذكرة الصيانة">Authorize & Schedule Ticket</span>
        </button>
      </div>
    </form>

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