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
  $odometer = !empty($_POST['odometer']) ? (int)$_POST['odometer'] : 0;
  $fuel_liters = !empty($_POST['fuel_liters']) ? (float)$_POST['fuel_liters'] : 0;
  $event_cost = !empty($_POST['event_cost']) ? (float)$_POST['event_cost'] : 0;
  $logged_by = trim($_POST['logged_by'] ?? '');
  $description = trim($_POST['description'] ?? '');

  if (empty($fleet_id) || empty($event_type) || empty($event_date) || empty($event_time) || empty($odometer)) {
    $error_msg = "Please fill in all required telemetry fields (Vehicle, Type, Date, Time, Odometer).";
  } else {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO vehicle_logs (fleet_id, event_type, event_date, event_time, odometer, fuel_liters, event_cost, logged_by, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$fleet_id, $event_type, $event_date, $event_time, $odometer, $fuel_liters, $event_cost, $logged_by, $description]);
      
      header("Location: vehicle_logs.php?id=" . urlencode($fleet_id));
      exit;
    } catch (PDOException $e) {
      $error_msg = "Error recording event: " . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Record Vehicle Telemetry Event | I-GAS Control Room" data-ar="تسجيل حركة مركبة / قراءة عداد | غرفة التحكم I-GAS">Record Vehicle Telemetry Event | I-GAS Control Room</title>
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
        <span data-en="LOGISTICS EVENT MANUAL JOURNAL ENTRY" data-ar="قيد يدوي لسجل أحداث الأسطول">LOGISTICS EVENT MANUAL JOURNAL ENTRY</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="TIMESTAMP:" data-ar="التوقيت الحالي:">TIMESTAMP:</span> <strong class="text-white"><?= date('Y-m-d H:i') ?></strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="shield-check" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
      <span data-en="AUTHORITY VERIFIED" data-ar="موثق بسلطة التشغيل">AUTHORITY VERIFIED</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6 max-w-4xl mx-auto w-full">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="vehicles_fleet.php" class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-[#1e3a50] text-slate-400 hover:border-teal-500 hover:text-teal-600 rounded-xs transition-colors">
            ← FLEET DATABASE
          </a>
          <span class="text-xs text-slate-400 font-mono">SYS-LOG-02</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Record Vehicle Telemetry Event" data-ar="تسجيل حركة مركبة / قراءة عداد">
          Record Vehicle Telemetry Event
        </h1>
        <p class="text-xs md:text-sm text-slate-400 mt-1" data-en="Manually record carrier route dispatches, arrival checks, refuel vouchers, and workshop service logs." data-ar="تسجيل يدوي لرحلات الشحن والتوزيع، العودة من المسارات، كوبونات التزود بالوقود، وصيانة الورشة.">
          Manually record carrier route dispatches, arrival checks, refuel vouchers, and workshop service logs.
        </p>
      </div>
      <div>
        <button type="button" onclick="history.back()" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] dark:hover:bg-neutral-700 transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
          <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
        </button>
      </div>
    </div>

    <?php if ($error_msg): ?>
      <div class="p-3 bg-red-50 dark:bg-red-950/40 border border-red-500/30 text-red-700 dark:text-red-400 rounded-xs text-xs font-mono flex items-center gap-2">
        <i data-lucide="alert-octagon" class="w-4 h-4 shrink-0"></i>
        <span><?= htmlspecialchars($error_msg) ?></span>
      </div>
    <?php endif; ?>

    <!-- Form Card -->
    <form action="" method="POST" class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden border-s-4 border-s-[#f97316]">
      <?= csrf_field() ?>

      <div class="p-6 space-y-6">

        <!-- Section 1: Event Context -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="clipboard-list" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="1. Event Classification & Target Unit" data-ar="1. تصنيف الحدث والناقلة المستهدفة">1. Event Classification & Target Unit</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Target Carrier Vehicle *" data-ar="الناقلة المستهدفة *">Target Carrier Vehicle *</label>
              <select name="vehicle_id" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                <option value="" disabled <?= empty($vehicle_id) ? 'selected' : '' ?> data-en="Select carrier asset ID..." data-ar="اختر معرف الناقلة...">Select carrier asset ID...</option>
                <?php foreach ($vehicles as $v): ?>
                  <option value="<?= htmlspecialchars($v['fleet_id']) ?>" <?= $vehicle_id === $v['fleet_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($v['fleet_id']) ?> (<?= htmlspecialchars($v['make_model']) ?> — <?= htmlspecialchars($v['plate_number']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Telemetry Event Type *" data-ar="نوع حدث التتبع *">Telemetry Event Type *</label>
              <select name="event_type" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                <option value="" disabled selected data-en="Select event classification..." data-ar="اختر تصنيف الحدث...">Select event classification...</option>
                <option value="dispatch" data-en="Dispatch (Outbound Route Run)" data-ar="انطلاق في مسار تسليم (خارج)">Dispatch (Outbound Route Run)</option>
                <option value="arrival" data-en="Arrival (Inbound Return to Plant)" data-ar="وصول وعودة للمصنع (داخل)">Arrival (Inbound Return to Plant)</option>
                <option value="refuel" data-en="Refuel Operations & Fuel Log" data-ar="تزويد وقود وتسجيل الاستهلاك">Refuel Operations & Fuel Log</option>
                <option value="maintenance" data-en="Maintenance, Inspection & Repairs" data-ar="صيانة دورية، فحص وإصلاح">Maintenance, Inspection & Repairs</option>
                <option value="incident" data-en="Operational Incident / Delay Notice" data-ar="حادث تشغيلي / إشعار تأخير">Operational Incident / Delay Notice</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Event Date *" data-ar="تاريخ الحدث *">Event Date *</label>
              <input type="date" name="event_date" value="<?= date('Y-m-d') ?>" class="w-full py-2 px-3 text-xs font-mono bg-[#112433] border border-[#1e3a50] rounded-xs text-white focus:outline-teal-500" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Event Time *" data-ar="وقت الحدث *">Event Time *</label>
              <input type="time" name="event_time" value="<?= date('H:i') ?>" class="w-full py-2 px-3 text-xs font-mono bg-[#112433] border border-[#1e3a50] rounded-xs text-white focus:outline-teal-500" required>
            </div>
          </div>
        </div>

        <!-- Section 2: Operational Metrics -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="gauge" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="2. Operational Telemetry & Cost" data-ar="2. قراءات العداد والوقود والتكلفة">2. Operational Telemetry & Cost</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Odometer Reading (KM) *" data-ar="قراءة عداد المسافة (كم) *">Odometer Reading (KM) *</label>
              <div class="relative">
                <input type="number" name="odometer" placeholder="0" class="w-full py-2 px-3 pr-10 rtl:pr-3 rtl:pl-10 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                <span class="absolute right-3 rtl:right-auto rtl:left-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-neutral-400 font-bold">KM</span>
              </div>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Fuel Dispensed (If Refuel)" data-ar="كمية الوقود المعبأة">Fuel Dispensed (If Refuel)</label>
              <div class="relative">
                <input type="number" step="0.01" name="fuel_liters" placeholder="0.00" class="w-full py-2 px-3 pr-10 rtl:pr-3 rtl:pl-10 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <span class="absolute right-3 rtl:right-auto rtl:left-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-neutral-400 font-bold">LTR</span>
              </div>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Associated Cost (SAR)" data-ar="التكلفة المسجلة (ريال)">Associated Cost (SAR)</label>
              <div class="relative">
                <input type="number" step="0.01" name="event_cost" placeholder="0.00" class="w-full py-2 px-3 pr-12 rtl:pr-3 rtl:pl-12 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <span class="absolute right-3 rtl:right-auto rtl:left-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-neutral-400 font-bold">SAR</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 3: Verification & Description -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="file-text" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="3. Verification Sign-Off & Observations" data-ar="3. توثيق المسؤول والملاحظات">3. Verification Sign-Off & Observations</span>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Recorded By / Authority Sign-Off" data-ar="المسؤول عن التسجيل / التوقيع">Recorded By / Authority Sign-Off</label>
              <input type="text" name="logged_by" placeholder="e.g. Gate Officer, Workshop Supervisor, Fleet Controller" data-en-placeholder="e.g. Gate Officer, Workshop Supervisor, Fleet Controller" data-ar-placeholder="مثال: مسؤول البوابة، مشرف الورشة، مراقب الأسطول" class="w-full py-2 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Operational Notes & Description" data-ar="ملاحظات وتفاصيل الحدث">Operational Notes & Description</label>
              <textarea name="description" rows="3" placeholder="Provide context regarding destination route, voucher serial, inspection findings, or delay causes..." data-en-placeholder="Provide context regarding destination route, voucher serial, inspection findings, or delay causes..." data-ar-placeholder="سجل أية تفاصيل عن المسار، رقم إيصال الوقود، نتائج الفحص، أو أسباب التأخير..." class="w-full py-2 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500"></textarea>
            </div>
          </div>
        </div>

      </div>

      <!-- Form Actions Bar -->
      <div class="p-4 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between">
        <button type="button" onclick="history.back()" class="px-4 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 hover:text-white transition-colors" data-en="Cancel" data-ar="إلغاء">
          Cancel
        </button>
        <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-6 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span data-en="Commit Telemetry Log" data-ar="اعتماد وتسجيل الحركة">Commit Telemetry Log</span>
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
    document.querySelectorAll('[data-en-placeholder]').forEach(el => {
      el.placeholder = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
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