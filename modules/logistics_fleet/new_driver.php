<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_permission($pdo, 'logistics.create');

$active_page = 'drivers_directory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Drivers Directory', 'Add New Driver'];

$error_msg = '';

$stmt_seq = $pdo->query("SELECT COUNT(id) FROM drivers");
$count = $stmt_seq->fetchColumn();
$new_driver_id = 'DRV-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $full_name = trim($_POST['full_name'] ?? '');
  $national_id = trim($_POST['national_id'] ?? '');
  $mobile_number = trim($_POST['mobile_number'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $license_class = $_POST['license_class'] ?? '';
  $license_number = trim($_POST['license_number'] ?? '');
  $license_expiry = $_POST['license_expiry'] ?? '';
  $medical_expiry = !empty($_POST['medical_expiry']) ? $_POST['medical_expiry'] : null;
  $status = $_POST['status'] ?? 'active';

  if (empty($full_name) || empty($national_id) || empty($mobile_number) || empty($license_class) || empty($license_number) || empty($license_expiry)) {
    $error_msg = "req_fields";
  } else {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO drivers (driver_id, full_name, national_id, mobile_number, email, license_class, license_number, license_expiry, medical_expiry, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([
        $new_driver_id, $full_name, $national_id, $mobile_number, $email, 
        $license_class, $license_number, $license_expiry, $medical_expiry, 
        $status
      ]);
      
      header("Location: drivers_directory.php");
      exit;
    } catch (PDOException $e) {
      $error_msg = "dup_error";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Accredit Hazmat Driver | I-GAS Control Room" data-ar="اعتماد سائق نقل مواد خطرة | غرفة التحكم I-GAS">Accredit Hazmat Driver | I-GAS Control Room</title>
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
        <span data-en="OPERATOR INDUCTION & VERIFICATION PROTOCOL" data-ar="بروتوكول اعتماد وتدريب السائقين الجدد">OPERATOR INDUCTION & VERIFICATION PROTOCOL</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="GENERATED OPERATOR ID:" data-ar="معرف السائق المخصص:">GENERATED OPERATOR ID:</span> <strong class="text-white"><?= htmlspecialchars($new_driver_id) ?></strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="shield-alert" class="w-3.5 h-3.5 text-amber-500"></i>
      <span data-en="MANDATORY DANGEROUS GOODS (HAZMAT) CLEARANCE" data-ar="تصريح نقل المواد الخطرة إلزامي">MANDATORY DANGEROUS GOODS (HAZMAT) CLEARANCE</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6 mx-auto w-full">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="drivers_directory.php" class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-[#1e3a50] text-slate-400 hover:border-teal-500 hover:text-teal-600 rounded-xs transition-colors">
            ← DRIVERS ROSTER
          </a>
          <span class="text-xs text-slate-400 font-mono">SYS-DRV-03</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Accredit & Register Hazmat Driver" data-ar="اعتماد وتسجيل سائق نقل مواد خطرة">
          Accredit & Register Hazmat Driver
        </h1>
        <p class="text-xs md:text-sm text-slate-400 mt-1" data-en="Enroll carrier personnel, verify heavy commercial transport permits, and record medical fitness certificates." data-ar="تسجيل سائقي الشاحنات، التحقق من رخص النقل الثقيل، وتوثيق شهادات اللياقة الطبية.">
          Enroll carrier personnel, verify heavy commercial transport permits, and record medical fitness certificates.
        </p>
      </div>
      <div>
        <a href="drivers_directory.php" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] dark:hover:bg-neutral-700 transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
          <span data-en="Return to Directory" data-ar="العودة للدليل">Return to Directory</span>
        </a>
      </div>
    </div>

    <?php if ($error_msg === 'req_fields'): ?>
      <div class="p-3 bg-red-50 dark:bg-red-950/40 border border-red-500/30 text-red-700 dark:text-red-400 rounded-xs text-xs font-mono flex items-center gap-2">
        <i data-lucide="alert-octagon" class="w-4 h-4 shrink-0"></i>
        <span data-en="Error: Please fill in all required driver fields (Name, National ID, Mobile, License Class, Serial, Expiry)." data-ar="خطأ: يرجى استكمال كافة الحقول المطلوبة (الاسم، الهوية، الجوال، فئة الرخصة، الرقم، وتاريخ الانتهاء).">
          Error: Please fill in all required driver fields (Name, National ID, Mobile, License Class, Serial, Expiry).
        </span>
      </div>
    <?php elseif ($error_msg === 'dup_error'): ?>
      <div class="p-3 bg-red-50 dark:bg-red-950/40 border border-red-500/30 text-red-700 dark:text-red-400 rounded-xs text-xs font-mono flex items-center gap-2">
        <i data-lucide="alert-octagon" class="w-4 h-4 shrink-0"></i>
        <span data-en="Error: A driver with this National ID or Driver Code already exists in the system." data-ar="خطأ: يوجد سائق مسجل مسبقاً بنفس رقم الهوية أو الرمز.">
          Error: A driver with this National ID or Driver Code already exists in the system.
        </span>
      </div>
    <?php endif; ?>

    <!-- Form Card -->
    <form action="" method="POST" class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden border-s-4 border-s-[#f97316]">
      <?= csrf_field() ?>

      <div class="p-6 space-y-8">
        
        <!-- Section 1: Personal Identification -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="user" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="1. Personal Identification & Contact" data-ar="1. بيانات الهوية والتواصل">1. Personal Identification & Contact</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Full Name (Official ID) *" data-ar="الاسم الكامل (حسب الهوية) *">Full Name (Official ID) *</label>
              <input type="text" name="full_name" placeholder="Driver full legal name" data-en-placeholder="Driver full legal name" data-ar-placeholder="الاسم القانوني الكامل للسائق" class="w-full py-2 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="National ID / Iqama Number *" data-ar="رقم الهوية الوطنية / الإقامة *">National ID / Iqama Number *</label>
              <input type="text" name="national_id" placeholder="10-digit ID number" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Mobile Number *" data-ar="رقم الجوال *">Mobile Number *</label>
              <input type="tel" name="mobile_number" placeholder="+966 5X XXX XXXX" dir="ltr" class="w-full py-2 px-3 text-xs font-mono text-left bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Email Address (Optional)" data-ar="البريد الإلكتروني (اختياري)">Email Address (Optional)</label>
              <input type="email" name="email" placeholder="driver@igas-industrial.com" dir="ltr" class="w-full py-2 px-3 text-xs font-mono text-left bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
            </div>
          </div>
        </div>

        <!-- Section 2: License Class & Hazmat Certification -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="award" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="2. Commercial License & Safety Compliance" data-ar="2. رخصة القيادة والامتثال لمعايير السلامة">2. Commercial License & Safety Compliance</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="License Category *" data-ar="فئة رخصة القيادة *">License Category *</label>
              <select name="license_class" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                <option value="" disabled selected data-en="Select license authorization..." data-ar="اختر تصنيف الرخصة...">Select license authorization...</option>
                <option value="heavy_hazmat" data-en="Heavy / Hazmat (Dangerous Goods Tankers)" data-ar="نقل ثقيل / صهاريج نقل المواد الخطرة">Heavy / Hazmat (Dangerous Goods Tankers)</option>
                <option value="heavy" data-en="Heavy Transport (Flatbed Cylinder Trucks)" data-ar="نقل ثقيل (شاحنات أسطوانات مسطحة)">Heavy Transport (Flatbed Cylinder Trucks)</option>
                <option value="light" data-en="Light Commercial Carrier" data-ar="نقل خفيف تجاري">Light Commercial Carrier</option>
                <option value="private" data-en="Private Utility Vehicles" data-ar="مركبات خدمة خاصة">Private Utility Vehicles</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="License Serial Number *" data-ar="رقم رخصة القيادة *">License Serial Number *</label>
              <input type="text" name="license_number" placeholder="Official license serial" class="w-full py-2 px-3 text-xs font-mono uppercase bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="License Expiry Date *" data-ar="تاريخ انتهاء الرخصة *">License Expiry Date *</label>
              <input type="date" name="license_expiry" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Occupational Medical Check Expiry" data-ar="تاريخ انتهاء الفحص الطبي المهني">Occupational Medical Check Expiry</label>
              <input type="date" name="medical_expiry" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
            </div>
          </div>
        </div>

        <!-- Section 3: System Assignment & Status -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="shield-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="3. Fleet Code & Induction Status" data-ar="3. الرمز المعين وحالة التشغيل">3. Fleet Code & Induction Status</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Assigned Operator ID" data-ar="معرف السائق المُنشأ">Assigned Operator ID</label>
              <input type="text" class="w-full py-2 px-3 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-xs text-slate-400 cursor-not-allowed" value="<?= htmlspecialchars($new_driver_id) ?>" disabled>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Initial Employment Status" data-ar="حالة التشغيل الأولية">Initial Employment Status</label>
              <select name="status" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <option value="active" selected data-en="Active / On Duty" data-ar="نشط / في العمل">Active / On Duty</option>
                <option value="on_leave" data-en="On Leave / Vacation" data-ar="في إجازة دورية">On Leave / Vacation</option>
                <option value="suspended" data-en="Suspended / Under Review" data-ar="موقوف / قيد المراجعة">Suspended / Under Review</option>
              </select>
            </div>
          </div>
        </div>

      </div>

      <!-- Form Actions Bar -->
      <div class="p-4 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between">
        <a href="drivers_directory.php" class="px-4 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 hover:text-white transition-colors" data-en="Discard" data-ar="إلغاء">
          Discard
        </a>
        <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-6 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span data-en="Accredit & Register Driver" data-ar="اعتماد وتسجيل السائق">Accredit & Register Driver</span>
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