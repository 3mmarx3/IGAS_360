<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_permission($pdo, 'logistics.edit');

$id = $_GET['id'] ?? null;
if (!$id) {
  $firstDrv = $pdo->query("SELECT id FROM drivers ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstDrv) {
    $id = $firstDrv;
  } else {
    header("Location: drivers_directory.php");
    exit;
  }
}

$stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = ? OR driver_id = ?");
$stmt->execute([$id, $id]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$driver) {
  header("Location: drivers_directory.php");
  exit;
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $full_name = trim($_POST['full_name'] ?? '');
  $national_id = trim($_POST['national_id'] ?? '');
  $mobile_number = trim($_POST['mobile_number'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $license_class = $_POST['license_class'] ?? 'light';
  $license_number = trim($_POST['license_number'] ?? '');
  $license_expiry = $_POST['license_expiry'] ?? null;
  $medical_expiry = $_POST['medical_expiry'] ?? null;
  $status = $_POST['status'] ?? 'active';
  $blood_type = trim($_POST['blood_type'] ?? '');
  $emergency_contact = trim($_POST['emergency_contact'] ?? '');
  $assigned_vehicle = trim($_POST['assigned_vehicle'] ?? 'unassigned');

  if (empty($full_name) || empty($national_id)) {
    $error_msg = "Full Name and National ID are required.";
  } else {
    try {
      $pdo->beginTransaction();

      $stmt_update = $pdo->prepare("
        UPDATE drivers 
        SET full_name = ?, national_id = ?, mobile_number = ?, email = ?, 
          license_class = ?, license_number = ?, license_expiry = ?, 
          medical_expiry = ?, status = ?, blood_type = ?, 
          emergency_contact = ?, assigned_vehicle = ?
        WHERE id = ?
      ");
      $stmt_update->execute([
        $full_name, $national_id, $mobile_number, $email, 
        $license_class, $license_number, $license_expiry, 
        $medical_expiry, $status, $blood_type, 
        $emergency_contact, $assigned_vehicle, 
        $id
      ]);

      if ($driver['assigned_vehicle'] !== 'unassigned' && $driver['assigned_vehicle'] !== $assigned_vehicle) {
        $stmt_clear = $pdo->prepare("UPDATE vehicles SET driver_id = 'unassigned' WHERE fleet_id = ?");
        $stmt_clear->execute([$driver['assigned_vehicle']]);
      }

      if ($assigned_vehicle !== 'unassigned') {
        $stmt_veh = $pdo->prepare("UPDATE vehicles SET driver_id = ? WHERE fleet_id = ?");
        $stmt_veh->execute([$driver['driver_id'], $assigned_vehicle]);
      }

      $pdo->commit();
      header("Location: driver_profile.php?id=" . urlencode($id));
      exit;
    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $error_msg = "Error updating driver details: " . $e->getMessage();
    }
  }
}

$stmt_vehicles = $pdo->prepare("SELECT fleet_id, make_model, plate_number FROM vehicles WHERE driver_id = 'unassigned' OR driver_id = ? OR fleet_id = ?");
$stmt_vehicles->execute([$driver['driver_id'], $driver['assigned_vehicle']]);
$available_vehicles = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);

$active_page = 'drivers_directory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Drivers Directory', 'Edit Driver'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Edit Driver: <?= htmlspecialchars($driver['driver_id']) ?> | I-GAS Control Room" data-ar="تعديل بيانات السائق: <?= htmlspecialchars($driver['driver_id']) ?> | غرفة التحكم I-GAS">Edit Driver: <?= htmlspecialchars($driver['driver_id']) ?> | I-GAS Control Room</title>
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
        <span data-en="EDIT OPERATOR REGISTRY PROFILE" data-ar="تعديل سجل المشغل في الأسطول">EDIT OPERATOR REGISTRY PROFILE</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="DRIVER ID:" data-ar="معرف السائق:">DRIVER ID:</span> <strong class="text-teal-400 dark:text-teal-400"><?= htmlspecialchars($driver['driver_id']) ?></strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="shield-check" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
      <span data-en="RECORD VERSION SECURE" data-ar="البيانات مؤمنة وموثقة">RECORD VERSION SECURE</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6 mx-auto w-full">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="driver_profile.php?id=<?= urlencode($id) ?>" class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-[#1e3a50] text-slate-400 hover:border-teal-500 hover:text-teal-600 rounded-xs transition-colors">
            ← OPERATOR DOSSIER
          </a>
          <span class="text-xs text-slate-400 font-mono">SYS-DRV-04</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Edit Operator Credentials" data-ar="تعديل بيانات واعتماد السائق">
          Edit Operator Credentials
        </h1>
        <p class="text-xs md:text-sm text-slate-400 mt-1" data-en="Modify contact information, commercial license permits, and vehicle fleet assignment for <?= htmlspecialchars($driver['driver_id']) ?>." data-ar="تحديث بيانات التواصل، تصاريح القيادة، وتعيين الشاحنة المسندة للسائق <?= htmlspecialchars($driver['driver_id']) ?>.">
          Modify contact information, commercial license permits, and vehicle fleet assignment for <?= htmlspecialchars($driver['driver_id']) ?>.
        </p>
      </div>
      <div class="flex items-center gap-2.5">
        <a href="driver_profile.php?id=<?= urlencode($id) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] dark:hover:bg-neutral-700 transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
          <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
        </a>
        <button type="submit" form="editDriverForm" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span data-en="Commit Changes" data-ar="حفظ التعديلات">Commit Changes</span>
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
    <form id="editDriverForm" method="POST" action="" class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden border-s-4 border-s-[#f97316]">
      <?= csrf_field() ?>

      <div class="p-6 space-y-8">
        
        <!-- Section 1: Personal Identification -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="user" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="1. Personal Identification & Contact Details" data-ar="1. البيانات الشخصية وبيانات التواصل">1. Personal Identification & Contact Details</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Full Legal Name *" data-ar="الاسم الكامل *">Full Legal Name *</label>
              <input type="text" name="full_name" class="w-full py-2 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= htmlspecialchars($driver['full_name']) ?>" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="National ID / Iqama Number *" data-ar="رقم الهوية الوطنية / الإقامة *">National ID / Iqama Number *</label>
              <input type="text" name="national_id" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= htmlspecialchars($driver['national_id']) ?>" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Mobile Number *" data-ar="رقم الجوال *">Mobile Number *</label>
              <input type="text" name="mobile_number" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= htmlspecialchars($driver['mobile_number'] ?? '') ?>" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Emergency Contact" data-ar="جهة الاتصال في الطوارئ">Emergency Contact</label>
              <input type="text" name="emergency_contact" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= htmlspecialchars($driver['emergency_contact'] ?? '') ?>">
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Blood Type" data-ar="فصيلة الدم">Blood Type</label>
              <?php $d_blood = $driver['blood_type'] ?? 'O+'; ?>
              <select name="blood_type" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <option value="O+" <?= $d_blood === 'O+' ? 'selected' : '' ?>>O+</option>
                <option value="O-" <?= $d_blood === 'O-' ? 'selected' : '' ?>>O-</option>
                <option value="A+" <?= $d_blood === 'A+' ? 'selected' : '' ?>>A+</option>
                <option value="A-" <?= $d_blood === 'A-' ? 'selected' : '' ?>>A-</option>
                <option value="B+" <?= $d_blood === 'B+' ? 'selected' : '' ?>>B+</option>
                <option value="B-" <?= $d_blood === 'B-' ? 'selected' : '' ?>>B-</option>
                <option value="AB+" <?= $d_blood === 'AB+' ? 'selected' : '' ?>>AB+</option>
                <option value="AB-" <?= $d_blood === 'AB-' ? 'selected' : '' ?>>AB-</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Email Address (Optional)" data-ar="البريد الإلكتروني (اختياري)">Email Address (Optional)</label>
              <input type="email" name="email" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= htmlspecialchars($driver['email'] ?? '') ?>">
            </div>
          </div>
        </div>

        <!-- Section 2: License Class & Compliance -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="award" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="2. Heavy Transport License & Medical Compliance" data-ar="2. رخصة النقل الثقيل والفحص الطبي">2. Heavy Transport License & Medical Compliance</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="License Class" data-ar="فئة رخصة القيادة">License Class</label>
              <select name="license_class" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <option value="heavy_hazmat" <?= $driver['license_class'] === 'heavy_hazmat' ? 'selected' : '' ?> data-en="Heavy / Hazmat (Dangerous Goods Tankers)" data-ar="نقل ثقيل / صهاريج المواد الخطرة">Heavy / Hazmat (Dangerous Goods Tankers)</option>
                <option value="heavy" <?= $driver['license_class'] === 'heavy' ? 'selected' : '' ?> data-en="Heavy Transport (Flatbed Cylinder Trucks)" data-ar="نقل ثقيل (شاحنات مسطحة)">Heavy Transport (Flatbed Cylinder Trucks)</option>
                <option value="light" <?= $driver['license_class'] === 'light' ? 'selected' : '' ?> data-en="Light Commercial Carrier" data-ar="نقل خفيف تجاري">Light Commercial Carrier</option>
                <option value="private" <?= $driver['license_class'] === 'private' ? 'selected' : '' ?> data-en="Private Utility Vehicles" data-ar="مركبات خدمة خاصة">Private Utility Vehicles</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="License Number *" data-ar="رقم رخصة القيادة *">License Number *</label>
              <input type="text" name="license_number" class="w-full py-2 px-3 text-xs font-mono uppercase bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= htmlspecialchars($driver['license_number']) ?>" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="License Expiry Date *" data-ar="تاريخ انتهاء الرخصة *">License Expiry Date *</label>
              <input type="date" name="license_expiry" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= htmlspecialchars($driver['license_expiry']) ?>" required>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Medical Check Expiry" data-ar="تاريخ انتهاء الفحص الطبي">Medical Check Expiry</label>
              <input type="date" name="medical_expiry" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= htmlspecialchars($driver['medical_expiry'] ?? '') ?>">
            </div>
          </div>
        </div>

        <!-- Section 3: Assignment & Status -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="shield-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="3. Fleet Assignment & Duty Status" data-ar="3. تعيين الشاحنة وحالة العمل">3. Fleet Assignment & Duty Status</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Driver Code" data-ar="معرف السائق">Driver Code</label>
              <input type="text" class="w-full py-2 px-3 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-xs text-slate-400 cursor-not-allowed" value="<?= htmlspecialchars($driver['driver_id']) ?>" disabled>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Duty Status" data-ar="الحالة التشغيلية">Duty Status</label>
              <select name="status" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <option value="active" <?= $driver['status'] === 'active' ? 'selected' : '' ?> data-en="Active / On Duty" data-ar="نشط / في العمل">Active / On Duty</option>
                <option value="on_leave" <?= $driver['status'] === 'on_leave' ? 'selected' : '' ?> data-en="On Leave / Vacation" data-ar="في إجازة دورية">On Leave / Vacation</option>
                <option value="suspended" <?= $driver['status'] === 'suspended' ? 'selected' : '' ?> data-en="Suspended / Under Review" data-ar="موقوف / قيد المراجعة">Suspended / Under Review</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Assigned Carrier Unit" data-ar="المركبة المسندة">Assigned Carrier Unit</label>
              <select name="assigned_vehicle" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500">
                <option value="unassigned" <?= $driver['assigned_vehicle'] === 'unassigned' ? 'selected' : '' ?> data-en="Unassigned (Floating Pool)" data-ar="غير معين (سائق احتياط)">Unassigned (Floating Pool)</option>
                <?php foreach ($available_vehicles as $v): ?>
                  <option value="<?= htmlspecialchars($v['fleet_id']) ?>" <?= $driver['assigned_vehicle'] === $v['fleet_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($v['fleet_id']) ?> - <?= htmlspecialchars($v['make_model']) ?> (<?= htmlspecialchars($v['plate_number']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

      </div>

      <!-- Form Actions Bar -->
      <div class="p-4 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between">
        <a href="driver_profile.php?id=<?= urlencode($id) ?>" class="px-4 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 hover:text-white transition-colors" data-en="Discard" data-ar="إلغاء">
          Discard
        </a>
        <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-6 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span data-en="Save Driver Profile" data-ar="حفظ التعديلات">Save Driver Profile</span>
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