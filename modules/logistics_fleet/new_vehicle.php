<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_permission($pdo, 'logistics.create');

$active_page = 'vehicles_fleet';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Add New Vehicle'];

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $fleet_id = $_POST['fleet_id'] ?? '';
  $plate_number = trim($_POST['plate_number'] ?? '');
  $make_model = trim($_POST['make_model'] ?? '');
  $vehicle_type = trim($_POST['vehicle_type'] ?? '');
  $vin = trim($_POST['vin'] ?? '');
  $manufacturing_year = !empty($_POST['manufacturing_year']) ? (int)$_POST['manufacturing_year'] : null;
  $load_capacity = !empty($_POST['load_capacity']) ? (float)$_POST['load_capacity'] : 0;
  $cylinder_capacity = !empty($_POST['cylinder_capacity']) ? (int)$_POST['cylinder_capacity'] : 0;
  $fuel_type = trim($_POST['fuel_type'] ?? 'diesel');
  $status = $_POST['status'] ?? 'available';
  $registration_expiry = !empty($_POST['registration_expiry']) ? $_POST['registration_expiry'] : null;
  $insurance_expiry = !empty($_POST['insurance_expiry']) ? $_POST['insurance_expiry'] : null;

  if (empty($plate_number) || empty($make_model) || empty($vehicle_type) || empty($vin)) {
    $error_msg = "req_fields";
  } else {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO vehicles (fleet_id, plate_number, make_model, vehicle_type, vin, manufacturing_year, load_capacity, cylinder_capacity, fuel_type, driver_id, status, registration_expiry, insurance_expiry)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unassigned', ?, ?, ?)
      ");
      $stmt->execute([
        $fleet_id, $plate_number, $make_model, $vehicle_type, $vin, $manufacturing_year, 
        $load_capacity, $cylinder_capacity, $fuel_type, $status, 
        $registration_expiry, $insurance_expiry
      ]);
      
      header("Location: vehicles_fleet.php");
      exit;
    } catch (PDOException $e) {
      $error_msg = "dup_error";
    }
  }
}

$stmt_seq = $pdo->query("SELECT COUNT(id) FROM vehicles");
$count = $stmt_seq->fetchColumn();
$next_fleet_id = 'FLT-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Register Hazmat Vehicle | I-GAS Control Room" data-ar="تسجيل مركبة نقل مواد خطرة | غرفة التحكم I-GAS">Register Hazmat Vehicle | I-GAS Control Room</title>
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
        <span data-en="NEW CARRIER COMMISSIONING PROTOCOL" data-ar="بروتوكول تدشين ناقلة جديدة">NEW CARRIER COMMISSIONING PROTOCOL</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="ASSIGNED FLEET ID:" data-ar="معرف الأسطول المخصص:">ASSIGNED FLEET ID:</span> <strong class="text-white"><?= htmlspecialchars($next_fleet_id) ?></strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="shield-alert" class="w-3.5 h-3.5 text-amber-500"></i>
      <span data-en="ADR / CIVIL DEFENSE COMPLIANCE MANDATORY" data-ar="الامتثال لمتطلبات الدفاع المدني و ADR إلزامي">ADR / CIVIL DEFENSE COMPLIANCE MANDATORY</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6 mx-auto w-full">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="vehicles_fleet.php" class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-[#1e3a50] text-slate-400 hover:border-teal-500 hover:text-teal-600 rounded-xs transition-colors">
            ← FLEET REGISTRY
          </a>
          <span class="text-xs text-slate-400 font-mono">SYS-VEH-02</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Register Hazmat Carrier Unit" data-ar="تسجيل ناقلة غاز / مركبة نقل مواد خطرة">
          Register Hazmat Carrier Unit
        </h1>
        <p class="text-xs md:text-sm text-slate-400 mt-1" data-en="Commission a new road tanker, bobtail, or flatbed transport vehicle with safety specifications." data-ar="تدشين صهريج طريق جديد، صهريج بوب تيل، أو شاحنة نقل أسطوانات مع توثيق معايير السلامة.">
          Commission a new road tanker, bobtail, or flatbed transport vehicle with safety specifications.
        </p>
      </div>
      <div>
        <a href="vehicles_fleet.php" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] dark:hover:bg-neutral-700 transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
          <span data-en="Return to Fleet" data-ar="العودة للأسطول">Return to Fleet</span>
        </a>
      </div>
    </div>

    <?php if ($error_msg === 'req_fields'): ?>
      <div class="p-3 bg-red-50 dark:bg-red-950/40 border border-red-500/30 text-red-700 dark:text-red-400 rounded-xs text-xs font-mono flex items-center gap-2">
        <i data-lucide="alert-octagon" class="w-4 h-4 shrink-0"></i>
        <span data-en="Error: Please complete all required vehicle identification fields (Plate, Model, Type, VIN)." data-ar="خطأ: يرجى استكمال جميع بيانات تعريف المركبة المطلوبة (اللوحة، الموديل، النوع، رقم الهيكل).">
          Error: Please complete all required vehicle identification fields (Plate, Model, Type, VIN).
        </span>
      </div>
    <?php elseif ($error_msg === 'dup_error'): ?>
      <div class="p-3 bg-red-50 dark:bg-red-950/40 border border-red-500/30 text-red-700 dark:text-red-400 rounded-xs text-xs font-mono flex items-center gap-2">
        <i data-lucide="alert-octagon" class="w-4 h-4 shrink-0"></i>
        <span data-en="Error: A vehicle with this Plate Number or Chassis Number (VIN) is already registered in the system." data-ar="خطأ: توجد مركبة مسجلة مسبقاً بنفس رقم اللوحة أو رقم الهيكل (VIN).">
          Error: A vehicle with this Plate Number or Chassis Number (VIN) is already registered in the system.
        </span>
      </div>
    <?php endif; ?>

    <!-- Form Card -->
    <form action="" method="POST" class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden border-s-4 border-s-[#f97316]">
      <?= csrf_field() ?>

      <div class="p-6 space-y-8">
        
        <!-- Section 1: Vehicle Identification -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="1. Identification & Chassis Registry" data-ar="1. بيانات التعريف ورقم الهيكل">1. Identification & Chassis Registry</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Fleet Asset ID" data-ar="معرف أصل الأسطول">Fleet Asset ID</label>
              <input type="text" name="fleet_id" class="w-full py-2 px-3 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-xs text-slate-400 cursor-not-allowed" value="<?= htmlspecialchars($next_fleet_id) ?>" readonly>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Plate Number *" data-ar="رقم اللوحة *">Plate Number *</label>
              <input type="text" name="plate_number" placeholder="e.g. 1234 ABC" data-en-placeholder="e.g. 1234 ABC" data-ar-placeholder="مثال: 1234 أ ب ج" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Chassis VIN (17 Chars) *" data-ar="رقم الهيكل VIN (17 خانة) *">Chassis VIN (17 Chars) *</label>
              <input type="text" name="vin" placeholder="17-character VIN" class="w-full py-2 px-3 text-xs font-mono uppercase bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Make & Chassis Model *" data-ar="الشركة المصنعة والموديل *">Make & Chassis Model *</label>
              <input type="text" name="make_model" placeholder="e.g. Mercedes-Benz Actros 3340" data-en-placeholder="e.g. Mercedes-Benz Actros 3340" data-ar-placeholder="مثال: مرسيدس أكتروس 3340" class="w-full py-2 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Vehicle Configuration Type *" data-ar="نوع تكوين المركبة *">Vehicle Configuration Type *</label>
              <select name="vehicle_type" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                <option value="cryo" data-en="Cryogenic Tanker (LOX/LIN/LAR)" data-ar="صهريج تبريد لنقل السوائل المبردة">Cryogenic Tanker (LOX/LIN/LAR)</option>
                <option value="bobtail" data-en="Bobtail Rigid Tanker (LPG/CO2)" data-ar="صهريج بوب تيل لتوزيع الغاز">Bobtail Rigid Tanker (LPG/CO2)</option>
                <option value="flatbed" data-en="Flatbed Cylinder Carrier" data-ar="شاحنة مسطحة لنقل أسطوانات الغاز">Flatbed Cylinder Carrier</option>
                <option value="pickup" data-en="Service & Emergency Pickup" data-ar="شاحنة خدمة وطوارئ">Service & Emergency Pickup</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Manufacturing Year" data-ar="سنة الصنع">Manufacturing Year</label>
              <input type="number" name="manufacturing_year" min="2000" max="2030" value="<?= date('Y') ?>" class="w-full py-2 px-3 text-xs font-mono bg-[#112433] border border-[#1e3a50] rounded-xs text-white focus:outline-teal-500">
            </div>
          </div>
        </div>

        <!-- Section 2: Technical Specifications & Payload -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="gauge" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="2. Technical Payload & Fuel System" data-ar="2. مواصفات الحمولة ونظام الوقود">2. Technical Payload & Fuel System</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Cryo/Bulk Load Capacity" data-ar="سعة الحمولة السائبة (طن)">Cryo/Bulk Load Capacity</label>
              <div class="relative">
                <input type="number" step="0.01" name="load_capacity" placeholder="22.50" class="w-full py-2 px-3 pr-12 rtl:pr-3 rtl:pl-12 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <span class="absolute right-3 rtl:right-auto rtl:left-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-neutral-400 font-bold">TON</span>
              </div>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Cylinder Rack Capacity" data-ar="سعة أقفاص الأسطوانات">Cylinder Rack Capacity</label>
              <div class="relative">
                <input type="number" name="cylinder_capacity" placeholder="120" class="w-full py-2 px-3 pr-12 rtl:pr-3 rtl:pl-12 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <span class="absolute right-3 rtl:right-auto rtl:left-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-neutral-400 font-bold">CYL</span>
              </div>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Engine Fuel Type" data-ar="نوع وقود المحرك">Engine Fuel Type</label>
              <select name="fuel_type" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <option value="diesel" data-en="Heavy Diesel" data-ar="ديزل شاحنات">Heavy Diesel</option>
                <option value="gasoline" data-en="Gasoline 95" data-ar="بنزين 95">Gasoline 95</option>
                <option value="cng" data-en="Compressed Natural Gas (CNG)" data-ar="غاز طبيعي مضغوط">Compressed Natural Gas (CNG)</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Section 3: Safety Compliance & Inspection Dates -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="shield-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="3. ADR Compliance & Permit Lifecycle" data-ar="3. الامتثال لمعايير ADR وتراخيص النقل">3. ADR Compliance & Permit Lifecycle</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Initial Dispatch Status" data-ar="الحالة التشغيلية الأولية">Initial Dispatch Status</label>
              <select name="status" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <option value="available" data-en="Available / Ready for Dispatch" data-ar="متاح / جاهز للتشغيل">Available / Ready for Dispatch</option>
                <option value="maintenance" data-en="Pre-Service Safety Inspection" data-ar="تحت الفحص الفني الأولي">Pre-Service Safety Inspection</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Registration Permit Expiry" data-ar="تاريخ انتهاء الاستمارة">Registration Permit Expiry</label>
              <input type="date" name="registration_expiry" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Hazmat Insurance Policy Expiry" data-ar="تاريخ انتهاء وثيقة التأمين">Hazmat Insurance Policy Expiry</label>
              <input type="date" name="insurance_expiry" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
            </div>
          </div>
        </div>

      </div>

      <!-- Form Actions Bar -->
      <div class="p-4 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between">
        <a href="vehicles_fleet.php" class="px-4 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 hover:text-white transition-colors" data-en="Discard" data-ar="إلغاء">
          Discard
        </a>
        <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-6 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span data-en="Save & Commission Vehicle" data-ar="حفظ وتدشين المركبة">Save & Commission Vehicle</span>
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