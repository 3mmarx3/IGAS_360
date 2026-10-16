<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_permission($pdo, 'logistics.create');

$active_page = 'dispatch_delivery';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Dispatch & Delivery', 'New Dispatch'];

$error_msg = '';

try {
  $stmt_seq = $pdo->query("SELECT COUNT(id) FROM dispatches");
  $count = (int)$stmt_seq->fetchColumn();
  $new_manifest_id = 'DSP-' . str_pad($count + 907, 4, '0', STR_PAD_LEFT);

  $stmt_orders = $pdo->query("
    SELECT po.order_number, po.delivery_address, po.delivery_date, po.delivery_time, p.company_name, p.address as client_address
    FROM purchase_orders po
    LEFT JOIN partners p ON po.client_id = p.id
    ORDER BY po.id DESC
  ");
  $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

  $stmt_vehicles = $pdo->query("SELECT fleet_id, make_model, plate_number, driver_id FROM vehicles WHERE status = 'available' ORDER BY fleet_id ASC");
  $vehicles = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);

  $stmt_drivers = $pdo->query("SELECT driver_id, full_name, assigned_vehicle FROM drivers WHERE status = 'active' ORDER BY full_name ASC");
  $drivers = $stmt_drivers->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
  $orders = [];
  $vehicles = [];
  $drivers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $manifest_id = $_POST['manifest_id'] ?? $new_manifest_id;
  $order_ref = $_POST['order_ref'] ?? '';
  $vehicle_id = $_POST['vehicle_id'] ?? '';
  $driver_id = $_POST['driver_id'] ?? '';
  $destination = trim($_POST['destination'] ?? '');
  $dispatch_date = $_POST['dispatch_date'] ?? '';
  $eta_time = $_POST['eta_time'] ?? '';
  $instructions = trim($_POST['instructions'] ?? '');

  if (empty($order_ref) || empty($vehicle_id) || empty($driver_id) || empty($destination) || empty($dispatch_date) || empty($eta_time)) {
    $error_msg = "Please fill in all required fields (Order, Vehicle, Driver, Destination, Date, ETA).";
  } else {
    try {
      $pdo->beginTransaction();

      $stmt = $pdo->prepare("
        INSERT INTO dispatches (manifest_id, order_ref, vehicle_id, driver_id, destination, dispatch_date, eta_time, instructions, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'dispatched')
      ");
      $stmt->execute([$manifest_id, $order_ref, $vehicle_id, $driver_id, $destination, $dispatch_date, $eta_time, $instructions]);

      $stmt_v = $pdo->prepare("UPDATE vehicles SET status = 'in_transit' WHERE fleet_id = ?");
      $stmt_v->execute([$vehicle_id]);

      $pdo->commit();
      header("Location: dispatch_delivery.php");
      exit;
    } catch (PDOException $e) {
      $pdo->rollBack();
      $error_msg = "Error creating dispatch manifest: Manifest ID might already exist.";
    }
  }
}

$driver_to_vehicle = [];
foreach ($drivers as $d) {
  if (!empty($d['assigned_vehicle']) && $d['assigned_vehicle'] !== 'unassigned') {
    $driver_to_vehicle[$d['driver_id']] = $d['assigned_vehicle'];
  }
}

$vehicle_to_driver = [];
foreach ($vehicles as $v) {
  if (!empty($v['driver_id']) && $v['driver_id'] !== 'unassigned') {
    $vehicle_to_driver[$v['fleet_id']] = $v['driver_id'];
  }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Schedule Delivery Manifest | I-GAS Control Room" data-ar="جدولة إرسالية شحن غاز | غرفة التحكم I-GAS">Schedule Delivery Manifest | I-GAS Control Room</title>
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
        <span class="w-2 h-2 rounded-full bg-cyan-500"></span>
        <span data-en="NEW CARRIER DISPATCH MANIFEST AUTHORIZATION" data-ar="تفويض بيان إرسالية ونقل شحنة جديدة">NEW CARRIER DISPATCH MANIFEST AUTHORIZATION</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="SCHEDULED MANIFEST ID:" data-ar="معرف البيان المخصص:">SCHEDULED MANIFEST ID:</span> <strong class="text-cyan-700 dark:text-cyan-400"><?= htmlspecialchars($new_manifest_id) ?></strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="shield-check" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
      <span data-en="AUTOMATED VEHICLE-DRIVER BINDING ENABLED" data-ar="الربط الآلي بين السائق والناقلة نشط">AUTOMATED VEHICLE-DRIVER BINDING ENABLED</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6 mx-auto w-full">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="dispatch_delivery.php" class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-[#1e3a50] text-slate-400 hover:border-teal-500 hover:text-teal-600 rounded-xs transition-colors">
            ← DISPATCH REGISTRY
          </a>
          <span class="text-xs text-slate-400 font-mono">SYS-DSP-03</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Create Dispatch Manifest" data-ar="إنشاء بيان إرسالية وتوزيع شحنة">
          Create Dispatch Manifest
        </h1>
        <p class="text-xs md:text-sm text-slate-400 mt-1" data-en="Authorize tanker deployment, bind customer order reference, and establish highway transit route schedule." data-ar="تفويض خروج الصهريج، ربط مرجع أمر الشراء، وتحديد جدول ومسار النقل السريع.">
          Authorize tanker deployment, bind customer order reference, and establish highway transit route schedule.
        </p>
      </div>
      <div>
        <a href="dispatch_delivery.php" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] dark:hover:bg-neutral-700 transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
          <span data-en="Return to Dispatches" data-ar="العودة للإرساليات">Return to Dispatches</span>
        </a>
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
      <input type="hidden" name="manifest_id" value="<?= htmlspecialchars($new_manifest_id) ?>">

      <div class="p-6 space-y-8">
        
        <!-- Section 1: Manifest Context -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="file-text" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="1. Manifest Serial & Customer Order Binding" data-ar="1. رقم البيان وربط طلب العميل">1. Manifest Serial & Customer Order Binding</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Manifest Tracking ID" data-ar="معرف تتبع البيان">Manifest Tracking ID</label>
              <input type="text" class="w-full py-2 px-3 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-xs text-slate-400 cursor-not-allowed font-bold" value="<?= htmlspecialchars($new_manifest_id) ?>" disabled>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Linked Customer Order *" data-ar="طلب الشراء المرتبط *">Linked Customer Order *</label>
              <select name="order_ref" id="order_ref_select" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                <option value="" disabled selected data-en="Select active purchase order..." data-ar="اختر أمر شراء نشط...">Select active purchase order...</option>
                <?php foreach ($orders as $o): ?>
                  <option value="<?= htmlspecialchars($o['order_number']) ?>" <?= (isset($_GET['po']) && $_GET['po'] === $o['order_number']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($o['order_number']) ?> — <?= htmlspecialchars($o['company_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Section 2: Resource Allocation -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="2. Carrier Unit & Hazmat Driver Allocation" data-ar="2. تخصيص الشاحنة والسائق المعتمد">2. Carrier Unit & Hazmat Driver Allocation</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Available Fleet Asset *" data-ar="المركبة المتاحة بالأسطول *">Available Fleet Asset *</label>
              <select name="vehicle_id" id="vehicle_select" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                <option value="" disabled selected data-en="Select available carrier..." data-ar="اختر مركبة متاحة...">Select available carrier...</option>
                <?php foreach ($vehicles as $v): ?>
                  <option value="<?= htmlspecialchars($v['fleet_id']) ?>">
                    <?= htmlspecialchars($v['fleet_id']) ?> (<?= htmlspecialchars($v['make_model']) ?> — <?= htmlspecialchars($v['plate_number']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="mt-1 text-[11px] font-mono text-teal-600 dark:text-teal-400 hidden items-center gap-1" id="vehicle_auto_hint">
                <i data-lucide="link" class="w-3 h-3"></i>
                <span data-en="Auto-selected based on assigned driver" data-ar="تم التحديد تلقائياً بناءً على السائق">Auto-selected based on assigned driver</span>
              </div>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Certified Hazmat Driver *" data-ar="السائق المعتمد *">Certified Hazmat Driver *</label>
              <select name="driver_id" id="driver_select" class="w-full py-2 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                <option value="" disabled selected data-en="Select available driver..." data-ar="اختر سائق متاح...">Select available driver...</option>
                <?php foreach ($drivers as $d): ?>
                  <option value="<?= htmlspecialchars($d['driver_id']) ?>"><?= htmlspecialchars($d['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="mt-1 text-[11px] font-mono text-teal-600 dark:text-teal-400 hidden items-center gap-1" id="driver_auto_hint">
                <i data-lucide="link" class="w-3 h-3"></i>
                <span data-en="Auto-selected based on assigned vehicle" data-ar="تم التحديد تلقائياً بناءً على المركبة">Auto-selected based on assigned vehicle</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 3: Routing & Delivery Schedule -->
        <div>
          <div class="flex items-center gap-2 pb-2 mb-4 border-b border-[#1a3346] text-xs font-mono uppercase tracking-wider font-semibold text-slate-200">
            <i data-lucide="map-pin" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="3. Routing, Schedule & Waybill Protocols" data-ar="3. المسار، الجدولة وبروتوكولات التسليم">3. Routing, Schedule & Waybill Protocols</span>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Destination Site Coordinates / Address *" data-ar="عنوان موقع التفريغ / الإحداثيات *">Destination Site Coordinates / Address *</label>
              <input type="text" name="destination" id="destination_input" placeholder="e.g. Jubail Industrial Zone 2, Gate 4, Oxygen Decant Station" data-en-placeholder="e.g. Jubail Industrial Zone 2, Gate 4, Oxygen Decant Station" data-ar-placeholder="مثال: الجبيل الصناعية 2، بوابة 4، محطة تفريغ الأكسجين" class="w-full py-2 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Dispatch Gate-Out Date *" data-ar="تاريخ الخروج من المصنع *">Dispatch Gate-Out Date *</label>
                <input type="date" name="dispatch_date" id="dispatch_date_input" value="<?= date('Y-m-d') ?>" class="w-full py-2 px-3 text-xs font-mono bg-[#112433] border border-[#1e3a50] rounded-xs text-white focus:outline-teal-500" required>
              </div>

              <div>
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Target Arrival Time (ETA) *" data-ar="الوقت المستهدف للوصول (ETA) *">Target Arrival Time (ETA) *</label>
                <input type="time" name="eta_time" id="eta_time_input" value="<?= date('H:i', strtotime('+3 hours')) ?>" class="w-full py-2 px-3 text-xs font-mono bg-[#112433] border border-[#1e3a50] rounded-xs text-white focus:outline-teal-500" required>
              </div>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-400 mb-1" data-en="Hazmat Decanting & Delivery Instructions" data-ar="تعليمات تفريغ المواد الخطرة والسلامة">Hazmat Decanting & Delivery Instructions</label>
              <textarea name="instructions" rows="3" placeholder="Specify static grounding cable protocols, cryogenic PPE requirements, and customer tank valve serial checks..." data-en-placeholder="Specify static grounding cable protocols, cryogenic PPE requirements, and customer tank valve serial checks..." data-ar-placeholder="حدد بروتوكولات التأريض الكهربائي، متطلبات معدات الوقاية من البرودة الشديدة، وفحص أرقام صمامات الخزان..." class="w-full py-2 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:outline-teal-500"></textarea>
            </div>
          </div>
        </div>

      </div>

      <!-- Form Actions Bar -->
      <div class="p-4 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between">
        <a href="dispatch_delivery.php" class="px-4 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 hover:text-white transition-colors" data-en="Discard" data-ar="إلغاء">
          Discard
        </a>
        <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-6 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span data-en="Authorize & Dispatch Run" data-ar="اعتماد وبدء الإرسالية">Authorize & Dispatch Run</span>
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

  const driverToVehicle = <?= json_encode($driver_to_vehicle, JSON_UNESCAPED_UNICODE) ?>;
  const vehicleToDriver = <?= json_encode($vehicle_to_driver, JSON_UNESCAPED_UNICODE) ?>;
  const ordersData = <?= json_encode($orders, JSON_UNESCAPED_UNICODE) ?>;

  const vehicleSelect = document.getElementById('vehicle_select');
  const driverSelect = document.getElementById('driver_select');
  const vehicleAutoHint = document.getElementById('vehicle_auto_hint');
  const driverAutoHint = document.getElementById('driver_auto_hint');
  
  const orderSelect = document.getElementById('order_ref_select');
  const destInput = document.getElementById('destination_input');
  const dateInput = document.getElementById('dispatch_date_input');
  const timeInput = document.getElementById('eta_time_input');

  let isSyncing = false;

  if (driverSelect && vehicleSelect) {
    driverSelect.addEventListener('change', function () {
      if (isSyncing) return;
      const selectedDriverId = this.value;
      const linkedVehicle = driverToVehicle[selectedDriverId];

      if (vehicleAutoHint) vehicleAutoHint.style.display = 'none';

      if (linkedVehicle) {
        const optionExists = Array.from(vehicleSelect.options).some(opt => opt.value === linkedVehicle);
        if (optionExists) {
          isSyncing = true;
          vehicleSelect.value = linkedVehicle;
          isSyncing = false;
          if (vehicleAutoHint) vehicleAutoHint.style.display = 'flex';
        }
      }
    });

    vehicleSelect.addEventListener('change', function () {
      if (isSyncing) return;
      const selectedVehicleId = this.value;
      const linkedDriver = vehicleToDriver[selectedVehicleId];

      if (driverAutoHint) driverAutoHint.style.display = 'none';

      if (linkedDriver) {
        const optionExists = Array.from(driverSelect.options).some(opt => opt.value === linkedDriver);
        if (optionExists) {
          isSyncing = true;
          driverSelect.value = linkedDriver;
          isSyncing = false;
          if (driverAutoHint) driverAutoHint.style.display = 'flex';
        }
      }
    });
  }

  if (orderSelect) {
    orderSelect.addEventListener('change', function() {
      const selectedOrder = ordersData.find(o => o.order_number === this.value);
      if(selectedOrder) {
        if (destInput) destInput.value = selectedOrder.delivery_address || selectedOrder.client_address || '';
        if(selectedOrder.delivery_date && dateInput) {
          dateInput.value = selectedOrder.delivery_date;
        }
        if(selectedOrder.delivery_time && timeInput) {
          timeInput.value = selectedOrder.delivery_time;
        }
      }
    });

    if (orderSelect.value) {
      orderSelect.dispatchEvent(new Event('change'));
    }
  }
</script>
</body>
</html>