<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'logistics.create');

$active_page = 'vehicles_fleet';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Vehicles Fleet', 'Manual Log Entry'];

$vehicle_id = $_GET['id'] ?? '';
$error_msg = '';

try {
  $stmt_vehicles = $pdo->query("SELECT fleet_id, plate_number, make_model FROM vehicles ORDER BY fleet_id ASC");
  $vehicles = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $vehicles = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $fleet_id = $_POST['vehicle_id'] ?? '';
  $event_type = $_POST['event_type'] ?? '';
  $event_date = $_POST['event_date'] ?? '';
  $event_time = $_POST['event_time'] ?? '';
  $odometer = (isset($_POST['odometer']) && $_POST['odometer'] !== '') ? (int)$_POST['odometer'] : null;
  $fuel_liters = !empty($_POST['fuel_liters']) ? (float)$_POST['fuel_liters'] : 0.00;
  $event_cost = !empty($_POST['event_cost']) ? (float)$_POST['event_cost'] : 0.00;
  $logged_by = trim($_POST['logged_by'] ?? '');
  $description = trim($_POST['description'] ?? '');

  if (empty($fleet_id) || empty($event_type) || empty($event_date) || empty($event_time) || $odometer === null) {
    $error_msg = "Please fill in all required fields, including the odometer reading.";
  } else {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO vehicle_logs (fleet_id, event_type, event_date, event_time, odometer, fuel_liters, event_cost, logged_by, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$fleet_id, $event_type, $event_date, $event_time, $odometer, $fuel_liters, $event_cost, $logged_by, $description]);
      
      header("Location: vehicles_fleet.php");
      exit;
    } catch (PDOException $e) {
      $error_msg = "Error saving log entry: " . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Manual Log Entry | I-GAS Control Room" data-ar="تسجيل حركة مركبة يدوي | غرفة التحكم I-GAS">Manual Log Entry | I-GAS Control Room</title>
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
        <span data-en="FLEET LOG MANUAL ENTRY PROTOCOL" data-ar="بروتوكول إدخال سجل حركة الأسطول اليدوي">FLEET LOG MANUAL ENTRY PROTOCOL</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="TIMESTAMP:" data-ar="الوقت الحالي:">TIMESTAMP:</span> <strong class="text-white"><?= date('Y-m-d H:i') ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="SHIFT:" data-ar="الوردية:">SHIFT:</span> <strong class="text-white">LOGISTICS-OPS</strong>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: LEVEL-2</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 w-full mx-auto space-y-6">

    <!-- Breadcrumbs & Title -->
    <div>
      <div class="flex items-center gap-2 text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
        <a href="vehicles_fleet.php" class="hover:text-white transition-colors" data-en="Fleet Database" data-ar="قاعدة بيانات الأسطول">Fleet Database</a>
        <span>/</span>
        <span class="text-white font-semibold" data-en="Manual Entry" data-ar="إدخال يدوي">Manual Entry</span>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white flex items-center gap-2">
            <i data-lucide="clipboard-pen" class="w-6 h-6 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Create Manual Log Entry" data-ar="تسجيل قيد حركة يدوي">Create Manual Log Entry</span>
          </h1>
          <p class="text-sm text-slate-400 mt-1" data-en="Manually record fleet events, fuel logs, dispatch movements, or workshop maintenance criteria." data-ar="تسجيل تحركات الأسطول، التزود بالوقود، قراءات العداد أو تقارير الصيانة الدورية يدوياً.">
            Manually record fleet events, fuel logs, dispatch movements, or workshop maintenance criteria.
          </p>
        </div>
        <div>
          <a href="vehicles_fleet.php" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700/50 rounded-xs transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span data-en="Back to Fleet" data-ar="العودة للأسطول">Back to Fleet</span>
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

      <!-- Section 1: Log Context -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Log Context & Asset Target" data-ar="سياق السجل وبيانات المركبة">Log Context & Asset Target</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Target Vehicle *" data-ar="المركبة المستهدفة *">
              Target Vehicle *
            </label>
            <select name="vehicle_id" required class="w-full h-10 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <option value="" disabled <?= empty($vehicle_id) ? 'selected' : '' ?> data-en="-- Select Vehicle Fleet ID --" data-ar="-- اختر معرف المركبة من الأسطول --">-- Select Vehicle Fleet ID --</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= htmlspecialchars($v['fleet_id']) ?>" <?= $vehicle_id === $v['fleet_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v['fleet_id']) ?> (<?= htmlspecialchars($v['make_model']) ?> — <?= htmlspecialchars($v['plate_number']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Event Type *" data-ar="نوع النشاط / الحدث *">
              Event Type *
            </label>
            <select name="event_type" required class="w-full h-10 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <option value="" disabled selected data-en="-- Select event classification --" data-ar="-- اختر تصنيف الحدث --">-- Select event classification --</option>
              <option value="dispatch" data-en="Dispatch (Outbound Route)" data-ar="إرسال (خط سير خارجي)">Dispatch (Outbound Route)</option>
              <option value="arrival" data-en="Arrival (Inbound Return)" data-ar="وصول (عودة للمنشأة)">Arrival (Inbound Return)</option>
              <option value="refuel" data-en="Refuel Operations" data-ar="تزود بالوقود">Refuel Operations</option>
              <option value="maintenance" data-en="Maintenance & Repairs" data-ar="صيانة وإصلاحات">Maintenance & Repairs</option>
              <option value="incident" data-en="Incident / Delay Report" data-ar="حادث / بلاغ تأخير">Incident / Delay Report</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Date of Event *" data-ar="تاريخ الحدث *">
              Date of Event *
            </label>
            <input type="date" name="event_date" value="<?= date('Y-m-d') ?>" required class="w-full h-10 px-3 text-xs font-mono bg-[#0c1924] border border-[#1e3a50] rounded-xs text-white focus:border-neutral-900 dark:focus:border-neutral-100 outline-none transition-colors">
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Time of Event *" data-ar="وقت الحدث *">
              Time of Event *
            </label>
            <input type="time" name="event_time" value="<?= date('H:i') ?>" required class="w-full h-10 px-3 text-xs font-mono bg-[#0c1924] border border-[#1e3a50] rounded-xs text-white focus:border-neutral-900 dark:focus:border-neutral-100 outline-none transition-colors">
          </div>
        </div>
      </div>

      <!-- Section 2: Operational Telemetry Metrics -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="gauge" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Operational Telemetry & Metrics" data-ar="المؤشرات التشغيلية والقياسات">Operational Telemetry & Metrics</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Current Odometer (KM) *" data-ar="قراءة العداد الحالية (كم) *">
              Current Odometer (KM) *
            </label>
            <div class="relative">
              <input type="number" name="odometer" placeholder="0" required class="w-full h-10 ps-3 pe-12 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <span class="absolute end-3 top-2.5 text-xs font-mono text-neutral-400 pointer-events-none">KM</span>
            </div>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Fuel Added (Liters)" data-ar="كمية الوقود المضافة (لتر)">
              Fuel Added (Liters)
            </label>
            <div class="relative">
              <input type="number" step="0.01" name="fuel_liters" placeholder="0.00" class="w-full h-10 ps-3 pe-12 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <span class="absolute end-3 top-2.5 text-xs font-mono text-neutral-400 pointer-events-none">LTR</span>
            </div>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Associated Cost (SAR)" data-ar="التكلفة الإجمالية (ريال)">
              Associated Cost (SAR)
            </label>
            <div class="relative">
              <input type="number" step="0.01" name="event_cost" placeholder="0.00" class="w-full h-10 ps-3 pe-12 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <span class="absolute end-3 top-2.5 text-xs font-mono text-neutral-400 pointer-events-none">SAR</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 3: Verification & Narration -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="file-text" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Verification & Operator Notes" data-ar="التحقق والملاحظات التشغيلية">Verification & Operator Notes</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 gap-6">
          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Logged By / Authority Sign-off *" data-ar="المسؤول / التوقيع المعتمد *">
              Logged By / Authority Sign-off *
            </label>
            <input type="text" name="logged_by" value="<?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '') ?>" placeholder="e.g. Gate Security, Workshop Supervisor..." required class="w-full h-10 px-3 text-xs font-sans bg-[#0c1924] border border-[#1e3a50] rounded-xs text-white focus:border-neutral-900 dark:focus:border-neutral-100 outline-none transition-colors">
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Log Description & Route Narrative" data-ar="وصف السجل وملاحظات المسار">
              Log Description & Route Narrative
            </label>
            <textarea name="description" rows="4" placeholder="Provide contextual description of the route, refuel voucher numbers, replacement item metrics, or workshop diagnostics criteria..." class="w-full p-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors"></textarea>
          </div>
        </div>
      </div>

      <!-- Form Actions Bar -->
      <div class="p-4 bg-[#09151f] flex items-center justify-between">
        <a href="vehicles_fleet.php" class="px-4 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 hover:text-white transition-colors" data-en="Discard" data-ar="إلغاء">
          Discard
        </a>
        <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-6 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span data-en="Commit Log Entry" data-ar="حفظ قيد السجل">Commit Log Entry</span>
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